<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/handlers/command_menu.php';
require_once __DIR__ . '/handlers/callback.php';
require_once __DIR__ . '/handlers/text.php';

$update = getUpdate();

if (!$update)
    exit;

// Pesan dari user
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';

    if ($text === '/menu') {
        handleMenu($chat_id);
    } else {
        handleText($chat_id, $text);
    }
}

// Callback tombol
elseif (isset($update['callback_query'])) {
    handleCallback($update['callback_query']);
}

echo "OK";
