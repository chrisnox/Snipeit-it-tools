# IT-Tools — Changelog

## v2.4.2 (2026-05-20)
- **FIX** Label ZPL layout redesigned: text left (large), QR code right (small module size 2)
- **FIX** `ausgabe_schreiben()` function name corrected in label and airwatch modules

## v2.4.1 (2026-05-20)
- **NEW** Dry Run mode for AirWatch and Lansweeper sync
  - Analyses what would be created without writing to SnipeIT
  - Shows `→ NEW` for missing devices, `⚠` for missing models
  - Yellow log color = Dry Run, blue/green = real sync
- Dry Run buttons in Admin → AirWatch and Lansweeper panels

## v2.4.0 (2026-05-20)
- **FIX** `ausgabe_datei_speichern()` → `ausgabe_schreiben()` (correct function name)
- **FIX** AirWatch `generate` function fixed

## v2.3.9 (2026-05-20)
- **FIX** Label bookmark: added `label` case to `build_permanent_bm()` — was falling through to PDF
- Label bookmark opens `label-print.html` standalone (no page context required)

## v2.3.8 (2026-05-20)
- **REWORK** Label Print module completely rewritten
  - Loads all accessories via `/api/proxy/accessories`
  - Categories derived client-side from accessory data
  - Category filter → accessory list → qty per item (max = available stock)
  - Unavailable items shown as greyed out, not selectable
  - No changes to any other module

## v2.3.7 (2026-05-20)
- **STANDARD** Proxy routes return raw SnipeIT response (no `json_ok` wrapping)
- `/api/proxy/*` → `proxy_pass()` → raw SnipeIT format `{total, rows:[...]}`
- `/api/*` → `json_ok()` → `{"ok": true, "data": {...}}`
- Removed `unwrap()` from PDF and Sign runners — not needed anymore
- Dashboard KPI loading fixed to use raw proxy format

## v2.3.6 (2026-05-20)
- **FIX** PDF runner: proxy responses now correctly parsed (`user.id` check)
- **FIX** Sign runner: same proxy response handling fix

## v2.3.5 (2026-05-20)
- **FIX** Route `/api/proxy/users/{id}` called handler `'users'` but handler is named `'user'`

## v2.3.4 (2026-05-19)
- **FIX** Dashboard 404: `hardware_list` route added (`/api/proxy/hardware` without ID)
- **FIX** Accessories 400: proxy handler now passes `limit` query param through
- **FIX** AirWatch save JSON error: `config_speichern()` wraps `generate()` in `ob_start()`

## v2.3.3 (2026-05-19)
- **FIX** `hardware_list` handler added to proxy module
- **FIX** `extra_hosts` required for container DNS resolution of `snipeit.example.com`

## v2.3.2 (2026-05-19)
- **FIX** Duplicate `}` in `distribute/runner.php` — PHP syntax error

## v2.3.1 (2026-05-19)
- **NEW** Dashboard: live KPIs from SnipeIT, sync status bar, quick actions, file status
- Dashboard auto-loads on admin open; manual refresh button

## v2.3.0 (2026-05-19)
- **NEW** AirWatch MDM module: sync status diff, manual sync, live log, device search bookmarklet
- **NEW** Lansweeper module: CSV import, column mapping config, import history
- Both modules use `SnipeIT::` library — no duplicate HTTP code
- Admin sidebar: new AirWatch and Lansweeper sections
- Bookmarklet: AirWatch search on `/hardware/{id}`

## v2.2.8 (2026-05-19)
- **NEW** SnipeIT Library v1.1.0: fully documented with quick-reference
- `getAll()` for automatic pagination
- Full convenience methods: Assets, Users, Accessories, Locations, Categories, Models

## v2.2.7 (2026-05-19)
- **FIX** Entrypoint now regenerates all files on every container start
- Auto-generate uses VERSION file as trigger — regenerates once per deploy

## v2.2.6 (2026-05-19)
- **FIX** Label bookmark: `build_permanent_bm()` had no `label` case
- **FIX** `showLabel` missing from `generate_install_page()` defaults
- **FIX** Category IDs corrected: iPad=5, Phone=2 (were swapped)

## v2.2.5 (2026-05-19)
- **FIX** Custom fields save error: `alle_runner_generieren()` now uses `ob_start()/finally/catch`
- **FIX** Label bookmark missing: `showLabel` default not in `distribute/module.php`

## v2.2.4 (2026-05-19)
- **FIX** `api.php`: `lade_module()` was called twice → duplicate function declaration
- **FIX** `api.php`: `require` → `require_once` in `lade_module()`

## v2.2.3 (2026-05-19)
- **FIX** `admin.html`: `apiGet/apiPost/apiDelete` use robust text parsing instead of `r.json()`
- PHP warnings before JSON response no longer break the admin

## v2.2.2 (2026-05-19)
- **FIX** `fields/module.php`: `alle_runner_generieren()` used `require` instead of `global $MODULE`

## v2.2.1 (2026-05-19)
- **FIX** JSON parse error on config: global `ob_start()` added to `api.php`
- `ob_end_clean()` in `json_ok`/`json_fehler` — PHP warnings never reach JSON output
- Auto-generate skipped on `/api/config` requests

## v2.2.0 (2026-05-06)
- **NEW** Label Print module completely reworked
  - Standalone page: location dropdown → accessory list → print
  - Label: QR code (SnipeIT link) + name + location + date
  - Adjustable quantity per item
  - Route `GET /api/proxy/locations` added
  - Route `GET /api/proxy/accessories?location_id=X` (filter)
  - Admin simplified: only IP, port, copies

## v2.1.2 (2026-05-06)
- **NEW** Auto-generate: missing runner files generated on first API request after deploy
- **NEW** `docker-entrypoint.sh`: generates files at container startup via Apache
- `dateien_generieren()` split — `alle_dateien_erzeugen()` callable without JSON body
- No manual `curl` after rebuild required

## v2.1.1 (2026-05-06)
- **FIX** Label bookmark missing on install page (`distribute/runner.php`)
- New section "Accessories page `/accessories/{id}`" on install.html
- Copy link for label bookmark added

## v2.1.0 (2026-05-06)
- **NEW** Label Print module (Zebra ZD410, 50×25mm, ZPL II, TCP 9100)
  - Bookmarklet from `/accessories/{id}`
  - Configurable fields: name, category, manufacturer, model, S/N, order no., purchase date, location, notes
  - Code-128 barcode (serial number, fallback: order number)
  - Test label function in admin
  - Admin section: Label Print (sidebar + config panel)
- Document header (PDF/Sign) adapts to Handover/Return mode
- Filename prefix: `Handover_` / `Return_`

## v2.0.0 (2026-05-06)
- **NEW** PDF mode picker: choose Handover or Return (single bookmarklet)
- **NEW** Return mode: condition column, RETURN stamp, remarks field
- **NEW** Digital signature in PDF (toggle, background upload after printing)
- **NEW** Accessories in PDF protocol
- **FIX** Race condition in PDF runner (data loads after mode selection)
- **FIX** `json_encode` instead of `addslashes` for all free-text fields (umlaut-safe)
- **FIX** `asset_data` column added to DB migration
- **FIX** Upload field names: `file[]` for users, `file[ ]` for assets (with fallback)
- **FIX** Copy links on install page (Return → bm-pdf)
- Signature document as PDF (JPEG in PDF-1.4, no external library)
- Robust JSON parsing of upload response (PHP warnings ignored)
- Route `/api/proxy/users/{id}/accessories` added
- Routing: `/` → `install.html`, `/admin` → Admin SPA

## v1.0.0 (2026-05-04) — Baseline
- Outlook Mail bookmarklet (`/hardware/{id}`)
- Device handover PDF with asset selection
- Digital signature with SnipeIT upload
- Custom field mapping (CRUD)
- MariaDB persistence
- Traefik integration / CORS solution via proxy

---
*Authored by Chris M.*
