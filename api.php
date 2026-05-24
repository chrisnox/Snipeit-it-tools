<?php
// ============================================================
//  IT-Tools — API Router
//  Version  : 2.1.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
// ============================================================

// Output-Buffer starten — verhindert dass PHP-Warnings/Notices
// vor dem JSON landen und den Parser brechen.
ob_start();

// Response-Header setzen
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS-Preflight sofort beantworten (Browser CORS-Check)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Konstanten: Modules-Directory und Output-Directory für generierte HTML-Fileen
define('MODULES_DIR', __DIR__ . '/modules');
define('OUTPUT_DIR',  getenv('TOOLS_OUTPUT_DIR') ?: '/var/www/html');

// Kern-Helper functions laden (DB-Connection, JSON-Responseen, File-Schreiber)
require MODULES_DIR . '/core/db.php';
require MODULES_DIR . '/core/response.php';
require MODULES_DIR . '/core/snipeit.php';

// ── Request parsen ────────────────────────────────────────────
$methode    = $_SERVER['REQUEST_METHOD'];
$pfad       = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segmente   = explode('/', $pfad);

// Führendes 'api'-Segment entfernen (z.B. /api/config → [config])
if (($segmente[0] ?? '') === 'api') array_shift($segmente);

$ressource = $segmente[0] ?? '';   // Erste Ebene: config, fields, generate, status, proxy, sign, modules
$param     = $segmente[1] ?? null; // Zweite Ebene: ID, Typ, Sub-Ressource

// ── Modulese automatisch laden ──────────────────────────────────
// Durchsucht modules/*/module.php und lädt jede gefundene File.
// core/ wird übersprungen — das ist kein Feature-Modules.
function lade_module(): array {
    $module = [];
    foreach (glob(MODULES_DIR . '/*/module.php') as $datei) {
        $name = basename(dirname($datei));
        if ($name === 'core') continue;
        $modul = require_once $datei;   // require_once verhindert doppelte Funktionsdeklarationen
        if (is_array($modul)) $module[$name] = $modul;
    }
    return $module;
}

$MODULE = lade_module();

// ── Auto-Generate ────────────────────────────────────────────
// Nicht auf config/fields/sign Requests (brauchen sauberes JSON).
// POST config = Saving → auch ausschließen (config_speichern ruft
// alle_runner_generieren auf — das würde Output vor JSON erzeugen).
$istConfigRequest = $ressource === 'config' || $ressource === 'fields' || $ressource === 'sign';
if (!$istConfigRequest) {
    $dir          = defined('OUTPUT_DIR') ? OUTPUT_DIR : __DIR__ . '/';
    $versionFile  = __DIR__ . '/VERSION';
    $generatedFile= $dir . '.generated-version';
    $currentVer   = file_exists($versionFile)  ? trim(file_get_contents($versionFile))  : '0';
    $generatedVer = file_exists($generatedFile) ? trim(file_get_contents($generatedFile)) : '';
    if ($currentVer !== $generatedVer) {
        ob_start();
        try {
            alle_dateien_erzeugen($MODULE);
            file_put_contents($generatedFile, $currentVer);
        } catch (\Throwable $e) { error_log('IT-Tools auto-generate: ' . $e->getMessage()); }
        ob_end_clean();
    }
}

// ── URL-Router ────────────────────────────────────────────────
match(true) {

    // Geladene Modulese auflisten (nützlich für Debugging)
    $methode === 'GET' && $ressource === 'modules'
        => json_ok(array_map(fn($m) => [
            'name'    => $m['name'],
            'label'   => $m['label']   ?? $m['name'],
            'version' => $m['version'] ?? '1.0',
            'section' => $m['section'] ?? $m['name'],
        ], $MODULE)),

    // Alle Configurationen aus der DB aggregiert zurückgeben
    $methode === 'GET' && $ressource === 'config'
        => config_lesen($MODULE),

    // Configuration eines Sections speichern und File(en) neu generieren
    $methode === 'POST' && $ressource === 'config'
        => config_speichern($MODULE),

    // Custom Field Mappings
    $methode === 'GET'    && $ressource === 'fields'                   => modul_aufrufen($MODULE, 'fields', 'get_all'),
    $methode === 'POST'   && $ressource === 'fields'                   => modul_aufrufen($MODULE, 'fields', 'save'),
    $methode === 'DELETE' && $ressource === 'fields'                   => modul_aufrufen($MODULE, 'fields', 'delete', $param),

    // Output-Fileen (HTML-Runner) neu generieren
    $methode === 'POST' && $ressource === 'generate'                   => dateien_generieren($MODULE),

    // Status der generierten Fileen
    $methode === 'GET'  && $ressource === 'status'                     => modul_aufrufen($MODULE, 'status', 'get'),

    // ── SnipeIT-Proxy ─────────────────────────────────────────
    // Alle SnipeIT-API-Aufrufe laufen über den Proxy — kein CORS-Problem,
    // da Browser nur it-tools.example.com (same-origin) aufruft.
    $methode === 'GET' && $ressource === 'proxy'
        && ($segmente[1] ?? '') === 'hardware' && isset($segmente[2])
        => modul_aufrufen($MODULE, 'proxy', 'hardware', $segmente[2]),

    // GET /api/proxy/hardware  (Liste, ohne ID)
    $methode === 'GET' && $ressource === 'proxy'
        && ($segmente[1] ?? '') === 'hardware' && !isset($segmente[2])
        => modul_aufrufen($MODULE, 'proxy', 'hardware_list'),

    $methode === 'GET' && $ressource === 'proxy'
        && ($segmente[1] ?? '') === 'users' && ($segmente[3] ?? '') === 'assets'
        => modul_aufrufen($MODULE, 'proxy', 'user_assets', $segmente[2]),

    // GET /api/proxy/users/{id}/accessories
    $methode === 'GET' && $ressource === 'proxy'
        && ($segmente[1] ?? '') === 'users' && ($segmente[3] ?? '') === 'accessories'
        => modul_aufrufen($MODULE, 'proxy', 'user_accessories', $segmente[2]),

    // GET /api/proxy/accessories?location_id=X
    $methode === 'GET' && $ressource === 'proxy'
        && ($segmente[1] ?? '') === 'accessories' && !isset($segmente[2])
        => modul_aufrufen($MODULE, 'proxy', 'accessories'),

    // GET /api/proxy/locations
    $methode === 'GET' && $ressource === 'proxy'
        && ($segmente[1] ?? '') === 'locations'
        => modul_aufrufen($MODULE, 'proxy', 'locations'),

    // POST /api/label/print
    $methode === 'POST' && $ressource === 'label' && ($segmente[1] ?? '') === 'print'
        => modul_aufrufen($MODULE, 'label', 'print'),

    // POST /api/label/test
    $methode === 'POST' && $ressource === 'label' && ($segmente[1] ?? '') === 'test'
        => modul_aufrufen($MODULE, 'label', 'test'),

    // ── AirWatch ──────────────────────────────────────────────
    // GET  /api/airwatch/status
    $methode === 'GET'  && $ressource === 'airwatch' && ($segmente[1] ?? '') === 'status'
        => modul_aufrufen($MODULE, 'airwatch', 'status'),
    // POST /api/airwatch/sync
    $methode === 'POST' && $ressource === 'airwatch' && ($segmente[1] ?? '') === 'sync'
        => modul_aufrufen($MODULE, 'airwatch', 'sync'),
    // GET  /api/airwatch/search?serial=X
    $methode === 'GET'  && $ressource === 'airwatch' && ($segmente[1] ?? '') === 'search'
        => modul_aufrufen($MODULE, 'airwatch', 'search'),
    // GET  /api/airwatch/logs
    $methode === 'GET'  && $ressource === 'airwatch' && ($segmente[1] ?? '') === 'logs'
        => modul_aufrufen($MODULE, 'airwatch', 'logs'),
    // GET  /api/airwatch/log/{id}
    $methode === 'GET'  && $ressource === 'airwatch' && ($segmente[1] ?? '') === 'log' && isset($segmente[2])
        => modul_aufrufen($MODULE, 'airwatch', 'log_detail', $segmente[2]),

    // ── Lansweeper ────────────────────────────────────────────
    // GET  /api/lansweeper/status
    $methode === 'GET'  && $ressource === 'lansweeper' && ($segmente[1] ?? '') === 'status'
        => modul_aufrufen($MODULE, 'lansweeper', 'status'),
    // POST /api/lansweeper/sync
    $methode === 'POST' && $ressource === 'lansweeper' && ($segmente[1] ?? '') === 'sync'
        => modul_aufrufen($MODULE, 'lansweeper', 'sync'),
    // GET  /api/lansweeper/logs
    $methode === 'GET'  && $ressource === 'lansweeper' && ($segmente[1] ?? '') === 'logs'
        => modul_aufrufen($MODULE, 'lansweeper', 'logs'),
    // GET  /api/lansweeper/log/{id}
    $methode === 'GET'  && $ressource === 'lansweeper' && ($segmente[1] ?? '') === 'log' && isset($segmente[2])
        => modul_aufrufen($MODULE, 'lansweeper', 'log_detail', $segmente[2]),

    $methode === 'GET' && $ressource === 'proxy'
        && ($segmente[1] ?? '') === 'users' && isset($segmente[2])
        => modul_aufrufen($MODULE, 'proxy', 'user', $segmente[2]),

    // ── Elektronische Signature ────────────────────────────────
    $methode === 'POST' && $ressource === 'sign'
        && ($segmente[1] ?? '') === 'submit'                           => modul_aufrufen($MODULE, 'sign', 'submit'),

    $methode === 'GET' && $ressource === 'sign'
        && ($segmente[1] ?? '') === 'list'                             => modul_aufrufen($MODULE, 'sign', 'list'),

    // Unbekannte Route
    default => json_fehler('Route nicht gefunden', 404),
};

// ── Handler-Funktionen ────────────────────────────────────────

/**
 * Liest die Configuration aller Modulese und gibt sie aggregiert zurück.
 * Die Responsestruktur ist identisch mit der alten monolithischen api.php.
 */
function config_lesen(array $module): void {
    $ergebnis = [];
    foreach ($module as $name => $mod) {
        if (isset($mod['get_config'])) {
            $abschnitt = $mod['section'] ?? $name;
            $ergebnis[$abschnitt] = ($mod['get_config'])();
        }
    }
    json_ok($ergebnis);
}

/**
 * Speichert die Configuration eines Sections in der DB
 * und generiert die zugehörige Output-File automatisch neu.
 */
function config_speichern(array $module): void {
    $koerper   = json_eingabe();
    $abschnitt = $koerper['section'] ?? null;
    $daten     = $koerper['data']    ?? [];
    if (!$abschnitt) json_fehler('Fehlender Schlüssel: section');

    foreach ($module as $name => $mod) {
        if (($mod['section'] ?? $name) === $abschnitt && isset($mod['save_config'])) {
            ($mod['save_config'])($daten);
            if (isset($mod['generate'])) {
                ob_start();
                try { ($mod['generate'])(); } catch (\Throwable $e) { error_log('generate: '.$e->getMessage()); }
                ob_end_clean();
            }
            json_ok(['gespeichert' => $abschnitt, 'generiert' => isset($mod['generate'])]);
        }
    }

    if ($abschnitt === 'shared') {
        abschnitt_speichern('shared', $daten);
        ob_start();
        foreach ($module as $mod) {
            if (isset($mod['generate'])) {
                try { ($mod['generate'])(); } catch (\Throwable $e) { error_log('generate: '.$e->getMessage()); }
            }
        }
        ob_end_clean();
        json_ok(['gespeichert' => 'shared', 'generiert' => true]);
    }

    json_fehler("Unbekannter Abschnitt: $abschnitt");
}

/**
 * Generiert eine oder alle Output-Fileen neu.
 * POST-Body: {"type": "all"} oder {"type": "mail"} etc.
 */
function alle_dateien_erzeugen(array $module, string $typ = 'all'): array {
    $erzeugt = [];
    foreach ($module as $name => $mod) {
        if (!isset($mod['generate'])) continue;
        if ($typ === 'all' || $typ === $name) {
            ($mod['generate'])();
            $erzeugt[] = $mod['output_file'] ?? $name;
        }
    }
    return $erzeugt;
}

function dateien_generieren(array $module): void {
    $koerper = json_eingabe();
    $typ     = $koerper['type'] ?? 'all';
    $erzeugt = alle_dateien_erzeugen($module, $typ);
    json_ok(['generated' => $erzeugt]);
}

/**
 * Ruft einen benannten Handler eines Moduless auf.
 * Gibt 404 zurück wenn Modules oder Handler nicht existiert.
 */
function modul_aufrufen(array $module, string $modulName, string $handler, mixed $param = null): void {
    if (!isset($module[$modulName])) json_fehler("Modul '$modulName' nicht gefunden", 404);
    $mod = $module[$modulName];
    if (!isset($mod[$handler]))    json_fehler("Handler '$handler' in Modul '$modulName' nicht gefunden", 404);
    $param !== null ? ($mod[$handler])($param) : ($mod[$handler])();
}
