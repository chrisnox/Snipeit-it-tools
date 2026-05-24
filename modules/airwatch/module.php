<?php
// ============================================================
//  IT-Tools — Modules: AirWatch MDM
//  Version  : 1.0.0
//  Modified : 2026-05-19
//  Author   :  Chris M.
//
//  Purpose (nur was SnipeIT nicht bereits kann):
//    - Sync-Status: Devicee in AirWatch vs SnipeIT vergleichen
//    - Manuellen Sync auslösen (mit Echtzeit-Logfenster)
//    - Einzelgerät in AirWatch suchen (Bookmarklet von /hardware/{id})
//
//  Nicht enthalten (SnipeIT macht das bereits):
//    - Asset-Liste anzeigen
//    - Asset-Details bearbeiten
//    - Status-Labels verwalten
//
//  AirWatch API: https://{host}/api/mdm/devices
//  Auth: Basic (tenant-code + base64 credentials)
//  SSL: self-signed → verify optional
// ============================================================

return [
    'name'        => 'airwatch',
    'label'       => 'AirWatch MDM',
    'version'     => '1.0.0',
    'section'     => 'airwatch',
    'output_file' => 'airwatch-search.html', // Bookmarklet: Gerät suchen

    'get_config' => function(): array {
        $standard = [
            'awUrl'        => '',
            'awUser'       => '',
            'awPassword'   => '',
            'awTenantCode' => '',
            'sslVerify'    => 0,        // 0 = aus (self-signed), 1 = an
            'pageSize'     => 500,
            'autoSync'     => 0,        // Automatischer Sync (Cron)
            'syncInterval' => 'daily',  // hourly|daily|weekly
        ];
        return array_replace_recursive($standard, abschnitt_lesen('airwatch') ?: []);
    },

    'save_config' => function(array $daten): void {
        $daten['sslVerify']  = empty($daten['sslVerify'])  ? 0 : 1;
        $daten['autoSync']   = empty($daten['autoSync'])   ? 0 : 1;
        $daten['pageSize']   = max(10, min(2000, intval($daten['pageSize'] ?? 500)));
        abschnitt_speichern('airwatch', $daten);
    },

    'generate' => function(): void {
        require_once __DIR__ . '/runner.php';
        ausgabe_schreiben('airwatch-search.html', airwatch_runner_html());
    },

    // GET /api/airwatch/status
    // Vergleicht AirWatch-Devicee mit SnipeIT — zeigt Diff
    'status' => function(): void {
        $cfg = abschnitt_lesen('airwatch');
        try {
            // AirWatch Devicee holen
            $awDevices  = airwatch_fetch_all_devices($cfg);
            // SnipeIT Phones + iPads (nur Serial numbern) via Library
            $snipeItems = airwatch_fetch_snipeit_serials();

            $awSerials  = [];
            foreach ($awDevices as $d) {
                $sn = strtoupper(trim($d['SerialNumber'] ?? $d['serial_number'] ?? ''));
                if ($sn) $awSerials[$sn] = $d;
            }
            $snipeSerials = [];
            foreach ($snipeItems as $a) {
                $sn = strtoupper(trim($a['serial'] ?? ''));
                if ($sn) $snipeSerials[$sn] = $a;
            }

            $nurInAW     = [];   // in AirWatch, nicht in SnipeIT
            $nurInSnipe  = [];   // in SnipeIT, nicht in AirWatch
            $beide       = 0;

            foreach ($awSerials as $sn => $d) {
                if (isset($snipeSerials[$sn])) { $beide++; }
                else { $nurInAW[] = ['serial'=>$sn, 'model'=>$d['Model']??$d['model']??'', 'friendly_name'=>$d['DeviceFriendlyName']??$d['friendly_name']??'']; }
            }
            foreach ($snipeSerials as $sn => $a) {
                if (!isset($awSerials[$sn])) {
                    $nurInSnipe[] = ['serial'=>$sn, 'name'=>$a['name']??'', 'asset_tag'=>$a['asset_tag']??''];
                }
            }

            json_ok([
                'aw_total'      => count($awSerials),
                'snipe_total'   => count($snipeSerials),
                'matched'       => $beide,
                'only_in_aw'    => $nurInAW,
                'only_in_snipe' => $nurInSnipe,
                'fetched_at'    => date('d.m.Y H:i:s'),
            ]);
        } catch (Throwable $e) {
            json_fehler('Status-Abruf fehlgeschlagen: ' . $e->getMessage());
        }
    },

    // POST /api/airwatch/sync
    // dry_run=true → analysiert nur, schreibt nichts in SnipeIT
    'sync' => function(): void {
        $b       = json_eingabe();
        $dryRun  = !empty($b['dry_run']);
        $cfg     = abschnitt_lesen('airwatch');
        airwatch_init_log_table();

        $log   = [];
        $log[] = date('H:i:s') . ($dryRun ? ' — [DRY RUN] Analyse gestartet (kein Schreiben)' : ' — Sync gestartet');

        try {
            $awDevices = airwatch_fetch_all_devices($cfg);
            $log[]     = date('H:i:s') . ' — AirWatch: ' . count($awDevices) . ' Geräte geladen';

            $snipeItems    = airwatch_fetch_snipeit_serials();
            $snipeBySerial = [];
            foreach ($snipeItems as $a) {
                $sn = strtoupper(trim($a['serial'] ?? ''));
                if ($sn) $snipeBySerial[$sn] = $a;
            }
            $log[] = date('H:i:s') . ' — SnipeIT: ' . count($snipeBySerial) . ' Geräte geladen';

            $wuerdeErstellt = []; $uebersprungen = 0; $fehler = 0;

            foreach ($awDevices as $d) {
                $sn = strtoupper(trim($d['SerialNumber'] ?? $d['serial_number'] ?? ''));
                if (!$sn) { $uebersprungen++; continue; }

                if (isset($snipeBySerial[$sn])) {
                    $uebersprungen++;
                    continue;
                }

                $modell     = $d['Model']              ?? $d['model']             ?? 'Unknown';
                $name       = $d['DeviceFriendlyName'] ?? $d['friendly_name']     ?? $sn;
                $hersteller = (str_contains($modell,'iPad') || str_contains($modell,'iPhone')) ? 'Apple' : explode(' ', $modell)[0];
                $cat        = str_contains($modell,'iPad') ? 'iPad (Kat.5)' : 'Telefon (Kat.2)';

                if ($dryRun) {
                    $wuerdeErstellt[] = $sn;
                    $log[] = date('H:i:s') . " → NEU: $sn | $name | $modell | $hersteller | $cat";
                } else {
                    try {
                        $result = SnipeIT::createAsset([
                            'asset_tag' => $sn,
                            'serial'    => $sn,
                            'name'      => $name,
                            'status_id' => 1,
                            'model_id'  => airwatch_get_or_create_model($modell, $hersteller),
                        ]);
                        if (SnipeIT::isSuccess($result) || isset($result['id'])) {
                            $wuerdeErstellt[] = $sn;
                            $log[] = date('H:i:s') . " ✓ Angelegt: $sn ($modell)";
                        } else {
                            $fehler++;
                            $log[] = date('H:i:s') . " ✗ Fehler: $sn — " . json_encode($result['messages'] ?? $result);
                        }
                    } catch (Throwable $e) {
                        $fehler++;
                        $log[] = date('H:i:s') . " ✗ Exception: $sn — " . $e->getMessage();
                    }
                }
            }

            $erstellt = count($wuerdeErstellt);
            if ($dryRun) {
                $log[] = date('H:i:s') . " — [DRY RUN] Ergebnis: $erstellt würden angelegt, $uebersprungen bereits vorhanden";
                $log[] = date('H:i:s') . " — Kein Schreiben — Dry Run abgeschlossen";
            } else {
                $log[] = date('H:i:s') . " — Fertig: $erstellt angelegt, $uebersprungen übersprungen, $fehler Fehler";
                pdo()->prepare("INSERT INTO airwatch_sync_log (started_at, finished_at, aw_devices, created, skipped, errors, log_text, status) VALUES (NOW(), NOW(), ?, ?, ?, ?, ?, ?)")
                     ->execute([count($awDevices), $erstellt, $uebersprungen, $fehler, implode("\n", $log), $fehler===0?'ok':'warn']);
            }

            json_ok(['log' => $log, 'dry_run' => $dryRun, 'would_create' => $wuerdeErstellt,
                     'created' => $dryRun ? 0 : $erstellt, 'skipped' => $uebersprungen, 'errors' => $fehler]);

        } catch (Throwable $e) {
            $log[] = date('H:i:s') . ' ✗ Abbruch: ' . $e->getMessage();
            json_ok(['log' => $log, 'dry_run' => $dryRun, 'created' => 0, 'skipped' => 0, 'errors' => 1]);
        }
    },

    // GET /api/airwatch/search?serial=X&imei=Y
    // Sucht ein einzelnes Device in AirWatch
    'search' => function(): void {
        $serial = trim($_GET['serial'] ?? '');
        $imei   = trim($_GET['imei']   ?? '');
        $cfg    = abschnitt_lesen('airwatch');

        if (!$serial && !$imei) json_fehler('serial oder imei Parameter fehlt');

        try {
            $param  = $serial ? "searchby=Serialnumber&id=$serial" : "searchby=IMEI&id=$imei";
            $result = airwatch_api_get($cfg, "/api/mdm/devices?$param");
            $devices = $result['Devices'] ?? $result['devices'] ?? [$result];

            if (empty($devices) || (isset($devices[0]) && empty($devices[0]))) {
                json_ok(['found' => false, 'devices' => []]);
            } else {
                json_ok(['found' => true, 'devices' => $devices]);
            }
        } catch (Throwable $e) {
            json_fehler('AirWatch-Suche fehlgeschlagen: ' . $e->getMessage());
        }
    },

    // GET /api/airwatch/logs
    'logs' => function(): void {
        airwatch_init_log_table();
        $rows = pdo()->query("SELECT id, started_at, finished_at, aw_devices, created, skipped, errors, status FROM airwatch_sync_log ORDER BY id DESC LIMIT 20")
                     ->fetchAll(PDO::FETCH_ASSOC);
        json_ok($rows);
    },

    // GET /api/airwatch/log/{id}
    'log_detail' => function(mixed $id): void {
        $row = pdo()->prepare("SELECT log_text FROM airwatch_sync_log WHERE id=?");
        $row->execute([$id]);
        $r = $row->fetchColumn();
        json_ok(['log' => $r ? explode("\n", $r) : []]);
    },
];

// ── Helper functions ────────────────────────────────────────────

function airwatch_init_log_table(): void {
    pdo()->exec("CREATE TABLE IF NOT EXISTS airwatch_sync_log (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        started_at  DATETIME,
        finished_at DATETIME,
        aw_devices  INT DEFAULT 0,
        created     INT DEFAULT 0,
        skipped     INT DEFAULT 0,
        errors      INT DEFAULT 0,
        log_text    LONGTEXT,
        status      VARCHAR(10) DEFAULT 'ok'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function airwatch_api_get(array $cfg, string $path): array {
    $url  = rtrim($cfg['awUrl'] ?? '', '/') . $path;
    $auth = base64_encode(($cfg['awUser'] ?? '') . ':' . ($cfg['awPassword'] ?? ''));
    $ctx  = stream_context_create(['http' => [
        'method'        => 'GET',
        'header'        => implode("\r\n", [
            "Authorization: Basic $auth",
            "aw-tenant-code: " . ($cfg['awTenantCode'] ?? ''),
            "Accept: application/json",
        ]),
        'ignore_errors' => true,
        'timeout'       => 30,
    ], 'ssl' => [
        'verify_peer'       => !empty($cfg['sslVerify']),
        'verify_peer_name'  => !empty($cfg['sslVerify']),
    ]]);

    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) throw new RuntimeException("AirWatch nicht erreichbar: $url");

    $data = json_decode($resp, true);
    if (!is_array($data)) throw new RuntimeException("Ungültige Antwort von AirWatch: " . substr($resp, 0, 100));
    return $data;
}

function airwatch_fetch_all_devices(array $cfg): array {
    $pageSize = intval($cfg['pageSize'] ?? 500);
    $page     = 1;
    $all      = [];
    do {
        $data    = airwatch_api_get($cfg, "/api/mdm/devices?pagesize=$pageSize&page=$page");
        $devices = $data['Devices'] ?? $data['devices'] ?? [];
        $all     = array_merge($all, $devices);
        $total   = $data['Total'] ?? $data['total'] ?? count($devices);
        $page++;
    } while (count($all) < $total && count($devices) === $pageSize);
    return $all;
}

function airwatch_fetch_snipeit_serials(): array {
    // Nur Phones (id=2) und iPads (id=5) — nicht alle Assets
    $phones = SnipeIT::getAll('hardware', ['category_id' => 2, 'limit' => 500]);
    $ipads  = SnipeIT::getAll('hardware', ['category_id' => 5, 'limit' => 500]);
    return array_merge($phones, $ipads);
}

function airwatch_get_or_create_model(string $name, string $manufacturer): int {
    // Model in SnipeIT suchen oder neu anlegen
    $res = SnipeIT::get('models', ['search' => $name, 'limit' => 5]);
    foreach ($res['rows'] ?? [] as $m) {
        if (stripos($m['name'] ?? '', $name) !== false) return $m['id'];
    }
    // Manufacturer-ID ermitteln
    $mfr    = SnipeIT::get('manufacturers', ['search' => $manufacturer, 'limit' => 5]);
    $mfrId  = ($mfr['rows'][0]['id'] ?? null);
    if (!$mfrId) {
        $newMfr = SnipeIT::post('manufacturers', ['name' => $manufacturer]);
        $mfrId  = $newMfr['payload']['id'] ?? $newMfr['id'] ?? 1;
    }
    $cat   = strpos($name, 'iPad') !== false ? 5 : 2; // iPad=5, Phone=2
    $model = SnipeIT::post('models', ['name' => $name, 'manufacturer_id' => $mfrId, 'category_id' => $cat]);
    return $model['payload']['id'] ?? $model['id'] ?? 1;
}
