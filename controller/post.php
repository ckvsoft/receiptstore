<?php

/**
 * POST endpoint (GenericPOST channel of the QRK receiptstore beta tool).
 *
 * Cevian-path:
 *   POST /cevian/receiptstore/post/index/<KEY>
 *   → Post::index(<KEY>)
 *
 * Body (JSON, exactly what QRK ReceiptTransport::uploadGenericPost sends):
 *   {"filename": "QRK-BON<n>-<token>.pdf", "receiptNum": n,
 *    "uploaded": "<UTC ISODate>", "data": "<base64 PDF>"}
 *
 * Response: {"url": "<base_url>/receiptstore/r/index/<filename>"}
 * The Bearer header is NOT used through FPM (see model header comment) -
 * the token comes from the URL / ?key=. The QRK linkField stays "url".
 */
class Post extends \ckvsoft\mvc\BaseController
{
    public function index($key = '')
    {
        $model = $this->loadModel('receiptstore');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'method not allowed (POST only)']);
            exit;
        }

        $model->requireToken($key);

        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload) || empty($payload['filename']) || empty($payload['data'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'invalid payload']);
            exit;
        }

        $body = (string) base64_decode((string) $payload['data'], true);
        $stored = $model->store($payload['filename'], $body);
        if ($stored === null) {
            error_log('receiptstore/post: rejected upload ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'invalid file content']);
            exit;
        }

        $model->maintenance();
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['url' => $model->linkFor($stored)]);
        exit;
    }
}
