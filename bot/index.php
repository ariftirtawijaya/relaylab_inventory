<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

$update = getUpdate();

if (!$update)
    exit;


// ======================================================
//  HANDLE CALLBACK BUTTON
// ======================================================
if (isset($update['callback_query'])) {

    $chat_id = $update['callback_query']['message']['chat']['id'];
    $data = $update['callback_query']['data'];

    sendMessage($chat_id, "⏳ Memproses...");

    // ---- MENU: Cek Stok ----
    if ($data === "cekstok") {
        setState($chat_id, "CEK_STOK_KEYWORD");
        sendMessage($chat_id, "🔎 *Cek Stok*\n\nSilakan kirim kata kunci item.");
        exit;
    }

    // ---- MENU: Cancel ----
    if ($data === "cancel") {
        clearState($chat_id);
        sendMessage($chat_id, "❌ Aksi dibatalkan.\nKetik /menu untuk kembali.");
        exit;
    }

    // ==========================================================
    //  STOK MASUK — STEP 1: USER MENEKAN TOMBOL "Stok Masuk"
    // ==========================================================
    if ($data === "stokmasuk") {

        setState($chat_id, "STOKMASUK_KEYWORD");

        sendMessage(
            $chat_id,
            "➕ *Stok Masuk*\n\n" .
            "Silakan kirim *kata kunci* item yang ingin ditambahkan stoknya.\n\nContoh:\n`h11`\n`kabel`\n`skun`"
        );
        exit;
    }

    // ==========================================================
    //  STOK MASUK — STEP 3: USER PILIH ITEM
    // ==========================================================
    if (str_starts_with($data, "pilihitem_")) {

        $item_id = (int) str_replace("pilihitem_", "", $data);

        // simpan state
        setState($chat_id, "STOKMASUK_QTY", ["item_id" => $item_id]);

        sendMessage($chat_id, "Masukkan jumlah stok yang akan ditambahkan:");
        exit;
    }

    exit;
}


// ======================================================
// HANDLE NORMAL CHAT MESSAGE
// ======================================================
$chat_id = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');

if (!$chat_id)
    exit;

$stateData = getState($chat_id);
$state = $stateData['state'] ?? null;


// ======================================================
// /MENU → tombol utama
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
// CANCEL
// ======================================================
if ($text === "/cancel") {
    clearState($chat_id);
    sendMessage($chat_id, "❌ Proses dibatalkan.");
    exit;
}


// ======================================================
// /cekstok
// ======================================================
if ($text === "/cekstok") {
    setState($chat_id, "CEK_STOK_KEYWORD");
    sendMessage($chat_id, "🔎 Kirim kata kunci pencarian:");
    exit;
}


// ======================================================
// CEK STOCK KEYWORD MODE
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
        sendMessage($chat_id, "❌ Tidak ditemukan item dengan kata: *{$text}*");
        exit;
    }

    $reply = "📦 *HASIL PENCARIAN:* `{$text}`\n\n";

    $n = 1;
    foreach ($results as $r) {

        $stok = rtrim(rtrim(number_format($r['stock_good'], 2, '.', ''), '0'), '.');
        $min = rtrim(rtrim(number_format($r['min_stock'], 2, '.', ''), '0'), '.');

        if ($r['stock_good'] <= 0)
            $status = "❌ *Habis*";
        elseif ($r['stock_good'] < $r['min_stock'])
            $status = "⚠ Low Stock";
        else
            $status = "";

        $reply .= "{$n}. *{$r['name']}*\n";
        $reply .= "   Stok: *{$stok} {$r['unit_code']}*   Min: {$min} {$status}\n\n";

        $n++;
    }

    sendMessage($chat_id, $reply);
    clearState($chat_id);
    exit;
}


// ======================================================
// ================= STOK MASUK =========================
// ======================================================

// STEP 2: USER KIRIM KEYWORD
if ($state === "STOKMASUK_KEYWORD") {

    $keyword = "%{$text}%";

    $stmt = $pdo->prepare("
    SELECT 
        i.id, 
        i.name, 
        u.code AS unit_code
    FROM items i
    JOIN units u ON u.id = i.unit_id
    WHERE i.name LIKE ?
    ORDER BY i.name ASC
    LIMIT 20
");


    $stmt->execute([$keyword]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        sendMessage($chat_id, "❌ Tidak ada item ditemukan untuk keyword: *{$text}*");
        exit;
    }

    // Generate button list
    $keyboard = ["inline_keyboard" => []];

    foreach ($items as $it) {
        $keyboard["inline_keyboard"][] = [
            [
                "text" => $it['name'] . " (" . $it['unit_code'] . ")",
                "callback_data" => "pilihitem_" . $it['id']
            ]
        ];
    }

    sendMessage($chat_id, "Pilih item yang ingin ditambah stoknya:", $keyboard);
    exit;
}


// STEP 4: USER INPUT QTY
if ($state === "STOKMASUK_QTY") {

    if (!is_numeric($text) || $text <= 0) {
        sendMessage($chat_id, "❌ Jumlah tidak valid. Masukkan angka lebih dari 0.");
        exit;
    }

    $qty = (float) $text;
    $item_id = $stateData['data']['item_id'];

    // INSERT STOCK IN
    $stmt = $pdo->prepare("
        INSERT INTO stock_movements (item_id, movement_date, movement_type, stock_type, qty, description)
        VALUES (?, NOW(), 'IN', 'GOOD', ?, 'Stok Masuk via Telegram')
    ");
    $stmt->execute([$item_id, $qty]);

    clearState($chat_id);

    sendMessage(
        $chat_id,
        "✅ *Stok berhasil ditambahkan!*\n\nItem ID: `{$item_id}`\nJumlah: *{$qty}*"
    );
    exit;
}


// ======================================================
// FALLBACK
// ======================================================
sendMessage($chat_id, "Perintah tidak dikenali.\nKetik /menu untuk melihat pilihan.");
