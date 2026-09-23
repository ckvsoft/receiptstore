<?php

/**
 * DAV endpoint (WebDAV-PUT emulation for the QRK receiptstore beta tool).
 *
 * Cevian-path:
 *   PUT /cevian/receiptstore/dav/index/<KEY>/<name>.pdf
 *   → Dav::index(<KEY>, <name>.pdf)
 *
 * Response: {"ok": 1, "url": "<base_url>/receiptstore/r/index/<name>.pdf"}
 * The QRK kassa only checks the HTTP status for the WebDAV channel; the
 * "url" member is informative for curl testing by hand.
 */
class Dav extends \ckvsoft\mvc\BaseController
{
    public function index($key = '', $file = '')
    {
        $model = $this->loadModel('receiptstore');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PUT') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'method not allowed (PUT only)']);
            exit;
        }

        $model->requireToken($key);
        $body = (string) file_get_contents('php://input');
        $stored = $model->store($file, $body);
        if ($stored === null) {
            error_log('receiptstore/dav: rejected upload '
                . ($_SERVER['REMOTE_ADDR'] ?? '?') . ' file=' . var_export($file, true));
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'invalid request (name/size/PDF magic)']);
            exit;
        }

        $model->maintenance();
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['ok' => 1, 'url' => $model->linkFor($stored)]);
        exit;
    }
}
