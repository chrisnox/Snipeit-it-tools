<?php
// ============================================================
//  IT-Tools — Installationsseite Generator
//  Version  : 2.0.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//  New v2.0:
//    - Rücknahme-Eintrag in Kopier-Sektion (verweist auf bm-pdf)
//    - Sign-Bookmark (showSign) in install.html integriert
//
//  Purpose:
//    Generiert die File install.html.
//    Diese Page wird Employeen gezeigt um Bookmarklets
//    auf ihre Lesezeichen-Leiste zu installieren.
//
//  Enthaltene Bookmarklets (konfigurierbar):
//    Outlook Mail       → Asset-Page /hardware/{id}
//    Devicee-Output PDF → Employee-Page /users/{id}
//    Signature           → Employee-Page /users/{id}
//
//  Wichtig: array_merge() mit Defaultwerten stellt sicher dass
//    neue Configurationsschlüssel (z.B. showSign) auch bei alten
//    DB-Einträgen funktionieren ohne Migration.
// ============================================================

function build_permanent_bm(string $type): string {
    $base = tools_url();
    if ($type === 'mail') {
        $page = "$base/snipeit-bm.html";
        return "javascript:(function(){var m=location.pathname.match(/\\/hardware\\/(\\d+)/);if(!m){alert('Nicht auf einer SnipeIT Asset-Seite');return;}window.open('$page?id='+m[1],'_blank','width=520,height=320,left=200,top=150');})();";
    }
    if ($type === 'sign') {
        $page = "$base/sign.html";
        return "javascript:(function(){var m=location.pathname.match(/\\/users\\/(\\d+)/);if(!m){alert('Auf einer SnipeIT User-Seite /users/{id} verwenden');return;}window.open('$page?user_id='+m[1],'_blank','width=780,height=940');})();";
    }
    if ($type === 'ruecknahme') {
        $page = "$base/snipeit-ruecknahme-pdf.html";
        return "javascript:(function(){var m=location.pathname.match(/\\/users\\/(\\d+)/);if(!m){alert('Auf einer SnipeIT User-Seite /users/{id} verwenden');return;}window.open('$page?user_id='+m[1],'_blank','width=880,height=900');})();";
    }
    if ($type === 'airwatch') {
        $page = "$base/airwatch-search.html";
        return "javascript:(function(){var m=location.pathname.match(/\\/hardware\\/(\\d+)/);if(!m){alert('Auf einer SnipeIT Asset-Seite /hardware/{id} verwenden');return;}var tag=document.querySelector('.col-md-12 h1')?.textContent?.trim()||'';var sn=document.querySelector('[data-original-title=\"Seriennummer\"] + td')?.textContent?.trim()||'';window.open('$page?serial='+encodeURIComponent(sn)+'&tag='+encodeURIComponent(tag),'_blank','width=420,height=520');})();";
    }
    if ($type === 'label') {
        $page = "$base/label-print.html";
        return "javascript:(function(){window.open('$page','_blank','width=680,height=720');})();";
    }
    // pdf
    $page = "$base/snipeit-ausgabe-pdf.html";
    return "javascript:(function(){var m=location.pathname.match(/\\/users\\/(\\d+)/);if(!m){alert('Auf einer SnipeIT User-Seite /users/{id} verwenden');return;}window.open('$page?user_id='+m[1],'_blank','width=880,height=900');})();";
}

function generate_install_page(): string {
    $mail = get_section('mail');

    // Merge stored config with defaults — ensures new keys (showSign etc.)
    // work even when the DB record predates them
    $dist = array_merge([
        'title'    => 'IT-Lesezeichen installieren',
        'subtitle' => 'Ziehe die gewünschten Lesezeichen auf deine Lesezeichen-Leiste.',
        'footer'   => 'Bei Fragen wende dich an die IT-Abteilung.',
        'showMail'      => 1,
        'showPdf'       => 1,
        'showSign'      => 1,
        'showRuecknahme'=> 1,
        'showLabel'     => 1,
        'showCopy'      => 1,
        'browsers' => ['chrome','edge','firefox','safari'],
    ], get_section('distribute') ?: []);

    $title    = htmlspecialchars($dist['title']    ?? '');
    $subtitle = htmlspecialchars($dist['subtitle'] ?? '');
    $footer   = htmlspecialchars($dist['footer']   ?? '');
    $showMail = !empty($dist['showMail']);
    $showPdf  = !empty($dist['showPdf']);
    $showSign = !empty($dist['showSign']);
    $showRuecknahme = !empty($dist['showRuecknahme'] ?? 1);
    $showCopy = !empty($dist['showCopy']);
    $browsers = is_array($dist['browsers']) ? $dist['browsers'] : ['chrome','edge','firefox','safari'];
    $mailLabel= htmlspecialchars($mail['btnLabel'] ?? 'Ausgabe an Buchhaltung');

    $mailBM  = build_permanent_bm('mail');
    $pdfBM   = build_permanent_bm('pdf');
    $signBM  = build_permanent_bm('sign');
    $labelBM = build_permanent_bm('label');
    $awBM    = build_permanent_bm('airwatch');

    $showLabel = ($dist['showLabel'] ?? 1) != 0;
    $showAW    = ($dist['showAW']    ?? 1) != 0;

    $BROWSER_STEPS = [
        'chrome'  => ['name'=>'Chrome',  'icon'=>'fa-brands fa-chrome',         'steps'=>['Lesezeichen-Leiste: <kbd>Strg+Umschalt+B</kbd>','Button gedrückt halten → auf Lesezeichen-Leiste ziehen','Loslassen ✓']],
        'edge'    => ['name'=>'Edge',    'icon'=>'fa-brands fa-edge',            'steps'=>['Favoriten-Leiste: <kbd>Strg+Umschalt+B</kbd>','Button gedrückt halten → auf Favoriten-Leiste ziehen','Loslassen ✓']],
        'firefox' => ['name'=>'Firefox', 'icon'=>'fa-brands fa-firefox-browser', 'steps'=>['<b>Ansicht → Symbolleisten → Lesezeichen-Toolbar</b>','Button auf Toolbar ziehen','Loslassen ✓']],
        'safari'  => ['name'=>'Safari',  'icon'=>'fa-brands fa-safari',          'steps'=>['<b>Darstellung → Favoritenleiste einblenden</b>','Button auf Favoritenleiste ziehen','Loslassen ✓']],
    ];

    function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES);
    }

    $tabsHtml = ''; $panesHtml = '';
    foreach ($browsers as $i => $brid) {
        $b = $BROWSER_STEPS[$brid] ?? null; if (!$b) continue;
        $active = $i === 0 ? ' active' : '';
        $disp   = $i === 0 ? 'block' : 'none';
        $tabsHtml  .= "<div class=\"bt{$active}\" onclick=\"sw(this,'bp-{$brid}')\"><i class=\"{$b['icon']}\" style=\"margin-right:5px\"></i>{$b['name']}</div>";
        $panesHtml .= "<div class=\"bp\" id=\"bp-{$brid}\" style=\"display:{$disp}\"><ol>";
        foreach ($b['steps'] as $step) $panesHtml .= "<li>{$step}</li>";
        $panesHtml .= "</ol></div>";
    }

    // ── Asset-Page bookmarks (Mail) ──────────────────────────
    $bmAssetHtml = '';
    if ($showMail) {
        $bmAssetHtml .= "<a class=\"bm\" href=\"".e($mailBM)."\" id=\"bm-mail\" onclick=\"alert('Auf die Lesezeichen-Leiste ziehen! Verwenden auf /hardware/{id}');return false;\" style=\"background:#0078d4\"><i class=\"fa-solid fa-envelope-open-text\"></i>{$mailLabel}</a>";
    }
    if ($showAW) {
        $bmAssetHtml .= "<a class=\"bm\" href=\"".e($awBM)."\" id=\"bm-aw\" onclick=\"alert('Auf die Lesezeichen-Leiste ziehen! Verwenden auf /hardware/{id}');return false;\" style=\"background:#0369a1\"><i class=\"fa-solid fa-mobile-screen-button\"></i>AirWatch suchen</a>";
    }

    // ── Accessories-Page bookmarks (Label) ─────────────────────
    $bmAccHtml = '';
    if ($showLabel) {
        $bmAccHtml .= "<a class=\"bm\" href=\"".e($labelBM)."\" id=\"bm-label\" onclick=\"alert('Auf die Lesezeichen-Leiste ziehen! Verwenden auf /accessories/{id}');return false;\" style=\"background:#2e7d32\"><i class=\"fa-solid fa-tag\"></i>Label drucken</a>";
    }

    // ── User-Page bookmarks (PDF + Sign) ────────────────────
    $bmUserHtml = '';
    if ($showPdf)  $bmUserHtml .= "<a class=\"bm\" href=\"".e($pdfBM)."\" id=\"bm-pdf\" onclick=\"alert('Auf die Lesezeichen-Leiste ziehen! Verwenden auf /users/{id}');return false;\" style=\"background:#7c5cbf\"><i class=\"fa-solid fa-file-pdf\"></i>Ausgabe &amp; Rücknahme PDF</a>";
    if ($showSign) $bmUserHtml .= "<a class=\"bm\" href=\"".e($signBM)."\" id=\"bm-sign\" onclick=\"alert('Auf die Lesezeichen-Leiste ziehen! Verwenden auf /users/{id}');return false;\" style=\"background:#5b3a8f\"><i class=\"fa-solid fa-pen-nib\"></i>Übergabe signieren</a>";

    // Section headers only if both groups are shown
    $hasAsset = $showMail;
    $hasAcc   = $showLabel;
    $hasUser  = $showPdf || $showSign || $showRuecknahme;
    $assetSection = $hasAsset ? "
  <div class=\"ba\">
    <div class=\"ba-t\"><i class=\"fa-solid fa-laptop\" style=\"margin-right:6px\"></i>Asset-Seite <code>/hardware/{id}</code></div>
    {$bmAssetHtml}
  </div>" : '';
    $accSection = $hasAcc ? "
  <div class=\"ba\">
    <div class=\"ba-t\"><i class=\"fa-solid fa-box-open\" style=\"margin-right:6px\"></i>Zubehör-Seite <code>/accessories/{id}</code></div>
    {$bmAccHtml}
  </div>" : '';
    $userSection = $hasUser ? "
  <div class=\"ba\">
    <div class=\"ba-t\"><i class=\"fa-solid fa-user\" style=\"margin-right:6px\"></i>Mitarbeiter-Seite <code>/users/{id}</code></div>
    {$bmUserHtml}
  </div>" : '';

    // ── Copy fallback ─────────────────────────────────────────
    $copyHtml = '';
    if ($showCopy && ($showMail || $showPdf || $showSign || $showRuecknahme || $showLabel)) {
        $copyHtml .= '<div class="ca"><div class="ca-t"><i class="fa-solid fa-keyboard" style="margin-right:6px"></i>Manuell installieren (falls Ziehen nicht funktioniert)</div>';
        if ($showMail)       $copyHtml .= "<div class=\"ci\"><div class=\"ci-l\">📤 {$mailLabel}</div><div class=\"cr\"><input id=\"ci-m\" readonly><button onclick=\"cp('ci-m',this)\">Kopieren</button></div></div>";
        if ($showLabel)      $copyHtml .= "<div class=\"ci\"><div class=\"ci-l\">🏷 Label drucken</div><div class=\"cr\"><input id=\"ci-lb\" readonly><button onclick=\"cp('ci-lb',this)\" style=\"background:#2e7d32\">Kopieren</button></div></div>";
        if ($showPdf)        $copyHtml .= "<div class=\"ci\"><div class=\"ci-l\">📄 Geräte-Ausgabe PDF</div><div class=\"cr\"><input id=\"ci-p\" readonly><button onclick=\"cp('ci-p',this)\" style=\"background:#7c5cbf\">Kopieren</button></div></div>";
        if ($showRuecknahme) $copyHtml .= "<div class=\"ci\"><div class=\"ci-l\">🔄 Rücknahmeprotokoll PDF</div><div class=\"cr\"><input id=\"ci-r\" readonly><button onclick=\"cp('ci-r',this)\" style=\"background:#b85c00\">Kopieren</button></div></div>";
        if ($showSign)       $copyHtml .= "<div class=\"ci\"><div class=\"ci-l\">✍ Übergabe signieren</div><div class=\"cr\"><input id=\"ci-s\" readonly><button onclick=\"cp('ci-s',this)\" style=\"background:#5b3a8f\">Kopieren</button></div></div>";
        $copyHtml .= '<div class="ch">Code kopieren → Rechtsklick auf Leiste → Lesezeichen hinzufügen → Code als URL einfügen.</div></div>';
        $copyHtml .= "<script>window.addEventListener('load',function(){";
        if ($showMail)       $copyHtml .= "var m=document.getElementById('ci-m');if(m&&document.getElementById('bm-mail'))m.value=document.getElementById('bm-mail').href;";
        if ($showLabel)      $copyHtml .= "var lb=document.getElementById('ci-lb');if(lb&&document.getElementById('bm-label'))lb.value=document.getElementById('bm-label').href;";
        if ($showPdf)        $copyHtml .= "var p=document.getElementById('ci-p');if(p&&document.getElementById('bm-pdf'))p.value=document.getElementById('bm-pdf').href;";
        if ($showRuecknahme) $copyHtml .= "var r=document.getElementById('ci-r');if(r&&document.getElementById('bm-pdf'))r.value=document.getElementById('bm-pdf').href;";
        if ($showSign)       $copyHtml .= "var s=document.getElementById('ci-s');if(s&&document.getElementById('bm-sign'))s.value=document.getElementById('bm-sign').href;";
        $copyHtml .= "});<\/script>";
    }

    $subtitleHtml = $subtitle ? "<p>{$subtitle}</p>" : '';
    $footerHtml   = $footer   ? "<div class=\"fn\">{$footer}</div>" : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{$title}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,Segoe UI,sans-serif;background:#f0f4f8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:white;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.10);max-width:580px;width:100%;padding:32px 28px}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.logo-icon{width:44px;height:44px;background:#0078d4;border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;flex-shrink:0}
h1{font-size:18px;font-weight:700;color:#111}
.logo p{font-size:12px;color:#666;margin-top:2px}
.ba{background:#f4f6fb;border-radius:8px;padding:14px 16px;margin:10px 0}
.ba-t{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#666;margin-bottom:10px}
.ba-t code{font-size:10px;background:#e8eaf0;padding:1px 5px;border-radius:3px;font-family:monospace;font-weight:400;color:#444}
.bm{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:7px;font-weight:600;font-size:13px;color:white;text-decoration:none;cursor:grab;user-select:none;margin:5px 5px 0 0;border:none;transition:opacity .15s}
.bm:hover{opacity:.9}
kbd{background:#f0f0f0;border:1px solid #ddd;border-radius:3px;padding:1px 5px;font-size:11px;font-family:monospace}
.st{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#666;margin:16px 0 8px}
.bt-row{display:flex;gap:4px;flex-wrap:wrap}
.bt{padding:7px 13px;border-radius:5px 5px 0 0;font-size:12px;font-weight:600;cursor:pointer;background:#f0f0f0;color:#555;border:1px solid #ddd;border-bottom:none}
.bt.active{background:white;color:#111}
.bp{background:white;border:1px solid #ddd;border-radius:0 5px 5px 5px;padding:12px 16px;margin-bottom:14px}
.bp ol{padding-left:18px;font-size:13px;line-height:2;color:#333}
.ca{border-top:1px solid #eee;margin-top:14px;padding-top:14px}
.ca-t{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#666;margin-bottom:10px}
.ci{margin-bottom:10px}.ci-l{font-size:12px;color:#444;margin-bottom:5px}
.cr{display:flex;gap:6px}
.cr input{flex:1;background:#f5f5f5;border:1px solid #ddd;border-radius:4px;padding:7px 9px;font-size:10px;font-family:monospace;color:#333;outline:none}
.cr button{background:#0078d4;border:none;border-radius:4px;color:white;padding:0 14px;cursor:pointer;font-size:12px;font-weight:600;transition:all .15s}
.cr button.ok{background:#27ae60}
.ch{font-size:11px;color:#888;margin-top:8px;line-height:1.5}
.fn{margin-top:18px;padding-top:12px;border-top:1px solid #eee;font-size:11px;color:#888;text-align:center}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon"><i class="fa-solid fa-bookmark"></i></div>
    <div><h1>{$title}</h1>{$subtitleHtml}</div>
  </div>
  {$assetSection}
  {$accSection}
  {$userSection}
  <div class="st">Schritt-für-Schritt Anleitung</div>
  <div class="bt-row">{$tabsHtml}</div>
  {$panesHtml}
  {$copyHtml}
  {$footerHtml}
</div>
<script>
function sw(tab,pid){document.querySelectorAll('.bt').forEach(function(t){t.classList.remove('active');});document.querySelectorAll('.bp').forEach(function(p){p.style.display='none';});tab.classList.add('active');var p=document.getElementById(pid);if(p)p.style.display='block';}
function cp(iid,btn){var inp=document.getElementById(iid);navigator.clipboard.writeText(inp.value).then(function(){var o=btn.textContent;btn.textContent='✓ Kopiert!';btn.className='ok';setTimeout(function(){btn.textContent=o;btn.className='';},2000);});}
</script>
</body>
</html>
HTML;
}

