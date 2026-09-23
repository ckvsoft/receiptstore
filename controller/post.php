<?php

/**
 * POST endpoint (generic POST channel of the QRK receiptstore).
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
 * The Bearer header is NOT used (FPM may drop it) - the token comes from
 * the URL path or ?key=.
 */
class Post extends ckvsoft\mvc\BaseController
{
    public function index($key = '')
    {
        $this->model = $this->loadModel('receiptstore');
        $request = new \ckvsoft\Request();

        if ($request->getServerVar('REQUEST_METHOD') !== 'POST') {
            http_response_code(405);
            \ckvsoft\Output::json(['error' => 'method not allowed (POST only)']);
            exit;
        }

        $this->model->requireToken($key);

        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload) || empty($payload['filename']) || empty($payload['data'])) {
            http_response_code(400);
            \ckvsoft\Output::json(['error' => 'invalid payload']);
            exit;
        }

        $body = (string) base64_decode((string) $payload['data'], true);
        $stored = $this->model->store($payload['filename'], $body);
        if ($stored === null) {
            error_log('receiptstore/post: rejected upload ' . (new \ckvsoft\Request())->getServerVar('REMOTE_ADDR', '?'));
            http_response_code(400);
            \ckvsoft\Output::json(['error' => 'invalid file content']);
            exit;
        }

        $this->model->maintenance();
        http_response_code(200);
        \ckvsoft\Output::json(['url' => $this->model->linkFor($stored)]);
        exit;
    }
}
