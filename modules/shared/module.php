<?php
// ============================================================
//  IT-Tools — Modules: Gemeinsame Configuration (shared)
//  Version  : 1.1.0
//  Modified : 2026-05-04
//  Author   :  Chris M.
//
//  Purpose:
//    Verwaltet die geteilte Configuration die alle Modulese benötigen:
//    - SnipeIT-URL
//    - Tools-URL (Basis-URL dieser Anwendung)
//    - API-Token für SnipeIT (wird nur server-seitig verwendet)
//
//  Wenn shared gespeichert wird → alle Runner-Fileen werden neu
//  generiert, da sich URL oder Token geändert haben könnte.
// ============================================================

return [
    'name'    => 'shared',
    'label'   => 'API / URLs',
    'version' => '1.1.0',
    'section' => 'shared',

    /**
     * Liest die gemeinsame Configuration.
     * Gibt Defaultwerte zurück wenn noch keine Configuration gespeichert ist.
     */
    'get_config' => function(): array {
        $standard = [
            'snipeitUrl' => '',
            'toolsUrl'   => '',
            'apiToken'   => '',
        ];
        $gespeichert = abschnitt_lesen('shared');
        // Gespeicherte Valuee überschreiben die Defaultwerte
        return $gespeichert ? array_merge($standard, $gespeichert) : $standard;
    },

    /**
     * Speichert die gemeinsame Configuration und generiert alle
     * Runner-Fileen neu, da sich URL oder Token geändert haben könnte.
     */
    'save_config' => function(array $daten): void {
        abschnitt_speichern('shared', $daten);

        // Alle Modulese mit generate()-Funktion neu ausführen
        require_once MODULES_DIR . '/fields/module.php';
        alle_runner_generieren();
    },
];
