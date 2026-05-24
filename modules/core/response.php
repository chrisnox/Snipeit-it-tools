<?php
// ============================================================
//  IT-Tools — Kern: JSON-Response-Helper functions
//  Version  : 1.1.0
//  Modified : 2026-05-04
//  Author   :  Chris M.
//
//  Purpose:
//    Einheitliche JSON-Responseen für alle API-Endpunkte.
//    Alle Funktionen beenden die Skriptausführung nach der Output (never).
//
//  Responseformat (Erfolg):
//    { "ok": true, "data": { ... } }
//
//  Responseformat (Error):
//    { "ok": false, "error": "Errormeldung" }
// ============================================================

/**
 * Sendet eine erfolgreiche JSON-Response mit HTTP 200.
 * Beendet die Skriptausführung.
 *
 * @param mixed $daten  Beliebige Daten die als 'data' zurückgegeben werden
 */
function json_ok(mixed $daten): never {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(
        ['ok' => true, 'data' => $daten],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Sendet eine Error-JSON-Response mit dem angegebenen HTTP-Statuscode.
 * Beendet die Skriptausführung.
 *
 * @param string $meldung  Errormeldung (wird als 'error' zurückgegeben)
 * @param int    $code     HTTP-Statuscode (Default: 400 Bad Request)
 */
function json_fehler(string $meldung, int $code = 400): never {
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    echo json_encode(
        ['ok' => false, 'error' => $meldung],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// Aliase für Kompatibilität mit bestehendem Code
function json_error(string $meldung, int $code = 400): never {
    json_fehler($meldung, $code);
}
