<?php

use ckvsoft\mvc\Config;

/**
 * S3 endpoint - SigV4 verification of QRK's ReceiptTransport::uploadS3.
 *
 * The kassa sends (path-style) to the HOST ROOT:
 *   PUT /<bucket>/<keyInBucket>
 *   Authorization: AWS4-HMAC-SHA256 Credential=<ak>/<date>/<region>/s3/
 *                  aws4_request, SignedHeaders=host;x-amz-content-sha256;
 *                  x-amz-date, Signature=<hex>
 *   x-amz-content-sha256: <sha256(body)>
 *   x-amz-date: <date>T<time>Z
 *
 * The canonical path IS the raw HTTP path (QRK signs exactly what it
 * sends). On a vhost that roots the service (or with module.json
 * "s3_prefix" naming an env wrapper to strip), the canonical rebuild is
 * byte-identical, so the signature check runs the full AWS chain. When
 * the FPM strips Authorization (dyndns lesson: Basic/Bearer are lost at
 * this layer) the service falls back to token-in-URL + intact payload
 * hash - the transfer is still proven, only the norm signature stays
 * un-proven there; see README for the two beta routes.
 */
class S3 extends \ckvsoft\mvc\BaseController
{
    private function hmac($key, $data)
    {
        return hash_hmac('sha256', (string) $data, (string) $key, true);
    }

    /** 's3/index/<token>/' inside "<bucket>/<keyInBucket>" -> token (or '') */
    private function tokenFromCanonical($canonical)
    {
        if (preg_match('#/s3/index/([A-Za-z0-9._-]+)/#', (string) $canonical, $m)) {
            return $m[1];
        }
        return '';
    }

    public function index($bucket = '', $key = '')
    {
        $model = $this->loadModel('receiptstore');

        // the retrieval link of the S3 channel points back to THIS route
        // (GET) - serving the object makes the beta bucket self-contained
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $content = $model->read(basename((string) $key));
            if ($content === null) {
                http_response_code(404);
                header('Content-Type: text/plain');
                echo 'not found (storage TTL ran out)';
                exit;
            }
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PUT') {
            http_response_code(405);
            header('Content-Type: text/plain');
            echo 'PUT only';
            exit;
        }

        $model = $this->loadModel('receiptstore');

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $queryPos = strpos($uri, '?');
        if ($queryPos !== false) $uri = substr($uri, 0, $queryPos);
        $prefix = (string) Config::module('s3_prefix', 'receiptstore');
        if ($prefix !== '' && strpos($uri, $prefix) === 0) $uri = substr($uri, strlen($prefix));
        $canonical = '/' . ltrim((string) $uri, '/');

        $body = (string) file_get_contents('php://input');
        $payloadHash = hash('sha256', $body);
        $amzSha = (string) ($_SERVER['HTTP_X_AMZ_CONTENT_SHA256'] ?? '');
        $amzDate = (string) ($_SERVER['HTTP_X_AMZ_DATE'] ?? '');
        $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $expectedAccess = trim((string) Config::module('s3AccessKey', 'receiptstore'));
        $secret = (string) (Config::module('s3Secret', 'receiptstore') ?: Config::module('token', 'receiptstore'));

        $verified = 'bad-request';
        if ($amzSha === '') {
            // intermediate layer stripped the x-amz headers entirely:
            // the token-in-URL branch below stays the identity proof
            $verified = 'no-amz-headers';
        } elseif (hash_equals($amzSha, $payloadHash) && $amzDate !== '') {
            $verified = 'payload-hash';
            if ($authorization !== '') {
                if (preg_match(
                    '/AWS4-HMAC-SHA256 Credential=([^\/]+)\/(\d{8})\/([^\/]+)\/s3\/aws4_request,'
                    . '\s*SignedHeaders=([^,]+),\s*Signature=([0-9a-f]+)/', $authorization, $m)
                    && hash_equals($expectedAccess, $m[1])) {
                    $datestamp = $m[2];
                    $region = $m[3];
                    $signature = $m[5];
                    // canonical request exactly per QRK (one request shape
                    // only: the three signed headers above)
                    $canonicalRequest = "PUT\n" . $canonical . "\n\n"
                        . "host:" . (string) ($_SERVER['HTTP_HOST'] ?? '') . "\n"
                        . "x-amz-content-sha256:" . $amzSha . "\n"
                        . "x-amz-date:" . $amzDate . "\n"
                        . "\nhost;x-amz-content-sha256;x-amz-date\n" . $payloadHash;
                    $scope = $datestamp . '/' . $region . '/s3/aws4_request';
                    $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n"
                        . hash('sha256', $canonicalRequest);
                    $dateKey = $this->hmac('AWS4' . $secret, $datestamp);
                    $regionKey = $this->hmac($dateKey, $region);
                    $serviceKey = $this->hmac($regionKey, 's3');
                    $signingKey = $this->hmac($serviceKey, 'aws4_request');
                    $verified = hash_equals($signature, bin2hex($this->hmac($signingKey, $stringToSign)))
                        ? 'sigv4' : 'bad-signature';
                } else {
                    $verified = 'bad-authorization';
                }
            }
        }

        // hand the object to storage: full SigV4, or (headers lost at FPM)
        // the token in the canonical key + intact payload hash
        $token = $this->tokenFromCanonical($canonical);
        $tokenOk = $token !== '' && hash_equals(
            trim((string) Config::module('token', 'receiptstore')), $model->requestKey($token));
        $name = basename((string) $key);

        if ($verified === 'sigv4' || ($verified === 'no-amz-headers' && $tokenOk)
            || ($verified === 'payload-hash' && $tokenOk)) {
            if ($model->store($name, $body) === null) {
                error_log('receiptstore/s3: rejected upload ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'invalid object']);
                exit;
            }
            $model->maintenance();
            http_response_code(200);
            echo '';
            exit;
        }

        error_log('receiptstore/s3: rejected (' . $verified . ') from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        http_response_code($verified === 'bad-signature' ? 403 : 400);
        header('Content-Type: application/json');
        echo json_encode(['error' => $verified]);
        exit;
    }
}
