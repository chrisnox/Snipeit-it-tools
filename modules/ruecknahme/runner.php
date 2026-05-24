<?php
// ============================================================
//  IT-Tools — Rücknahmeprotokoll Runner Generator
//  Version  : 2.1.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//
//  Ablauf:
//    1. User + Assets laden (Proxy)
//    2. Asset-Auswahl Panel
//    3a. "Printen" → A4 rendern + Printdialog (unverändert)
//    3b. "Digital signieren" → Signature-Modal → Bestätigen
//        → A4 mit Signature-Abbild + Upload zu SnipeIT im Hintergrund
// ============================================================

function ruecknahme_runner_generieren(): string {
    $pdf    = abschnitt_lesen('ruecknahme');
    $shared = shared();

    $company   = addslashes($pdf['company']  ?? 'Muster GmbH');
    $docTitle  = addslashes($pdf['docTitle'] ?? 'Rücknahmeprotokoll IT-Geräte');
    $footer    = addslashes($pdf['footer']   ?? '');
    $note      = addslashes($pdf['note']     ?? '');
    $cats      = $pdf['cats']    ?? [];
    $simCatId  = intval(($pdfCfg ?? abschnitt_lesen('pdf'))['simCatId'] ?? 6);
    $pdfCfg   = abschnitt_lesen('pdf');
    $pdfFields = $pdfCfg['fields'] ?? [];

    $cfMap   = cf_karte();
    $kstKey  = addslashes($cfMap['kst']['snipeit_key']   ?? '_snipeit_kst_5');
    $kstFb   = addslashes($cfMap['kst']['fallback_key']  ?? '');
    $imeiKey = addslashes($cfMap['imei']['snipeit_key']  ?? '_snipeit_imei_3');
    $imeiFb  = addslashes($cfMap['imei']['fallback_key'] ?? 'IMEI');

    $catConfig = json_encode([
        'notebook' => ['id'=>4,         'label'=>'Notebook',  'color'=>'#1a3a6b','enabled'=>(bool)($cats['notebook']??false)],
        'phone'    => ['id'=>5,         'label'=>'Telefon',   'color'=>'#1a4a2e','enabled'=>(bool)($cats['phone']??false)],
        'ipad'     => ['id'=>2,         'label'=>'iPad',      'color'=>'#3a2a1a','enabled'=>(bool)($cats['ipad']??false)],
        'sim'      => ['id'=>$simCatId, 'label'=>'SIM-Karte', 'color'=>'#2a1a4a','enabled'=>(bool)($cats['sim']??false)],
    ], JSON_UNESCAPED_UNICODE);

    $fieldConfig = json_encode([
        'notebook' => array_keys(array_filter($pdfFields['notebook'] ?? [])),
        'phone'    => array_keys(array_filter($pdfFields['phone']    ?? [])),
        'ipad'     => array_keys(array_filter($pdfFields['ipad']     ?? [])),
        'sim'      => array_keys(array_filter($pdfFields['sim']      ?? [])),
    ], JSON_UNESCAPED_UNICODE);

    $kstExtract = $kstFb
        ? "(u.custom_fields&&(u.custom_fields['{$kstKey}']||u.custom_fields['{$kstFb}']))?(u.custom_fields['{$kstKey}']?u.custom_fields['{$kstKey}'].value:u.custom_fields['{$kstFb}'].value):'\u2014'"
        : "(u.custom_fields&&u.custom_fields['{$kstKey}'])?u.custom_fields['{$kstKey}'].value:'\u2014'";

    $imeiExtract = "function imei_get(a){var cf=a.custom_fields||{};var x=cf['{$imeiKey}']".($imeiFb?"||cf['{$imeiFb}']":'').";return x&&x.value?x.value:'\u2014';}";

    $noteJs   = $note   ? "html+='<div class=\"nb\">'+esc('{$note}')+'</div>';"   : '';
    $footerJs = $footer ? "html+='<div class=\"df\">'+esc('{$footer}')+'</div>';" : '';
    $docTitleEsc = htmlspecialchars($docTitle, ENT_QUOTES);

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$docTitleEsc}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/4.1.7/signature_pad.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;background:#e8eaf0;min-height:100vh;padding:20px}
/* Ladeanimation */
#ls{display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:14px}
.sp{width:38px;height:38px;border:4px solid #ddd;border-top-color:#1a3a6b;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.lm{font-size:13px;color:#555}
/* Toolbar Drucken */
#tb{display:none;position:fixed;top:0;left:0;right:0;background:#1a1a2e;padding:8px 18px;z-index:100;gap:10px;align-items:center}
#tb .tt{font-size:15px;font-weight:600;color:white;flex:1}
.tbb{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer}
.tbb-p{background:#4f8ef7;color:white}.tbb-g{background:transparent;color:#aaa;border:1px solid #444}
/* A4 */
#aw{display:none;padding-top:52px}
.a4{background:white;width:794px;min-height:1123px;padding:40px 48px;margin:0 auto 20px;font-size:11px;color:#1a1a1a;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.dh{display:flex;justify-content:space-between;border-bottom:2px solid #1a1a2e;padding-bottom:12px;margin-bottom:16px}
.dt{font-size:18px;font-weight:700;color:#1a1a2e}
.eb{background:#f4f6fb;border:1px solid #dde2ee;border-radius:5px;padding:12px 14px;margin-bottom:16px}
.eg{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.il-l{font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:1px}
.il-v{font-size:12px;font-weight:600;color:#1a1a2e;border-bottom:1px solid #ccd;padding-bottom:2px;min-height:18px}
.ds{margin-bottom:14px}
.dh2{color:white;padding:6px 12px;border-radius:3px 3px 0 0;font-weight:700;font-size:10px;letter-spacing:.06em;text-transform:uppercase}
.dt2{width:100%;border-collapse:collapse;font-size:10px}
.dt2 th{padding:5px 8px;text-align:left;border:1px solid #dde;font-weight:600;color:#333;font-size:9px;text-transform:uppercase;background:#f0f2f8}
.dt2 td{padding:5px 8px;border:1px solid #dde;color:#1a1a2e}
.dt2 tr:nth-child(even) td{background:#f8f9fc}
.nb{background:#fff9e6;border:1px solid #f0d080;border-radius:3px;padding:8px 12px;margin:12px 0;font-size:9px;color:#555;line-height:1.5}
.sg{margin-top:22px;display:grid;grid-template-columns:1fr 1fr;gap:28px}
.sl{font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#444;margin-bottom:5px}
.sln{border-bottom:1px solid #333;height:32px;margin-bottom:5px}
.ss{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ss-l{font-size:8px;color:#888;border-bottom:1px solid #ccc;padding-bottom:1px}
.ss-v{height:14px}
.df{margin-top:20px;padding-top:8px;border-top:1px solid #ddd;font-size:8px;color:#888;text-align:center}
.nd{text-align:center;padding:16px;color:#888;font-style:italic}
.sig-img{max-width:280px;max-height:80px;display:block;margin-top:3px;border:1px solid #dde;border-radius:3px}
/* Auswahl-Panel */
#sel{display:none;min-height:100vh;padding:20px}
.sel-wrap{max-width:700px;margin:0 auto}
.sel-bar{background:#1a1a2e;border-radius:10px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.sel-info{flex:1}
.sel-title{font-size:14px;font-weight:600;color:white}
.sel-sub{font-size:11px;color:#888;margin-top:2px}
.btn-x{background:transparent;border:1px solid #444;color:#aaa;padding:7px 13px;border-radius:5px;cursor:pointer;font-size:12px;font-weight:600}
.btn-sign{background:#5b3a8f;border:none;color:white;padding:8px 16px;border-radius:5px;cursor:pointer;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px}
.btn-print{background:#b85c00;border:none;color:white;padding:8px 16px;border-radius:5px;cursor:pointer;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px}
.sel-card{background:white;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.1);overflow:hidden}
.sel-card-hdr{background:#2a3a5b;color:white;padding:11px 16px;display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600}
.sel-card-body{padding:16px}
/* Signatur-Modal */
#sig-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:center;justify-content:center;padding:20px}
.sig-modal-inner{background:white;border-radius:12px;max-width:520px;width:100%;padding:24px;display:flex;flex-direction:column;gap:14px}
.sig-modal-title{font-size:16px;font-weight:700;color:#1a1a2e;display:flex;align-items:center;gap:8px}
.sig-wrap{position:relative;border:2px dashed #ccd;border-radius:8px;background:#fafafa;overflow:hidden;touch-action:none}
.sig-wrap canvas{display:block;width:100%;height:180px}
.sig-hint{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:13px;color:#bbb;pointer-events:none;text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px}
.sig-hint i{font-size:26px}
.sig-clear{position:absolute;top:8px;right:8px;background:rgba(255,255,255,.9);border:1px solid #ddd;border-radius:5px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;color:#666}
.sig-clear:hover{color:#e05555;border-color:#e05555}
.sig-modal-btns{display:flex;gap:10px;justify-content:flex-end}
.btn-annul{background:none;border:1px solid #dde;color:#666;padding:9px 18px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600}
.btn-confirm{background:#1a3a6b;border:none;color:white;padding:9px 20px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;display:flex;align-items:center;gap:7px}
/* Upload-Toast */
#toast{display:none;position:fixed;bottom:16px;right:16px;background:#1a1a2e;color:white;padding:10px 16px;border-radius:8px;font-size:12px;z-index:300;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,.3)}
@media print{body{background:white;padding:0}#tb{display:none!important}#aw{padding-top:0!important}#sel,#sig-modal,#toast{display:none!important}.a4{box-shadow:none;margin:0}@page{size:A4;margin:0}}
</style>
</head>
<body>

<div id="ls"><div class="sp"></div><div class="lm" id="lm">Lade Benutzerdaten...</div></div>

<!-- Auswahl-Panel -->
<div id="sel">
<div class="sel-wrap">
  <!-- Toolbar mit zwei Aktionen -->
  <div class="sel-bar">
    <div class="sel-info">
      <div class="sel-title"><i class="fa-solid fa-rotate-left" style="color:#b85c00;margin-right:7px"></i>{$docTitleEsc}</div>
      <div class="sel-sub" id="sel-user-info">—</div>
    </div>
    <button class="btn-x" onclick="window.close()"><i class="fa-solid fa-xmark"></i> Schließen</button>
    <button class="btn-sign" onclick="signaturOeffnen()"><i class="fa-solid fa-pen-nib"></i> Digital signieren</button>
    <button class="btn-print" onclick="drucken(null)"><i class="fa-solid fa-print"></i> Drucken</button>
  </div>

  <!-- Asset-Auswahl -->
  <div class="sel-card">
    <div class="sel-card-hdr">
      <i class="fa-solid fa-laptop"></i><span style="flex:1">Assets auswählen</span>
      <button onclick="selAll(true)"  style="background:rgba(255,255,255,.15);border:none;color:white;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer">Alle</button>
      <button onclick="selAll(false)" style="background:rgba(255,255,255,.15);border:none;color:white;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px">Keine</button>
      <span id="sel-count" style="font-size:11px;color:#aac;margin-left:8px;min-width:70px;text-align:right"></span>
    </div>
    <div class="sel-card-body">
      <div id="sel-list"></div>
      <div id="sel-empty" style="display:none;text-align:center;padding:16px;color:#888;font-size:13px">Keine Geräte der konfigurierten Kategorien gefunden.</div>
    </div>
  </div>

  <!-- Zubehör-Auswahl (wird angezeigt wenn Accessories vorhanden) -->
  <div class="sel-card" id="acc-wrap" style="display:none">
    <div class="sel-card-hdr" style="background:#4a5568">
      <i class="fa-solid fa-box-open"></i>
      <span style="flex:1">Zubehör <span style="font-weight:400;font-size:10px;opacity:.7">ausgecheckt beim Mitarbeiter</span></span>
    </div>
    <div>
      <div id="acc-list"></div>
      <div id="acc-empty" style="display:none;padding:14px;text-align:center;color:#888;font-size:13px">Kein Zubehör ausgecheckt.</div>
    </div>
  </div>
</div>
</div>

<!-- Signatur-Modal (erscheint über dem Panel) -->
<div id="sig-modal">
  <div class="sig-modal-inner">
    <div class="sig-modal-title">
      <i class="fa-solid fa-pen-nib" style="color:#5b3a8f"></i>
      Digitale Signatur
    </div>
    <p style="font-size:12px;color:#666">Unterschrift wird zu jedem ausgewählten Asset in SnipeIT hochgeladen und im Dokument abgedruckt.</p>
    <div class="sig-wrap">
      <canvas id="sig-canvas"></canvas>
      <div class="sig-hint" id="sig-hint">
        <i class="fa-solid fa-pen-fancy"></i>
        <span>Hier unterschreiben</span>
      </div>
      <button class="sig-clear" onclick="signaturLeeren()"><i class="fa-solid fa-rotate-left"></i> Leeren</button>
    </div>
    <div id="sig-warn" style="display:none;background:#fff3cd;border:1px solid #ffc107;border-radius:5px;padding:8px 12px;font-size:12px;color:#856404">
      <i class="fa-solid fa-circle-exclamation"></i> Bitte zuerst unterschreiben.
    </div>
    <div class="sig-modal-btns">
      <button class="btn-annul" onclick="signaturSchliessen()">Abbrechen</button>
      <button class="btn-confirm" onclick="signaturBestaetigen()">
        <i class="fa-solid fa-circle-check"></i> Bestätigen &amp; Drucken
      </button>
    </div>
  </div>
</div>

<!-- Druck-Toolbar -->
<div id="tb">
  <span class="tt"><i class="fa-solid fa-file-pdf" style="margin-right:6px"></i>{$docTitleEsc}</span>
  <button class="tbb tbb-g" onclick="zurueck()"><i class="fa-solid fa-arrow-left"></i> Zurück</button>
  <button class="tbb tbb-g" onclick="window.close()"><i class="fa-solid fa-xmark"></i> Schließen</button>
  <button class="tbb tbb-p" onclick="window.print()"><i class="fa-solid fa-print"></i> Drucken / Als PDF</button>
</div>
<div id="aw"><div class="a4" id="doc"></div></div>

<!-- Upload-Toast -->
<div id="toast">
  <div class="sp" style="width:16px;height:16px;border-width:2px;flex-shrink:0"></div>
  <span id="toast-msg">Signatur wird hochgeladen...</span>
</div>

<script>
(function(){
  var CAT    = {$catConfig};
  var FIELDS = {$fieldConfig};
  var FL     = {asset_tag:'Asset-Tag',asset_name:'Asset-Name/Rufnummer',model:'Modell',serial:'Seriennummer',manufacturer:'Hersteller',imei:'IMEI',purchase_date:'Kaufdatum',warranty:'Garantie bis'};
  {$imeiExtract}

  var ALLE=[], ALLE_ACC=[], BENUTZER={}, selIds=new Set(), selAccIds=new Set(), pad=null;

  function esc(s){return s?String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'):''}
  function gf(a,k){
    if(k==='imei')return imei_get(a);
    if(k==='asset_tag')return a.asset_tag||'\u2014';
    if(k==='asset_name')return a.name||'\u2014';
    if(k==='model')return a.model&&a.model.name?a.model.name:'\u2014';
    if(k==='serial')return a.serial||'\u2014';
    if(k==='manufacturer')return a.manufacturer&&a.manufacturer.name?a.manufacturer.name:'\u2014';
    if(k==='purchase_date')return a.purchase_date&&(a.purchase_date.formatted||a.purchase_date.date)||'\u2014';
    if(k==='warranty')return a.warranty_expires&&(a.warranty_expires.date||a.warranty_expires)||'\u2014';
    return '\u2014';
  }
  function iF(l,v){return '<div><div class="il-l">'+esc(l)+'</div><div class="il-v">'+esc(v)+'</div></div>';}
  function sB(l,sigUrl){
    var inner = sigUrl
      ? '<img class="sig-img" src="'+sigUrl+'" alt="Unterschrift">'
      : '<div class="sln"></div>';
    return '<div><div class="sl">'+l+'</div>'+inner+
      '<div class="ss"><div><div class="ss-l">Datum</div><div class="ss-v"></div></div>'+
      '<div><div class="ss-l">Stempel/Name</div><div class="ss-v"></div></div></div></div>';
  }

  // ── Asset-Auswahl aufbauen ───────────────────────────────────
  function aufbauen(u, assets, accessories){
    BENUTZER=u; ALLE=assets; ALLE_ACC=accessories||[]; selIds=new Set(); selAccIds=new Set();
    document.getElementById('sel-user-info').textContent=
      u.name+' · '+(u.department&&u.department.name?u.department.name:u.department||'');
    var liste=document.getElementById('sel-list'), leer=document.getElementById('sel-empty');
    var gruppen={}, vorhanden=false;
    assets.forEach(function(a){
      var cid=a.category&&a.category.id?a.category.id:0;
      var inKat=Object.values(CAT).some(function(c){return c.enabled&&cid===c.id;});
      if(!inKat)return;
      vorhanden=true;
      var cn=a.category&&a.category.name?a.category.name:'Sonstiges';
      var farbe='#1a3a6b';
      Object.values(CAT).forEach(function(c){if(c.enabled&&cid===c.id)farbe=c.color;});
      if(!gruppen[cn])gruppen[cn]={farbe:farbe,items:[]};
      gruppen[cn].items.push(a);
      selIds.add(a.id);
    });
    if(!vorhanden){leer.style.display='block';liste.innerHTML='';zaehler();accAufbauen();return;}
    var html='';
    Object.keys(gruppen).forEach(function(gn){
      var g=gruppen[gn];
      html+='<div style="margin-bottom:12px">';
      html+='<div style="background:'+g.farbe+';color:white;padding:5px 12px;border-radius:4px 4px 0 0;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">'+esc(gn)+'</div>';
      html+='<div style="border:1px solid #dde;border-top:none;border-radius:0 0 4px 4px;overflow:hidden">';
      g.items.forEach(function(a,ai){
        var sub=(a.model&&a.model.name?a.model.name:'')+' · '+esc(a.serial||'');
        html+='<label style="display:flex;align-items:center;gap:12px;padding:10px 14px;cursor:pointer;background:'+(ai%2?'#f8f9fc':'white')+';border-bottom:1px solid #eee">'+
          '<input type="checkbox" id="ck-'+a.id+'" checked onchange="umschalten('+a.id+')" style="width:15px;height:15px;accent-color:'+g.farbe+';cursor:pointer;flex-shrink:0">'+
          '<div style="flex:1;min-width:0">'+
            '<div style="font-size:12px;font-weight:700;font-family:monospace;color:#1a3a6b">'+esc(a.asset_tag||'')+'</div>'+
            '<div style="font-size:12px;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(a.name||'')+'</div>'+
            '<div style="font-size:11px;color:#888">'+sub+'</div>'+
          '</div></label>';
      });
      html+='</div></div>';
    });
    liste.innerHTML=html; zaehler();
    accAufbauen();
  }

  // ── Accessories-Auswahl aufbauen ─────────────────────────────────
  function accAufbauen(){
    var wrap=document.getElementById('acc-wrap');
    var liste=document.getElementById('acc-list');
    var leer=document.getElementById('acc-empty');
    if(!ALLE_ACC.length){
      leer.style.display='block';
      wrap.style.display='block';
      return;
    }
    wrap.style.display='block';
    var html='';
    ALLE_ACC.forEach(function(acc,ai){
      var name=acc.name||'';
      var cat=acc.category&&acc.category.name?acc.category.name:'';
      var qty=(acc.pivot&&acc.pivot.qty)?acc.pivot.qty:(acc.pivot&&acc.pivot.assigned_qty)?acc.pivot.assigned_qty:1;
      selAccIds.add(acc.id);
      html+='<label style="display:flex;align-items:center;gap:12px;padding:9px 14px;cursor:pointer;background:'+(ai%2?'#f8f9fc':'white')+';border-bottom:1px solid #eee">'+
        '<input type="checkbox" id="acc-'+acc.id+'" checked onchange="accUmschalten('+acc.id+')" style="width:15px;height:15px;accent-color:#4a5568;cursor:pointer;flex-shrink:0">'+
        '<div style="flex:1;min-width:0">'+
          '<div style="font-size:12px;font-weight:600;color:#333">'+esc(name)+'</div>'+
          (cat?'<div style="font-size:11px;color:#888">'+esc(cat)+'</div>':'')+
        '</div>'+
        (qty>1?'<span style="font-size:11px;background:#e8eaf0;padding:2px 8px;border-radius:10px;color:#555">'+qty+'×</span>':'')+
      '</label>';
    });
    liste.innerHTML=html;
  }

  window.umschalten=function(id){
    var ck=document.getElementById('ck-'+id);
    if(selIds.has(id)){selIds.delete(id);if(ck)ck.checked=false;}
    else{selIds.add(id);if(ck)ck.checked=true;}
    zaehler();
  };
  window.accUmschalten=function(id){
    var ck=document.getElementById('acc-'+id);
    if(selAccIds.has(id)){selAccIds.delete(id);if(ck)ck.checked=false;}
    else{selAccIds.add(id);if(ck)ck.checked=true;}
  };
  window.selAll=function(s){
    ALLE.forEach(function(a){
      var ck=document.getElementById('ck-'+a.id);if(!ck)return;
      if(s){selIds.add(a.id);ck.checked=true;}else{selIds.delete(a.id);ck.checked=false;}
    });
    zaehler();
  };
  function zaehler(){
    var el=document.getElementById('sel-count');
    if(el)el.textContent=selIds.size+' ausgewählt';
  }

  // ── Signature-Modal ───────────────────────────────────────────
  window.signaturOeffnen=function(){
    var gewaehlte=ALLE.filter(function(a){return selIds.has(a.id);});
    if(!gewaehlte.length){alert('Bitte mindestens ein Gerät auswählen.');return;}
    document.getElementById('sig-warn').style.display='none';
    document.getElementById('sig-modal').style.display='flex';
    // Pad initialisieren (einmalig)
    if(!pad){
      var canvas=document.getElementById('sig-canvas');
      var hint=document.getElementById('sig-hint');
      function groesse(){
        var r=Math.max(window.devicePixelRatio||1,1);
        canvas.width=canvas.offsetWidth*r; canvas.height=canvas.offsetHeight*r;
        canvas.getContext('2d').scale(r,r); if(pad)pad.clear();
      }
      pad=new SignaturePad(canvas,{penColor:'#1a1a2e',minWidth:1.5,maxWidth:3});
      pad.addEventListener('beginStroke',function(){hint.style.display='none';});
      window.addEventListener('resize',groesse);
      groesse();
    }
  };
  window.signaturSchliessen=function(){
    document.getElementById('sig-modal').style.display='none';
  };
  window.signaturLeeren=function(){
    if(pad)pad.clear();
    document.getElementById('sig-hint').style.display='flex';
  };
  window.signaturBestaetigen=function(){
    if(!pad||pad.isEmpty()){
      document.getElementById('sig-warn').style.display='block';return;
    }
    var sigUrl=pad.toDataURL('image/png');
    document.getElementById('sig-modal').style.display='none';
    drucken(sigUrl);
    hochladen(sigUrl);
  };

  // ── Printen (mit oder ohne Signature) ─────────────────────────
  window.drucken=function(sigUrl){
    var gewaehlte=ALLE.filter(function(a){return selIds.has(a.id);});
    if(!gewaehlte.length){alert('Bitte mindestens ein Gerät auswählen.');return;}
    var gewaehlteAcc=ALLE_ACC.filter(function(a){return selAccIds.has(a.id);});
    rendern(BENUTZER, gewaehlte, gewaehlteAcc, sigUrl);
  };

  window.zurueck=function(){
    document.getElementById('aw').style.display='none';
    document.getElementById('tb').style.display='none';
    document.getElementById('sel').style.display='block';
  };

  // ── A4 rendern ───────────────────────────────────────────────
  function rendern(u, assets, accessories, sigUrl){
    var today=new Date().toLocaleDateString('de-AT',{day:'2-digit',month:'2-digit',year:'numeric'});
    var dept=u.department&&u.department.name?u.department.name:(u.department||'\u2014');
    var kst=({$kstExtract});
    var html='';
    html+='<div class="dh"><div><div class="dt">{$docTitle}</div><div style="font-size:10px;color:#666">{$company}</div></div><div style="text-align:right;font-size:10px"><div style="color:#b85c00;font-weight:700;letter-spacing:.08em;margin-bottom:3px">R\u00dcCKNAHME</div><div style="color:#444">Datum: <b>'+today+'</b></div></div></div>';
    html+='<div class="eb"><div style="font-weight:700;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#444;margin-bottom:6px">Mitarbeiter</div><div class="eg">'+iF('Name',u.name)+iF('Abteilung',dept)+iF('Kostenstelle',kst)+'</div></div>';
    // Assets
    var grouped={notebook:[],phone:[],ipad:[],sim:[]};
    assets.forEach(function(a){var cid=a.category&&a.category.id?a.category.id:0;Object.keys(CAT).forEach(function(c){if(cid===CAT[c].id)grouped[c].push(a);});});
    var any=false;
    ['notebook','phone','ipad','sim'].forEach(function(cat){
      if(!CAT[cat].enabled)return;var flds=FIELDS[cat]||[];if(!flds.length)return;var items=grouped[cat];if(!items.length)return;any=true;
      html+='<div class="ds"><div class="dh2" style="background:'+CAT[cat].color+'">'+CAT[cat].label+'</div><table class="dt2"><thead><tr>';
      flds.forEach(function(k){html+='<th>'+esc(FL[k]||k)+'</th>';});
      html+='<th>Zustand</th><th style="width:22px">\u2713</th></tr></thead><tbody>';
      items.forEach(function(a){html+='<tr>';flds.forEach(function(k){html+='<td>'+esc(gf(a,k))+'</td>';});html+='<td style="font-size:9px;color:#888">☐ Gut&nbsp;&nbsp;☐ Beschädigt&nbsp;&nbsp;☐ Defekt</td><td></td></tr>';});
      html+='</tbody></table></div>';
    });
    if(!any)html+='<div class="nd">Keine Geräte ausgewählt / in den konfigurierten Kategorien.</div>';
    // Accessories-Section
    if(accessories&&accessories.length){
      html+='<div class="ds" style="margin-top:6px">';
      html+='<div class="dh2" style="background:#4a5568">Zubeh\u00f6r</div>';
      html+='<table class="dt2"><thead><tr>';
      html+='<th>Bezeichnung</th><th>Kategorie</th><th style="width:50px">Anzahl</th><th style="width:22px">\u2713</th>';
      html+='</tr></thead><tbody>';
      accessories.forEach(function(acc,i){
        var qty=(acc.pivot&&acc.pivot.qty)?acc.pivot.qty:(acc.pivot&&acc.pivot.assigned_qty)?acc.pivot.assigned_qty:1;
        var cat=acc.category&&acc.category.name?acc.category.name:'';
        html+='<tr'+(i%2?' style="background:#f8f9fc"':'')+'>';
        html+='<td>'+esc(acc.name||'')+'</td>';
        html+='<td>'+esc(cat)+'</td>';
        html+='<td style="text-align:center">'+qty+'</td>';
        html+='<td></td>';
        html+='</tr>';
      });
      html+='</tbody></table></div>';
    }
    {$noteJs}
    html+='<div style="margin:10px 0"><div class="dh2" style="background:#4a5568;border-radius:3px 3px 0 0">Bemerkungen / Sch\u00e4den</div><div style="border:1px solid #dde;border-top:none;min-height:44px;padding:6px 10px;font-size:10px;color:#aaa;font-style:italic">Freitext / Bemerkungen zur R\u00fccknahme</div></div>';
    html+='<div class="sg">'+sB('Mitarbeiter (Rückgabe)',sigUrl)+sB('IT-Abteilung (Rücknahme)',null)+'</div>';
    {$footerJs}
    document.getElementById('doc').innerHTML=html;
    document.getElementById('sel').style.display='none';
    document.getElementById('ls').style.display='none';
    document.getElementById('tb').style.display='flex';
    document.getElementById('aw').style.display='block';
    setTimeout(function(){window.print();},700);
  }

  // ── Upload im Hintergrund ────────────────────────────────────
  function hochladen(sigUrl){
    var toast=document.getElementById('toast');
    var msg=document.getElementById('toast-msg');
    toast.style.display='flex';
    var gewaehlte=ALLE.filter(function(a){return selIds.has(a.id);});
    var u=BENUTZER;
    var cf=u.custom_fields||{};
    var kst=({$kstExtract});
    var dept=u.department&&u.department.name?u.department.name:(u.department||'');
    fetch('/api/sign/submit',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        user_id:u.id, user_name:u.name||'', user_dept:dept,
        user_kst:kst!=='\u2014'?kst:'',
        signature:sigUrl,
        assets:gewaehlte.map(function(a){return{
          id:a.id,tag:a.asset_tag||'',name:a.name||'',
          model:a.model&&a.model.name?a.model.name:'',
          serial:a.serial||'',category:a.category&&a.category.name?a.category.name:'',
          imei:imei_get(a)!=='\u2014'?imei_get(a):'',
        };})
      })
    })
    .then(function(r){return r.json();})
    .then(function(res){
      var n=res.ok?(res.data.uploaded_count||0):0;
      msg.innerHTML='<i class="fa-solid '+(res.ok?'fa-circle-check" style="color:#27ae60':'fa-triangle-exclamation" style="color:#f0a040')+'"></i> '+
        (res.ok?(res.data.user_uploaded?'Mitarbeiter-Akte ✓ · ':'')+res.data.uploaded_count+' Asset'+(res.data.uploaded_count!==1?'s':'')+' ✓':'Fehler: '+(res.error||'Upload fehlgeschlagen'));
      toast.querySelector('.sp').style.display='none';
      setTimeout(function(){toast.style.display='none';},4000);
    })
    .catch(function(){
      msg.textContent='Upload fehlgeschlagen';
      setTimeout(function(){toast.style.display='none';},3000);
    });
  }

  // ── Daten laden ──────────────────────────────────────────────
  var params=new URLSearchParams(location.search);
  var uid=params.get('user_id');
  var lm=document.getElementById('lm');
  if(!uid){lm.textContent='Fehler: Keine user_id in URL.';return;}

  Promise.all([
    fetch('/api/proxy/users/'+uid).then(function(r){if(!r.ok)throw new Error('User HTTP '+r.status);return r.json();}),
    fetch('/api/proxy/users/'+uid+'/assets').then(function(r){if(!r.ok)throw new Error('Assets HTTP '+r.status);return r.json();}),
    fetch('/api/proxy/users/'+uid+'/accessories').then(function(r){return r.ok?r.json():[];}).catch(function(){return[];})
  ]).then(function(res){
    var u=res[0];var ar=res[1];var acc=res[2];
    var assets=Array.isArray(ar)?ar:(ar.rows||[]);
    var accessories=Array.isArray(acc)?acc:(acc.rows||[]);
    if(!u.id)throw new Error('Benutzer nicht gefunden.');
    document.getElementById('ls').style.display='none';
    document.getElementById('sel').style.display='block';
    aufbauen(u,assets,accessories);
  }).catch(function(e){
    lm.textContent='Fehler: '+e.message;
    document.querySelector('.sp').style.display='none';
  });
})();
</script>
</body>
</html>
HTML;
}

function generate_ruecknahme_runner(): string { return ruecknahme_runner_generieren(); }

