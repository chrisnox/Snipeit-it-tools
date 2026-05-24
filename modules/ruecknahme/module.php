<?php
// ============================================================
//  IT-Tools — Modules: Rücknahmeprotokoll PDF
//  Version  : 1.0.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//
//  Purpose:
//    Configured und generiert das Rücknahme-Bookmarklet.
//    Entspricht dem PDF-Output-Modules, jedoch mit angepasstem
//    Dokumentenkopf, Signaturezeile und Zustands-Column.
//
//  Verwendung:
//    Bookmarklet auf SnipeIT User-Page (/users/{id}).
//    IT wählt die zurückgenommenen Devicee aus, erfasst den
//    Zustand (Gut / Beschädigt / Defekt) und druckt das
//    A4-Rücknahmeprotokoll.
//
//  Generierte File: snipeit-ruecknahme-pdf.html
// ============================================================

return [
    'name'        => 'ruecknahme',
    'label'       => 'Rücknahmeprotokoll PDF',
    'version'     => '1.0.0',
    'section'     => 'ruecknahme',
    'output_file' => 'snipeit-ruecknahme-pdf.html',

    /**
     * Configuration lesen. Defaultwerte werden mit gespeicherten
     * Valueen zusammengeführt (array_merge) damit neue Key
     * auch bei alten DB-Einträgen funktionieren.
     */
    'get_config' => function(): array {
        $standard = [
            'docTitle' => 'Rücknahmeprotokoll IT-Geräte',
            'footer'   => 'IT-Abteilung · Bitte nach Unterzeichnung einscannen und in SnipeIT hochladen.',
            'note'     => 'Mit meiner Unterschrift bestätige ich die Rückgabe der oben angeführten Geräte in dem angegebenen Zustand.',
            'cats'     => ['notebook'=>1, 'phone'=>1, 'ipad'=>1, 'sim'=>1],
        ];
        $gespeichert = abschnitt_lesen('ruecknahme');
        return $gespeichert ? array_merge($standard, $gespeichert) : $standard;
    },

    'save_config' => function(array $daten): void {
        abschnitt_speichern('ruecknahme', $daten);
    },

    'generate' => function(): void {
        require_once __DIR__ . '/runner.php';
        ausgabe_schreiben('snipeit-ruecknahme-pdf.html', ruecknahme_runner_generieren());
    },
];
