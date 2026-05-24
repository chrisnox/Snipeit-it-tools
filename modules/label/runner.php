<?php
// ============================================================
//  IT-Tools — Label Runner
//  Version  : 3.0.0
//  Modified : 2026-05-20
//  Author   :  Chris M.
//
//  Generiert label-print.html
//  Ablauf: Alle Accessories laden → Categoryn ableiten →
//          Category wählen → Accessories + Menge wählen → Printen
// ============================================================

function label_runner_html(): string
{
    $cfg    = abschnitt_lesen('label');
    $copies = intval($cfg['copies'] ?? 1);

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Label drucken</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,Arial,sans-serif;background:#1a1a2e;min-height:100vh;padding:16px;font-size:13px;color:#e2e8f0}
.wrap{max-width:640px;margin:0 auto;display:flex;flex-direction:column;gap:12px}
/* Header */
.hdr{display:flex;align-items:center;gap:10px;padding-bottom:12px;border-bottom:1px solid #2d3748}
.hdr i{font-size:20px;color:#38bdf8}
.hdr h1{font-size:15px;font-weight:700;color:white}
.hdr-sub{font-size:11px;color:#64748b;margin-top:1px}
/* Cards */
.card{background:#0f172a;border:1px solid #1e293b;border-radius:10px;overflow:hidden}
.card-hdr{padding:11px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #1e293b;font-size:12px;font-weight:600;color:#94a3b8}
/* Filter */
.filter-row{padding:12px 16px;display:flex;gap:10px;align-items:center}
.filter-row select{flex:1;background:#1e293b;border:1px solid #2d3748;color:#e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px}
.filter-row select option{background:#1e293b}
/* Liste */
#acc-list{max-height:380px;overflow-y:auto}
.acc-row{display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid #1e293b;transition:background .1s}
.acc-row:last-child{border-bottom:none}
.acc-row:hover{background:#1e293b}
.acc-row.disabled{opacity:.4;pointer-events:none}
.acc-check{width:16px;height:16px;accent-color:#38bdf8;cursor:pointer;flex-shrink:0}
.acc-info{flex:1;min-width:0}
.acc-name{font-size:13px;font-weight:600;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acc-sub{font-size:11px;color:#64748b;margin-top:2px}
.acc-avail{font-size:11px;padding:2px 7px;border-radius:10px;flex-shrink:0}
.avail-ok{background:#0f3a1f;color:#34d399}
.avail-zero{background:#3a1a00;color:#fbbf24}
/* Mengen-Eingabe */
.qty-wrap{display:flex;align-items:center;gap:4px;flex-shrink:0}
.qty-btn{width:26px;height:26px;border:1px solid #2d3748;border-radius:4px;background:#1e293b;color:#e2e8f0;cursor:pointer;font-size:15px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.qty-btn:hover:not(:disabled){background:#2d3748}
.qty-btn:disabled{opacity:.3;cursor:not-allowed}
.qty-inp{width:40px;text-align:center;border:1px solid #2d3748;border-radius:4px;background:#1e293b;color:#e2e8f0;padding:3px 0;font-size:13px;font-weight:600}
/* Druck-Leiste */
.print-bar{background:#0369a1;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px}
.print-info{flex:1;color:white;font-size:13px;font-weight:600}
.print-info small{font-weight:400;opacity:.8;font-size:11px;margin-left:6px}
.btn{padding:8px 20px;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-print{background:white;color:#0369a1}.btn-print:hover{background:#e0f2fe}
.btn-print:disabled{opacity:.5;cursor:not-allowed}
/* Spinner */
.sp{width:20px;height:20px;border:3px solid #1e293b;border-top-color:#38bdf8;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.loading{display:flex;align-items:center;justify-content:center;gap:12px;padding:28px;color:#64748b;font-size:13px}
/* Toast */
#toast{display:none;position:fixed;bottom:16px;right:16px;border-radius:8px;padding:10px 16px;font-size:12px;z-index:200;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,.4);max-width:300px}
.toast-ok{background:#0f3a1f;color:#34d399}
.toast-err{background:#3a0f0f;color:#f87171}
.toast-info{background:#0f172a;color:#94a3b8;border:1px solid #1e293b}
/* Leer */
.empty{text-align:center;padding:28px;color:#475569;font-size:13px}
.empty i{font-size:24px;display:block;margin-bottom:8px;opacity:.4}
</style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <div class="hdr">
    <i class="fa-solid fa-tag"></i>
    <div>
      <h1>Label drucken — Zebra ZD410</h1>
      <div class="hdr-sub">50×25 mm · QR-Code · Bezeichnung · Kategorie · Datum</div>
    </div>
  </div>

  <!-- Kategorie-Filter -->
  <div class="card">
    <div class="card-hdr">
      <span><i class="fa-solid fa-filter" style="margin-right:6px"></i>Kategorie filtern</span>
      <button onclick="allesWaehlen(true)"  style="background:none;border:1px solid #2d3748;border-radius:4px;padding:3px 9px;font-size:11px;font-weight:600;cursor:pointer;color:#94a3b8;margin-right:4px">Alle</button>
      <button onclick="allesWaehlen(false)" style="background:none;border:1px solid #2d3748;border-radius:4px;padding:3px 9px;font-size:11px;font-weight:600;cursor:pointer;color:#94a3b8">Keine</button>
    </div>
    <div class="filter-row">
      <select id="cat-sel" onchange="filterKategorie()">
        <option value="">Lade Kategorien...</option>
      </select>
      <button onclick="ladeDaten()" style="background:#1e293b;border:1px solid #2d3748;border-radius:6px;padding:7px 12px;color:#94a3b8;font-size:12px;cursor:pointer"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
  </div>

  <!-- Accessories-Liste -->
  <div class="card" id="list-card">
    <div class="card-hdr">
      <span id="list-title"><i class="fa-solid fa-boxes-stacked" style="margin-right:6px"></i>Zubehör</span>
      <span id="sel-count" style="font-size:11px;color:#38bdf8"></span>
    </div>
    <div id="acc-list"><div class="loading"><div class="sp"></div><span>Lade Zubehör...</span></div></div>
  </div>

  <!-- Druckleiste -->
  <div id="print-bar" style="display:none" class="print-bar">
    <div class="print-info">
      <span id="print-sum">0 Etiketten</span>
      <small id="print-pos"></small>
    </div>
    <button id="btn-drucken" class="btn btn-print" onclick="drucken()">
      <i class="fa-solid fa-print"></i> Drucken
    </button>
  </div>

</div>

<!-- Toast -->
<div id="toast"><span id="toast-msg"></span></div>

<script>
(function(){
  var ALLE = [];        // alle Accessories
  var ANZEIGE = [];     // gefilterte Liste
  var SEL = {};         // {id: qty}

  // ── Daten laden ─────────────────────────────────────────────
  function ladeDaten(){
    document.getElementById('acc-list').innerHTML =
      '<div class="loading"><div class="sp"></div><span>Lade Zubehör...</span></div>';
    document.getElementById('cat-sel').innerHTML = '<option value="">Lade...</option>';
    document.getElementById('print-bar').style.display = 'none';
    SEL = {};

    fetch('/api/proxy/accessories?limit=500')
      .then(function(r){ return r.json(); })
      .then(function(d){
        ALLE = d.rows || d || [];
        bauKategorien();
        filterKategorie();
      })
      .catch(function(e){
        document.getElementById('acc-list').innerHTML =
          '<div class="empty"><i class="fa-solid fa-circle-xmark"></i>Fehler: '+esc(e.message)+'</div>';
      });
  }

  // ── Category-Dropdown aufbauen ──────────────────────────────
  function bauKategorien(){
    var cats = {};
    ALLE.forEach(function(a){
      var cn = a.category && a.category.name ? a.category.name : 'Ohne Kategorie';
      cats[cn] = (cats[cn]||0) + 1;
    });
    var sorted = Object.keys(cats).sort();
    var sel = document.getElementById('cat-sel');
    sel.innerHTML = '<option value="">Alle Kategorien ('+ALLE.length+')</option>';
    sorted.forEach(function(cn){
      var o = document.createElement('option');
      o.value = cn;
      o.textContent = cn + ' (' + cats[cn] + ')';
      sel.appendChild(o);
    });
  }

  // ── Liste nach Category filtern ────────────────────────────
  window.filterKategorie = function(){
    var cat = document.getElementById('cat-sel').value;
    ANZEIGE = cat ? ALLE.filter(function(a){
      return (a.category && a.category.name ? a.category.name : 'Ohne Kategorie') === cat;
    }) : ALLE.slice();
    SEL = {};
    bauListe();
  };

  // ── Liste rendern ────────────────────────────────────────────
  function bauListe(){
    var el = document.getElementById('acc-list');
    var ti = document.getElementById('list-title');
    ti.innerHTML = '<i class="fa-solid fa-boxes-stacked" style="margin-right:6px"></i>Zubehör <span style="font-weight:400;color:#64748b">('+ANZEIGE.length+')</span>';

    if(!ANZEIGE.length){
      el.innerHTML='<div class="empty"><i class="fa-solid fa-box-open"></i>Kein Zubehör in dieser Kategorie</div>';
      leiste(); return;
    }

    var html = '';
    ANZEIGE.forEach(function(a){
      var avail = Math.max(0, (a.qty||0) - (a.qty_checkedout||0));
      var zero  = avail === 0;
      html += '<div class="acc-row'+(zero?' disabled':'')+'">'+
        '<input type="checkbox" class="acc-check" id="ck-'+a.id+'"'+(zero?'':' checked')+
          ' onchange="toggle('+a.id+','+avail+')" style="accent-color:#38bdf8">'+
        '<div class="acc-info">'+
          '<div class="acc-name">'+esc(a.name||'')+'</div>'+
          '<div class="acc-sub">'+esc((a.category&&a.category.name)||'')+
            (a.model_number?' · '+esc(a.model_number):'')+'</div>'+
        '</div>'+
        '<span class="acc-avail '+(zero?'avail-zero':'avail-ok')+'">'+
          (zero?'Nicht verfügbar':'Verfügbar: '+avail)+
        '</span>'+
        (zero?'':
          '<div class="qty-wrap">'+
            '<button class="qty-btn" onclick="aendere('+a.id+',-1,'+avail+')" id="qb-m-'+a.id+'">−</button>'+
            '<input class="qty-inp" type="number" id="qty-'+a.id+'" value="1" min="1" max="'+avail+'"'+
              ' onchange="setQty('+a.id+',this.value,'+avail+')">'+
            '<button class="qty-btn" onclick="aendere('+a.id+',1,'+avail+')" id="qb-p-'+a.id+'">+</button>'+
          '</div>')+
        '</div>';
    });
    el.innerHTML = html;

    // Alle verfügbaren vorauswählen
    ANZEIGE.forEach(function(a){
      var avail = Math.max(0, (a.qty||0) - (a.qty_checkedout||0));
      if(avail > 0) SEL[a.id] = 1;
    });
    leiste();
  }

  // ── Toggle / Menge ───────────────────────────────────────────
  window.toggle = function(id, max){
    var ck = document.getElementById('ck-'+id);
    if(ck && ck.checked) SEL[id] = parseInt(document.getElementById('qty-'+id)?.value||1);
    else delete SEL[id];
    leiste();
  };
  window.aendere = function(id, d, max){
    var inp = document.getElementById('qty-'+id);
    if(!inp) return;
    var v = Math.max(1, Math.min(max, parseInt(inp.value||1)+d));
    inp.value = v;
    if(SEL[id] !== undefined){ SEL[id]=v; leiste(); }
  };
  window.setQty = function(id, val, max){
    var v = Math.max(1, Math.min(max, parseInt(val)||1));
    var inp = document.getElementById('qty-'+id);
    if(inp) inp.value = v;
    if(SEL[id] !== undefined){ SEL[id]=v; leiste(); }
  };
  window.allesWaehlen = function(s){
    ANZEIGE.forEach(function(a){
      var avail = Math.max(0,(a.qty||0)-(a.qty_checkedout||0));
      if(!avail) return;
      var ck=document.getElementById('ck-'+a.id);
      if(ck) ck.checked=s;
      if(s) SEL[a.id]=parseInt(document.getElementById('qty-'+a.id)?.value||1);
      else delete SEL[a.id];
    });
    leiste();
  };

  // ── Printleiste aktualisieren ────────────────────────────────
  function leiste(){
    var ids = Object.keys(SEL);
    var total = ids.reduce(function(s,id){ return s+SEL[id]; }, 0);
    var bar = document.getElementById('print-bar');
    var sum = document.getElementById('print-sum');
    var pos = document.getElementById('print-pos');
    var cnt = document.getElementById('sel-count');
    if(cnt) cnt.textContent = ids.length + ' ausgewählt';
    if(total > 0){
      bar.style.display = 'flex';
      sum.textContent   = total + ' Etikett'+(total!==1?'ten':'');
      pos.textContent   = '· '+ids.length+' Position'+(ids.length!==1?'en':'');
    } else {
      bar.style.display = 'none';
    }
    var btn = document.getElementById('btn-drucken');
    if(btn) btn.disabled = total === 0;
  }

  // ── Printen ──────────────────────────────────────────────────
  window.drucken = function(){
    var btn = document.getElementById('btn-drucken');
    if(btn) btn.disabled = true;
    toast('Sende an Drucker...', 'info');

    var items = Object.keys(SEL).map(function(id){
      var a = ALLE.find(function(x){ return String(x.id)===String(id); });
      return {
        id:       parseInt(id),
        name:     a ? (a.name||'') : '',
        category: a && a.category ? (a.category.name||'') : '',
        qty:      SEL[id],
      };
    });

    fetch('/api/label/print', {
      method:  'POST',
      headers: {'Content-Type':'application/json'},
      body:    JSON.stringify({items: items})
    })
    .then(function(r){ return r.text(); })
    .then(function(t){
      var s=t.indexOf('{'); if(s>0) t=t.substring(s);
      var d=JSON.parse(t);
      if(d.ok){
        toast(d.data.printed+' Etiketten gedruckt \u2713', 'ok');
      } else {
        toast(d.error||'Fehler', 'err');
      }
    })
    .catch(function(e){ toast('Netzwerkfehler: '+e.message, 'err'); })
    .finally(function(){ if(btn) btn.disabled=false; });
  };

  // ── Helpers ──────────────────────────────────────────────────
  function esc(s){ return s?String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'):''; }
  function toast(msg, typ){
    var el=document.getElementById('toast');
    var em=document.getElementById('toast-msg');
    em.textContent=msg;
    el.className='';
    el.classList.add('toast-'+typ);
    el.style.display='flex';
    clearTimeout(el._t);
    el._t=setTimeout(function(){el.style.display='none';},4000);
  }

  // Beim Öffnen sofort laden
  window.ladeDaten = ladeDaten;
  ladeDaten();
})();
</script>
</body>
</html>
HTML;
}
