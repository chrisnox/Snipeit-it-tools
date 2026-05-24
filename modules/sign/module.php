<?php
// ============================================================
//  IT-Tools — Modules: Elektronische Signature
//  Version  : 2.1.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//  New v2.x:
//    - modus-Parameter: "ausgabe" oder "ruecknahme"
//    - Filename-Prefix: Uebergabe_ oder Ruecknahme_
//    - Fileformat: PDF statt PNG
//    - Upload zu User (file[]) UND Assets (file[ ]) mit Fallback
//    - asset_data ALTER TABLE Migration bei altem DB-Schema
//
return [
    'name'        => 'sign',
    'label'       => 'Elektronische Signatur',
    'version'     => '2.0.0',
    'section'     => 'sign',
    'output_file' => 'sign.html',

    'get_config' => function(): array {
        $standard = [
            'enabled'     => 1,
            'confirmText' => 'Ich bestätige den Erhalt der oben angeführten Geräte und die Kenntnisnahme der IT-Nutzungsrichtlinien.',
            'cats'        => ['notebook'=>1,'phone'=>1,'ipad'=>1,'sim'=>0],
            'uploadEach'  => 1,
        ];
        $gespeichert = abschnitt_lesen('sign');
        return $gespeichert ? array_merge($standard, $gespeichert) : $standard;
    },

    'save_config' => function(array $daten): void {
        abschnitt_speichern('sign', $daten);
    },

    'generate' => function(): void {
        require_once __DIR__ . '/runner.php';
        ausgabe_schreiben('sign.html', sign_runner_generieren());
    },

    // ── POST /api/sign/submit ─────────────────────────────────
    'submit' => function(): void {
        ob_start();

        $b        = json_eingabe();
        $userId   = intval($b['user_id']  ?? 0);
        $userName = trim($b['user_name']  ?? '');
        $userDept = trim($b['user_dept']  ?? '');
        $userKST  = trim($b['user_kst']   ?? '');
        $modus    = trim($b['modus']      ?? 'ausgabe'); // 'ausgabe' oder 'ruecknahme'
        $assets   = $b['assets']          ?? [];
        $sigB64   = $b['signature']       ?? '';

        if (!$userId)       { ob_end_clean(); json_fehler('user_id fehlt'); }
        if (!$sigB64)       { ob_end_clean(); json_fehler('Signatur fehlt'); }
        if (empty($assets)) { ob_end_clean(); json_fehler('Keine Assets ausgewählt'); }

        $cfg = abschnitt_lesen('sign');

        $sigB64 = preg_replace('/^data:image\/[a-z]+;base64,/', '', $sigB64);

        // Dokument generieren — als PDF
        $docBytes  = null;
        $docFehler = null;
        try {
            require_once __DIR__ . '/document.php';
            $docBytes = sign_dokument_erstellen([
                'user_name'     => $userName,
                'user_dept'     => $userDept,
                'user_kst'      => $userKST,
                'assets'        => $assets,
                'confirm_text'  => $cfg['confirmText'] ?? '',
                'company'       => abschnitt_lesen('pdf')['company'] ?? 'IT',
                'signature_b64' => $sigB64,
                'date'          => date('d.m.Y'),
                'modus'         => $modus,
            ]);
        } catch (Throwable $e) {
            $docFehler = $e->getMessage();
            error_log('IT-Tools sign: Dokument-Generierung fehlgeschlagen: ' . $docFehler);
        }

        ob_end_clean();

        // In DB speichern
        try {
            pdo()->prepare(
                "INSERT INTO sign_signatures
                   (user_id, user_name, user_dept, user_kst, asset_ids, asset_data)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $userId, $userName, $userDept, $userKST,
                json_encode(array_column($assets, 'id')),
                json_encode($assets),
            ]);
            $sigId = pdo()->lastInsertId();
        } catch (PDOException $e) {
            error_log('IT-Tools sign: DB-Fehler: ' . $e->getMessage());
            $sigId = null;
        }

        // Upload zu SnipeIT — Employee UND jedes Asset
        $hochgeladen      = [];
        $fehler           = [];
        $userHochgeladen  = false;
        $userFehler       = null;

        if (!empty($cfg['uploadEach'])) {
            $sicherName = preg_replace('/[^a-zA-Z0-9-]/', '-', $userName ?: 'user');
            $prefix     = ($modus === 'ruecknahme') ? 'Ruecknahme' : 'Uebergabe';
            $dateiname  = $prefix . '_' . date('Y-m-d') . '_' . $sicherName . '.pdf';
            $dateiDaten = $docBytes ?: '%PDF-1.4 %empty';

            // Upload via SnipeIT Client Library
            $userHochgeladen = SnipeIT::uploadToUser($userId, $dateiDaten, $dateiname);
            if (!$userHochgeladen) error_log('IT-Tools sign: Upload zu User fehlgeschlagen');

            foreach ($assets as $asset) {
                $assetId = intval($asset['id'] ?? 0);
                if (!$assetId) continue;
                $ok = SnipeIT::uploadToAsset($assetId, $dateiDaten, $dateiname);
                if ($ok) { $hochgeladen[] = $assetId; }
                else     { $fehler[] = ['asset_id'=>$assetId, 'tag'=>$asset['tag']??'']; }
            }

            // DB-Eintrag aktualisieren
            if ($sigId) {
                pdo()->prepare("UPDATE sign_signatures SET uploaded=?, upload_log=? WHERE id=?")
                     ->execute([
                         ($userHochgeladen && empty($fehler)) ? 1 : 0,
                         json_encode([
                             'user_hochgeladen' => $userHochgeladen,
                             'user_fehler'      => $userFehler,
                             'hochgeladen'      => $hochgeladen,
                             'fehler'           => $fehler,
                         ]),
                         $sigId,
                     ]);
            }
        }

        json_ok([
            'sig_id'         => $sigId,
            'user_uploaded'  => $userHochgeladen,
            'uploaded'       => $hochgeladen,
            'uploaded_count' => count($hochgeladen),
            'errors'         => $fehler,
            'doc_generated'  => $docBytes !== null,
            'doc_error'      => $docFehler,
        ]);
    },

    // ── GET /api/sign/list — Audit-Trail ──────────────────────
    'list' => function(): void {
        $zeilen = pdo()->query(
            "SELECT id, user_id, user_name, user_dept, asset_ids, signed_at, uploaded, upload_log
             FROM sign_signatures ORDER BY signed_at DESC LIMIT 100"
        )->fetchAll();
        json_ok($zeilen);
    },
];
