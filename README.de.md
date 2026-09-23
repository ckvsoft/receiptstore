# receiptstore — QRK „Digitaler Beleg / Ablage-Link" (Beta)

Eigenständiges Cevian-Modul für den QRK-Belegablauf: Die Kassa lädt die
Beleg-PDFs per WebDAV, generischem POST oder S3 hoch, der Kunde ruft sie
über einen einfachen Link ab.

- keine Datenbank, ein Token (module.json), TTL-Cleanup mit hartem File-Cap
- Dateien liegen im `var/` der Cevian-Site (nicht im Modulbaum);
  module.json `storage_dir` setzt einen absoluten Pfad als Override
- der Abruf-Link trägt bewusst keinen Key: der Zufalls-Dateiname ist das
  einzige, was der Kunden-Browser kennt

## Installation

1. Ordner nach `<cevian>/modules/receiptstore` klonen.
2. `module.json.example` nach `module.json` kopieren und setzen:
   - `token` — Key für die Uploads,
   - `base_url` — öffentliche Basis der Cevian-Site,
   - `storage_dir` — optionaler absoluter Speicherpfad (Default `<cevian>/var`),
   - Grenzen (`ttl_hours`, `max_files`, `max_bytes`) nach Bedarf.
3. QRK-Kanal „Beleg-Ablage" wie unten konfigurieren.

## Endpunkte (Cevian-Path)

| Kanal            | Methode | Route                                             |
|------------------|---------|---------------------------------------------------|
| WebDAV (PUT)     | PUT     | `/cevian/receiptstore/dav/index/<KEY>/<name>.pdf` |
| GenericPOST      | POST    | `/cevian/receiptstore/post/index/<KEY>`           |
| S3 (SigV4)       | PUT     | `/cevian/receiptstore/s3/index/<KEY>/<name>.pdf`  |
| Abruf (Kunde)    | GET     | `/cevian/receiptstore/r/index/<name>.pdf`         |

- `<KEY>` = `token` aus module.json (auch `?key=`; Basic/Bearer-Header
  erreichen FPM über manche Proxys nicht zuverlässig).
- Dateien: `var/receiptstore/*.pdf`, TTL `ttl_hours` (Default 24 h),
  File-Cap `max_files` (Default 500), Größen-Cap `max_bytes` (2 MB),
  PDF-Magic-Check (`%PDF`), Name-Regex `[A-Za-z0-9._-]+\.pdf`.

## QRK-Kanal-Settings

- **WebDAV:** Endpunkt `https://<host>/cevian/receiptstore/dav/index/<KEY>`,
  Abruf-Link Basis `https://<host>/cevian/receiptstore/r/index/`.
- **POST:** Endpunkt `https://<host>/cevian/receiptstore/post/index/<KEY>`,
  Link-Feld `url`.
- **S3:** Endpunkt = Host + Basis-Pfad (z. B. `https://<host>/cevian`),
  Bucket `receiptstore`, Unterordner `s3/index/<KEY>`,
  Access/Secret aus module.json (`s3AccessKey`/`s3Secret`). QRK signiert
  den vollen Request-Pfad; das Modul vergleicht ihn 1:1 mit dem
  REQUEST_URI (`s3_prefix` bleibt leer, außer ein Wrapper-Prefix ist
  unvermeidbar). Strippt eine Zwischenachse den Authorization-Header/
  die x-amz-Header, fällt die Prüfung auf URL-Token (Payload-Hash, sofern
  möglich) zurück.

## S3-Signatur-Hinweis

SigV4 wird nur dann vollständig geprüft, wenn der Host die
`/<bucket>/<key>`-Route am Root bedient (module.json `s3_prefix` für einen
Wrapper-Prefix). Sonst griff der URL-Token/Payload-Hash-Fallback; das
Modul-Log zeigt den Fall (sigv4 / payload-hash / no-amz-headers).

## English version

See [README.md](README.md).
