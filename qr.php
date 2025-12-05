<?php
require_once __DIR__ . '/libs/phpqrcode/qrlib.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    exit('Invalid QR');
}

// URL tujuan QR
$baseUrl = (isset($_SERVER['HTTPS']) ? "https://" : "http://")
    . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['PHP_SELF']), '/');

$link = $baseUrl . "/scan.php?id=" . $id;

// Header PNG
header('Content-Type: image/png');

// Generate langsung output PNG
QRcode::png($link, false, QR_ECLEVEL_H, 6);
exit;
