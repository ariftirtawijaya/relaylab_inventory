<?php
require_once __DIR__ . '/libs/phpqrcode/qrlib.php';

// ambil id kategori, bukan item
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    exit('Invalid QR');
}

// URL tujuan QR → API kategori
$link = "https://relaylab.id/inventory/api/category.php?id=" . $id;

// output QR PNG
header('Content-Type: image/png');
QRcode::png($link, false, QR_ECLEVEL_H, 6);
exit;
