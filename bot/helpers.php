<?php
require_once __DIR__ . '/config.php';

// Kirim pesan biasa
function sendMessage($chat_id, $text, $reply_markup = null)
{
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];

    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }

    file_get_contents(API_URL . "sendMessage?" . http_build_query($data));
}

// Ambil update dari Telegram
function getUpdate()
{
    $input = file_get_contents("php://input");
    return json_decode($input, true);
}

// Simpan state percakapan
function setState($chat_id, $state, $data = [])
{
    $file = SESSION_PATH . $chat_id . '.json';
    file_put_contents($file, json_encode([
        'state' => $state,
        'data' => $data
    ]));
}

// Ambil state percakapan
function getState($chat_id)
{
    $file = SESSION_PATH . $chat_id . '.json';
    if (!file_exists($file))
        return null;
    return json_decode(file_get_contents($file), true);
}

// Reset state
function clearState($chat_id)
{
    $file = SESSION_PATH . $chat_id . '.json';
    if (file_exists($file))
        unlink($file);
}
