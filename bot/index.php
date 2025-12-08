<?php
file_put_contents(__DIR__ . "/test_log.txt", date("Y-m-d H:i:s") . " | RAW: " . file_get_contents("php://input") . "\n", FILE_APPEND);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';

// Ambil update
$update = getUpdate();

// Jika update kosong → exit
if (!$update)
    exit("NO UPDATE");

// Deteksi jenis update
$chat_id = $update['message']['chat']['id'] ?? null;
$text = $update['message']['text'] ?? '';

// Jika command "/menu"
if ($text === "/menu") {

    $reply_markup = [
        "keyboard" => [
            [
                ["text" => "📦 Cek Stok"],
                ["text" => "📥 Input Stok Masuk"],
            ],
            [
                ["text" => "📤 Input Stok Keluar"],
                ["text" => "🔍 Cari Item"],
            ],
            [
                ["text" => "⚠ Low Stock"]
            ]
        ],
        "resize_keyboard" => true,
        "one_time_keyboard" => false
    ];

    sendMessage($chat_id, "Pilih menu:", $reply_markup);
    exit;
}

// Handle tombol-tombol di keyboard
switch ($text) {

    case "📦 Cek Stok":
        sendMessage($chat_id, "Masukkan nama item untuk cek stok:");
        setState($chat_id, "CEK_STOK");
        break;

    case "📥 Input Stok Masuk":
        sendMessage($chat_id, "Masukkan item & jumlah (format: NAMA - JUMLAH)");
        setState($chat_id, "STOK_IN");
        break;

    case "📤 Input Stok Keluar":
        sendMessage($chat_id, "Masukkan item & jumlah (format: NAMA - JUMLAH)");
        setState($chat_id, "STOK_OUT");
        break;

    case "🔍 Cari Item":
        sendMessage($chat_id, "Ketik kata kunci item untuk pencarian:");
        setState($chat_id, "SEARCH_ITEM");
        break;

    case "⚠ Low Stock":
        require_once __DIR__ . "/handlers/low_stock.php";
        break;

    default:
        // Jika sedang dalam state tertentu → route ke handler
        $state = getState($chat_id);

        if ($state) {
            require_once __DIR__ . "/router_state.php";
            exit;
        }

        // Tidak dikenali
        sendMessage($chat_id, "Perintah tidak dikenali.\nKetik /menu untuk mulai.");
        break;
}
