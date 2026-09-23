<?php

use ckvsoft\mvc\Config;

/**
 * Receiptstore_Model - BETA only.
 *
 * Storage tool for the QRK "Ablage-Link" (digitaler Beleg). No database:
 * PDFs go to <site>/var/receiptstore/<name>.pdf with an mtime-based TTL
 * cleanup and a hard file cap, so the beta tool can never fill the disk.
 * The token lives in module.json (server side only); Basic/Bearer headers
 * are NOT reliable through PHP-FPM on this host (see AGENTS.md, dyndns
 * lesson), so the upload endpoints take the key via the URL path or ?key=.
 * The retrieval link needs NO key on purpose: the random file name is the
 * only secret a customer browser holds (a UUID token is not guessable).
 */
class Receiptstore_Model extends \ckvsoft\mvc\Model
{
    private function storageDir(): string
    {
        // 1 = module.json "storage_dir" (self-hosting: absolute path or
        // relative to the cevian root, e.g. "/srv/qrk-storage"),
        // 2 = the cevian-site var (cevian/var, NOT the module tree)
        $candidates = [
            trim((string) Config::module('storage_dir', 'receiptstore')),
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'var',
        ];
        foreach ($candidates as $base) {
            if ($base === '') continue;
            $dir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'receiptstore';
            if (!is_dir($dir)) @mkdir($dir, 0770, true);
            if (is_dir($dir) && is_writable($dir)) return $dir;
        }
        error_log('receiptstore: no writable storage directory found (tried: '
            . implode(', ', array_filter($candidates)) . ') - check php-fpm ownership');
        return '';
    }

    /** ?key= fallback (dyndns lesson: Authorization header reaches FPM unreliably) */
    public function requestKey($pathToken): string
    {
        $request = new \ckvsoft\Request();
        return trim((string) ($pathToken ?: $request->getQuery('key', '')));
    }

    /** Path token checked against module.json "token"; exits on failure */
    public function requireToken($pathToken)
    {
        $token = $this->requestKey($pathToken);
        $expected = trim((string) Config::module('token', 'receiptstore'));
        if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
            error_log('receiptstore: badauth from ' . (new \ckvsoft\Request())->getServerVar('REMOTE_ADDR', '?'));
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'invalid key']);
            exit;
        }
    }

    public function validFileName($name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]+\.pdf$/i', (string) $name);
    }

    /** mtime TTL cleanup + hard file cap; called on every store */
    public function maintenance()
    {
        $dir = $this->storageDir();
        if ($dir === '' || !is_dir($dir)) return;
        $ttl = (int) (Config::module('ttl_hours', 'receiptstore') ?: 24);
        $max = (int) (Config::module('max_files', 'receiptstore') ?: 500);
        $maxBytes = (int) (Config::module('max_bytes', 'receiptstore') ?: 2000000);
        $cutoff = time() - $ttl * 3600;
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        // oldest first - cap cleanup runs before the age rule
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        foreach ($files as $f) {
            if (filemtime((string)$f) < $cutoff) @unlink((string)$f);
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        while (count($files) > $max) {
            $oldest = array_shift($files);
            @unlink((string)$oldest);
        }
    }

    /** Validates size + PDF magic (%PDF), writes and returns the file name */
    public function store($name, $body): ?string
    {
        $name = (string) $name;
        if (!$this->validFileName($name)) return null;
        $maxBytes = (int) (Config::module('max_bytes', 'receiptstore') ?: 2000000);
        if (strlen($body) === 0 || strlen($body) > $maxBytes) return null;
        if (substr($body, 0, 4) !== '%PDF') return null;
        $dir = $this->storageDir();
        if ($dir === '' || !is_dir($dir)) return null;
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $body) === false) {
            error_log('receiptstore: write failed - check ownership of ' . $dir
                . ' (needs the php-fpm user)');
            return null;
        }
        return $name;
    }

    public function read($name): ?string
    {
        $name = (string) $name;
        if (!$this->validFileName($name)) return null;
        $dir = $this->storageDir();
        if ($dir === '') return null;
        $path = $dir . DIRECTORY_SEPARATOR . basename($name);
        if (!is_file($path)) return null;
        $content = @file_get_contents($path);
        return $content === false || $content === '' ? null : $content;
    }

    /** Retrieval link for a stored file (no key on purpose - see header) */
    public function linkFor($name): string
    {
        $base = trim((string) Config::module('base_url', 'receiptstore'));
        if ($base === '') {
            $host = (new \ckvsoft\Request())->getServerVar('HTTP_HOST', 'localhost');
            $base = 'https://' . $host . '/cevian';
        }
        return rtrim($base, '/') . '/receiptstore/r/index/' . rawurlencode((string) $name);
    }
}
