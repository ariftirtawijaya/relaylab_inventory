<?php
require_once __DIR__ . '/config.php';

/**
 * ============================================
 *  KIRIM PESAN TELEGRAM (PASTI TIDAK TIMEOUT)
 *  Hanya menggunakan cURL — aman untuk webhook
 * ============================================
 */
function sendMessage($chat_id, $text, $reply_markup = null)
{
    $url = API_URL . "sendMessage";

    $params = [
        "chat_id" => $chat_id,
        "text" => $text,
        "parse_mode" => "Markdown"
    ];

    if ($reply_markup) {
        $params["reply_markup"] = json_encode($reply_markup);
    }

    return tgRequest($url, $params);
}

/**
 * ============================================
 *  CURL WRAPPER
 * ============================================
 */
function tgRequest($url, $params)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("TELEGRAM CURL ERROR: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return $response;
}

/**
 * ============================================
 *  AMBIL UPDATE DARI TELEGRAM
 * ============================================
 */
function getUpdate()
{
    $input = file_get_contents("php://input");
    return json_decode($input, true);
}

/**
 * ============================================
 *  STATE MANAGEMENT
 * ============================================
 */
function setState($chat_id, $state, $data = [])
{
    $file = SESSION_PATH . $chat_id . '.json';
    file_put_contents($file, json_encode([
        'state' => $state,
        'data' => $data
    ]));
}

function getState($chat_id)
{
    $file = SESSION_PATH . $chat_id . '.json';
    if (!file_exists($file))
        return null;
    return json_decode(file_get_contents($file), true);
}

function clearState($chat_id)
{
    $file = SESSION_PATH . $chat_id . '.json';
    if (file_exists($file))
        unlink($file);
}

function sendLongMessage($chat_id, $text, $reply_markup = null)
{
    $max = 3800; // aman dari limit Telegram (4096)

    // jika pesan pendek → kirim biasa
    if (strlen($text) <= $max) {
        return sendMessage($chat_id, $text, $reply_markup);
    }

    // jika panjang → potong jadi beberapa bagian
    $parts = str_split($text, $max);

    foreach ($parts as $part) {
        sendMessage($chat_id, $part);
        usleep(150000); // jeda 0.15s biar aman dari flood limit
    }

    return true;
}
