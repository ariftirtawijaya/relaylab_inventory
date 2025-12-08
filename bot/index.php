<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

$update = getUpdate();

if (!$update) {
    exit;
}

/* =======================================================
   ===============  HANDLE CALLBACK BUTTON  ===============
   ======================================================= */
if (isset($update['callback_query'])) {

    $chat_id = $update['callback_query']['message']['chat']['id'] ?? null;
    $data = $update['callback_query']['data'] ?? '';

    if (!$chat_id) {
        exit;
    }

    // (opsional) indikator proses
    sendMessage($chat_id, "⏳ Memproses...");

    /* ---------------- MENU: KEMBALI KE MENU ---------------- */
    if ($data === "menu") {

        clearState($chat_id);

        $keyboard = [
            "inline_keyboard" => [
                [
                    ["text" => "📦  Cek Stok", "callback_data" => "cekstok"],
                ],
                [
                    ["text" => "➕  Stok Masuk", "callback_data" => "stokmasuk"],
                    ["text" => "➖  Stok Keluar", "callback_data" => "stokkeluar"],
                ],
                [
                    ["text" => "⚠️  Low Stock", "callback_data" => "lowstock"],
                ],
                [
                    ["text" => "❌  Batal", "callback_data" => "cancel"],
                ],
            ],
        ];

        sendMessage($chat_id, "*🗂 MENU UTAMA*\nSilakan pilih:", $keyboard);
        exit;
    }

    /* ---------------- MENU: CEK STOK ---------------- */
    if ($data === "cekstok") {
        setState($chat_id, "CEK_STOK_KEYWORD");
        sendMessage($chat_id, "🔎 *Cek Stok*\n\nSilahkan kirim kata kunci item.");
        exit;
    }

    /* ---------------- MENU: LOW STOCK ---------------- */
    if ($data === "lowstock") {

        $stmt = $pdo->query("
        SELECT 
            i.name,
            c.name AS category,
            i.min_stock,
            u.code AS unit_code,
            COALESCE((SELECT SUM(qty) FROM stock_movements WHERE item_id=i.id AND movement_type='IN'),0)
            -
            COALESCE((SELECT SUM(qty) FROM stock_movements WHERE item_id=i.id AND movement_type='OUT'),0)
            AS stock_good
        FROM items i
        JOIN categories c ON c.id = i.category_id
        JOIN units u ON u.id = i.unit_id
        HAVING stock_good < min_stock
        ORDER BY c.name ASC, i.name ASC
    ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            sendMessage($chat_id, "🎉 *Semua stok aman!*");
            exit;
        }

        $reply = "⚠️ *DAFTAR LOW STOCK*\n(urut kategori)\n\n";

        $current_cat = "";
        $n = 1;

        foreach ($rows as $r) {

            $stok = rtrim(rtrim(number_format($r['stock_good'], 2, '.', ''), '0'), '.');
            $min = rtrim(rtrim(number_format($r['min_stock'], 2, '.', ''), '0'), '.');

            $status = $r['stock_good'] <= 0 ? "❌ *Habis*" : "⚠️";

            if ($current_cat !== $r['category']) {
                $current_cat = $r['category'];
                $reply .= "【*{$current_cat}*】\n";
            }

            $reply .= "{$n}. *{$r['name']}*\n";
            $reply .= "   Stok: *{$stok} {$r['unit_code']}* / Min: *{$min}* {$status}\n\n";

            $n++;
        }

        sendLongMessage($chat_id, $reply);
        exit;
    }


    /* ---------------- MENU: CANCEL ---------------- */
    if ($data === "cancel") {
        clearState($chat_id);
        sendMessage($chat_id, "❌ Aksi dibatalkan.\nKetik /menu untuk kembali.");
        exit;
    }

    /* ==========================================================
       ==================== STOK MASUK ===========================
       ========================================================== */

    // STEP 1: USER TEKAN "Stok Masuk"
    if ($data === "stokmasuk") {

        setState($chat_id, "STOKMASUK_KEYWORD");

        sendMessage(
            $chat_id,
            "➕ *Stok Masuk*\n\n" .
            "Silakan kirim *kata kunci* item yang ingin ditambahkan stoknya.\n\nContoh:\n" .
            "`h11`\n`kabel`\n`skun`"
        );
        exit;
    }

    // STEP 3: USER PILIH ITEM UNTUK STOK MASUK
    if (str_starts_with($data, "pilihitem_")) {

        $item_id = (int) str_replace("pilihitem_", "", $data);

        if ($item_id <= 0) {
            sendMessage($chat_id, "❌ Item tidak valid.");
            exit;
        }

        setState($chat_id, "STOKMASUK_QTY", ["item_id" => $item_id]);

        sendMessage($chat_id, "Masukkan jumlah stok yang akan *ditambahkan*:");
        exit;
    }

    /* ==========================================================
       ==================== STOK KELUAR ==========================
       ========================================================== */

    // STEP 1: USER TEKAN "Stok Keluar"
    if ($data === "stokkeluar") {

        setState($chat_id, "STOKKELUAR_KEYWORD");

        sendMessage(
            $chat_id,
            "➖ *Stok Keluar*\n\n" .
            "Silakan kirim *kata kunci* item yang ingin dikurangi stoknya.\n\nContoh:\n" .
            "`h11`\n`kabel`\n`skun`"
        );
        exit;
    }

    // STEP 3: USER PILIH ITEM UNTUK STOK KELUAR
    if (str_starts_with($data, "keluaritem_")) {

        $item_id = (int) str_replace("keluaritem_", "", $data);

        if ($item_id <= 0) {
            sendMessage($chat_id, "❌ Item tidak valid.");
            exit;
        }

        // ambil data item & stok
        $stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.name,
                u.code AS unit_code,
                COALESCE((SELECT SUM(qty) FROM stock_movements WHERE item_id=i.id AND movement_type='IN'),0)
                -
                COALESCE((SELECT SUM(qty) FROM stock_movements WHERE item_id=i.id AND movement_type='OUT'),0)
                AS stock_good
            FROM items i
            JOIN units u ON u.id = i.unit_id
            WHERE i.id = ?
            LIMIT 1
        ");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            sendMessage($chat_id, "❌ Item tidak ditemukan.");
            exit;
        }

        setState($chat_id, "STOKKELUAR_QTY", [
            "item_id" => $item["id"],
            "name" => $item["name"],
            "unit" => $item["unit_code"],
            "stock_good" => (float) $item["stock_good"],
        ]);

        sendMessage(
            $chat_id,
            "Masukkan jumlah stok *keluar* untuk *{$item['name']} ({$item['unit_code']})*.\n" .
            "Stok tersedia: *{$item['stock_good']}*"
        );
        exit;
    }

    // kalau callback data lain, diam saja
    exit;
}


/* =======================================================
   ===============  HANDLE NORMAL MESSAGE  ===============
   ======================================================= */

$chat_id = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');

if (!$chat_id) {
    exit;
}

$stateData = getState($chat_id);
$state = $stateData['state'] ?? null;


/* ======================================================
   ======================== MENU =========================
   ====================================================== */
if ($text === "/menu") {

    clearState($chat_id);

    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "📦  Cek Stok", "callback_data" => "cekstok"],
            ],
            [
                ["text" => "➕  Stok Masuk", "callback_data" => "stokmasuk"],
                ["text" => "➖  Stok Keluar", "callback_data" => "stokkeluar"],
            ],
            [
                ["text" => "⚠️  Low Stock", "callback_data" => "lowstock"],
            ],
            [
                ["text" => "❌  Batal", "callback_data" => "cancel"],
            ],
        ],
    ];

    sendMessage($chat_id, "*🗂 MENU UTAMA*\nSilakan pilih:", $keyboard);
    exit;
}


/* ======================================================
   ======================== CANCEL =======================
   ====================================================== */
if ($text === "/cancel") {
    clearState($chat_id);
    sendMessage($chat_id, "❌ Proses dibatalkan.");
    exit;
}

/* ======================================================
   ======================= /CEK STOK =====================
   ====================================================== */

// (opsional) kalau user mau pakai /cekstok langsung
if ($text === "/cekstok") {
    setState($chat_id, "CEK_STOK_KEYWORD");
    sendMessage($chat_id, "🔎 Kirim kata kunci pencarian:");
    exit;
}

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

        if ($r['stock_good'] <= 0) {
            $status = "❌ *Habis*";
        } elseif ($r['stock_good'] < $r['min_stock']) {
            $status = "⚠ Low Stock";
        } else {
            $status = "";
        }

        $reply .= "{$n}. *{$r['name']}*\n";
        $reply .= "   Stok: *{$stok} {$r['unit_code']}*   Min: {$min} {$status}\n\n";
        $n++;
    }
    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "🔎 Kembali ke Pencarian", "callback_data" => "cekstok"],
            ],
            [
                ["text" => "⬅ Kembali ke Menu", "callback_data" => "menu"],
            ]
        ],
    ];
    sendMessage($chat_id, $reply, $keyboard);
    clearState($chat_id);
    exit;
}


/* ======================================================
   ======================= STOK MASUK ====================
   ====================================================== */

// STEP 2: KEYWORD
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
        sendMessage($chat_id, "❌ Tidak ada item untuk keyword: *{$text}*");
        exit;
    }

    $keyboard = ["inline_keyboard" => []];

    foreach ($items as $it) {
        $keyboard["inline_keyboard"][] = [
            [
                "text" => $it['name'] . " (" . $it['unit_code'] . ")",
                "callback_data" => "pilihitem_" . $it['id'],
            ],
        ];
    }

    sendMessage($chat_id, "Pilih item yang ingin *ditambah* stoknya:", $keyboard);
    exit;
}


// STEP 4: INPUT QTY STOK MASUK
if ($state === "STOKMASUK_QTY") {

    $data = $stateData['data'] ?? null;

    if (!$data || empty($data['item_id'])) {
        clearState($chat_id);
        sendMessage($chat_id, "❌ State tidak valid. Ulangi dari /menu.");
        exit;
    }

    if (!is_numeric($text) || $text <= 0) {
        sendMessage($chat_id, "❌ Jumlah tidak valid. Masukkan angka lebih dari 0.");
        exit;
    }

    $qty = (float) $text;
    $item_id = (int) $data['item_id'];

    $stmt = $pdo->prepare("
        INSERT INTO stock_movements 
            (item_id, movement_date, movement_type, stock_type, qty, description)
        VALUES 
            (?, NOW(), 'IN', 'GOOD', ?, 'Stok Masuk via Telegram')
    ");
    $stmt->execute([$item_id, $qty]);

    clearState($chat_id);
    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "⬅ Kembali ke Menu", "callback_data" => "menu"],
            ]
        ],
    ];
    sendMessage(
        $chat_id,
        "✅ *Stok berhasil ditambahkan!*\n\n" .
        "Item ID: `{$item_id}`\n" .
        "Jumlah: *{$qty}*",
        $keyboard
    );
    exit;
}


/* ======================================================
   ======================= STOK KELUAR ===================
   ====================================================== */

// STEP 2: KEYWORD
if ($state === "STOKKELUAR_KEYWORD") {

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
        sendMessage($chat_id, "❌ Tidak ada item ditemukan untuk: *{$text}*");
        exit;
    }

    $keyboard = ["inline_keyboard" => []];

    foreach ($items as $it) {
        $keyboard["inline_keyboard"][] = [
            [
                "text" => $it["name"] . " (" . $it["unit_code"] . ")",
                "callback_data" => "keluaritem_" . $it["id"],
            ],
        ];
    }

    sendMessage($chat_id, "Pilih item yang akan *dikurangi* stoknya:", $keyboard);
    exit;
}


// STEP 4: INPUT QTY STOK KELUAR
if ($state === "STOKKELUAR_QTY") {

    $d = $stateData["data"] ?? null;

    if (
        !$d ||
        !isset($d["item_id"], $d["stock_good"], $d["name"], $d["unit"])
    ) {
        clearState($chat_id);
        sendMessage($chat_id, "❌ State tidak valid. Ulangi dari /menu.");
        exit;
    }

    if (!is_numeric($text) || $text <= 0) {
        sendMessage($chat_id, "❌ Jumlah tidak valid. Masukkan angka > 0.");
        exit;
    }

    $qty = (float) $text;

    if ($qty > $d["stock_good"]) {
        sendMessage(
            $chat_id,
            "❌ Stok tidak cukup!\nStok tersedia hanya *{$d['stock_good']}*."
        );
        exit;
    }

    // simpan stok keluar
    $stmt = $pdo->prepare("
        INSERT INTO stock_movements 
            (item_id, movement_date, movement_type, stock_type, qty, description)
        VALUES 
            (?, NOW(), 'OUT', 'GOOD', ?, ?)
    ");
    $stmt->execute([
        $d["item_id"],
        $qty, // pakai input user
        'Stok Keluar via Telegram',
    ]);

    $sisa = $d["stock_good"] - $qty;

    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "⬅ Kembali ke Menu", "callback_data" => "menu"],
            ],
        ],
    ];

    sendMessage(
        $chat_id,
        "✔ *Stok berhasil dikurangi!*\n\n" .
        "Item: *{$d['name']} ({$d['unit']})*\n" .
        "Jumlah keluar: *{$qty}*\n" .
        "Sisa stok: *{$sisa} {$d['unit']}*",
        $keyboard
    );

    clearState($chat_id);
    exit;
}


/* ======================================================
   ======================== FALLBACK =====================
   ====================================================== */
sendMessage($chat_id, "Perintah tidak dikenali.\nKetik /menu untuk melihat pilihan.");
