<?php

/**
 * Receiptstore controller - the module's root route
 * (/cevian/receiptstore/). Without the sub-controllers below (dav/post/
 * s3/r), the bootstrap otherwise dies with "non-existent controller:
 * Receiptstore". This action serves the same plain route overview the
 * module-root index.php prints.
 */
class Receiptstore extends ckvsoft\mvc\BaseController
{
    public function index()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/plain; charset=UTF-8');
        echo "QRK receiptstore (beta test tool - dav/post/s3/r only)\n\n";
        echo "Digitaler Beleg 'Ablage-Link' storage endpoints for the QRK kassa.\n";
        echo "PUT /cevian/receiptstore/dav/index/<KEY>/<name>.pdf    (WebDAV channel)\n";
        echo "POST /cevian/receiptstore/post/index/<KEY>            (GenericPOST channel)\n";
        echo "PUT /cevian/receiptstore/s3/index/<KEY>/<name>.pdf    (S3 channel)\n";
        echo "GET /cevian/receiptstore/r/index/<name>.pdf           (retrieval, no token)\n\n";
        echo "Token lives in module.json (server side only). See README.md.\n";
        exit;
    }
}
