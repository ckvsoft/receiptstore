<?php
header('Content-Type: text/plain');
echo "QRK receiptstore (BETA test tool - webdav/post/r only)\n\n";
echo "Digitaler Beleg 'Ablage-Link' storage endpoints for the QRK kassa.\n";
echo "PUT /receiptstore/dav/index/<KEY>/<name>.pdf   (WebDAV channel)\n";
echo "POST /cevian/receiptstore/post/index/<KEY>     (GenericPOST channel, JSON body)\n";
echo "GET  /cevian/receiptstore/r/index/<name>.pdf   (retrieval, no token: name = secret)\n\n";
echo "Token lives in module.json (server side only). S3 must be tested against a\n";
echo "REAL provider - see README.md (canonical path + header limits at FPM).\n";
exit;
