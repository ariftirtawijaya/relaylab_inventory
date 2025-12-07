<?php
// config/telegram.php
// -------------------

// Isi BOT TOKEN dari @BotFather
define('TELEGRAM_BOT_TOKEN', '8079678971:AAFvuZWFzxsfMKHj1J4F4tY7YRZn4vF3ErM');

// Isi CHAT ID pribadi atau grup
define('TELEGRAM_CHAT_ID', '318416641');


/**
 * Mengirim pesan Telegram.
 * Menampilkan pesan error jika gagal.
 */
function telegram_send_message(string $text): bool
{
    $token = TELEGRAM_BOT_TOKEN;
    $chatId = TELEGRAM_CHAT_ID;

    // Telegram max 4096 chars per message
    $maxLength = 4000; // lebih aman daripada 4096
    $chunks = str_split($text, $maxLength);

    foreach ($chunks as $chunk) {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $chunk,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        echo "\n--- TELEGRAM RESPONSE ---\n";
        var_dump($response);

        if ($error) {
            echo "\n--- TELEGRAM CURL ERROR ---\n";
            var_dump($error);
            return false;
        }
    }

    return true;
}



