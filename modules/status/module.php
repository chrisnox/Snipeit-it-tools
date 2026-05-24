<?php
// ============================================================
//  IT-Tools — Modules: File-Status
//  Version  : 1.2.0
//  Modified : 2026-05-04
//  Author   :  Chris M.
//
//  Purpose:
//    Prüft ob alle generierten Output-Fileen existieren und
//    gibt deren Status (Existenz, Changesdatum, Filegröße) zurück.
//
//  Überwachte Fileen:
//    snipeit-bm.html           → Outlook Mail Bookmarklet Runner
//    snipeit-ausgabe-pdf.html  → Devicee-Output PDF Runner
//    install.html              → Employee Lesezeichen-Installationsseite
//    sign.html                 → Elektronische Signature Page
//
//  Endpunkt: GET /api/status
// ============================================================

return [
    'name'    => 'status',
    'label'   => 'Datei-Status',
    'version' => '1.2.0',

    /**
     * Prüft den Status aller generierten Output-Fileen.
     * Gibt für jede File zurück ob sie existiert, wann sie
     * zuletzt geändert wurde und wie groß sie ist.
     */
    'get' => function(): void {
        $verzeichnis = OUTPUT_DIR;

        // Liste aller zu überwachenden Output-Fileen
        $dateien = [
            'snipeit-bm.html',          // Mail-Bookmarklet Runner
            'snipeit-ausgabe-pdf.html', // PDF Runner mit Asset-Auswahl
            'install.html',             // Mitarbeiter Installationsseite
            'sign.html',                // Elektronische Signatur Seite
        ];

        $status = [];
        foreach ($dateien as $datei) {
            $pfad = "$verzeichnis/$datei";
            if (file_exists($pfad)) {
                $status[$datei] = [
                    'vorhanden'   => true,
                    'geaendert'   => date('Y-m-d H:i:s', filemtime($pfad)),
                    'groesse'     => filesize($pfad),
                    // Auch englische Key für Kompatibilität
                    'exists'      => true,
                    'modified'    => date('Y-m-d H:i:s', filemtime($pfad)),
                    'size'        => filesize($pfad),
                ];
            } else {
                $status[$datei] = [
                    'vorhanden' => false,
                    'exists'    => false,
                ];
            }
        }

        json_ok([
            'ausgabe_verzeichnis' => $verzeichnis,
            'beschreibbar'        => is_writable($verzeichnis),
            'output_dir'          => $verzeichnis,
            'writable'            => is_writable($verzeichnis),
            'dateien'             => $status,
            'files'               => $status,  // Kompatibilitäts-Alias
        ]);
    },
];
