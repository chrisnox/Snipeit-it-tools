<?php
// ============================================================
//  IT-Tools — Einstiegspunkt (Landing Page)
//  Version  : 2.0.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//
//  Wurzel-URL (/) liefert install.html (Employee-Page).
//  Admin-Oberfläche ist unter /admin erreichbar.
//  Falls install.html noch nicht generiert wurde → Weiterleitung zu /admin.
// ============================================================

$datei = __DIR__ . '/install.html';
if (file_exists($datei)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($datei);
} else {
    header('Location: /admin');
    exit;
}
