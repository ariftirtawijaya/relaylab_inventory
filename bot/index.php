<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

$update = getUpdate();

if (!$update)
    exit;

// ======================================================
// Handle CALLBACK BUTTON
// ======================================================
if (isset($update['callback_query'])) {

    $chat_id = $update['callback_query']['message']['chat']['id'];
    $data = $update['callback_query']['data'];

    // Hapus loading di Telegram
    sendMessage($chat_id, "⏳ Memproses...");

    // ---- MENU: Cek Stok ----
    if ($data === "cekstok") {
        setState($chat_id, "CEK_STOK_KEYWORD");

        sendMessage(
            $chat_id,
            "🔎 *Cek Stok*\n\n" .
            "Silakan kirim *kata kunci* item yang ingin dicari.\n\nContoh:\n" .
            "`kabel`\n`skun`\n`h11`\n\n" .
            "Ketik /cancel untuk membatalkan."
        );
        exit;
    }

    // ---- MENU: Cancel ----
    if ($data === "cancel") {
        clearState($chat_id);
        sendMessage($chat_id, "❌ Aksi dibatalkan.\nKetik /menu untuk kembali.");
        exit;
    }

    // ---- MENU lain belum aktif ----
    if ($data === "stokmasuk") {
        sendMessage($chat_id, "🚧 *Fitur Stok Masuk belum diaktifkan.*");
        exit;
    }

    if ($data === "stokkeluar") {
        sendMessage($chat_id, "🚧 *Fitur Stok Keluar belum diaktifkan.*");
        exit;
    }

    if ($data === "lowstock") {
        sendMessage($chat_id, "🚧 *Fitur Low Stock belum diaktifkan.*");
        exit;
    }

    exit;
}


// ======================================================
// NORMAL CHAT MESSAGE
// ======================================================
$chat_id = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');

if (!$chat_id)
    exit;

// Ambil state user
$state = getState($chat_id)['state'] ?? null;


// ======================================================
// /MENU → tampilkan tombol cantik
// ======================================================
if ($text === "/menu") {

    clearState($chat_id);

    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "📦  Cek Stok", "callback_data" => "cekstok"]
            ],
            [
                ["text" => "➕  Stok Masuk", "callback_data" => "stokmasuk"],
                ["text" => "➖  Stok Keluar", "callback_data" => "stokkeluar"]
            ],
            [
                ["text" => "⚠️  Low Stock", "callback_data" => "lowstock"]
            ],
            [
                ["text" => "❌  Batal", "callback_data" => "cancel"]
            ]
        ]
    ];

    sendMessage($chat_id, "*🗂 MENU UTAMA*\nSilakan pilih:", $keyboard);
    exit;
}


// ======================================================
// /cancel
// ======================================================
if ($text === "/cancel") {
    clearState($chat_id);
    sendMessage($chat_id, "❌ Proses dibatalkan.");
    exit;
}


// ======================================================
// 1️⃣ USER ketik /cekstok → minta keyword
// ======================================================
if ($text === "/cekstok") {

    setState($chat_id, "CEK_STOK_KEYWORD");

    sendMessage(
        $chat_id,
        "🔎 *Cek Stok*\n\nSilakan kirim *kata kunci* item yang ingin dicari.\n\nContoh:\n`kabel`\n`skun`\n`h11`\n\nKetik /cancel untuk membatalkan."
    );
    exit;
}


// ======================================================
// 2️⃣ Mode pencarian stok
// ======================================================
if ($state === "CEK_STOK_KEYWORD") {

    $keyword = "%{$text}%";

    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.name,
            i.min_stock,
            u.code AS unit_code,
            COALESCE((SELECT SUM(qty) FROM stock_movements WHERE item_id=i.id AND movement_type='IN'), 0)
            -
            COALESCE((SELECT SUM(qty) FROM stock_movements WHERE item_id=i.id AND movement_type='OUT'), 0)
            AS stock_good
        FROM items i
        JOIN units u ON u.id = i.unit_id
        WHERE i.name LIKE ?
        ORDER BY i.name ASC
        LIMIT 20
    ");

    $stmt->execute([$keyword]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$results) {
        sendMessage($chat_id, "❌ Tidak ditemukan item dengan keyword: *{$text}*");
        exit;
    }

    $reply = "📦 *HASIL PENCARIAN:* `{$text}`\n\n";

    $n = 1;
    foreach ($results as $r) {

        $stok = rtrim(rtrim(number_format($r['stock_good'], 2, '.', ''), '0'), '.');
        $min = rtrim(rtrim(number_format($r['min_stock'], 2, '.', ''), '0'), '.');

        if ($r['stock_good'] <= 0) {
            $status = "❌ *Habis*";
        } elseif ($r['stock_good'] < $r['min_stock']) {
            $status = "⚠ Low Stock";
        } else {
            $status = "";
        }

        $reply .= "{$n}. *{$r['name']}*\n   Stok: *{$stok} {$r['unit_code']}*   Min: {$min}   {$status}\n\n";
        $n++;
    }

    sendMessage($chat_id, $reply);
    clearState($chat_id);
    exit;
}


// ======================================================
// FALLBACK
// ======================================================
sendMessage($chat_id, "Perintah tidak dikenali.\nKetik /menu untuk melihat opsi.");
