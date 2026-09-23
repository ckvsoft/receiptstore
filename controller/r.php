<?php

/**
 * R endpoint - the retrieval link a customer browser opens (QR target).
 *
 * Cevian-path:
 *   GET /cevian/receiptstore/r/index/<name>.pdf
 *
 * NO key on purpose: the random file name IS the only secret the customer
 * holds. The model still validates the name format, so nothing outside
 * the storage directory can be touched.
 */
class R extends ckvsoft\mvc\BaseController
{
    public function index($file = '')
    {
        $this->model = $this->loadModel('receiptstore');
        $request = new \ckvsoft\Request();

        if ($request->getServerVar('REQUEST_METHOD') !== 'GET') {
            http_response_code(405);
            header('Content-Type: text/plain');
            echo 'GET only';
            exit;
        }

        $content = $this->model->read($file);
        if ($content === null) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'not found (storage TTL ran out - receipts only live hours)';
            exit;
        }

        $this->model->maintenance(); // no-op safe on the retrieval path too
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename((string) $file) . '"');
        header('Cache-Control: private, max-age=3600');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
