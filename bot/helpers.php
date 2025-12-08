<?php
require_once __DIR__ . '/config.php';

/**
 * ============================================
 *  KIRIM PESAN KE TELEGRAM (SUPER STABLE)
 *  Menggunakan cURL agar tidak timeout di hosting
 * ============================================
 */
function sendMessage($chat_id, $text, $reply_markup = null)
{
    $token = BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];

    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);

    return file_get_contents($url, false, $context);
}


/**
 * ============================================
 *  CURL WRAPPER — PENGGANTI file_get_contents
 *  Timeout cepat (5 detik), aman untuk webhook
 * ============================================
 */
function tgRequest($url, $params)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 5,             // mencegah timeout 10 detik webhook Telegram
        CURLOPT_SSL_VERIFYPEER => false,  // hosting shared sering masalah SSL
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
 *  AMBIL UPDATE WEBHOOK
 * ============================================
 */
function getUpdate()
{
    $input = file_get_contents("php://input");
    return json_decode($input, true);
}

/**
 * ============================================
 *  STATE MANAGEMENT (SIMPEL)
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
