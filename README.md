# IT-Tools — SnipeIT Browser Toolkit

[![Version](https://img.shields.io/badge/version-2.4.2-blue)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.2-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Docker](https://img.shields.io/badge/docker-ready-blue)](Dockerfile.it-tools)

Browser bookmarklets and admin interface for daily IT asset management with [SnipeIT](https://snipeitapp.com).

---

## Features

| Module | Bookmarklet on | Function |
|---|---|---|
| **Outlook Mail** | `/hardware/{id}` | Pre-filled handover mail to accounting |
| **Handover & Return PDF** | `/users/{id}` | A4 protocol with mode picker, accessories, optional signature |
| **Digital Signature** | `/users/{id}` | Signature → PDF → uploaded to employee + assets in SnipeIT |
| **Label Print** | Anywhere | Accessory labels on Zebra ZD410 (50×25mm, QR code) |
| **AirWatch MDM** | `/hardware/{id}` | Sync status, dry run, manual sync, device lookup |
| **Lansweeper** | Admin only | CSV import with dry run, column mapping, import history |

---

## Quick Start

### 1. Clone

```bash
git clone https://github.com/yourorg/it-tools.git
cd it-tools
```

### 2. Configure environment

```bash
cp .env.example .env
# Edit .env — set MARIADB_ROOT_PASSWORD
```

### 3. Add to docker-compose.yml

```yaml
  it-tools:
    build:
      context: ./it-tools
      dockerfile: Dockerfile.it-tools
    container_name: it-tools
    restart: always
    ports: ["7777:80"]
    # Required if SnipeIT hostname is not resolvable from inside the container:
    # extra_hosts: ["snipeit.example.com:192.168.1.100"]
    environment:
      TOOLS_DB_HOST: mariadb
      TOOLS_DB_NAME: snipeit_tools
      TOOLS_DB_USER: root
      TOOLS_DB_PASS: ${MARIADB_ROOT_PASSWORD}
      TOOLS_OUTPUT_DIR: /var/www/html
    volumes:
      - ./it-tools/admin.html:/var/www/html/admin.html:ro
      - ./it-tools/ls_brut_exp:/data/BRUT:ro    # Lansweeper CSV exports
    depends_on:
      mariadb:
        condition: service_healthy
```

### 4. Start

```bash
docker compose up -d --build it-tools
```

### 5. Configure

Open `http://localhost:7777/admin` → **API / URLs** → enter your SnipeIT URL and API token.

Install bookmarklets via `http://localhost:7777` (the install page).

---

## Architecture

```
it-tools/
├── api.php                     # URL router — all requests enter here
├── admin.html                  # Admin SPA (bind-mount → hot-reload)
├── index.php                   # → install.html or /admin
├── .htaccess                   # Apache routes: /, /admin, /api/*
├── Dockerfile.it-tools
├── docker-entrypoint.sh        # Auto-generates runner files on start
├── init-tools-db.sql           # DB schema (5 tables)
└── modules/
    ├── core/
    │   ├── db.php              # PDO singleton, config helpers, ausgabe_schreiben()
    │   ├── response.php        # json_ok(), json_fehler(), proxy_pass()
    │   └── snipeit.php         # SnipeIT API client library ← central
    ├── proxy/                  # CORS fix: /api/proxy/* → raw SnipeIT response
    ├── mail/                   # Outlook mail bookmarklet
    ├── pdf/                    # Handover & Return PDF
    ├── sign/                   # Digital signature + PDF generator (no libs)
    ├── label/                  # Zebra ZD410 label print
    ├── distribute/             # Employee install page generator
    ├── fields/                 # Custom field mapping UI
    ├── airwatch/               # AirWatch MDM sync
    ├── lansweeper/             # Lansweeper CSV import
    ├── shared/                 # Shared config (URL, token)
    └── status/                 # Generated file status API
```

### API standard

```
/api/proxy/*  →  proxy_pass()  →  raw SnipeIT response   {total, rows:[...]}
/api/*        →  json_ok()     →  {"ok": true, "data": {...}}
```

Frontend code calling `/api/proxy/*` uses `r.json()` directly — no unwrapping needed.

### Adding a module

1. Create `modules/{name}/module.php` returning a handler array
2. Add routes to `api.php`
3. Rebuild container → module auto-discovered

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full module structure.

### SnipeIT Library

```php
// Read
SnipeIT::getAsset(42)
SnipeIT::getUser(1595)
SnipeIT::getAccessories(['location_id' => 5])
SnipeIT::getAll('hardware', ['category_id' => 4])  // auto-paginated

// Write
SnipeIT::createAsset([...])
SnipeIT::updateAsset(42, [...])
SnipeIT::checkoutAsset(42, ['assigned_user' => 1595])

// Upload
SnipeIT::uploadToUser(1595, $pdfBytes, 'Protocol.pdf')
SnipeIT::uploadToAsset(42,  $pdfBytes, 'Protocol.pdf')
```

---

## Known SnipeIT API quirks

| Issue | Solution |
|---|---|
| User file upload field name | `file[]` (no space) |
| Asset file upload field name | `file[ ]` (with space — SnipeIT quirk) |
| CORS blocks browser → SnipeIT | Proxy module forwards server-side |
| PHP warnings before JSON | `ob_start()` global + `ob_end_clean()` in `json_ok()` |
| Soft-deleted assets | `/bytag` returns `deleted_at` — must be handled explicitly |

---

## Requirements

- Docker + Docker Compose
- MariaDB (can share existing instance)
- SnipeIT v8.x
- Zebra ZD410 network printer (optional, for label printing)

---

## Documentation

- [GUIDE.md](GUIDE.md) — detailed installation, configuration and user workflows
- [CONTRIBUTING.md](CONTRIBUTING.md) — how to add modules and contribute
- [CHANGELOG.md](CHANGELOG.md) — full version history

---

## License

[MIT](LICENSE)

---
*Authored by Chris M.*
