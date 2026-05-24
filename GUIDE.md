# IT-Tools — User & Installation Guide

**Version 2.4 · May 2026**

> For a quick overview see [README.md](README.md).
> This document covers deployment details, admin configuration and end-user workflows.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Initial Configuration](#initial-configuration)
4. [Installing Bookmarklets](#installing-bookmarklets)
5. [Module Guide](#module-guide)
6. [Architecture & Extension](#architecture--extension)
7. [Troubleshooting](#troubleshooting)
8. [Changelog Summary](#changelog-summary)

---

## Prerequisites

- Docker + Docker Compose
- MariaDB (existing instance can be shared)
- SnipeIT v8.x
- Network access: IT-Tools container → SnipeIT (server-side, no CORS issue)
- Traefik (optional, for domain routing)

---

## Installation

### Step 1 — File structure

```
it-tools/
├── .htaccess
├── Dockerfile.it-tools
├── api.php
├── index.php
├── admin.html                   ← Bind-mount (hot-reload without rebuild)
├── init-tools-db.sql
└── modules/
    ├── core/db.php + response.php + snipeit.php
    ├── shared/, mail/, pdf/, distribute/
    ├── fields/, proxy/, sign/, status/
    ├── label/, airwatch/, lansweeper/
```

### Step 2 — docker-compose.yml

```yaml
  it-tools:
    build:
      context: ./it-tools
      dockerfile: Dockerfile.it-tools
    container_name: it-tools
    restart: always
    ports: ["7777:80"]
    extra_hosts: ["snipeit.example.com:192.168.1.100"]
    environment:
      TOOLS_DB_HOST: mariadb
      TOOLS_DB_NAME: snipeit_tools
      TOOLS_DB_USER: root
      TOOLS_DB_PASS: ${MARIADB_ROOT_PASSWORD}
      TOOLS_OUTPUT_DIR: /var/www/html
    volumes:
      - ./it-tools/admin.html:/var/www/html/admin.html:ro
      - ./it-tools/ls_brut_exp:/data/BRUT:ro
    depends_on:
      mariadb:
        condition: service_healthy
```

### Step 3 — Initialize database

```bash
docker exec -i mariadb mariadb \
  -uroot -p$(grep MARIADB_ROOT_PASSWORD .env | cut -d= -f2) \
  < ./it-tools/init-tools-db.sql
```

### Step 4 — Build and start

```bash
docker compose up -d --build it-tools
```

---

## Initial Configuration

Open admin: `http://it-tools.example.com/admin`

### API / URLs (section: shared)

| Field | Example |
|---|---|
| SnipeIT URL | `http://snipeit.example.com` |
| Tools URL | `http://it-tools.example.com` |
| API Token | Read/Write token from SnipeIT → Profile → API Keys |

### PDF — Handover & Return

| Field | Description |
|---|---|
| Company name | Appears in document header |
| Document title | Title for handover protocol |
| Note text | Text above signature field |
| Footer | Bottom of page text |
| Categories + Fields | Which devices and columns appear |

### Digital Signature

| Field | Description |
|---|---|
| Confirmation text | Text the employee confirms with signature |
| Categories | Which assets are selectable |
| Upload to SnipeIT | Auto-upload (recommended: enabled) |

### Label Print

| Field | Description |
|---|---|
| Printer IP | e.g. `10.0.0.50` |
| Port | `9100` (ZPL over TCP) |
| Default copies | Starting quantity |

### AirWatch MDM

| Field | Description |
|---|---|
| AirWatch URL | `https://192.168.1.50` |
| Username / Password | MDM API credentials |
| Tenant Code | `aw-tenant-code` header value |
| SSL Verify | Disable for self-signed certificates |

### Lansweeper

| Field | Description |
|---|---|
| Directory (container path) | `/data/BRUT` |
| File pattern | `*.csv` |
| Column mapping | Serial, AssetName, User, Model, Manufacturer |
| SnipeIT defaults | Category ID, Status ID for new assets |

---

## Installing Bookmarklets

**Show bookmark bar:**
- Chrome / Edge / Firefox: `Cmd+Shift+B`
- Safari: View → Show Favorites Bar

**Install page:** `http://it-tools.example.com`

Drag each button to the bookmark bar. One-time setup — permanent.

| Bookmarklet | Page | Function |
|---|---|---|
| Mail to Accounting | `/hardware/{id}` | Outlook mail |
| Handover & Return PDF | `/users/{id}` | A4 protocol |
| Sign Handover | `/users/{id}` | Digital signature |
| Print Label | Anywhere | Zebra ZD410 label |
| AirWatch Search | `/hardware/{id}` | MDM device details |

---

## Module Guide

### Outlook Mail

1. Open SnipeIT asset → click bookmarklet
2. Popup loads asset data → Outlook opens with pre-filled mail
3. Review and send

### Handover & Return PDF

1. Open SnipeIT user → click bookmarklet
2. **Mode picker** appears — choose Handover or Return
3. Assets and accessories load (all pre-selected)
4. Adjust selection + quantities
5. Optional: toggle **"Sign digitally"** → draw signature
6. **Print** → A4 document + print dialog

**Handover vs Return differences:**

| | Handover | Return |
|---|---|---|
| Toolbar color | Purple | Orange |
| Stamp | — | RETURN |
| Condition column | — | ☐ Good ☐ Damaged ☐ Defective |
| Remarks field | — | ✓ |
| Filename | `Handover_DATE_Name.pdf` | `Return_DATE_Name.pdf` |

### Digital Signature (sign.html)

1. Open SnipeIT user → click bookmarklet
2. Select assets
3. Employee signs (finger, stylus or mouse)
4. Confirm → PDF generated and uploaded to:
   - Employee file (SnipeIT User → Files tab)
   - Each selected asset (SnipeIT Asset → Files tab)

### Label Print

1. Click bookmarklet (from anywhere)
2. Select category from dropdown
3. Choose accessories + quantity per item (max = available stock)
4. Click Print → ZPL sent to Zebra ZD410 via TCP port 9100

**Label content (50×25mm):**
- Name (large)
- Category
- Date
- QR code (opens SnipeIT accessory page)

### AirWatch MDM

**Admin → AirWatch → Sync & Status:**

1. Click **Load** to see current diff (AirWatch vs SnipeIT)
2. Click **Dry Run** to preview what would be created (no writes)
3. Click **Sync** to create missing devices in SnipeIT

**Bookmarklet on `/hardware/{id}`:** Opens popup with live MDM status (enrollment, OS, IMEI).

### Lansweeper CSV Import

**Admin → Lansweeper → CSV Import:**

1. Click **Status** to see available CSV files
2. Click **Dry Run** to preview import (no writes)
   - `→ NEW` = would be created
   - `⚠` = model missing in SnipeIT (would be auto-created)
3. Click **Import** to create missing notebooks in SnipeIT

---

## Architecture & Extension

### Routes

| Method | Path | Function |
|---|---|---|
| GET | `/api/proxy/hardware/{id}` | Load asset |
| GET | `/api/proxy/hardware?category_id=X` | Asset list |
| GET | `/api/proxy/users/{id}` | Load user |
| GET | `/api/proxy/users/{id}/assets` | User assets |
| GET | `/api/proxy/users/{id}/accessories` | User accessories |
| GET | `/api/proxy/accessories?location_id=X` | Filtered accessories |
| GET | `/api/proxy/locations` | All locations |
| POST | `/api/sign/submit` | Submit signature + upload |
| POST | `/api/airwatch/sync` | AirWatch sync (dry_run supported) |
| POST | `/api/lansweeper/sync` | Lansweeper import (dry_run supported) |
| POST | `/api/generate` | Regenerate runner files |
| GET | `/api/status` | File status |

### API Standard

```
/api/proxy/*  →  proxy_pass()  →  raw SnipeIT response (no wrapping)
/api/*        →  json_ok()     →  {"ok": true, "data": {...}}
```

### Database schema

```sql
settings          -- module configuration (JSON per section)
custom_fields     -- logical name → SnipeIT custom_field key
sign_signatures   -- signature audit trail
airwatch_sync_log -- AirWatch sync history
lansweeper_sync_log -- Lansweeper import history
```

### Known SnipeIT API quirks

| Issue | Solution |
|---|---|
| User file upload field | `file[]` (no space) |
| Asset file upload field | `file[ ]` (with space) |
| CORS blocks browser fetch | Proxy module forwards server-side |
| PHP warnings before JSON | `ob_start()` at top of api.php |

---

## Troubleshooting

### Container won't start

```bash
docker logs it-tools --tail 50
```

### API not responding

```bash
curl http://it-tools.example.com/api/status
```

### 401/403 from SnipeIT

```bash
TOKEN=$(docker exec -i mariadb mariadb -uroot \
  -p$(grep MARIADB_ROOT_PASSWORD .env | cut -d= -f2) snipeit_tools \
  -sNe "SELECT JSON_UNQUOTE(JSON_EXTRACT(data,'$.apiToken')) FROM settings WHERE section='shared';")

curl -s -o /dev/null -w "%{http_code}" \
  -H "Authorization: Bearer $TOKEN" \
  http://snipeit.example.com/api/v1/hardware/1
# Expected: 200
```

### JSON parse error in admin

```bash
curl -s http://it-tools.example.com/api/config | head -c 100
# Must start with: {"ok":true,"data":{
```

### File not generated

```bash
curl -s -X POST http://it-tools.example.com/api/generate \
  -H "Content-Type: application/json" -d '{"type":"all"}'

curl -I http://it-tools.example.com/label-print.html
# Expected: HTTP/1.1 200 OK
```

### Common commands

```bash
docker ps | grep it-tools
docker logs it-tools --tail 50
docker compose up -d --build it-tools
docker exec -i mariadb mariadb \
  -uroot -p$(grep MARIADB_ROOT_PASSWORD .env | cut -d= -f2) snipeit_tools
```

---
*Authored by Chris M.*
