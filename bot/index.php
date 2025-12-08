<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

$update = getUpdate();

if (!$update)
    exit;

$chat_id = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');

if (!$chat_id)
    exit;


// ==============================================
// HANDLE /MENU
// ==============================================
if ($text === "/menu") {
    clearState($chat_id);

    $keyboard = [
        'keyboard' => [
            [['text' => '/cekstok']],
            [['text' => '/cancel']]
        ],
        'resize_keyboard' => true
    ];

    sendMessage($chat_id, "*Menu Utama*\nPilih perintah:", $keyboard);
    exit;
}


// ==============================================
// HANDLE /CANCEL
// ==============================================
if ($text === "/cancel") {
    clearState($chat_id);
    sendMessage($chat_id, "❌ Proses dibatalkan.");
    exit;
}


// Ambil state user
$state = getState($chat_id)['state'] ?? null;


// ==============================================
// 1️⃣ USER KETIK /cekstok → MINTA KEYWORD
// ==============================================
if ($text === "/cekstok") {

    setState($chat_id, "CEK_STOK_KEYWORD");

    sendMessage(
        $chat_id,
        "🔎 *Cek Stok*\n\nSilakan kirim *kata kunci* item yang ingin dicari.\n\nContoh:\n`kabel`\n`skun`\n`h11`\n\nKetik /cancel untuk membatalkan."
    );
    exit;
}


// ==============================================
// 2️⃣ USER SEDANG DALAM MODE CEK_STOK_KEYWORD
// ==============================================
if ($state === "CEK_STOK_KEYWORD") {

    $keyword = "%{$text}%";

    $stmt = $pdo->prepare("
    SELECT 
        i.id,
        i.name,
        i.min_stock,
        u.code AS unit_code,
        COALESCE((
            SELECT SUM(qty) FROM stock_movements 
            WHERE item_id = i.id AND movement_type = 'IN'
        ), 0)
        -
        COALESCE((
            SELECT SUM(qty) FROM stock_movements 
            WHERE item_id = i.id AND movement_type = 'OUT'
        ), 0) AS stock_good
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

        // Status stok
        if ($r['stock_good'] <= 0) {
            $status = "❌ *Habis*";
        } elseif ($r['stock_good'] < $r['min_stock']) {
            $status = "⚠ Low Stock";
        } else {
            $status = "";
        }

        $reply .= "{$n}. *{$r['name']}*\n   Stok: *{$stok} {$r['code']}*   Min: {$min}   {$status}\n\n";
        $n++;
    }

    sendMessage($chat_id, $reply);

    // reset state setelah selesai
    clearState($chat_id);
    exit;
}


// ==============================================
// FALLBACK
// ==============================================
sendMessage($chat_id, "Perintah tidak dikenali.\nKetik /menu untuk melihat opsi.");
