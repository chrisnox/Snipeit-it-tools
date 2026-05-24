<?php
// ============================================================
//  IT-Tools — SnipeIT API Client Library
//  Version  : 1.1.0
//  Modified : 2026-05-19
//  Author   :  Chris M.
//
//  Zentrale Bibliothek für alle SnipeIT API-Aufrufe.
//  Jedes Modules verwendet ausschließlich diese Klasse —
//  kein Modules kennt Token, URL oder HTTP-Details.
//
//  Lazy-Init: URL und Token werden beim ersten Aufruf
//  automatisch aus der DB gelesen.
//
//  ── STANDARD ───────────────────────────────────────────────
//
//  /api/proxy/*  → proxy_pass()  → rohe SnipeIT-Response
//                                  (no wrapping, directly usable)
//
//  /api/*        → json_ok()     → {"ok":true,"data":{...}}
//
//  Alle Proxy-Aufrufe im Frontend: r.json() direkt verwenden.
//  No unwrap() nötig. SnipeIT gibt immer {total, rows:[...]}
//  oder ein einzelnes Objekt zurück.
//
// ============================================================
//
//  LESEN
//    SnipeIT::get('hardware', ['limit'=>50])        → array
//    SnipeIT::getAll('hardware', ['status'=>'RTD']) → array  (alle Pagen)
//    SnipeIT::getAsset(42)                          → asset array
//    SnipeIT::getAsset('NB-001')                    → asset by tag
//    SnipeIT::getUser(1595)                         → user array
//    SnipeIT::getUserAssets(1595)                   → assets of user
//    SnipeIT::getUserAccessories(1595)              → accessories of user
//    SnipeIT::getAccessories(['location_id'=>5])    → filtered list
//    SnipeIT::getLocations()                        → all locations
//    SnipeIT::getCategories()                       → all categories
//    SnipeIT::getModels()                           → all models
//    SnipeIT::getManufacturers()                    → all manufacturers
//    SnipeIT::getStatusLabels()                     → all status labels
//
//  SCHREIBEN
//    SnipeIT::post('hardware', $data)               → created asset
//    SnipeIT::patch('hardware/42', $data)           → updated asset
//    SnipeIT::put('hardware/42', $data)             → full update
//    SnipeIT::delete('hardware/42')                 → deleted
//    SnipeIT::createAsset($data)                    → created asset
//    SnipeIT::updateAsset(42, $data)                → updated asset
//    SnipeIT::checkoutAsset(42, $data)              → checked out
//    SnipeIT::checkinAsset(42)                      → checked in
//
//  DATEI-UPLOAD
//    SnipeIT::uploadToUser(1595, $pdfBytes, 'doc.pdf')   → bool
//    SnipeIT::uploadToAsset(42, $pdfBytes, 'doc.pdf')    → bool
//    SnipeIT::uploadFile('hardware/42/files', $bytes, 'f.pdf') → array
//
//  FEHLERBEHANDLUNG
//    Alle Methoden werfen SnipeITException bei Connectionsproblemen.
//    try { $res = SnipeIT::get(...); }
//    catch (SnipeITException $e) { json_fehler($e->getMessage()); }
//
//  HILFSMETHODEN
//    SnipeIT::isSuccess($response)   → bool
//    SnipeIT::baseUrl()              → 'http://snipeit.example.com'
// ============================================================

class SnipeITException extends RuntimeException {}

class SnipeIT
{
    private static ?string $url     = null;
    private static ?string $token   = null;
    private static int     $timeout = 15;

    // ── Initialization ────────────────────────────────────────

    /** Manuell initialisieren (optional — sonst Lazy-Init aus DB) */
    public static function init(string $url, string $token, int $timeout = 15): void
    {
        self::$url     = rtrim($url, '/') . '/api/v1';
        self::$token   = $token;
        self::$timeout = $timeout;
    }

    /** Lazy-Init: liest URL und Token aus der DB */
    private static function boot(): void
    {
        if (self::$url && self::$token) return;
        $cfg   = abschnitt_lesen('shared');
        $url   = trim($cfg['snipeitUrl'] ?? '');
        $token = trim($cfg['apiToken']   ?? '');
        if (!$url || !$token) {
            throw new SnipeITException('SnipeIT URL oder API-Token nicht konfiguriert. Bitte im Admin unter API / URLs eintragen.');
        }
        self::init($url, $token);
    }

    // ── Basis HTTP-Methoden ────────────────────────────────────

    /** GET /api/v1/{endpoint}?{params} */
    public static function get(string $endpoint, array $params = []): array
    {
        self::boot();
        $url = self::$url . '/' . ltrim($endpoint, '/');
        if ($params) $url .= '?' . http_build_query($params);
        return self::request('GET', $url);
    }

    /**
     * GET mit automatischem Paging — gibt alle Datensätze zurück.
     * Nützlich für große Listen (Assets, User, etc.)
     */
    public static function getAll(string $endpoint, array $params = [], int $limit = 500): array
    {
        $params['limit']  = $limit;
        $params['offset'] = 0;
        $all = [];
        do {
            $res   = self::get($endpoint, $params);
            $rows  = $res['rows'] ?? [];
            $all   = array_merge($all, $rows);
            $params['offset'] += $limit;
            $total = $res['total'] ?? count($rows);
        } while (count($rows) === $limit && count($all) < $total);
        return $all;
    }

    /** POST /api/v1/{endpoint} */
    public static function post(string $endpoint, array $data = []): array
    {
        self::boot();
        return self::request('POST', self::$url . '/' . ltrim($endpoint, '/'), $data);
    }

    /** PATCH /api/v1/{endpoint} */
    public static function patch(string $endpoint, array $data = []): array
    {
        self::boot();
        return self::request('PATCH', self::$url . '/' . ltrim($endpoint, '/'), $data);
    }

    /** PUT /api/v1/{endpoint} */
    public static function put(string $endpoint, array $data = []): array
    {
        self::boot();
        return self::request('PUT', self::$url . '/' . ltrim($endpoint, '/'), $data);
    }

    /** DELETE /api/v1/{endpoint} */
    public static function delete(string $endpoint): array
    {
        self::boot();
        return self::request('DELETE', self::$url . '/' . ltrim($endpoint, '/'));
    }

    // ── File-Upload ───────────────────────────────────────────

    /**
     * Multipart-Upload einer File zu einem SnipeIT-Endpunkt.
     *
     * @param string $endpoint  z.B. 'hardware/42/files'
     * @param string $fileData  Rohe File-Bytes
     * @param string $filename  Filename inkl. Endung
     * @param string $mimeType  MIME-Type (default: application/pdf)
     * @param string $fieldName Formularfeld-Name (default: file[])
     */
    public static function uploadFile(
        string $endpoint,
        string $fileData,
        string $filename,
        string $mimeType = 'application/pdf',
        string $fieldName = 'file[]'
    ): array {
        self::boot();
        $url  = self::$url . '/' . ltrim($endpoint, '/');
        $bnd  = 'ITToolsBnd' . uniqid();
        $body = "--$bnd\r\n"
              . "Content-Disposition: form-data; name=\"$fieldName\"; filename=\"$filename\"\r\n"
              . "Content-Type: $mimeType\r\n\r\n"
              . $fileData
              . "\r\n--$bnd--\r\n";

        $ctx  = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", [
                'Authorization: Bearer ' . self::$token,
                'Accept: application/json',
                "Content-Type: multipart/form-data; boundary=$bnd",
                'Content-Length: ' . strlen($body),
            ]),
            'content'       => $body,
            'ignore_errors' => true,
            'timeout'       => self::$timeout,
        ]]);

        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) throw new SnipeITException("Upload fehlgeschlagen: $endpoint");
        return json_decode($resp, true) ?: [];
    }

    /**
     * File zur Employee-Akte hochladen.
     * SnipeIT erwartet field 'file[]' (User-Endpunkt).
     * Fallback: 'file[ ]' (mit Emptyzeichen, ältere Versionen).
     */
    public static function uploadToUser(int $userId, string $fileData, string $filename, string $mimeType = 'application/pdf'): bool
    {
        $res = self::uploadFile("users/$userId/files", $fileData, $filename, $mimeType, 'file[]');
        if (self::isSuccess($res)) return true;
        $res2 = self::uploadFile("users/$userId/files", $fileData, $filename, $mimeType, 'file[ ]');
        return self::isSuccess($res2);
    }

    /**
     * File zu einem Asset hochladen.
     * SnipeIT erwartet field 'file[ ]' (mit Emptyzeichen, Hardware-Endpunkt).
     * Fallback: 'file[]' (ohne Emptyzeichen).
     */
    public static function uploadToAsset(int $assetId, string $fileData, string $filename, string $mimeType = 'application/pdf'): bool
    {
        $res = self::uploadFile("hardware/$assetId/files", $fileData, $filename, $mimeType, 'file[ ]');
        if (self::isSuccess($res)) return true;
        $res2 = self::uploadFile("hardware/$assetId/files", $fileData, $filename, $mimeType, 'file[]');
        return self::isSuccess($res2);
    }

    // ── Komfort-Methoden ──────────────────────────────────────

    /** Asset per ID oder Asset-Tag laden */
    public static function getAsset(int|string $idOrTag): array
    {
        return is_int($idOrTag)
            ? self::get("hardware/$idOrTag")
            : self::get("hardware/bytag/$idOrTag");
    }

    public static function getUser(int $id): array             { return self::get("users/$id"); }
    public static function getUserAssets(int $id): array       { return self::get("users/$id/assets",      ['limit'=>200]); }
    public static function getUserAccessories(int $id): array  { return self::get("users/$id/accessories", ['limit'=>200]); }

    public static function getAccessory(int $id): array        { return self::get("accessories/$id"); }
    public static function getAccessories(array $params = []): array
    {
        return self::get('accessories', array_merge(['limit'=>500,'sort'=>'name','order'=>'asc'], $params));
    }

    public static function getLocations(array $params = []): array
    {
        return self::get('locations', array_merge(['limit'=>500,'sort'=>'name','order'=>'asc'], $params));
    }
    public static function getCategories(array $params = []): array
    {
        return self::get('categories', array_merge(['limit'=>500,'sort'=>'name','order'=>'asc'], $params));
    }
    public static function getModels(array $params = []): array
    {
        return self::get('models', array_merge(['limit'=>500,'sort'=>'name','order'=>'asc'], $params));
    }
    public static function getManufacturers(): array  { return self::get('manufacturers', ['limit'=>500]); }
    public static function getStatusLabels(): array   { return self::get('statuslabels',  ['limit'=>500]); }

    public static function createAsset(array $data): array            { return self::post('hardware', $data); }
    public static function updateAsset(int $id, array $data): array   { return self::patch("hardware/$id", $data); }
    public static function checkoutAsset(int $id, array $data): array { return self::post("hardware/$id/checkout", $data); }
    public static function checkinAsset(int $id, array $data = []): array { return self::post("hardware/$id/checkin", $data); }

    // ── Hilfsmethoden ─────────────────────────────────────────

    /**
     * Prüft ob eine SnipeIT-Response erfolgreich war.
     * SnipeIT gibt in verschiedenen Endpunkten unterschiedliche
     * Erfolgs-Indikatoren zurück — diese Methode normalisiert das.
     */
    public static function isSuccess(array $res): bool
    {
        return ($res['status']             ?? '') === 'success'
            || ($res['messages']['status'] ?? '') === 'success';
    }

    /** Gibt die SnipeIT-Basis-URL zurück (ohne /api/v1) */
    public static function baseUrl(): string
    {
        self::boot();
        return substr(self::$url, 0, -strlen('/api/v1'));
    }

    // ── Interner HTTP-Client ───────────────────────────────────

    private static function request(string $method, string $url, ?array $data = null): array
    {
        $headers = [
            'Authorization: Bearer ' . self::$token,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $opts = [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout'       => self::$timeout,
        ];

        if ($data !== null) {
            $opts['content'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $ctx  = stream_context_create(['http' => $opts]);
        $resp = @file_get_contents($url, false, $ctx);

        if ($resp === false) {
            throw new SnipeITException("Verbindung zu SnipeIT fehlgeschlagen: $url");
        }

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            throw new SnipeITException("Ungültige JSON-Antwort: " . substr($resp, 0, 100));
        }

        return $decoded;
    }
}
