<?php
// ============================================================
//  IT-Tools — AirWatch Search Runner
//  Version  : 1.0.0
//  Modified : 2026-05-19
//  Author   :  Chris M.
//
//  Generiert airwatch-search.html:
//    Bookmarklet auf /hardware/{id} — öffnet Popup mit
//    AirWatch-Deviceedaten zum aktuellen SnipeIT Asset.
// ============================================================

function airwatch_runner_html(): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AirWatch Gerät</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,Arial,sans-serif;background:#0f172a;color:#e2e8f0;font-size:13px;padding:18px;min-height:100vh}
.hdr{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #1e293b}
.hdr i{font-size:20px;color:#38bdf8}
.hdr h1{font-size:15px;font-weight:600;color:white}
.hdr-sub{font-size:11px;color:#64748b;margin-top:1px}
.card{background:#1e293b;border-radius:8px;padding:14px;margin-bottom:12px}
.card-t{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#475569;margin-bottom:10px}
.row{display:grid;grid-template-columns:130px 1fr;gap:4px;padding:5px 0;border-bottom:1px solid #0f172a}
.row:last-child{border-bottom:none}
.lbl{font-size:11px;color:#64748b}
.val{font-size:12px;color:#e2e8f0;font-weight:500;word-break:break-all}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.badge-ok{background:#0f3a1f;color:#34d399}
.badge-warn{background:#3a2a0f;color:#fbbf24}
.badge-err{background:#3a0f0f;color:#f87171}
.sp{width:24px;height:24px;border:3px solid #334155;border-top-color:#38bdf8;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto}
@keyframes spin{to{transform:rotate(360deg)}}
.center{text-align:center;padding:28px;color:#475569}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer}
.btn-blue{background:#0369a1;color:white}.btn-blue:hover{background:#0284c7}
</style>
</head>
<body>
<div class="hdr">
  <i class="fa-solid fa-mobile-screen-button"></i>
  <div>
    <div class="h1">AirWatch MDM</div>
    <div class="hdr-sub" id="asset-info">Lade Asset-Daten...</div>
  </div>
</div>
<div id="content"><div class="center"><div class="sp"></div><div style="margin-top:10px;font-size:12px">Suche in AirWatch...</div></div></div>
<script>
(function(){
  var sn  = new URLSearchParams(location.search).get('serial');
  var tag = new URLSearchParams(location.search).get('tag');
  var info = document.getElementById('asset-info');
  var out  = document.getElementById('content');

  if(info && tag) info.textContent = 'Asset-Tag: ' + tag;

  if(!sn){ out.innerHTML='<div class="center" style="color:#f87171"><i class="fa-solid fa-circle-xmark"></i><br><br>Keine Seriennummer verfügbar.<br>Das Asset hat möglicherweise keine S/N in SnipeIT.</div>'; return; }

  fetch('/api/airwatch/search?serial='+encodeURIComponent(sn))
    .then(function(r){return r.text();})
    .then(function(t){
      var s=t.indexOf('{');if(s>0)t=t.substring(s);
      var d=JSON.parse(t);
      if(!d.ok){ out.innerHTML='<div class="center" style="color:#f87171"><i class="fa-solid fa-circle-xmark"></i><br><br>Fehler: '+esc(d.error||'Unbekannt')+'</div>'; return; }
      if(!d.data.found||!d.data.devices.length){ out.innerHTML='<div class="center"><i class="fa-solid fa-circle-question" style="font-size:24px;color:#475569"></i><br><br>Gerät nicht in AirWatch gefunden.<br><small style="color:#475569">S/N: '+esc(sn)+'</small></div>'; return; }
      var dev=d.data.devices[0];
      var html='';
      // Status-Badge
      var enrolled=dev.EnrollmentStatus||dev.enrollment_status||'';
      var badgeCls=enrolled==='Enrolled'?'badge-ok':(enrolled?'badge-warn':'badge-err');
      html+='<div class="card"><div class="card-t">MDM Status</div>';
      html+=field('Status','<span class="badge '+badgeCls+'"><i class="fa-solid fa-'+(enrolled==='Enrolled'?'check':'exclamation')+'"></i>'+esc(enrolled||'Unbekannt')+'</span>');
      html+=field('Gerätename',dev.DeviceFriendlyName||dev.friendly_name||'—');
      html+=field('Zuletzt online',dev.LastSeen||dev.last_seen||'—');
      html+='</div>';
      html+='<div class="card"><div class="card-t">Gerät</div>';
      html+=field('Modell',dev.Model||dev.model||'—');
      html+=field('Betriebssystem',(dev.OperatingSystem||dev.os||'')+(dev.OSBuildVersion?' ('+dev.OSBuildVersion+')':''));
      html+=field('Seriennummer',dev.SerialNumber||dev.serial_number||sn);
      html+=field('IMEI',dev.Imei||dev.imei||'—');
      html+='</div>';
      if(dev.LocationGroupName||dev.location_group_name){
        html+='<div class="card"><div class="card-t">Organisation</div>';
        html+=field('Standort-Gruppe',dev.LocationGroupName||dev.location_group_name||'—');
        html+=field('Zugewiesen',dev.UserEmailAddress||dev.user_email||dev.UserName||'—');
        html+='</div>';
      }
      out.innerHTML=html;
    })
    .catch(function(e){ out.innerHTML='<div class="center" style="color:#f87171">Verbindungsfehler: '+esc(e.message)+'</div>'; });

  function field(l,v){ return '<div class="row"><div class="lbl">'+esc(l)+'</div><div class="val">'+v+'</div></div>'; }
  function esc(s){ return s?String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'):''; }
})();
</script>
</body>
</html>
HTML;
}
