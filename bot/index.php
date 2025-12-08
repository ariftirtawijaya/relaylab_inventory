<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/../config/db.php'; // koneksi DB inventory

$update = getUpdate();

// Log untuk debugging
file_put_contents(
    __DIR__ . "/test_log.txt",
    date("Y-m-d H:i:s") . " | RAW: " . json_encode($update) . "\n",
    FILE_APPEND
);

$chat_id = $update['message']['chat']['id'] ?? null;
$text = $update['message']['text'] ?? '';

if (!$chat_id)
    exit;

// =========================
//     MENU UTAMA
// =========================
if ($text === "/menu") {
    clearState($chat_id);
    sendMessage($chat_id, "Pilih menu:", getMainMenu());
    exit;
}

// =========================
//   FITUR CEK STOK (START)
// =========================
if ($text === "📦 Cek Stok") {
    clearState($chat_id);

    setState($chat_id, "CEK_STOK_AWAIT_KEYWORD");

    sendMessage(
        $chat_id,
        "Silakan ketik nama item yang ingin dicek stoknya.\n\nContoh: *H11 Male*"
    );
    exit;
}

// =========================
//   FITUR CEK STOK (PROSES)
// =========================
$state = getState($chat_id);

if ($state && $state['state'] === "CEK_STOK_AWAIT_KEYWORD") {
    $keyword = trim($text);

    if ($keyword === "") {
        sendMessage($chat_id, "Nama item tidak boleh kosong.");
        exit;
    }

    // Cari item
    $stmt = $pdo->prepare("
        SELECT i.*, c.name AS category_name, u.code AS unit_code
        FROM items i
        JOIN categories c ON c.id = i.category_id
        JOIN units u ON u.id = i.unit_id
        WHERE i.name LIKE ?
        LIMIT 5
    ");
    $stmt->execute(['%' . $keyword . '%']);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        sendMessage(
            $chat_id,
            "Item *$keyword* tidak ditemukan.\nCoba kata lain."
        );
        exit;
    }

    // Jika hanya 1 hasil → tampilkan langsung
    if (count($items) === 1) {
        $it = $items[0];

        $msg =
            "🔎 *HASIL CEK STOK*\n\n" .
            "*Nama:* {$it['name']}\n" .
            "*Kategori:* {$it['category_name']}\n" .
            "*Unit:* {$it['unit_code']}\n" .
            "*Stok Baik:* " . number_format($it['stock_good'], 0, ',', '.') . "\n" .
            "*Stok Rusak:* " . number_format($it['stock_bad'], 0, ',', '.') . "\n" .
            "*Minimal:* " . number_format($it['min_stock'], 0, ',', '.') . "\n";

        sendMessage($chat_id, $msg, getMainMenu());
        clearState($chat_id);
        exit;
    }

    // Jika lebih dari satu hasil → tampilkan list
    $msg = "🔎 *Beberapa item ditemukan:* \n\n";

    foreach ($items as $i) {
        $msg .=
            "• *{$i['name']}*\n" .
            "   Stok: " . number_format($i['stock_good'], 0, ',', '.') . "\n\n";
    }

    $msg .= "_Ketik nama item lebih lengkap untuk detail._";

    sendMessage($chat_id, $msg);
    exit;
}

// =========================
//  Fallback
// =========================
sendMessage($chat_id, "Gunakan /menu untuk melihat menu.");
