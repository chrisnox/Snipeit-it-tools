<?php
// ============================================================
//  IT-Tools — Signature-Page Generator
//  Version  : 2.1.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//  New v2.x:
//    - confirmText via json_encode + textContent (Umlaut-sicher)
//
//  Purpose:
//    Generiert die File sign.html.
//    Adaptiert vom PDF-Modules: gleiche Category-Configuration,
//    gleiche Asset-Auswahl-UI, gleiche Proxy-Aufrufe.
//
//  Ablauf in sign.html (client-seitig):
//    1. User-Daten + alle Assets über Proxy laden
//    2. Asset-Auswahl anzeigen (gleiche UI wie PDF — Categoryn
//       mit Farben, Checkboxen, Alle/Noe)
//    3. Signature-Canvas + Bestätigungstext anzeigen
//    4. "Unterschreiben & Bestätigen" → POST /api/sign/submit
//    5. PHP generiert PNG-Dokument + lädt es zu jedem Asset hoch
//
//  Category-Configuration:
//    Wird aus dem PDF-Modules übernommen (gleiche Category-IDs,
//    Farben und Fieldkonfiguration). Sign nutzt zusätzlich die
//    eigene cats-Configuration um zu steuern welche Categoryn
//    im Signatureformular erscheinen.
//
//  CORS-Lösung:
//    Alle SnipeIT-Aufrufe laufen über /api/proxy/* (same-origin).
//    Token verbleibt in der DB, erscheint NICHT in der HTML-File.
// ============================================================

/**
 * Generiert den HTML-Inhalt für sign.html.
 * Übernimmt Category-Configuration und Asset-Auswahl-UI vom PDF-Modules.
 */
function sign_runner_generieren(): string {
    // Configuration aus beiden Modulesen laden
    $cfg  = abschnitt_lesen('sign');
    $pdf  = abschnitt_lesen('pdf');

    // Bestätigungstext (aus Sign-Configuration)
    $confirmText = addslashes(
        $cfg['confirmText'] ?? 'Ich bestätige den Erhalt der oben angeführten Geräte und die Kenntnisnahme der IT-Nutzungsrichtlinien.'
    );
    $company  = htmlspecialchars($pdf['company'] ?? 'IT');
    $simCatId = intval($pdf['simCatId'] ?? 6);

    // Welche Categoryn im Signatureformular erscheinen
    // Kann in Sign-Configuration übersteuert werden, Default = PDF-Categoryn
    $signCats = $cfg['cats'] ?? $pdf['cats'] ?? ['notebook'=>1,'phone'=>1,'ipad'=>1,'sim'=>0];

    // Category-Configuration (identisch mit PDF — gleiche IDs, Farben, Labels)
    $catConfig = json_encode([
        'notebook' => ['id'=>4,         'label'=>'Notebook',  'color'=>'#1a3a6b', 'enabled'=>(bool)($signCats['notebook']??false)],
        'phone'    => ['id'=>2,         'label'=>'Telefon',   'color'=>'#1a4a2e', 'enabled'=>(bool)($signCats['phone']??false)],
        'ipad'     => ['id'=>5,         'label'=>'iPad',      'color'=>'#3a2a1a', 'enabled'=>(bool)($signCats['ipad']??false)],
        'sim'      => ['id'=>$simCatId, 'label'=>'SIM-Karte', 'color'=>'#2a1a4a', 'enabled'=>(bool)($signCats['sim']??false)],
    ], JSON_UNESCAPED_UNICODE);

    // Fields die im Signature-Dokument erscheinen (aus PDF-Configuration)
    $pdfFields = $pdf['fields'] ?? [];
    $fieldConfig = json_encode([
        'notebook' => array_keys(array_filter($pdfFields['notebook'] ?? ['asset_tag'=>1,'asset_name'=>1,'model'=>1,'serial'=>1])),
        'phone'    => array_keys(array_filter($pdfFields['phone']    ?? ['asset_tag'=>1,'model'=>1,'serial'=>1,'imei'=>1])),
        'ipad'     => array_keys(array_filter($pdfFields['ipad']     ?? ['asset_tag'=>1,'model'=>1,'serial'=>1])),
        'sim'      => array_keys(array_filter($pdfFields['sim']      ?? ['asset_tag'=>1,'asset_name'=>1])),
    ], JSON_UNESCAPED_UNICODE);

    // Custom Fields für IMEI (aus DB-Mapping)
    $cfMap   = cf_karte();
    $imeiKey = addslashes($cfMap['imei']['snipeit_key']  ?? '_snipeit_imei_3');
    $imeiFb  = addslashes($cfMap['imei']['fallback_key'] ?? 'IMEI');
    $kstKey  = addslashes($cfMap['kst']['snipeit_key']   ?? '_snipeit_kst_5');
    $kstFb   = addslashes($cfMap['kst']['fallback_key']  ?? '');

    // JavaScript-Extraktor für IMEI (gleich wie in PDF-Runner)
    $imeiExtract = "function imei_get(a){var cf=a.custom_fields||{};var x=cf['{$imeiKey}']" .
                   ($imeiFb ? "||cf['{$imeiFb}']" : '') .
                   ";return x&&x.value?x.value:'\u2014';}";

    // KST-Extraktor für User-Info
    $kstExtract = $kstFb
        ? "(u.custom_fields&&(u.custom_fields['{$kstKey}']||u.custom_fields['{$kstFb}']))?(u.custom_fields['{$kstKey}']?u.custom_fields['{$kstKey}'].value:u.custom_fields['{$kstFb}'].value):'\u2014'"
        : "(u.custom_fields&&u.custom_fields['{$kstKey}'])?u.custom_fields['{$kstKey}'].value:'\u2014'";

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Übergabe-Signatur — {$company}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/4.1.7/signature_pad.umd.min.js"></script>
<style>
/* ── Reset & Basis ──────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{background:#f0f2f7;font-family:-apple-system,Segoe UI,sans-serif;font-size:14px;color:#1a1a2e}

/* ── Layout ─────────────────────────────────────────────── */
.page{max-width:700px;margin:0 auto;padding:16px;display:flex;flex-direction:column;gap:12px}

/* ── Toolbar (fixiert oben) ─────────────────────────────── */
.toolbar{position:sticky;top:0;z-index:50;background:#1a1a2e;padding:10px 16px;display:flex;align-items:center;gap:10px;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.2)}
.toolbar-title{font-size:13px;font-weight:600;color:white;flex:1}
.toolbar-sub{font-size:11px;color:#888;margin-top:1px}
.btn-close{background:transparent;border:1px solid #444;color:#aaa;padding:6px 12px;border-radius:5px;cursor:pointer;font-size:11px;font-weight:600}
.btn-submit{background:#1a3a6b;border:none;color:white;padding:8px 18px;border-radius:5px;cursor:pointer;font-size:13px;font-weight:600;display:flex;align-items:center;gap:7px;transition:background .15s}
.btn-submit:hover:not(:disabled){background:#24509a}
.btn-submit:disabled{opacity:.4;cursor:not-allowed}

/* ── Karten ─────────────────────────────────────────────── */
.card{background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden}
.card-hdr{padding:12px 16px;display:flex;align-items:center;gap:9px;font-size:12px;font-weight:600;color:white}
.card-hdr i{font-size:14px;opacity:.9}
.card-body{padding:14px 16px}

/* ── Mitarbeiter-Info ───────────────────────────────────── */
.user-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.ui-item label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#888;display:block;margin-bottom:2px}
.ui-item span{font-size:13px;font-weight:600;color:#1a1a2e}

/* ── Asset-Auswahl (identisch mit PDF) ──────────────────── */
.sel-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.sel-count{font-size:12px;color:#888}
.sel-btns{display:flex;gap:6px}
.sel-btns button{background:none;border:1px solid #dde;border-radius:4px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;color:#555;transition:all .1s}
.sel-btns button:hover{background:#f0f2f7}
.cat-hdr{color:white;padding:6px 12px;border-radius:4px 4px 0 0;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.cat-body{border:1px solid #dde;border-top:none;border-radius:0 0 4px 4px;overflow:hidden;margin-bottom:10px}
.asset-row{display:flex;align-items:center;gap:12px;padding:10px 14px;cursor:pointer;transition:background .1s;border-bottom:1px solid #f0f0f0}
.asset-row:last-child{border-bottom:none}
.asset-row:hover{background:#f4f6fb}
.asset-row.selected{background:#eef4fe}
.asset-cb{width:18px;height:18px;border:2px solid #ccc;border-radius:4px;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .15s;font-size:10px;color:transparent}
.asset-row.selected .asset-cb{background:#1a3a6b;border-color:#1a3a6b;color:white}
.asset-info{flex:1;min-width:0}
.asset-tag{font-size:11px;font-weight:700;font-family:monospace;color:#1a3a6b}
.asset-name{font-size:12px;color:#1a1a2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.asset-sub{font-size:10px;color:#888}
.no-assets{text-align:center;padding:16px;color:#888;font-size:13px;font-style:italic}

/* ── Bestätigungstext ───────────────────────────────────── */
.confirm-box{background:#fffbea;border:1px solid #f0d060;border-radius:8px;padding:12px 14px;font-size:13px;line-height:1.6;color:#333}

/* ── Signatur-Canvas ────────────────────────────────────── */
.sig-wrap{position:relative;border:2px dashed #ccd;border-radius:8px;background:#fafafa;overflow:hidden;touch-action:none;margin-bottom:8px}
.sig-wrap canvas{display:block;width:100%;height:180px}
.sig-hint{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:13px;color:#bbb;pointer-events:none;text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px}
.sig-hint i{font-size:26px}
.sig-clear{position:absolute;top:8px;right:8px;background:rgba(255,255,255,.9);border:1px solid #ddd;border-radius:5px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;color:#666}
.sig-clear:hover{color:#e05555;border-color:#e05555}

/* ── Warnungen ──────────────────────────────────────────── */
.warn{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:9px 13px;font-size:12px;color:#856404;display:none;margin-top:6px}

/* ── Lade-Zustand ───────────────────────────────────────── */
#state-loading{display:flex;position:fixed;inset:0;background:#f0f2f7;z-index:100;justify-content:center;align-items:center;flex-direction:column;gap:14px}
.spinner{width:34px;height:34px;border:4px solid #e0e4f0;border-top-color:#1a3a6b;border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
#load-msg{font-size:13px;color:#666}

/* ── Fehler-Zustand ─────────────────────────────────────── */
#state-error{display:none;position:fixed;inset:0;background:#f0f2f7;z-index:100;align-items:center;justify-content:center;padding:24px}
.err-icon{width:54px;height:54px;background:#fee;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;color:#e05555;margin:0 auto 12px}

/* ── Erfolgs-Zustand ────────────────────────────────────── */
#state-success{display:none;position:fixed;inset:0;background:#f0f2f7;z-index:100;overflow-y:auto;padding:24px}
.ok-icon{width:62px;height:62px;background:#eafaf1;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;color:#27ae60;margin:0 auto 12px}
.result-row{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:7px;font-size:13px;margin-bottom:5px}
.result-row.ok{background:#eafaf1;color:#1e8449}
.result-row.err{background:#fef9e7;color:#935116}

@media(min-width:520px){.sig-wrap canvas{height:220px}}
</style>
</head>
<body>

<!-- Lade-Animation -->
<div id="state-loading">
  <div class="spinner"></div>
  <div id="load-msg">Lade Benutzerdaten...</div>
</div>

<!-- Fehler-Anzeige -->
<div id="state-error">
  <div style="max-width:400px;width:100%;text-align:center">
    <div class="err-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <h2 style="font-size:17px;margin-bottom:8px">Fehler</h2>
    <p id="error-msg" style="color:#666;font-size:13px"></p>
    <button onclick="window.close()" style="margin-top:16px;padding:10px 24px;background:#1a1a2e;color:white;border:none;border-radius:7px;font-size:13px;cursor:pointer">Schließen</button>
  </div>
</div>

<!-- Erfolgs-Anzeige -->
<div id="state-success">
  <div style="max-width:520px;margin:0 auto;text-align:center">
    <div class="ok-icon"><i class="fa-solid fa-circle-check"></i></div>
    <h2 style="font-size:19px;margin-bottom:6px">Unterschrift gespeichert</h2>
    <p id="success-msg" style="color:#666;font-size:13px;margin-bottom:14px"></p>
    <div id="result-list" style="text-align:left;margin-bottom:20px"></div>
    <button onclick="window.close()" style="padding:12px 28px;background:#1a1a2e;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer">Fenster schließen</button>
  </div>
</div>

<!-- Hauptinhalt -->
<div id="main-content" style="display:none">
<div class="page">

  <!-- Toolbar -->
  <div class="toolbar">
    <div style="flex:1">
      <div class="toolbar-title"><i class="fa-solid fa-pen-nib" style="margin-right:7px"></i>{$company} — Übergabe-Bestätigung</div>
      <div class="toolbar-sub" id="toolbar-sub">Bitte Assets auswählen und unterschreiben</div>
    </div>
    <button class="btn-close" onclick="window.close()"><i class="fa-solid fa-xmark"></i> Schließen</button>
    <button class="btn-submit" id="btn-submit" onclick="absenden()" disabled>
      <i class="fa-solid fa-circle-check"></i>Unterschreiben
    </button>
  </div>

  <!-- Mitarbeiter-Info -->
  <div class="card">
    <div class="card-hdr" style="background:#1a3a6b">
      <i class="fa-solid fa-user"></i><span>Mitarbeiter</span>
    </div>
    <div class="card-body">
      <div class="user-grid">
        <div class="ui-item"><label>Name</label><span id="u-name">—</span></div>
        <div class="ui-item"><label>Abteilung</label><span id="u-dept">—</span></div>
        <div class="ui-item"><label>Kostenstelle</label><span id="u-kst">—</span></div>
      </div>
    </div>
  </div>

  <!-- Asset-Auswahl -->
  <div class="card">
    <div class="card-hdr" style="background:#2a3a5b">
      <i class="fa-solid fa-laptop"></i><span>Zu übergebende Geräte</span>
    </div>
    <div class="card-body">
      <div class="sel-toolbar">
        <span class="sel-count" id="sel-count">0 ausgewählt</span>
        <div class="sel-btns">
          <button onclick="alleWaehlen(true)">Alle</button>
          <button onclick="alleWaehlen(false)">Keine</button>
        </div>
      </div>
      <div id="asset-liste"></div>
      <div id="keine-assets" class="no-assets" style="display:none">
        Keine Geräte der konfigurierten Kategorien gefunden.
      </div>
      <div class="warn" id="warn-assets">
        <i class="fa-solid fa-circle-exclamation"></i> Bitte mindestens ein Gerät auswählen.
      </div>
    </div>
  </div>

  <!-- Bestätigungstext -->
  <div class="card">
    <div class="card-hdr" style="background:#2a3a5b">
      <i class="fa-solid fa-file-lines"></i><span>Bestätigung</span>
    </div>
    <div class="card-body">
      <div class="confirm-box">{$confirmText}</div>
    </div>
  </div>

  <!-- Unterschrift -->
  <div class="card">
    <div class="card-hdr" style="background:#2a3a5b">
      <i class="fa-solid fa-signature"></i><span>Unterschrift</span>
    </div>
    <div class="card-body">
      <p style="font-size:12px;color:#888;margin-bottom:10px">
        Mit Finger, Stift oder Maus im Feld unten unterschreiben:
      </p>
      <div class="sig-wrap">
        <canvas id="sig-canvas"></canvas>
        <div class="sig-hint" id="sig-hint">
          <i class="fa-solid fa-pen-fancy"></i>
          <span>Hier unterschreiben</span>
        </div>
        <button class="sig-clear" onclick="signaturLeeren()">
          <i class="fa-solid fa-rotate-left"></i> Leeren
        </button>
      </div>
      <div class="warn" id="warn-sig">
        <i class="fa-solid fa-circle-exclamation"></i> Bitte unterschreiben.
      </div>
    </div>
  </div>

  <div style="height:16px"></div>
</div><!-- .page -->
</div><!-- #main-content -->

<script>
(function(){
  // ── Configuration (aus PHP eingebettet) ─────────────────
  var CAT    = {$catConfig};
  var FIELDS = {$fieldConfig};
  var FL     = {
    asset_tag:'Asset-Tag', asset_name:'Asset-Name / Rufnummer',
    model:'Modell', serial:'Seriennummer',
    manufacturer:'Hersteller', imei:'IMEI',
    purchase_date:'Kaufdatum', warranty:'Garantie bis'
  };

  // ── Zustand ─────────────────────────────────────────────
  var pad, alleAssets = [], benutzer = {}, ausgewaehlteIds = new Set();

  // ── IMEI-Extraktor (aus Custom Fields DB-Mapping) ───────
  {$imeiExtract}

  // ── Helper functions ─────────────────────────────────────
  function esc(s){ return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }

  function feldWert(a, k) {
    if(k === 'imei')          return imei_get(a);
    if(k === 'asset_tag')     return a.asset_tag || '\u2014';
    if(k === 'asset_name')    return a.name || '\u2014';
    if(k === 'model')         return a.model && a.model.name ? a.model.name : '\u2014';
    if(k === 'serial')        return a.serial || '\u2014';
    if(k === 'manufacturer')  return a.manufacturer && a.manufacturer.name ? a.manufacturer.name : '\u2014';
    if(k === 'purchase_date') return a.purchase_date && (a.purchase_date.formatted || a.purchase_date.date) || '\u2014';
    if(k === 'warranty')      return a.warranty_expires && (a.warranty_expires.date || a.warranty_expires) || '\u2014';
    return '\u2014';
  }

  function ladeMsg(m) { document.getElementById('load-msg').textContent = m; }

  function zeigeAnzeige(id) {
    ['state-loading','state-error','state-success','main-content'].forEach(function(s){
      var el = document.getElementById(s);
      if(el) el.style.display = (s === id) ? (s === 'main-content' ? 'block' : 'flex') : 'none';
    });
  }

  function zeigeFehler(msg) {
    document.getElementById('error-msg').textContent = msg;
    zeigeAnzeige('state-error');
  }

  // ── User rendern ─────────────────────────────────────
  function renderBenutzer(u) {
    document.getElementById('u-name').textContent = u.name || '—';
    var dept = u.department && u.department.name ? u.department.name : (u.department || '—');
    document.getElementById('u-dept').textContent = dept;
    var cf  = u.custom_fields || {};
    var kst = ({$kstExtract});
    document.getElementById('u-kst').textContent = kst;
    document.getElementById('toolbar-sub').textContent = u.name + ' · ' + dept;
    benutzer = u;
  }

  // ── Asset-Liste rendern (identisch mit PDF-Modules) ────────
  function renderAssets(assets) {
    var liste   = document.getElementById('asset-liste');
    var keineEl = document.getElementById('keine-assets');

    // Nach konfigurierten Categoryn filtern
    var gefiltert = assets.filter(function(a) {
      var cid = a.category && a.category.id ? a.category.id : 0;
      return Object.values(CAT).some(function(c) { return c.enabled && cid === c.id; });
    });

    alleAssets = gefiltert;

    if(!gefiltert.length) {
      keineEl.style.display = 'block';
      liste.innerHTML = '';
      zaehlerAktualisieren();
      return;
    }

    // Nach Categoryn gruppieren
    var gruppen = {};
    gefiltert.forEach(function(a) {
      var cid     = a.category && a.category.id ? a.category.id : 0;
      var catName = a.category && a.category.name ? a.category.name : 'Sonstiges';
      var farbe   = '#1a3a6b';
      Object.values(CAT).forEach(function(c) { if(c.enabled && cid === c.id) farbe = c.color; });
      if(!gruppen[catName]) gruppen[catName] = {farbe: farbe, items: []};
      gruppen[catName].items.push(a);
      ausgewaehlteIds.add(a.id); // alle vorausgewählt
    });

    var html = '';
    Object.keys(gruppen).forEach(function(grpName) {
      var grp = gruppen[grpName];
      html += '<div class="cat-hdr" style="background:' + grp.farbe + '">' + esc(grpName) + '</div>';
      html += '<div class="cat-body">';
      grp.items.forEach(function(a, ai) {
        var cf2    = a.custom_fields || {};
        var imei   = imei_get(a);
        var model  = a.model && a.model.name ? a.model.name : '';
        var subTxt = esc(model) + (imei !== '\u2014' ? ' · IMEI: ' + esc(imei) : '');
        html +=
          '<div class="asset-row selected" id="ar-' + a.id + '" onclick="umschalten(' + a.id + ')">' +
          '<div class="asset-cb"><i class="fa-solid fa-check"></i></div>' +
          '<div class="asset-info">' +
            '<div class="asset-tag">' + esc(a.asset_tag || '') + '</div>' +
            '<div class="asset-name">' + esc(a.name || '') + '</div>' +
            '<div class="asset-sub">' + subTxt + '</div>' +
          '</div>' +
          '</div>';
      });
      html += '</div>';
    });

    liste.innerHTML = html;
    zaehlerAktualisieren();
    submitBtn();
  }

  // ── Auswahl-Steuerung ────────────────────────────────────
  window.umschalten = function(id) {
    var row = document.getElementById('ar-' + id);
    if(ausgewaehlteIds.has(id)) {
      ausgewaehlteIds.delete(id);
      row.classList.remove('selected');
    } else {
      ausgewaehlteIds.add(id);
      row.classList.add('selected');
    }
    zaehlerAktualisieren();
    submitBtn();
  };

  window.alleWaehlen = function(zustand) {
    alleAssets.forEach(function(a) {
      var row = document.getElementById('ar-' + a.id);
      if(!row) return;
      if(zustand) { ausgewaehlteIds.add(a.id);    row.classList.add('selected'); }
      else        { ausgewaehlteIds.delete(a.id); row.classList.remove('selected'); }
    });
    zaehlerAktualisieren();
    submitBtn();
  };

  function zaehlerAktualisieren() {
    var n = ausgewaehlteIds.size;
    document.getElementById('sel-count').textContent = n + ' ausgewählt';
  }

  function submitBtn() {
    var btn = document.getElementById('btn-submit');
    if(btn) btn.disabled = (ausgewaehlteIds.size === 0);
  }

  // ── Signature-Pad ─────────────────────────────────────────
  function signaturInit() {
    var canvas = document.getElementById('sig-canvas');
    var hint   = document.getElementById('sig-hint');

    function groesseAnpassen() {
      var ratio = Math.max(window.devicePixelRatio || 1, 1);
      var w = canvas.offsetWidth, h = canvas.offsetHeight;
      canvas.width  = w * ratio;
      canvas.height = h * ratio;
      canvas.getContext('2d').scale(ratio, ratio);
      if(pad) pad.clear();
    }

    pad = new SignaturePad(canvas, {penColor:'#1a1a2e', minWidth:1.5, maxWidth:3});
    pad.addEventListener('beginStroke', function() { hint.style.display = 'none'; });
    window.addEventListener('resize', groesseAnpassen);
    groesseAnpassen();
  }

  window.signaturLeeren = function() {
    if(pad) pad.clear();
    document.getElementById('sig-hint').style.display = 'flex';
  };

  // ── Absenden ─────────────────────────────────────────────
  window.absenden = function() {
    // Validierung
    document.getElementById('warn-assets').style.display = 'none';
    document.getElementById('warn-sig').style.display    = 'none';

    var gewaehlteAssets = alleAssets.filter(function(a) { return ausgewaehlteIds.has(a.id); });
    if(!gewaehlteAssets.length) {
      document.getElementById('warn-assets').style.display = 'block';
      return;
    }
    if(!pad || pad.isEmpty()) {
      document.getElementById('warn-sig').style.display = 'block';
      return;
    }

    // Ladeanimation
    var btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;border-width:3px"></div> Wird verarbeitet...';
    ladeMsg('Dokument wird erstellt und hochgeladen...');
    zeigeAnzeige('state-loading');

    // Userdaten aufbereiten
    var u   = benutzer;
    var cf  = u.custom_fields || {};
    var kst = ({$kstExtract});
    var dept = u.department && u.department.name ? u.department.name : (u.department || '');

    // Nutzlast für API
    var nutzlast = {
      user_id:   u.id,
      user_name: u.name     || '',
      user_dept: dept,
      user_kst:  kst !== '\u2014' ? kst : '',
      signature: pad.toDataURL('image/png'),
      assets: gewaehlteAssets.map(function(a) {
        return {
          id:       a.id,
          tag:      a.asset_tag || '',
          name:     a.name      || '',
          model:    a.model && a.model.name ? a.model.name : '',
          serial:   a.serial    || '',
          category: a.category  && a.category.name ? a.category.name : '',
          imei:     imei_get(a) !== '\u2014' ? imei_get(a) : '',
        };
      }),
    };

    // API-Aufruf
    fetch('/api/sign/submit', {
      method:  'POST',
      headers: {'Content-Type': 'application/json'},
      body:    JSON.stringify(nutzlast),
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if(!res.ok) throw new Error(res.error || 'Unbekannter Fehler');
      zeigeErfolg(res.data, gewaehlteAssets);
    })
    .catch(function(e) {
      zeigeAnzeige('main-content');
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-circle-check"></i>Unterschreiben';
      alert('Fehler beim Hochladen: ' + e.message);
    });
  };

  // ── Erfolgs-Anzeige ──────────────────────────────────────
  function zeigeErfolg(daten, assets) {
    var n = daten.uploaded_count || 0;
    var userOk = daten.user_uploaded;
    document.getElementById('success-msg').textContent =
      'Unterschrift von ' + (benutzer.name || '') + ' gespeichert.';

    var html = '';
    // Employee-Upload
    html += '<div class="result-row ' + (userOk ? 'ok' : 'err') + '">' +
      '<i class="fa-solid ' + (userOk ? 'fa-circle-check' : 'fa-triangle-exclamation') + '"></i>' +
      '<span><b>Mitarbeiter-Akte</b> (' + esc(benutzer.name||'') + ')' +
      (userOk ? ' → gespeichert' : ' → Fehler beim Upload') + '</span></div>';
    // Asset-Uploads
    assets.forEach(function(a) {
      var ok = daten.uploaded && daten.uploaded.includes(a.id);
      html += '<div class="result-row ' + (ok ? 'ok' : 'err') + '">' +
        '<i class="fa-solid ' + (ok ? 'fa-circle-check' : 'fa-triangle-exclamation') + '"></i>' +
        '<span><b>' + esc(a.tag||'') + '</b> ' + esc(a.name||'') +
        (ok ? ' → gespeichert' : ' → Fehler beim Upload') + '</span></div>';
    });
    document.getElementById('result-list').innerHTML = html;
    zeigeAnzeige('state-success');
  }

  // ── Start ────────────────────────────────────────────────
  var params = new URLSearchParams(location.search);
  var uid    = params.get('user_id');

  if(!uid) {
    zeigeFehler('Keine user_id in URL. Bookmarklet auf einer SnipeIT User-Seite /users/{id} verwenden.');
    return;
  }

  ladeMsg('Lade Benutzerdaten...');

  Promise.all([
    fetch('/api/proxy/users/' + uid)
      .then(function(r) { if(!r.ok) throw new Error('Benutzer HTTP ' + r.status); return r.json(); }),
    fetch('/api/proxy/users/' + uid + '/assets')
      .then(function(r) { if(!r.ok) throw new Error('Assets HTTP '   + r.status); return r.json(); }),
  ])
  .then(function(res) {
    var u        = res[0];
    var rohdaten = res[1];
    var assets   = Array.isArray(rohdaten) ? rohdaten : (rohdaten.rows || []);

    if(!u||!u.id) throw new Error('Benutzer nicht gefunden.');

    renderBenutzer(u);
    renderAssets(assets);
    signaturInit();

    zeigeAnzeige('main-content');
  })
  .catch(function(e) { zeigeFehler(e.message); });

})();
</script>
</body>
</html>
HTML;
}

// Alias für Kompatibilität
function generate_sign_runner(): string {
    return sign_runner_generieren();
}
