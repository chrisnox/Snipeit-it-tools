<?php
// ============================================================
//  IT-Tools — Modules: SnipeIT Proxy
//  Version  : 2.1.0
//  Modified : 2026-05-20
//  Author   :  Chris M.
//
//  STANDARD: Proxy-Routes geben die rohe SnipeIT-Response zurück
//  (kein json_ok-Wrapping). Alle anderen /api/* Routes verwenden
//  json_ok(). Damit braucht kein Frontend-Code unwrap().
//
//  Browser → /api/proxy/* → PHP → SnipeIT API → rohe Response
// ============================================================

return [
    'name'    => 'proxy',
    'label'   => 'SnipeIT Proxy',
    'version' => '2.1.0',
    'section' => 'proxy',

    // GET /api/proxy/hardware/{id}
    'hardware' => function(mixed $id): void {
        try { proxy_pass(SnipeIT::getAsset((int)$id)); }
        catch (SnipeITException $e) { proxy_error($e->getMessage()); }
    },

    // GET /api/proxy/hardware?category_id=X&limit=Y
    'hardware_list' => function(): void {
        try {
            $p = ['limit' => (int)($_GET['limit'] ?? 50), 'offset' => (int)($_GET['offset'] ?? 0)];
            if (isset($_GET['category_id'])) $p['category_id'] = (int)$_GET['category_id'];
            if (isset($_GET['status_id']))   $p['status_id']   = (int)$_GET['status_id'];
            if (isset($_GET['search']))      $p['search']      = $_GET['search'];
            proxy_pass(SnipeIT::get('hardware', $p));
        } catch (SnipeITException $e) { proxy_error($e->getMessage()); }
    },

    // GET /api/proxy/users/{id}
    'user' => function(mixed $id): void {
        try { proxy_pass(SnipeIT::getUser((int)$id)); }
        catch (SnipeITException $e) { proxy_error($e->getMessage()); }
    },

    // GET /api/proxy/users/{id}/assets
    'user_assets' => function(mixed $id): void {
        try { proxy_pass(SnipeIT::getUserAssets((int)$id)); }
        catch (SnipeITException $e) { proxy_error($e->getMessage()); }
    },

    // GET /api/proxy/users/{id}/accessories
    'user_accessories' => function(mixed $id): void {
        try { proxy_pass(SnipeIT::getUserAccessories((int)$id)); }
        catch (SnipeITException $e) { proxy_error($e->getMessage()); }
    },

    // GET /api/proxy/accessories?location_id=X&limit=Y
    'accessories' => function(): void {
        try {
            $p = ['limit' => (int)($_GET['limit'] ?? 500), 'sort' => 'name', 'order' => 'asc'];
            if (!empty($_GET['location_id'])) $p['location_id'] = (int)$_GET['location_id'];
            if (!empty($_GET['offset']))      $p['offset']      = (int)$_GET['offset'];
            proxy_pass(SnipeIT::get('accessories', $p));
        } catch (SnipeITException $e) { proxy_error($e->getMessage()); }
    },

    // GET /api/proxy/locations
    'locations' => function(): void {
        try { proxy_pass(SnipeIT::getLocations()); }
        catch (SnipeITException $e) { proxy_error($e->getMessage()); }
    },
];

// ── Proxy-Output-Helpers ───────────────────────────────────────
// Gibt die rohe SnipeIT-Response weiter — kein json_ok-Wrapping.
// Jeder Code der /api/proxy/* aufruft bekommt direkt SnipeIT-Format.

function proxy_pass(array $data): never {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function proxy_error(string $msg, int $code = 502): never {
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'messages' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
//  IT-Tools — Proxy Modules
//  Version  : 2.0.0
//  Modified : 2026-05-19
//
//  Purpose:
//    Leitet Browser-Fetch-Requestn server-seitig an SnipeIT
//    weiter. Löst CORS-Problem — der API-Token bleibt im
//    PHP-Container und erscheint nie im Browser.
//
//  Jetzt powered by SnipeIT Client Library (core/snipeit.php).
//  No manuelles HTTP-Handling mehr nötig.
// ============================================================

return [
    'name'    => 'proxy',
    'label'   => 'SnipeIT Proxy',
    'version' => '2.0.0',
    'section' => 'proxy',

    'hardware' => function(mixed $id): void {
        try { json_ok(SnipeIT::getAsset((int)$id)); }
        catch (SnipeITException $e) { json_fehler($e->getMessage()); }
    },

    'hardware_list' => function(): void {
        try {
            $p = [];
            if (isset($_GET['category_id'])) $p['category_id'] = (int)$_GET['category_id'];
            if (isset($_GET['status_id']))   $p['status_id']   = (int)$_GET['status_id'];
            if (isset($_GET['search']))      $p['search']      = $_GET['search'];
            $p['limit']  = (int)($_GET['limit']  ?? 1);
            $p['offset'] = (int)($_GET['offset'] ?? 0);
            json_ok(SnipeIT::get('hardware', $p));
        } catch (SnipeITException $e) { json_fehler($e->getMessage()); }
    },

    'user' => function(mixed $id): void {
        try { json_ok(SnipeIT::getUser((int)$id)); }
        catch (SnipeITException $e) { json_fehler($e->getMessage()); }
    },

    'user_assets' => function(mixed $id): void {
        try { json_ok(SnipeIT::getUserAssets((int)$id)); }
        catch (SnipeITException $e) { json_fehler($e->getMessage()); }
    },

    'user_accessories' => function(mixed $id): void {
        try { json_ok(SnipeIT::getUserAccessories((int)$id)); }
        catch (SnipeITException $e) { json_fehler($e->getMessage()); }
    },

    'accessories' => function(): void {
        try {
            $params = ['limit' => intval($_GET['limit'] ?? 500), 'sort' => 'name', 'order' => 'asc'];
            if (!empty($_GET['location_id'])) $params['location_id'] = intval($_GET['location_id']);
            if (!empty($_GET['offset']))      $params['offset']      = intval($_GET['offset']);
            json_ok(SnipeIT::get('accessories', $params));
        } catch (SnipeITException $e) { json_fehler($e->getMessage()); }
    },

    'locations' => function(): void {
        try { json_ok(SnipeIT::getLocations()); }
        catch (SnipeITException $e) { json_fehler($e->getMessage()); }
    },
];
