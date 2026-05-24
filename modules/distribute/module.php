<?php
// ============================================================
//  IT-Tools — Lesezeichen-Verteilungsseite
//  Version  : 2.0.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//  New v2.0:
//    - showRuecknahme Flag (kein eigenes Bookmark — PDF hat Mode-Picker)
//    - Copy-Link für Rücknahme verweist auf bm-pdf (selbes Bookmark)
//  modules/distribute/module.php
//  Lesezeichen-Verteilung module.
//  Owns section 'distribute'.
//  Generates: install.html
// ============================================================

return [
    'name'        => 'distribute',
    'label'       => 'Lesezeichen verteilen',
    'version'     => '1.0',
    'section'     => 'distribute',
    'output_file' => 'install.html',

    'get_config' => function(): array {
        $defaults = [
            'title'         => 'IT-Lesezeichen installieren',
            'subtitle'      => 'Ziehe die gewünschten Lesezeichen auf deine Lesezeichen-Leiste.',
            'footer'        => 'Bei Fragen wende dich an die IT-Abteilung.',
            'showMail'      => 1,
            'showPdf'       => 1,
            'showSign'      => 1,
            'showRuecknahme'=> 1,
            'showLabel'     => 1,
            'showCopy'      => 1,
            'browsers'      => ['chrome','edge','firefox','safari'],
        ];
        $stored = get_section('distribute');
        return $stored ? array_merge($defaults, $stored) : $defaults;
    },

    'save_config' => function(array $data): void {
        upsert_section('distribute', $data);
    },

    'generate' => function(): void {
        require_once __DIR__ . '/runner.php';
        write_output('install.html', generate_install_page());
    },
];
