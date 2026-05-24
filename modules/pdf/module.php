<?php
// ============================================================
//  IT-Tools — Modules: Devicee-Output PDF
//  Version  : 2.0.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//  New v2.0:
//    - noteJs/footerJs via json_encode (kein addslashes-Problem mehr)
//    - accessories Route via proxy genutzt
//
//  Purpose:
//    Configured und generiert das Devicee-Output-PDF-Bookmarklet.
//
//  Verwendung:
//    IT-Employee klickt Bookmarklet auf einer SnipeIT User-Page
//    (/users/{id}). Ein Popup öffnet sich mit einer Asset-Auswahl.
//    Nach Bestätigung wird ein druckbares A4-Übergabeprotokoll gerendert.
//
//  Generierte File: snipeit-ausgabe-pdf.html
//
//  Ablauf in der generierten File:
//    1. User-Daten + Assets über Proxy laden
//    2. Asset-Auswahl anzeigen (alle vorausgewählt, einzeln abwählbar)
//    3. "Printen"-Button → A4-Dokument rendern → Browser-Printdialog
//    4. "← Zurück"-Button → zurück zur Auswahl
//
//  Konfigurierbare Categoryn und Fields:
//    Notebook, Phone, iPad, SIM-Karte
//    Für jede Category: Welche Fields im Protocol erscheinen
// ============================================================

return [
    'name'        => 'pdf',
    'label'       => 'Geräte-Ausgabe PDF',
    'version'     => '1.3.0',
    'section'     => 'pdf',
    'output_file' => 'snipeit-ausgabe-pdf.html',

    /**
     * Liest die PDF-Configuration aus der DB.
     * Gibt Defaultwerte zurück wenn noch keine Configuration gespeichert ist.
     */
    'get_config' => function(): array {
        $standard = [
            'company'   => 'Muster GmbH',
            'docTitle'  => 'Übergabeprotokoll IT-Geräte',
            'footer'    => 'IT-Abteilung · Bitte nach Unterzeichnung einscannen und in SnipeIT hochladen.',
            'note'      => 'Mit meiner Unterschrift bestätige ich den Erhalt der oben angeführten Geräte sowie die Kenntnisnahme der IT-Nutzungsrichtlinien.',
            // Welche Categoryn angezeigt werden (1=an, 0=aus)
            'cats'      => ['notebook'=>1, 'phone'=>1, 'ipad'=>0, 'sim'=>1],
            'simCatId'  => 6,   // SIM-Kategorie-ID in SnipeIT (Admin → Categories prüfen)
            // Welche Fields pro Category im Protocol erscheinen
            'fields'    => [
                'notebook' => ['asset_tag'=>1,'asset_name'=>1,'model'=>1,'serial'=>1,'manufacturer'=>0,'purchase_date'=>0,'warranty'=>0],
                'phone'    => ['asset_tag'=>1,'model'=>1,'serial'=>1,'imei'=>1,'asset_name'=>0,'manufacturer'=>0],
                'ipad'     => ['asset_tag'=>1,'model'=>1,'serial'=>1,'imei'=>0],
                'sim'      => ['asset_tag'=>1,'asset_name'=>1,'model'=>0],
            ],
        ];
        $gespeichert = abschnitt_lesen('pdf');
        return $gespeichert ? array_merge($standard, $gespeichert) : $standard;
    },

    /**
     * Speichert die PDF-Configuration in der DB.
     */
    'save_config' => function(array $daten): void {
        abschnitt_speichern('pdf', $daten);
    },

    /**
     * Generiert die Runner-File snipeit-ausgabe-pdf.html neu.
     */
    'generate' => function(): void {
        require_once __DIR__ . '/runner.php';
        ausgabe_schreiben('snipeit-ausgabe-pdf.html', pdf_runner_generieren());
    },
];
