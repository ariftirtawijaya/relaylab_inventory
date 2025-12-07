<?php
// config/telegram.php
// -------------------

// Isi BOT TOKEN dari @BotFather
define('TELEGRAM_BOT_TOKEN', '7968450422:AAHyoyMT6OUcw_fk9iCeQ7h78P42mBJvFS0');

// Isi CHAT ID pribadi atau grup
define('TELEGRAM_CHAT_IDS', [
    '318416641',    // Akun Arif
    '8300371133',    // Akun kedua (isi dengan chat_id baru)
]);



/**
 * Mengirim pesan Telegram.
 * Menampilkan pesan error jika gagal.
 */
function telegram_send_message(string $text): bool
{
    $token = TELEGRAM_BOT_TOKEN;
    $chatIds = TELEGRAM_CHAT_IDS;

    if (empty($token) || empty($chatIds)) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    // Maksimal aman 3500 karakter
    $chunks = str_split($text, 3500);

    $success = true;

    foreach ($chatIds as $chatId) {
        foreach ($chunks as $chunk) {

            $data = [
                'chat_id' => $chatId,
                'text' => $chunk,
                'parse_mode' => null,  // Tidak pakai Markdown apa pun = AMAN
            ];

            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data),
                    'timeout' => 10,
                ],
            ];

            $context = stream_context_create($options);

            $result = @file_get_contents($url, false, $context);

            echo "\n--- SEND TO {$chatId} ---\n";
            var_dump($result);

            if ($result === false) {
                $success = false;
            } else {
                $json = json_decode($result, true);
                if (!$json['ok']) {
                    echo "\nERROR JSON:\n";
                    var_dump($json);
                    $success = false;
                }
            }
        }
    }

    return $success;
}
