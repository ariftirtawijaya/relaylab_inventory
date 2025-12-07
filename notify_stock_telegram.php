<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/telegram.php';

date_default_timezone_set('Asia/Jakarta');

// Ambil semua item yang stok_good < min_stock
$sql = "
SELECT 
    i.id,
    i.name,
    i.min_stock,
    i.notes,
    c.name AS category_name,
    u.code AS unit_code,
    (
        COALESCE((
            SELECT SUM(qty) 
            FROM stock_movements 
            WHERE item_id = i.id AND movement_type = 'IN' AND stock_type = 'GOOD'
        ), 0)
        -
        COALESCE((
            SELECT SUM(qty) 
            FROM stock_movements 
            WHERE item_id = i.id AND movement_type = 'OUT' AND stock_type = 'GOOD'
        ), 0)
    ) AS stock_good
FROM items i
JOIN categories c ON c.id = i.category_id
JOIN units u ON u.id = i.unit_id
HAVING stock_good < min_stock
ORDER BY c.name, i.name
";

$rows = $pdo->query($sql)->fetchAll();

if (!$rows) {
    telegram_send_message("✅ Semua stok aman. Tidak ada item yang kekurangan stok.");
    exit;
}

// Group berdasarkan kategori
$grouped = [];
foreach ($rows as $r) {
    $cat = strtoupper($r['category_name']);
    if (!isset($grouped[$cat]))
        $grouped[$cat] = [];
    $grouped[$cat][] = $r;
}

// Bangun pesan
$time = date('H:i');
$totalItems = count($rows);

$message = "📦 LAPORAN STOK MENIPIS / HABIS\n";
$message .= "🕒 {$time} WIB\n";
$message .= "Total item: {$totalItems}\n\n";

foreach ($grouped as $cat => $items) {
    $message .= "📂 {$cat}\n";

    foreach ($items as $i) {
        $message .= "— {$i['name']} ({$i['unit_code']})\n";
        $message .= "   Stok: {$i['stock_good']} / Min: {$i['min_stock']}\n";
    }

    $message .= "\n";
}

// Kirim ke Telegram
telegram_send_message($message);

echo "Notifikasi terkirim.\n";
