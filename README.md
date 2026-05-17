# IT-Tools for SnipeIT

> Browser bookmarklet toolkit that extends SnipeIT with one-click asset handover PDFs, digital signatures, accessory label printing, and automated accounting emails — all without requiring a SnipeIT login for end users.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP](https://img.shields.io/badge/PHP-8.2-purple.svg)](https://php.net)
[![Docker](https://img.shields.io/badge/Docker-Compose-blue.svg)](https://docs.docker.com/compose/)
[![SnipeIT](https://img.shields.io/badge/SnipeIT-v8.x-orange.svg)](https://snipeit.com)

---

## What it does

IT-Tools runs as a lightweight PHP/Apache Docker container alongside your existing SnipeIT stack. IT staff install three browser bookmarklets once — from then on, every workflow is a single click on any SnipeIT page.

| Bookmarklet | Used on | What happens |
|---|---|---|
| **Mail to Accounting** | `/hardware/{id}` | Opens Outlook with a pre-filled asset handover email |
| **Handover & Return PDF** | `/users/{id}` | Mode picker → select assets + accessories → print A4 protocol with optional digital signature |
| **Sign Handover** | `/users/{id}` | Full-page signature canvas → generates PDF → uploads to SnipeIT user file and each asset file |
| **Label Print** | `label-print.html` | Search and select accessories → send ZPL directly to Zebra network printer |

---

## Features

- **No SnipeIT login required** for end users — the API token stays server-side in PHP
- **Handover & Return PDF** — single bookmarklet with a mode picker; generates different A4 documents:
  - Handover: asset table, accessory table, signature field
  - Return: adds condition column (Good / Damaged / Defective), RETURN stamp, remarks field
- **Digital signature** — drawn on screen, embedded as image in the PDF, automatically uploaded to SnipeIT as a PDF file
  - Filename prefix: `Uebergabe_` or `Ruecknahme_` depending on mode
- **Accessory label printing** — ZPL sent directly via TCP to any Zebra network printer (tested on ZD410, 50×25mm labels)
  - Configurable fields: name, category, manufacturer, checkout date, barcode, QR code
- **Automatic accessory loading** — accessories checked out to the user appear alongside assets in the PDF
- **Custom field mapping** — map SnipeIT custom field keys (e.g. `_snipeit_kst_5`) to logical names used across all modules
- **CORS-free proxy** — all SnipeIT API calls are proxied server-side; no browser CORS issues
- **Admin UI** — single-page app at `/admin` to configure all modules without editing files
- **Modular architecture** — drop a folder in `modules/` and it is auto-discovered; no routing changes needed

---

## Screenshots

> _Add screenshots here — Admin UI, PDF mode picker, signature canvas, label print page_

---

## Requirements

- Docker + Docker Compose
- SnipeIT v8.x (API access)
- MariaDB (can share the existing SnipeIT database server)
- Traefik (optional, for domain routing)
- Zebra network printer (optional, for label printing)

---

## Quick Start

### 1. Clone

```bash
git clone https://github.com/YOUR_USERNAME/it-tools.git
cd it-tools
```

### 2. Add to your docker-compose.yml

```yaml
  it-tools:
    build:
      context: ./it-tools
      dockerfile: Dockerfile.it-tools
    container_name: it-tools
    restart: always
    ports:
      - "7777:80"
    environment:
      TOOLS_DB_HOST: mariadb
      TOOLS_DB_NAME: snipeit_tools
      TOOLS_DB_USER: root
      TOOLS_DB_PASS: ${MARIADB_ROOT_PASSWORD}
      TOOLS_OUTPUT_DIR: /var/www/html
    volumes:
      - ./it-tools/admin.html:/var/www/html/admin.html:ro
    depends_on:
      mariadb:
        condition: service_healthy
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.it-tools.rule=Host(`tools.yourdomain.com`)"
      - "traefik.http.routers.it-tools.entrypoints=web"
      - "traefik.http.services.it-tools.loadbalancer.server.port=80"
```

Add the DB init file to MariaDB:

```yaml
  mariadb:
    volumes:
      - ./it-tools/init-tools-db.sql:/docker-entrypoint-initdb.d/02-tools-db.sql:ro
```

### 3. Build and start

```bash
docker compose up -d --build it-tools
```

### 4. Open the admin and configure

```
http://tools.yourdomain.com/admin
```

Set your SnipeIT URL, API token, company name, and which fields to show on PDFs and labels.

### 5. Generate runner files

```bash
curl -X POST http://tools.yourdomain.com/api/generate \
  -H "Content-Type: application/json" -d '{"type":"all"}'
```

### 6. Install bookmarklets

Open `http://tools.yourdomain.com` and drag the buttons to your bookmarks bar.

---

## Architecture

```
Browser (SnipeIT page)
    │
    └── Bookmarklet click
            │
            ▼
    tools.yourdomain.com (PHP/Apache container)
            │
            ├── /admin          → Admin SPA (admin.html, bind-mounted)
            ├── /               → Bookmarklet install page (install.html)
            ├── /api/proxy/*    → SnipeIT API proxy (token stays server-side)
            ├── /api/sign/submit → Generate PDF + upload to SnipeIT
            ├── /api/label/print → Send ZPL to Zebra printer via TCP
            └── /api/generate   → Rebuild runner HTML files from config
```

### Module system

Each module is a folder under `modules/` containing a `module.php` that returns a PHP array:

```php
return [
    'name'        => 'mymodule',
    'label'       => 'My Module',
    'section'     => 'mymodule',       // DB config key
    'output_file' => 'mymodule.html',  // generated file
    'get_config'  => function(): array { ... },
    'save_config' => function(array $data): void { ... },
    'generate'    => function(): void { ... },
    // custom handlers (e.g. 'print', 'submit') callable via /api/{module}/{handler}
];
```

No changes to `api.php` needed — modules are auto-discovered.

### Built-in modules

| Module | Section | Generated file | Description |
|---|---|---|---|
| `shared` | shared | — | SnipeIT URL + API token |
| `mail` | mail | `snipeit-bm.html` | Outlook email bookmarklet |
| `pdf` | pdf | `snipeit-ausgabe-pdf.html` | Handover & Return PDF |
| `sign` | sign | `sign.html` | Signature canvas + PDF upload |
| `distribute` | distribute | `install.html` | Bookmarklet install page |
| `label` | label | `label-print.html` | Zebra label printing |
| `fields` | — | — | Custom field key mapping |
| `proxy` | — | — | SnipeIT API proxy |
| `status` | — | — | Generated file status API |

### PDF generation (no external libraries)

The signature document is created entirely with PHP's built-in GD extension:

1. GD draws the A4 document (794×1123 px) with all fields and the signature image
2. `imagejpeg()` produces JPEG bytes
3. A minimal PDF-1.4 wrapper embeds the JPEG using only string operations
4. The PDF is uploaded to SnipeIT via multipart POST

### CORS solution

SnipeIT's `Content-Security-Policy` blocks browser `fetch()` calls to other origins. IT-Tools solves this by proxying all API calls server-side — the browser only ever talks to `tools.yourdomain.com`.

---

## Database

IT-Tools uses its own MariaDB database (`snipeit_tools`) and never touches the SnipeIT database.

```sql
settings          -- module configuration (JSON per section)
custom_fields     -- SnipeIT custom field key mapping
sign_signatures   -- signature audit trail
```

---

## Environment variables

| Variable | Description | Default |
|---|---|---|
| `TOOLS_DB_HOST` | MariaDB host | `mariadb` |
| `TOOLS_DB_NAME` | Database name | `snipeit_tools` |
| `TOOLS_DB_USER` | Database user | — |
| `TOOLS_DB_PASS` | Database password | — |
| `TOOLS_OUTPUT_DIR` | Where to write generated HTML files | `/var/www/html` |

---

## API reference

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/proxy/hardware/{id}` | Fetch asset |
| `GET` | `/api/proxy/users/{id}` | Fetch user |
| `GET` | `/api/proxy/users/{id}/assets` | Fetch user assets |
| `GET` | `/api/proxy/users/{id}/accessories` | Fetch user accessories |
| `GET` | `/api/proxy/accessories` | Fetch all accessories |
| `POST` | `/api/sign/submit` | Submit signature, generate PDF, upload |
| `GET` | `/api/sign/list` | Signature audit trail |
| `POST` | `/api/label/print` | Print labels `{ ids: [], qty: 1 }` |
| `POST` | `/api/label/test` | Print test label |
| `POST` | `/api/generate` | Regenerate runner files `{ type: "all" }` |
| `GET` | `/api/status` | Status of generated files |
| `GET` | `/api/config/{section}` | Read module config |
| `POST` | `/api/config/{section}` | Save module config |

---

## Tested with

- SnipeIT v8.4.0
- PHP 8.2 / Apache
- MariaDB 10.11
- Traefik v3.1
- Docker Compose v2
- Zebra ZD410 (203 DPI, 50×25mm labels)
- Chrome, Edge, Firefox, Safari

---
