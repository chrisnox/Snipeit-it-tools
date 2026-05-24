<?php
// ============================================================
//  IT-Tools — Modules: Custom Field Mapping
//  Version  : 1.2.0
//  Modified : 2026-05-04
//  Author   :  Chris M.
//
//  Purpose:
//    Verwaltet die Zuordnung zwischen logischen Fieldnamen
//    und den tatsächlichen SnipeIT custom_fields-Keyn.
//
//  Beispiel:
//    Logischer Name: 'kst'
//    SnipeIT-Key: '_snipeit_kst_5'
//    Fallback-Key: 'KST' (optional, wird probiert wenn primärer Key leer)
//
//  Nach jeder Change werden ALLE Runner-Fileen neu generiert,
//  da Custom Fields in Mail, PDF und Sign-Page verwendet werden.
//
//  Databasestruktur (Table: custom_fields):
//    id            INT AUTO_INCREMENT PRIMARY KEY
//    logical_name  VARCHAR(64)   Eindeutiger logischer Name (z.B. 'kst')
//    label         VARCHAR(128)  Anzeige-Name (z.B. 'Cost center')
//    snipeit_key   VARCHAR(256)  Primärer SnipeIT-Key
//    fallback_key  VARCHAR(256)  Ausweich-Key (optional)
//    tool          VARCHAR(32)   Verwendung: 'mail', 'pdf' oder 'both'
//    sort_order    TINYINT       Sortierung in der Admin-Oberfläche
// ============================================================

return [
    'name'    => 'fields',
    'label'   => 'Custom Fields',
    'version' => '1.2.0',
    'section' => 'fields',

    /**
     * Gibt alle Custom Field Mappings sortiert nach sort_order zurück.
     * GET /api/fields
     */
    'get_all' => function(): void {
        $felder = pdo()->query(
            "SELECT * FROM custom_fields ORDER BY sort_order, id"
        )->fetchAll();
        json_ok($felder);
    },

    /**
     * Speichert ein neues oder aktualisiert ein bestehendes Custom Field Mapping.
     * Verwendet logical_name als eindeutigen Key (UPSERT).
     * POST /api/fields
     *
     * Pflichtfelder: logical_name, snipeit_key
     */
    'save' => function(): void {
        $daten = json_eingabe();

        if (!($daten['logical_name'] ?? '') || !($daten['snipeit_key'] ?? '')) {
            json_fehler('logical_name und snipeit_key sind Pflichtfelder');
        }

        pdo()->prepare(
            "INSERT INTO custom_fields
                (logical_name, label, snipeit_key, fallback_key, tool, sort_order)
             VALUES
                (:ln, :label, :sk, :fb, :tool, :sort)
             ON DUPLICATE KEY UPDATE
                label        = VALUES(label),
                snipeit_key  = VALUES(snipeit_key),
                fallback_key = VALUES(fallback_key),
                tool         = VALUES(tool),
                sort_order   = VALUES(sort_order)"
        )->execute([
            ':ln'    => $daten['logical_name'],
            ':label' => $daten['label']        ?? $daten['logical_name'],
            ':sk'    => $daten['snipeit_key'],
            ':fb'    => $daten['fallback_key'] ?? null,
            ':tool'  => $daten['tool']         ?? 'both',
            ':sort'  => intval($daten['sort_order'] ?? 0),
        ]);

        // Alle Runner-Fileen neu generieren da Custom Fields sich geändert haben
        alle_runner_generieren();
        json_ok(['gespeichert' => $daten['logical_name'], 'generiert' => true]);
    },

    /**
     * Löscht ein Custom Field Mapping anhand seiner ID.
     * DELETE /api/fields/{id}
     */
    'delete' => function(mixed $id): void {
        if (!$id) json_fehler('Feld-ID fehlt');
        pdo()->prepare("DELETE FROM custom_fields WHERE id = ?")->execute([$id]);
        // Alle Runner-Fileen neu generieren
        alle_runner_generieren();
        json_ok(['geloescht' => $id]);
    },
];

/**
 * Generiert alle Runner-Fileen (Mail, PDF, Sign, Install) neu.
 * Wird nach jeder Change an Custom Fields aufgerufen.
 */
function alle_runner_generieren(): void {
    global $MODULE;
    ob_start();
    try {
        foreach ($MODULE as $name => $modul) {
            if (in_array($name, ['core', 'fields', 'status', 'proxy', 'shared'])) continue;
            if (isset($modul['generate'])) {
                ($modul['generate'])();
            }
        }
    } catch (\Throwable $e) {
        error_log('IT-Tools alle_runner_generieren: ' . $e->getMessage());
    } finally {
        ob_end_clean(); // alle Ausgaben (Warnings, Notices) verwerfen
    }
}
