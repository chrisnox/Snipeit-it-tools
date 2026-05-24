<?php
// ============================================================
//  IT-Tools — Modules: Outlook Mail Bookmarklet
//  Version  : 1.2.0
//  Modified : 2026-05-04
//  Author   :  Chris M.
//
//  Purpose:
//    Configured und generiert das Outlook-Mail-Bookmarklet.
//
//  Verwendung:
//    IT-Employee klickt Bookmarklet auf einer SnipeIT Asset-Page
//    (/hardware/{id}). Ein Popup öffnet sich, lädt die Asset-Daten
//    über den Proxy und öffnet Outlook mit einer vorausgefüllten Mail.
//
//  Generierte File: snipeit-bm.html
//
//  Konfigurierbare Fields (3 Gruppen):
//    Employee: Name, E-Mail, Department, Cost center, Location
//    Asset: Category, Asset-Tag, Asset-Name, Manufacturer, Model, Serial number, IMEI
//    Beschaffung: Lieferant, Order number, Purchase date, Kaufpreis, Warranty, Outputdatum, SnipeIT-Link
// ============================================================

return [
    'name'        => 'mail',
    'label'       => 'Outlook Mail',
    'version'     => '1.2.0',
    'section'     => 'mail',
    'output_file' => 'snipeit-bm.html',

    /**
     * Liest die Mail-Configuration aus der DB.
     * Gibt Defaultwerte zurück wenn noch keine Configuration gespeichert ist.
     */
    'get_config' => function(): array {
        $standard = [
            'mailTo'     => '',
            'mailCc'     => '',
            'senderName' => 'IT',
            'btnLabel'   => 'Ausgabe an Buchhaltung',
            // Welche Fields in der Mail erscheinen (1=an, 0=aus)
            'fields' => [
                'mitarbeiter' => [
                    'user_name'  => 1,
                    'user_email' => 1,
                    'user_dept'  => 1,
                    'kst'        => 1,
                    'location'   => 1,
                ],
                'asset' => [
                    'category'    => 1,
                    'asset_tag'   => 1,
                    'asset_name'  => 1,
                    'manufacturer'=> 0,
                    'model'       => 1,
                    'serial'      => 1,
                    'imei'        => 0,
                ],
                'beschaffung' => [
                    'supplier'      => 0,
                    'order_number'  => 0,
                    'purchase_date' => 1,
                    'purchase_cost' => 1,
                    'warranty'      => 0,
                    'checkout_date' => 1,
                    'snipeit_link'  => 1,
                ],
            ],
        ];
        $gespeichert = abschnitt_lesen('mail');
        return $gespeichert ? array_merge($standard, $gespeichert) : $standard;
    },

    /**
     * Speichert die Mail-Configuration in der DB.
     */
    'save_config' => function(array $daten): void {
        abschnitt_speichern('mail', $daten);
    },

    /**
     * Generiert die Runner-File snipeit-bm.html neu.
     * Wird automatisch nach dem Saving aufgerufen.
     */
    'generate' => function(): void {
        require_once __DIR__ . '/runner.php';
        ausgabe_schreiben('snipeit-bm.html', mail_runner_generieren());
    },
];
