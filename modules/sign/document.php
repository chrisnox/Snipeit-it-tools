<?php
// ============================================================
//  IT-Tools — GD PNG Dokument-Generator
//  Version  : 2.0.0
//  Modified : 2026-05-06
//  Author   :  Chris M.
//  New v2.0:
//    - Output als PDF statt PNG (JPEG in minimaler PDF-Hülle)
//    - Modus-Parameter: Übergabe- oder Rücknahmeprotokoll im Header
//    - sign_jpeg_zu_pdf(): minimaler PDF-Generator ohne externe Bibliothek
//  modules/sign/document.php — GD PNG Dokument-Generator
// ============================================================

function sign_dokument_erstellen(array $p): string {
    $W = 794; $H = 1123;
    $img = imagecreatetruecolor($W, $H);

    $cWhite  = imagecolorallocate($img, 255, 255, 255);
    $cDark   = imagecolorallocate($img, 26,  26,  46);
    $cMid    = imagecolorallocate($img, 90,  95,  120);
    $cLight  = imagecolorallocate($img, 240, 242, 248);
    $cBlue   = imagecolorallocate($img, 26,  58,  107);
    $cBlueLt = imagecolorallocate($img, 180, 200, 240);
    $cBorder = imagecolorallocate($img, 200, 210, 230);
    $cRowAlt = imagecolorallocate($img, 248, 249, 252);

    imagefill($img, 0, 0, $cWhite);

    $fReg  = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    $fBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    $hasTTF = file_exists($fReg) && function_exists('imagettftext');

    $pad = 48; $y = 0;
    $date = $p['date'] ?? date('d.m.Y');

    // Header
    imagefilledrectangle($img, 0, 0, $W, 68, $cBlue);
    $istRuecknahme = ($p['modus'] ?? 'ausgabe') === 'ruecknahme';
    $docTitel      = $istRuecknahme ? 'Rücknahmebestätigung · Elektronische Signatur' : 'Übergabebestätigung · Elektronische Signatur';
    $footerTitel   = $istRuecknahme ? 'Elektronisches Rücknahmeprotokoll' : 'Elektronisches Übergabeprotokoll';

    tt($img, 15, $pad, 28, $cWhite,  $fBold, $hasTTF, $p['company'] ?? 'IT');
    tt($img, 10, $pad, 48, $cBlueLt, $fReg,  $hasTTF, $docTitel);
    tt($img, 9, $W-180, 28, $cBlueLt, $fReg, $hasTTF, 'Datum: ' . $date);
    $y = 84;

    // Employee
    imagefilledrectangle($img, $pad, $y, $W-$pad, $y+72, $cLight);
    imagerectangle(      $img, $pad, $y, $W-$pad, $y+72, $cBorder);
    tt($img, 8,  $pad+10, $y+16, $cMid,  $fBold, $hasTTF, 'MITARBEITER');
    tt($img, 13, $pad+10, $y+36, $cDark, $fBold, $hasTTF, $p['user_name'] ?? '');
    $sub = trim(($p['user_dept']??'') . ($p['user_kst'] ? ' · KST '.$p['user_kst'] : ''));
    tt($img, 10, $pad+10, $y+56, $cMid, $fReg, $hasTTF, $sub);
    $y += 84;

    // Devicee-Table
    tt($img, 8, $pad, $y+4, $cMid, $fBold, $hasTTF, 'ÜBERGEBENE GERÄTE');
    $y += 18;
    $cw = ($W - 2*$pad) / 4;
    $rowH = 22;
    imagefilledrectangle($img, $pad, $y, $W-$pad, $y+$rowH, $cBlue);
    foreach (['Asset-Tag','Bezeichnung','Modell','Seriennummer'] as $ci => $h) {
        tt($img, 9, $pad+8+intval($ci*$cw), $y+15, $cWhite, $fBold, $hasTTF, $h);
    }
    $y += $rowH;
    foreach (($p['assets']??[]) as $ai => $asset) {
        $bg = $ai%2===0 ? $cWhite : $cRowAlt;
        imagefilledrectangle($img, $pad, $y, $W-$pad, $y+$rowH, $bg);
        imagerectangle(      $img, $pad, $y, $W-$pad, $y+$rowH, $cBorder);
        for ($ci=1;$ci<4;$ci++) {
            $lx=$pad+intval($ci*$cw);
            imageline($img,$lx,$y,$lx,$y+$rowH,$cBorder);
        }
        foreach ([$asset['tag']??'',$asset['name']??'',$asset['model']??'',$asset['serial']??''] as $ci=>$v) {
            $t = mb_strlen($v)>22 ? mb_substr($v,0,20).'…' : $v;
            tt($img, 9, $pad+8+intval($ci*$cw), $y+15, $cDark, $fReg, $hasTTF, $t);
        }
        $y += $rowH;
    }
    $y += 24;

    // Bestätigungstext
    tt($img, 8, $pad, $y+4, $cMid, $fBold, $hasTTF, 'BESTÄTIGUNG');
    $y += 18;
    imagefilledrectangle($img, $pad, $y, $W-$pad, $y+2, $cBorder);
    $y += 12;
    foreach (explode("\n", wordwrap($p['confirm_text']??'', 90, "\n", true)) as $line) {
        tt($img, 10, $pad, $y+13, $cDark, $fReg, $hasTTF, $line);
        $y += 18;
    }
    $y += 20;

    // Signature-Canvas
    tt($img, 8, $pad, $y+4, $cMid, $fBold, $hasTTF, 'UNTERSCHRIFT MITARBEITER');
    $y += 18;
    $sigW=340; $sigH=130;
    imagefilledrectangle($img, $pad, $y, $pad+$sigW, $y+$sigH, $cLight);
    imagerectangle(      $img, $pad, $y, $pad+$sigW, $y+$sigH, $cBorder);
    if (!empty($p['signature_b64'])) {
        $sigImg = @imagecreatefromstring(base64_decode($p['signature_b64']));
        if ($sigImg) {
            $sw=imagesx($sigImg); $sh=imagesy($sigImg);
            $scale=min(($sigW-20)/max($sw,1),($sigH-20)/max($sh,1),1.0);
            $dw=intval($sw*$scale); $dh=intval($sh*$scale);
            imagecopyresampled($img,$sigImg,$pad+intval(($sigW-$dw)/2),$y+intval(($sigH-$dh)/2),0,0,$dw,$dh,$sw,$sh);
            imagedestroy($sigImg);
        }
    }
    $lineY = $y+$sigH+5;
    imageline($img,$pad,$lineY,$pad+$sigW,$lineY,$cDark);
    tt($img, 9, $pad, $lineY+14, $cMid, $fReg, $hasTTF, ($p['user_name']??'').', '.$date);

    // IT-Box rechts
    $itX=$pad+$sigW+28; $itW=$W-$pad-$itX;
    imagefilledrectangle($img,$itX,$y,$itX+$itW,$y+$sigH,imagecolorallocate($img,252,252,255));
    imagerectangle(      $img,$itX,$y,$itX+$itW,$y+$sigH,$cBorder);
    tt($img, 8,  $itX+8, $y+16,       $cMid, $fBold, $hasTTF, 'IT-ÜBERGABE');
    tt($img, 8,  $itX+8, $y+$sigH-10, $cMid, $fReg,  $hasTTF, 'Stempel / Name');
    imageline($img,$itX,$y+$sigH+5,$itX+$itW,$y+$sigH+5,$cDark);
    tt($img, 9, $itX+8, $y+$sigH+18, $cMid, $fReg, $hasTTF, 'IT-Abteilung, '.$date);

    // Footer
    imagefilledrectangle($img,0,$H-35,$W,$H,$cLight);
    imageline($img,0,$H-35,$W,$H-35,$cBorder);
    tt($img, 8, $pad, $H-18, $cMid, $fReg, $hasTTF,
       'IT Tools · '.($p['company']??'').' · '.$footerTitel.' · '.$date);

    // JPEG erzeugen (für PDF-Embedding)
    ob_start(); imagejpeg($img, null, 92); $jpeg = ob_get_clean(); imagedestroy($img);

    // JPEG in minimales PDF einbetten
    return sign_jpeg_zu_pdf($jpeg, 794, 1123);
}

function tt($img, int $size, int $x, int $y, int $color, string $font, bool $hasTTF, string $text): void {
    if (!$text) return;
    if ($hasTTF) { imagettftext($img,$size,0,$x,$y,$color,$font,$text); }
    else { imagestring($img,$size>=13?5:($size>=10?4:3),$x,$y-10,$text,$color); }
}

/**
 * Bettet ein JPEG-Bild in ein minimales PDF ein.
 * No externes Paket nötig — PDF-Struktur wird direkt erzeugt.
 * A4 bei 72 DPI: 595 × 842 Punkte.
 */
function sign_jpeg_zu_pdf(string $jpeg, int $imgW, int $imgH): string {
    $jpegLen = strlen($jpeg);
    // A4-Pagengröße in Punkten
    $pw = 595; $ph = 842;

    $objs = [];

    // Obj 1: Katalog
    $objs[] = "1 0 obj\n<</Type /Catalog /Pages 2 0 R>>\nendobj";
    // Obj 2: Pagen
    $objs[] = "2 0 obj\n<</Type /Pages /Kids [3 0 R] /Count 1>>\nendobj";
    // Obj 3: Page
    $objs[] = "3 0 obj\n<</Type /Page /Parent 2 0 R /MediaBox [0 0 $pw $ph]"
            . " /Contents 4 0 R /Resources <</XObject <</Im1 5 0 R>>>>>>\nendobj";
    // Obj 4: Content-Stream (Bild auf ganze Page skalieren)
    $stream = "q $pw 0 0 $ph 0 0 cm /Im1 Do Q";
    $sLen   = strlen($stream);
    $objs[] = "4 0 obj\n<</Length $sLen>>\nstream\n$stream\nendstream\nendobj";
    // Obj 5: JPEG-Bild
    $objs[] = "5 0 obj\n<</Type /XObject /Subtype /Image /Width $imgW /Height $imgH"
            . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode"
            . " /Length $jpegLen>>\nstream\n$jpeg\nendstream\nendobj";

    // Alle Objekte zusammensetzen + Offsets merken
    $pdf      = "%PDF-1.4\n";
    $offsets  = [];
    foreach ($objs as $i => $obj) {
        $offsets[$i + 1] = strlen($pdf);
        $pdf .= $obj . "\n";
    }

    // Cross-Reference-Table
    $xrefOffset = strlen($pdf);
    $n = count($objs) + 1; // +1 für Null-Eintrag
    $pdf .= "xref\n0 $n\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $off) {
        $pdf .= sprintf("%010d 00000 n \n", $off);
    }
    $pdf .= "trailer\n<</Size $n /Root 1 0 R>>\nstartxref\n$xrefOffset\n%%EOF\n";

    return $pdf;
}

// Alias für Kompatibilität
function generate_sign_document(array $p): string { return sign_dokument_erstellen($p); }
