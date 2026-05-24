<?php
// ============================================================
//  IT-Tools — Mail Runner Generator
//  Version  : 1.2.0
//  Modified : 2026-05-04
//  Author   :  Chris M.
//
//  Purpose:
//    Generiert die File snipeit-bm.html.
//    Diese File wird durch das Mail-Bookmarklet auf einer
//    SnipeIT Asset-Page (/hardware/{id}) geöffnet.
//
//  Ablauf in snipeit-bm.html (client-seitig):
//    1. Asset-ID aus URL-Parameter lesen
//    2. Asset-Daten über Proxy laden (/api/proxy/hardware/{id})
//    3. Configurede Fields aus den Asset-Daten extrahieren
//    4. mailto:-Link zusammenbauen und Outlook öffnen
//
//  CORS-Lösung:
//    Der Proxy-Endpunkt (/api/proxy/hardware/{id}) wird verwendet
//    statt eines direkten Aufrufs an snipeit.example.com.
//    So hat der Browser nur Same-Origin-Requestn → kein CORS-Problem.
//    Der API-Token ist NICHT in der HTML-File enthalten.
// ============================================================

/**
 * Generiert den HTML-Inhalt für snipeit-bm.html.
 *
 * Die Funktion liest die aktuelle Configuration aus der DB,
 * baut daraus JavaScript-Ausdrücke zur Field-Extraktion und
 * bettet diese in eine fertige HTML-Page ein.
 *
 * @return string  Vollständiger HTML-Inhalt der Runner-File
 */
function mail_runner_generieren(): string {
    $mail   = abschnitt_lesen('mail');
    $shared = shared();

    // Configurationswerte auslesen
    $snipeitUrl = $shared['snipeitUrl'] ?? '';
    $mailAn     = $mail['mailTo']       ?? '';
    $mailCc     = $mail['mailCc']       ?? '';
    $absender   = $mail['senderName']   ?? 'IT';
    $btnLabel   = $mail['btnLabel']     ?? 'Ausgabe an Buchhaltung';
    $felder     = $mail['fields']       ?? [];

    // Beschriftungen für alle möglichen Fields (deutsch)
    $feldBez = [
        'user_name'     => 'Name',
        'user_email'    => 'E-Mail',
        'user_dept'     => 'Abteilung',
        'kst'           => 'Kostenstelle',
        'location'      => 'Standort',
        'category'      => 'Kategorie',
        'asset_tag'     => 'Asset-Tag',
        'asset_name'    => 'Asset-Name',
        'manufacturer'  => 'Hersteller',
        'model'         => 'Modell',
        'serial'        => 'Seriennummer',
        'imei'          => 'IMEI',
        'supplier'      => 'Lieferant',
        'order_number'  => 'Bestellnummer',
        'purchase_date' => 'Kaufdatum',
        'purchase_cost' => 'Kaufpreis',
        'warranty'      => 'Garantie',
        'checkout_date' => 'Ausgabe-Datum',
        'snipeit_link'  => 'SnipeIT',
    ];

    // JavaScript-Ausdrücke zur Field-Extraktion aus dem SnipeIT-API-Response
    // Custom Fields (kst, imei, location) werden über die DB-Mappings aufgelöst
    $extraktoren = [
        'user_name'    => "a.assigned_to&&a.assigned_to.name?a.assigned_to.name:'\u2014'",
        'user_email'   => "a.assigned_to&&a.assigned_to.email?a.assigned_to.email:'\u2014'",
        'user_dept'    => "a.assigned_to&&a.assigned_to.department?(a.assigned_to.department.name||a.assigned_to.department):'\u2014'",
        'kst'          => cf_js_extraktor('kst'),
        'location'     => cf_js_extraktor('location') . "||(a.location&&a.location.name?a.location.name:'\u2014')",
        'category'     => "a.category&&a.category.name?a.category.name:'\u2014'",
        'asset_tag'    => "a.asset_tag||'\u2014'",
        'asset_name'   => "a.name||'\u2014'",
        'manufacturer' => "a.manufacturer&&a.manufacturer.name?a.manufacturer.name:'\u2014'",
        'model'        => "a.model&&a.model.name?a.model.name:'\u2014'",
        'serial'       => "a.serial||'\u2014'",
        'imei'         => cf_js_extraktor('imei'),
        'supplier'     => "a.supplier&&a.supplier.name?a.supplier.name:'\u2014'",
        'order_number' => "a.order_number||'\u2014'",
        'purchase_date'=> "a.purchase_date&&(a.purchase_date.formatted||a.purchase_date.date)||'\u2014'",
        'purchase_cost'=> "a.purchase_cost||'\u2014'",
        'warranty'     => "a.warranty_months?a.warranty_months+' Monate (bis '+(a.warranty_expires&&(a.warranty_expires.date||a.warranty_expires)||'\u2014')+')':'\u2014'",
        'checkout_date'=> "today",
        'snipeit_link' => "'{$snipeitUrl}/hardware/'+a.id",
    ];

    $gruppenBez = [
        'mitarbeiter' => 'MITARBEITER',
        'asset'       => 'ASSET',
        'beschaffung' => 'BESCHAFFUNG',
    ];

    // JavaScript-Variablen und Mail-Text aus den konfigurierten Fieldsn aufbauen
    $variablen = [];
    $mailTeile = ["'Sehr geehrte Buchhaltung,\\n\\nfolgendes Asset wurde am '+today+' ausgegeben:\\n\\n'"];
    $ccTeil    = $mailCc ? "&cc={$mailCc}" : '';

    foreach ($gruppenBez as $gruppe => $gruppenTitel) {
        // Nur aktive Fields dieser Gruppe
        $keys = array_keys(array_filter($felder[$gruppe] ?? []));
        if (!$keys) continue;

        $trenn = str_repeat('─', 33);
        $mailTeile[] = "'{$trenn}\\n{$gruppenTitel}\\n{$trenn}\\n'";

        foreach ($keys as $k) {
            // JavaScript-Variable für dieses Field deklarieren
            $variablen[] = "var {$k}=(" . ($extraktoren[$k] ?? "'—'") . ");";
            // Beschriftung linksbündig auf 20 Zeichen auffüllen
            $bez = str_pad(($feldBez[$k] ?? $k) . ':', 20);
            $mailTeile[] = "'{$bez}'+{$k}+'\\n'";
        }
        $mailTeile[] = "'\\n'"; // Leerzeile nach jeder Gruppe
    }
    $mailTeile[] = "'Mit freundlichen Grüßen\\n{$absender}'";

    // JavaScript-Blöcke zusammenbauen
    $variablenJs = implode('', $variablen);
    $mailTextJs  = implode('+', $mailTeile);
    $btnLabelEsc = htmlspecialchars($btnLabel);

    // Proxy-URL: same-origin, kein CORS, Token bleibt server-seitig
    $proxyUrl = '/api/proxy/hardware/';

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{$btnLabelEsc}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,sans-serif;background:#0f1117;color:#d8dce8;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#181c27;border:1px solid #2a2f3e;border-radius:12px;padding:32px 28px;max-width:400px;width:100%;text-align:center}
.icon{width:52px;height:52px;background:#0078d4;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;color:white;margin:0 auto 14px}
h1{font-size:15px;font-weight:600;margin-bottom:4px}
.sub{font-size:12px;color:#606578;margin-bottom:20px}
.spinner{width:28px;height:28px;border:3px solid #2a2f3e;border-top-color:#4f8ef7;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px}
@keyframes spin{to{transform:rotate(360deg)}}
.status{font-size:13px;color:#606578}
</style>
</head>
<body>
<div class="card">
  <div class="icon"><i class="fa-solid fa-envelope-open-text"></i></div>
  <h1>{$btnLabelEsc}</h1>
  <p class="sub">Asset-Daten werden geladen...</p>
  <div class="spinner"></div>
  <div class="status" id="s">Bitte warten...</div>
</div>
<script>
// Lädt Asset-Daten über Proxy und öffnet Outlook mit vorausgefüllter Mail
(function(){
  var params=new URLSearchParams(location.search);
  var id=params.get('id');
  var s=document.getElementById('s');
  if(!id){s.textContent='Fehler: Keine Asset-ID';s.style.color='#e74c3c';return;}
  s.textContent='Lade Asset #'+id+'...';
  // Proxy-Aufruf statt direktem SnipeIT-Zugriff (vermeidet CORS)
  fetch('{$proxyUrl}'+id)
  .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
  .then(function(a){
    if(!a.id){s.textContent='Asset nicht gefunden';s.style.color='#e74c3c';return;}
    var cff=a.custom_fields||{};
    // Helper function für Custom Fields
    function cf(k){var x=cff[k];return x&&x.value?x.value:'\u2014';}
    var today=new Date().toLocaleDateString('de-AT',{day:'2-digit',month:'2-digit',year:'numeric'});
    // Automatisch generierte Field-Variablen
    {$variablenJs}
    var betreff='[Asset-Ausgabe] '+asset_tag+' \u2192 '+user_name;
    var text={$mailTextJs};
    var href='mailto:{$mailAn}?{$ccTeil}&subject='+encodeURIComponent(betreff)+'&body='+encodeURIComponent(text);
    window.location.href=href;
    s.textContent='\u2713 Outlook wird ge\u00f6ffnet...';s.style.color='#27ae60';
  })
  .catch(function(e){s.textContent='\u2717 '+e.message;s.style.color='#e74c3c';});
})();
</script>
</body>
</html>
HTML;
}

// Alias für Kompatibilität mit bestehendem Code
function generate_mail_runner(): string {
    return mail_runner_generieren();
}
