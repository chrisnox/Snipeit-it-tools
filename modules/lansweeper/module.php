<?php
// ============================================================
//  IT-Tools — Modules: Lansweeper CSV-Sync
//  Version  : 1.0.0
//  Modified : 2026-05-19
//  Author   :  Chris M.
//
//  Purpose (nur was SnipeIT nicht bereits kann):
//    - CSV aus konfigurierbarem Directory lesen
//    - Vergleich mit SnipeIT: fehlende Notebooks anlegen
//    - Import-Log mit Statistik
//    - Manueller Sync-Button im Admin
//
//  Nicht enthalten:
//    - Asset-Liste (SnipeIT macht das)
//    - Asset-Details bearbeiten (SnipeIT macht das)
//
//  CSV-Format (;-Separator):
//    Serial number;User/Login;AssetName;Model;Manufacturer;...
// ============================================================

return [
    'name'        => 'lansweeper',
    'label'       => 'Lansweeper',
    'version'     => '1.0.0',
    'section'     => 'lansweeper',
    'output_file' => null, // kein Bookmarklet — Admin-only Tool

    'get_config' => function(): array {
        $standard = [
            'csvPath'        => '/data/BRUT',    // Verzeichnis im Container
            'csvPattern'     => 'web*.csv',       // Dateiname-Muster (glob)
            'delimiter'      => ';',
            'encoding'       => 'UTF-8',          // UTF-8 oder Windows-1252
            // Columnnnamen im CSV → logische Fieldnamen
            'colSerial'      => 'Seriennummer',
            'colAssetName'   => 'AssetName',
            'colUser'        => 'Benutzer/Anmeldung',
            'colModel'       => 'Modell',
            'colManufacturer'=> 'Hersteller',
            // SnipeIT Defaults für neue Assets
            'defaultStatusId'=> 1,
            'defaultModelId' => 0,   // 0 = Modell aus CSV oder Fallback
            'categoryId'     => 4,   // Kategorie-ID für Notebooks
        ];
        return array_replace_recursive($standard, abschnitt_lesen('lansweeper') ?: []);
    },

    'save_config' => function(array $daten): void {
        $daten['defaultStatusId'] = intval($daten['defaultStatusId'] ?? 1);
        $daten['defaultModelId']  = intval($daten['defaultModelId']  ?? 0);
        $daten['categoryId']      = intval($daten['categoryId']      ?? 4);
        abschnitt_speichern('lansweeper', $daten);
    },

    'generate' => null, // kein HTML-Runner, Admin-only

    // GET /api/lansweeper/status
    // Zeigt letzten Import-Status + gefundene CSV-Fileen
    'status' => function(): void {
        lansweeper_init_table();
        $cfg   = abschnitt_lesen('lansweeper');
        $path  = rtrim($cfg['csvPath'] ?? '/data/BRUT', '/');
        $pat   = $cfg['csvPattern'] ?? 'web*.csv';
        $files = glob("$path/$pat") ?: [];

        $letzter = pdo()->query("SELECT * FROM lansweeper_sync_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        json_ok([
            'csv_path'     => $path,
            'csv_pattern'  => $pat,
            'csv_files'    => array_map(function($f){ return ['name'=>basename($f),'size'=>filesize($f),'mtime'=>date('d.m.Y H:i',filemtime($f))]; }, $files),
            'last_sync'    => $letzter ?: null,
        ]);
    },

    // POST /api/lansweeper/sync
    // dry_run=true → analysiert nur, schreibt nichts in SnipeIT
    'sync' => function(): void {
        $b       = json_eingabe();
        $dryRun  = !empty($b['dry_run']);
        lansweeper_init_table();
        $cfg     = abschnitt_lesen('lansweeper');
        $path    = rtrim($cfg['csvPath']    ?? '/data/BRUT', '/');
        $pat     = $cfg['csvPattern']       ?? 'web*.csv';
        $files   = glob("$path/$pat")       ?: [];
        $log     = [];
        $log[]   = date('H:i:s') . ($dryRun ? ' — [DRY RUN] Analyse gestartet (kein Schreiben)' : ' — Import gestartet');

        if (!$files) {
            $log[] = date('H:i:s') . ' ✗ Keine CSV-Dateien in: ' . $path . '/' . $pat;
            if (!$dryRun) lansweeper_save_log(0, 0, 0, 0, 1, $log, 'warn');
            json_ok(['log' => $log, 'dry_run' => $dryRun, 'files' => 0]);
            return;
        }
        $log[] = date('H:i:s') . ' — ' . count($files) . ' CSV-Datei(en) gefunden';

        $snipeItems    = SnipeIT::getAll('hardware', ['category_id' => $cfg['categoryId'] ?? 4]);
        $snipeBySerial = [];
        foreach ($snipeItems as $a) {
            $sn = strtoupper(trim($a['serial'] ?? ''));
            if ($sn) $snipeBySerial[$sn] = true;
        }
        $log[] = date('H:i:s') . ' — SnipeIT: ' . count($snipeBySerial) . ' Notebooks geladen';

        $gelesene = 0; $wuerdeErstellt = []; $uebersprungen = 0; $fehler = 0;
        $delimiter = $cfg['delimiter'] ?? ';';
        $encoding  = $cfg['encoding']  ?? 'UTF-8';

        foreach ($files as $csvFile) {
            $log[] = date('H:i:s') . ' — Lese: ' . basename($csvFile);
            $rows  = lansweeper_read_csv($csvFile, $delimiter, $encoding);
            $log[] = date('H:i:s') . ' — ' . count($rows) . ' Zeilen gelesen';

            foreach ($rows as $row) {
                $sn = strtoupper(trim($row[$cfg['colSerial'] ?? 'Seriennummer'] ?? ''));
                if (!$sn) continue;
                $gelesene++;
                if (isset($snipeBySerial[$sn])) { $uebersprungen++; continue; }

                $assetName  = trim($row[$cfg['colAssetName']   ?? 'AssetName']          ?? $sn);
                $modellName = trim($row[$cfg['colModel']        ?? 'Modell']             ?? 'Unknown');
                $hersteller = trim($row[$cfg['colManufacturer'] ?? 'Hersteller']         ?? explode(' ', $modellName)[0]);
                $user       = trim($row[$cfg['colUser']         ?? 'Benutzer/Anmeldung'] ?? '');

                if ($dryRun) {
                    // Model prüfen ohne anlegen
                    $res = SnipeIT::get('models', ['search' => $modellName, 'limit' => 3]);
                    $modellOk = !empty($res['rows']);
                    $wuerdeErstellt[] = $sn;
                    $icon = $modellOk ? '→' : '⚠';
                    $log[] = date('H:i:s') . " $icon NEU: $sn | $assetName | $modellName | $hersteller" .
                             ($user ? " | User: $user" : '') .
                             (!$modellOk ? ' [Modell fehlt — würde neu angelegt]' : '');
                } else {
                    $modelId = $cfg['defaultModelId'] > 0
                        ? $cfg['defaultModelId']
                        : lansweeper_get_or_create_model($modellName, $hersteller, intval($cfg['categoryId'] ?? 4));
                    try {
                        $result = SnipeIT::createAsset([
                            'asset_tag'  => $sn,
                            'serial'     => $sn,
                            'name'       => $assetName,
                            'status_id'  => intval($cfg['defaultStatusId'] ?? 1),
                            'model_id'   => $modelId,
                        ]);
                        if (SnipeIT::isSuccess($result) || isset($result['payload']['id'])) {
                            $wuerdeErstellt[] = $sn;
                            $snipeBySerial[$sn] = true;
                            $log[] = date('H:i:s') . " ✓ $sn — $assetName";
                        } else {
                            $fehler++;
                            $msg = is_array($result['messages'] ?? null) ? json_encode($result['messages']) : ($result['status'] ?? 'Fehler');
                            $log[] = date('H:i:s') . " ✗ $sn — $msg";
                        }
                    } catch (Throwable $e) {
                        $fehler++;
                        $log[] = date('H:i:s') . " ✗ $sn — " . $e->getMessage();
                    }
                }
            }
        }

        $erstellt = count($wuerdeErstellt);
        if ($dryRun) {
            $log[] = date('H:i:s') . " — [DRY RUN] $gelesene gelesen, $erstellt würden angelegt, $uebersprungen bereits vorhanden";
            $log[] = date('H:i:s') . ' — Kein Schreiben — Dry Run abgeschlossen';
        } else {
            $log[]  = date('H:i:s') . " — Fertig: $gelesene gelesen, $erstellt angelegt, $uebersprungen vorhanden, $fehler Fehler";
            lansweeper_save_log($gelesene, $erstellt, $uebersprungen, $fehler, count($files), $log, $fehler>0?'warn':'ok');
        }

        json_ok(['log' => $log, 'dry_run' => $dryRun, 'would_create' => $wuerdeErstellt,
                 'read' => $gelesene, 'created' => $dryRun?0:$erstellt,
                 'skipped' => $uebersprungen, 'errors' => $fehler]);
    },

    // GET /api/lansweeper/logs
    'logs' => function(): void {
        lansweeper_init_table();
        $rows = pdo()->query("SELECT id, started_at, csv_files, rows_read, created, skipped, errors, status FROM lansweeper_sync_log ORDER BY id DESC LIMIT 20")
                     ->fetchAll(PDO::FETCH_ASSOC);
        json_ok($rows);
    },

    // GET /api/lansweeper/log/{id}
    'log_detail' => function(mixed $id): void {
        $stmt = pdo()->prepare("SELECT log_text FROM lansweeper_sync_log WHERE id=?");
        $stmt->execute([$id]);
        $r = $stmt->fetchColumn();
        json_ok(['log' => $r ? explode("\n", $r) : []]);
    },
];

// ── Helper functions ────────────────────────────────────────────

function lansweeper_init_table(): void {
    pdo()->exec("CREATE TABLE IF NOT EXISTS lansweeper_sync_log (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        started_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        csv_files   INT DEFAULT 0,
        rows_read   INT DEFAULT 0,
        created     INT DEFAULT 0,
        skipped     INT DEFAULT 0,
        errors      INT DEFAULT 0,
        log_text    LONGTEXT,
        status      VARCHAR(10) DEFAULT 'ok'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function lansweeper_save_log(int $read, int $created, int $skipped, int $errors, int $files, array $log, string $status): void {
    pdo()->prepare("INSERT INTO lansweeper_sync_log (csv_files, rows_read, created, skipped, errors, log_text, status) VALUES (?,?,?,?,?,?,?)")
         ->execute([$files, $read, $created, $skipped, $errors, implode("\n", $log), $status]);
}

function lansweeper_read_csv(string $file, string $delimiter, string $encoding): array {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return [];

    // Encoding konvertieren falls nötig
    if (strtoupper($encoding) !== 'UTF-8') {
        $lines = array_map(fn($l) => mb_convert_encoding($l, 'UTF-8', $encoding), $lines);
    }

    $headers = str_getcsv(array_shift($lines), $delimiter);
    $rows    = [];
    foreach ($lines as $line) {
        $cols = str_getcsv($line, $delimiter);
        if (count($cols) >= count($headers)) {
            $rows[] = array_combine($headers, array_slice($cols, 0, count($headers)));
        }
    }
    return $rows;
}

function lansweeper_get_or_create_model(string $name, string $manufacturer, int $categoryId): int {
    // Model in SnipeIT suchen
    $res = SnipeIT::get('models', ['search' => $name, 'limit' => 5]);
    foreach ($res['rows'] ?? [] as $m) {
        if (strcasecmp(trim($m['name'] ?? ''), $name) === 0) return $m['id'];
    }
    // Manufacturer suchen/anlegen
    $mfr   = SnipeIT::get('manufacturers', ['search' => $manufacturer, 'limit' => 5]);
    $mfrId = ($mfr['rows'][0]['id'] ?? null);
    if (!$mfrId) {
        $new   = SnipeIT::post('manufacturers', ['name' => $manufacturer]);
        $mfrId = $new['payload']['id'] ?? $new['id'] ?? 1;
    }
    // Model anlegen
    $model = SnipeIT::post('models', ['name' => $name, 'manufacturer_id' => $mfrId, 'category_id' => $categoryId]);
    return $model['payload']['id'] ?? $model['id'] ?? 1;
}
