<?php
// ============================================================
//  IT-Tools — Kern: Databaseverbindung & Helper functions
//  Version  : 1.2.0
//  Modified : 2026-05-04
//  Author   :  Chris M.
//
//  Purpose:
//    Stellt allen Modulesen eine gemeinsame PDO-Singleton-Connection
//    zur MariaDB bereit. Enthält Helper functions für:
//      - Configurationsabschnitte lesen/schreiben (settings-Table)
//      - SnipeIT Custom Fields auflesen
//      - Output-Fileen schreiben
//      - JSON-Input parsen
//
//  Connectionsparameter (aus Docker-Environment-Variablen):
//    TOOLS_DB_HOST  → MariaDB-Hostname (Default: mariadb)
//    TOOLS_DB_NAME  → Databasename    (Default: snipeit_tools)
//    TOOLS_DB_USER  → Username     (Default: root)
//    TOOLS_DB_PASS  → Passwort         (Default: leer)
// ============================================================

/**
 * Gibt die gemeinsame PDO-Databaseverbindung zurück.
 * Beim ersten Aufruf wird die Connection hergestellt und
 * in einer statischen Variable für alle weiteren Aufrufe gespeichert.
 */
function pdo(): PDO {
    static $verbindung = null;
    if ($verbindung) return $verbindung;

    $host = getenv('TOOLS_DB_HOST') ?: 'mariadb';
    $name = getenv('TOOLS_DB_NAME') ?: 'snipeit_tools';
    $user = getenv('TOOLS_DB_USER') ?: 'root';
    $pass = getenv('TOOLS_DB_PASS') ?: '';

    try {
        $verbindung = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        json_fehler('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage(), 500);
    }

    return $verbindung;
}

// ── Configurationsabschnitte (settings-Table) ───────────────

/**
 * Liest einen Configurationsabschnitt aus der DB.
 * Gibt leeres Array zurück wenn der Section nicht existiert.
 *
 * @param string $abschnitt  z.B. 'mail', 'pdf', 'shared'
 */
function abschnitt_lesen(string $abschnitt): array {
    $stmt = pdo()->prepare("SELECT data FROM settings WHERE section = ?");
    $stmt->execute([$abschnitt]);
    $zeile = $stmt->fetch();
    return $zeile ? (json_decode($zeile['data'], true) ?? []) : [];
}

// Alias für Kompatibilität mit älterem Code
function get_section(string $abschnitt): array {
    return abschnitt_lesen($abschnitt);
}

/**
 * Speichert oder aktualisiert einen Configurationsabschnitt in der DB.
 * Verwendet INSERT ... ON DUPLICATE KEY UPDATE für Upsert-Verhalten.
 *
 * @param string $abschnitt  Name des Sections
 * @param array  $daten      Zu speichernde Configurationsdaten
 */
function abschnitt_speichern(string $abschnitt, array $daten): void {
    pdo()->prepare(
        "INSERT INTO settings (section, data)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()"
    )->execute([
        $abschnitt,
        json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    ]);
}

// Alias für Kompatibilität
function upsert_section(string $abschnitt, array $daten): void {
    abschnitt_speichern($abschnitt, $daten);
}

/**
 * Liest alle Configurationsabschnitte auf einmal.
 * Gibt ein assoziatives Array zurück: ['mail' => [...], 'pdf' => [...], ...]
 */
function alle_konfigurationen(): array {
    $zeilen  = pdo()->query("SELECT section, data FROM settings")->fetchAll();
    $ergebnis = [];
    foreach ($zeilen as $z) {
        $ergebnis[$z['section']] = json_decode($z['data'], true) ?? [];
    }
    return $ergebnis;
}

// ── Gemeinsame Zugriffsfunktionen für oft benötigte Valuee ─────

/**
 * Gibt den 'shared'-Configurationsabschnitt zurück
 * (SnipeIT-URL, Tools-URL, API-Token).
 */
function shared(): array {
    return abschnitt_lesen('shared');
}

/**
 * Gibt die SnipeIT-Basis-URL ohne abschließenden Schrägstrich zurück.
 * Defaultwert: http://snipeit.example.com
 */
function snipeit_url(): string {
    return rtrim(shared()['snipeitUrl'] ?? '', '/');
}

/**
 * Gibt den SnipeIT-API-Token zurück.
 * Wird server-seitig verwendet — erscheint nicht in generierten HTML-Fileen.
 */
function api_token(): string {
    return shared()['apiToken'] ?? '';
}

/**
 * Gibt die Tools-Basis-URL ohne abschließenden Schrägstrich zurück.
 * Defaultwert: http://it-tools.example.com
 */
function tools_url(): string {
    return rtrim(shared()['toolsUrl'] ?? '', '/');
}

// ── Custom Fields (SnipeIT-Fields) ───────────────────────────

/**
 * Liest alle Custom Field Mappings aus der DB.
 * Gibt eine Map zurück: ['kst' => [...], 'imei' => [...], ...]
 */
function cf_karte(): array {
    $zeilen = pdo()->query(
        "SELECT * FROM custom_fields ORDER BY sort_order, id"
    )->fetchAll();
    $karte = [];
    foreach ($zeilen as $z) $karte[$z['logical_name']] = $z;
    return $karte;
}

// Alias für Kompatibilität
function get_cf_map(): array {
    return cf_karte();
}

/**
 * Erstellt einen JavaScript-Ausdruck der den Value eines Custom Fields
 * aus dem SnipeIT-API-Response extrahiert.
 *
 * Beispiel für 'kst' mit Fallback:
 *   → "cf('_snipeit_kst_5')||cf('KST')"
 *
 * Wird in generierten Runner-HTML-Fileen eingebettet.
 *
 * @param string $name      Logischer Fieldname (z.B. 'kst', 'imei')
 * @param string $fallback  JavaScript-Ausdruck wenn Field nicht existiert
 */
function cf_js_extraktor(string $name, string $fallback = "'\u2014'"): string {
    $karte = cf_karte();
    $feld  = $karte[$name] ?? null;
    if (!$feld) return $fallback;

    $schluessel = addslashes($feld['snipeit_key']);
    $ausweich   = $feld['fallback_key'] ? addslashes($feld['fallback_key']) : null;

    return $ausweich
        ? "cf('$schluessel')||cf('$ausweich')"
        : "cf('$schluessel')";
}

// Alias für Kompatibilität
function cf_js_extractor(string $name, string $fallback = "'\u2014'"): string {
    return cf_js_extraktor($name, $fallback);
}

// ── Output-Fileen ───────────────────────────────────────────

/**
 * Schreibt eine generierte File in das Output-Directory.
 * Das Output-Directory ist der Apache-Webroot (/var/www/html),
 * damit die Fileen direkt über HTTP erreichbar sind.
 *
 * @param string $dateiname  Filename (z.B. 'install.html')
 * @param string $inhalt     Fileinhalt (HTML)
 */
function ausgabe_schreiben(string $dateiname, string $inhalt): void {
    $verzeichnis = OUTPUT_DIR;
    $pfad        = "$verzeichnis/$dateiname";

    if (!is_writable($verzeichnis)) {
        error_log("IT-Tools: Ausgabe-Verzeichnis $verzeichnis ist nicht beschreibbar");
        return;
    }

    file_put_contents($pfad, $inhalt);
}

// Alias für Kompatibilität
function write_output(string $dateiname, string $inhalt): void {
    ausgabe_schreiben($dateiname, $inhalt);
}

// ── Input-Processing ──────────────────────────────────────

/**
 * Liest und dekodiert den JSON-Request-Body.
 * Gibt ein leeres Array zurück wenn kein Body vorhanden oder kein gültiges JSON.
 */
function json_eingabe(): array {
    $roh = file_get_contents('php://input');
    if (!$roh) return [];
    $dekodiert = json_decode($roh, true);
    return is_array($dekodiert) ? $dekodiert : [];
}

// Alias für Kompatibilität
function json_input(): array {
    return json_eingabe();
}
