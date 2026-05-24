<?php
// ============================================================
//  IT-Tools — Modules: Label-Print (Zebra ZD410)
//  Version  : 3.0.0
//  Modified : 2026-05-20
//  Author   :  Chris M.
//
//  Ablauf:
//    1. label-print.html öffnet sich (Bookmarklet, standalone)
//    2. Alle Accessories laden via /api/proxy/accessories
//    3. Categoryn werden client-seitig aus den Daten abgeleitet
//    4. Category wählen → Accessories filtern
//    5. Accessories auswählen + Printanzahl (max = verfügbar)
//    6. ZPL → TCP → Zebra ZD410
//
//  Label (50×25mm):
//    QR-Code (SnipeIT-Link) · Name · Category · Datum
//
//  Noe Changes an api.php oder anderen Modulesen nötig.
//  Nutzt bestehende Route: GET /api/proxy/accessories
// ============================================================

return [
    'name'        => 'label',
    'label'       => 'Label Druck',
    'version'     => '3.0.0',
    'section'     => 'label',
    'output_file' => 'label-print.html',

    'get_config' => function(): array {
        return array_replace_recursive([
            'printerIp'   => '',
            'printerPort' => '9100',
            'copies'      => '1',
        ], abschnitt_lesen('label') ?: []);
    },

    'save_config' => function(array $daten): void {
        $daten['printerPort'] = intval($daten['printerPort'] ?? 9100);
        $daten['copies']      = max(1, min(10, intval($daten['copies'] ?? 1)));
        abschnitt_speichern('label', $daten);
    },

    'generate' => function(): void {
        require_once __DIR__ . '/runner.php';
        ausgabe_schreiben('label-print.html', label_runner_html());
    },

    // POST /api/label/print
    'print' => function(): void {
        $b      = json_eingabe();
        $items  = $b['items']  ?? [];
        $cfg    = abschnitt_lesen('label');
        $ip     = trim($cfg['printerIp']   ?? '');
        $port   = intval($cfg['printerPort'] ?? 9100);
        $base   = rtrim(abschnitt_lesen('shared')['snipeitUrl'] ?? '', '/');

        if (!$ip)    json_fehler('Drucker-IP nicht konfiguriert — bitte im Admin einstellen');
        if (!$items) json_fehler('Keine Positionen ausgewählt');

        $sock = @fsockopen($ip, $port, $errno, $errstr, 5);
        if (!$sock) json_fehler("Drucker nicht erreichbar: $errstr ($errno)");

        $total = 0;
        foreach ($items as $item) {
            $id    = intval($item['id']       ?? 0);
            $name  = label_esc($item['name']  ?? '');
            $cat   = label_esc($item['category'] ?? '');
            $date  = date('d.m.Y');
            $qty   = max(1, intval($item['qty'] ?? 1));
            $url   = $base ? "{$base}/accessories/{$id}" : "ACC-{$id}";

            $zpl = label_zpl($name, $cat, $date, $url, $qty);
            fwrite($sock, $zpl);
            $total += $qty;
        }
        fclose($sock);
        json_ok(['printed' => $total, 'items' => count($items)]);
    },

    // POST /api/label/test
    'test' => function(): void {
        $cfg  = abschnitt_lesen('label');
        $ip   = trim($cfg['printerIp']   ?? '');
        $port = intval($cfg['printerPort'] ?? 9100);
        if (!$ip) json_fehler('Drucker-IP nicht konfiguriert');
        $sock = @fsockopen($ip, $port, $errno, $errstr, 5);
        if (!$sock) json_fehler("Drucker nicht erreichbar: $errstr ($errno)");
        fwrite($sock, label_zpl('Testlabel', 'IT-Zubehör', date('d.m.Y'), 'http://snipeit.example.com', 1));
        fclose($sock);
        json_ok(['message' => 'Testlabel gesendet']);
    },
];

// ── ZPL (50×25mm = 400×200 dots bei 203 DPI) ──────────────────
function label_zpl(string $name, string $cat, string $date, string $url, int $qty): string {
    // 50×25mm bei 203 DPI = 400×200 dots
    // Layout: Text links groß | QR-Code rechts klein (Modules 2)
    return "^XA\n"
        . "^PW400\n"
        . "^LL200\n"
        . "^LH0,0\n"
        . "^CI28\n"
        . ($qty > 1 ? "^PQ{$qty}\n" : ''  )
        // Text links
        . "^FO10,14^A0N,30,30^FD{$name}^FS\n"   // Name groß
        . "^FO10,52^A0N,20,20^FD{$cat}^FS\n"    // Kategorie
        . "^FO10,80^A0N,18,18^FD{$date}^FS\n"   // Datum
        . "^FO10,106^GB265,1,1^FS\n"             // Trennlinie
        . "^FO10,115^A0N,14,14^FDIT-Zubehoer^FS\n"
        // QR-Code rechts klein
        . "^FO290,12^BQN,2,2^FDMA,{$url}^FS\n"
        . "^XZ\n";
}

function label_esc(string $s): string {
    static $map = ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss'];
    return substr(str_replace(['^','~','"'], '', strtr($s, $map)), 0, 35);
}
