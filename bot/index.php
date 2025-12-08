<?php
file_put_contents(__DIR__ . "/test_log.txt", date("Y-m-d H:i:s") . " | RAW: " . file_get_contents("php://input") . "\n", FILE_APPEND);

require_once __DIR__ . '/helpers.php';

$update = getUpdate();

if (!$update) {
    sendMessage(318416641, "Webhook OK tapi update kosong.");
    exit;
}

$chat_id = $update['message']['chat']['id'] ?? null;
$text = $update['message']['text'] ?? '';

if ($text === "/menu") {
    sendMessage($chat_id, "Menu berhasil muncul! 🎉");
    exit;
}

// fallback
sendMessage($chat_id, "Perintah tidak dikenali.");
