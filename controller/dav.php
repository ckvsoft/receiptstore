<?php

/**
 * DAV endpoint (WebDAV-PUT emulation for the QRK receiptstore).
 *
 * Cevian-path:
 *   PUT /cevian/receiptstore/dav/index/<KEY>/<name>.pdf
 *   → Dav::index(<KEY>, <name>.pdf)
 *
 * Response: {"ok": 1, "url": "<base_url>/receiptstore/r/index/<name>.pdf"}
 * The QRK kassa only checks the HTTP status for the WebDAV channel; the
 * "url" member is informative for curl testing by hand.
 */
class Dav extends ckvsoft\mvc\BaseController
{
    public function index($key = '', $file = '')
    {
        $this->model = $this->loadModel('receiptstore');
        $request = new \ckvsoft\Request();

        if ($request->getServerVar('REQUEST_METHOD') !== 'PUT') {
            http_response_code(405);
            \ckvsoft\Output::json(['error' => 'method not allowed (PUT only)']);
            exit;
        }

        $this->model->requireToken($key);
        $body = (string) file_get_contents('php://input');
        $stored = $this->model->store($file, $body);
        if ($stored === null) {
            error_log('receiptstore/dav: rejected upload '
                . (new \ckvsoft\Request())->getServerVar('REMOTE_ADDR', '?') . ' file=' . var_export($file, true));
            http_response_code(400);
            \ckvsoft\Output::json(['error' => 'invalid request (name/size/PDF magic)']);
            exit;
        }

        $this->model->maintenance();
        http_response_code(200);
        \ckvsoft\Output::json(['ok' => 1, 'url' => $this->model->linkFor($stored)]);
        exit;
    }
}
