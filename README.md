# receiptstore — QRK „Digitaler Beleg / Ablage-Link" (Beta, selbst hostbar)

Eigenständiges Cevian-Modul (kein DB-Zwang, ein Token, TTL-Cleanup).
Installation: Ordner nach `<cevian>/modules/receiptstore` klonen,
`module.json.example` nach `module.json` kopieren (token, storage_dir,
base_url setzen). QRK-Settings siehe unten. Beta-Status: am Beta-Server
betrieben, Feedback über Issues.

**BETA-Testwerkzeug, kein Produkt:** QRK hostet nichts; das Modul simuliert
die Betreiber-Ablage für den Beta-Durchlauf des Autors. Keine DB, kein
RBAC — ein Token aus `module.json` (nur am Server, Repo:
`module.json.example`), Dateien unter `var/receiptstore/` mit
TTL-Cleanup (mtime) und hartem File-Cap.

## Endpunkte (Cevian-path)

| Kanal            | Methode | Route                                                |
|------------------|---------|------------------------------------------------------|
| WebDAV (PUT)     | PUT     | `/cevian/receiptstore/dav/index/<KEY>/<name>.pdf`    |
| GenericPOST      | POST    | `/cevian/receiptstore/post/index/<KEY>`              |
| S3 (SigV4)       | PUT     | `/cevian/receiptstore/s3/index/<KEY>/<name>.pdf`     |
| Abruf (Kunde)    | GET     | `/cevian/receiptstore/r/index/<name>.pdf`            |

- `<KEY>` = `token` aus module.json (auch `?key=` — **Basic/Bearer-Header
  erreichen FPM auf diesem Host nicht zuverlässig**, dyndns-Lektion).
- Das Retrieval braucht bewusst KEINEN Token: der zufällige Dateiname ist
  das einzige Geheimnis, das der Kunden-Browser hält.
- Dateien: `var/receiptstore/*.pdf` (cevian-Root, NICHT im Modulbaum;
  Modulbaum ist kein storage), override via module.json `storage_dir`
  (absolute Pfad; Self-Hosting-Szenario "Plugin-Download"), TTL
  `ttl_hours` (Default 24 h), File-Cap `max_files` (Default 500),
  Größen-Cap `max_bytes` (2 MB), PDF-Magic-Check (`%PDF`), Name-Regex
  `[A-Za-z0-9._-]+\.pdf`.
- Konfig im `base_url`-Key (Default `https://<host>/cevian`).

## QRK-Settings (Beta, Endkunden-Reihenfolge)

- **WebDAV:** `Endpunkt = https://service.ckvsoft.at/cevian/receiptstore/dav/index/<KEY>`
  (leer lassen = keine Zugangsdaten), `Abruf-Link Basis =
  https://service.ckvsoft.at/cevian/receiptstore/r/index/`
- **POST:** `Endpunkt = https://service.ckvsoft.at/cevian/receiptstore/post/index/<KEY>`,
  Link-Feld `url`, Bearer-Token egal (FPM).
- **S3:** `Endpunkt = https://service.ckvsoft.at/cevian` (HOST + Basis-
  pfad!), `Bucket = receiptstore`, `Unterordner = s3/index/<KEY>`,
  `Access Key ID`/`Secret Access Key` = module.json
  (`s3AccessKey`/`s3Secret`). QRK
  signiert den vollen Request-Pfad (Basis-Pfad + Bucket + Key) und das
  Modul vergleicht ihn 1:1 mit dem REQUEST_URI (`s3_prefix` bleibt leer,
  außer ein Wrapper bleibt ungewollt im URI). Nimmt die Schicht dazwischen den Authorization-Header (oder die
  x-amz-Header) ganz weg, fällt die Prüfung auf URL-Token (Payload-Hash
  sofern möglich) zurück — das Modul-Logfile zeigt, welcher Fall griff
  (sigv4 / payload-hash / no-amz-headers).

## S3-Signatur-Hinweis
Das Beta-Modul prüft die SigV4-Signatur NUR, wenn der Host die
`/<bucket>/<key>`-Route am Root kennt (module.json `s3_prefix` setzt den
Wrapper-Prefix, wenn die cevian-bootstrap hinter einem Prefix mountet).
Ohne diese Route bleibt der Fallback (Payload-Hash + URL-Token); der
normkonforme SigV4-Fall selbst ist im UnitMock (ReceiptTransport) und an
einem echten Provider (Hetzner/MinIO) testbar.
