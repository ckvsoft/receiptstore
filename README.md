# receiptstore — QRK digital receipt / retrieval-link endpoint (beta)

Standalone Cevian module for the QRK "Digitaler Beleg / Ablage-Link" flow:
receipt PDFs are uploaded by the till via WebDAV, generic POST or S3 and
retrieved by the customer through a plain URL.

- no database, one token (module.json), TTL cleanup with a hard file cap
- files go to the cevian-site `var/` (NOT the module tree); module.json
  `storage_dir` sets an absolute override for self-hosting
- the customer retrieval link carries no key on purpose: the random
  file name is the only secret a customer browser holds

## Installation

1. Clone this folder into `<cevian>/modules/receiptstore`.
2. Copy `module.json.example` to `module.json` and set:
   - `token` — the upload key,
   - `base_url` — the public base of your cevian site,
   - `storage_dir` — optional absolute storage path (default `<cevian>/var`),
   - limits (`ttl_hours`, `max_files`, `max_bytes`) as needed.
3. Configure the QRK "Beleg-Ablage" channel settings as documented below.

## Endpoints (cevian path)

| Channel         | Method | Route                                             |
|-----------------|--------|---------------------------------------------------|
| WebDAV (PUT)    | PUT    | `/cevian/receiptstore/dav/index/<KEY>/<name>.pdf` |
| GenericPOST     | POST   | `/cevian/receiptstore/post/index/<KEY>`           |
| S3 (SigV4)      | PUT    | `/cevian/receiptstore/s3/index/<KEY>/<name>.pdf`  |
| Retrieval (GET) | GET    | `/cevian/receiptstore/r/index/<name>.pdf`         |

- `<KEY>` = the module.json `token` (also via `?key=`; Basic/Bearer headers
  may not reach PHP-FPM reliably through some proxies).
- Files: `var/receiptstore/*.pdf`, TTL `ttl_hours` (default 24 h), file cap
  `max_files` (default 500), size cap `max_bytes` (2 MB), PDF magic check
  (`%PDF`), file name regex `[A-Za-z0-9._-]+\.pdf`.

## QRK channel settings

- **WebDAV:** endpoint `https://<host>/cevian/receiptstore/dav/index/<KEY>`,
  retrieval base `https://<host>/cevian/receiptstore/r/index/`.
- **POST:** endpoint `https://<host>/cevian/receiptstore/post/index/<KEY>`,
  link field `url`.
- **S3:** endpoint = host + base path (e. g. `https://<host>/cevian`),
  bucket `receiptstore`, folder `s3/index/<KEY>`, access/secret from
  module.json (`s3AccessKey`/`s3Secret`). QRK signs the full request path
  and the module compares it 1:1 against the REQUEST_URI (`s3_prefix`
  stays empty unless a wrapper prefix is unavoidable). If an intermediate
  layer strips the Authorization/x-amz headers, the check falls back to
  the URL token (payload hash where possible).

## S3 signature note

SigV4 is checked fully only when the host serves `/<bucket>/<key>` at the
root (module.json `s3_prefix` covers a wrapper prefix). Otherwise the
URL-token/payload-hash fallback applies; the module log states which case
was used (sigv4 / payload-hash / no-amz-headers).

## Deutsche Version

Siehe [README.de.md](README.de.md).
