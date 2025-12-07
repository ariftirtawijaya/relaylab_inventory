<?php

define('TELEGRAM_BOT_TOKEN', '7968450422:AAHyoyMT6OUcw_fk9iCeQ7h78P42mBJvFS0');

define('TELEGRAM_CHAT_IDS', [
    '318416641',
    '8300371133',
]);

function telegram_send_message(string $text): bool
{
    $token = TELEGRAM_BOT_TOKEN;
    $chatIds = TELEGRAM_CHAT_IDS;

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $all_success = true;

    foreach ($chatIds as $chatId) {

        echo "\n=== KIRIM KE {$chatId} ===\n";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,   // penting! hosting bisa error SSL
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        curl_close($ch);

        var_dump($response);
        echo "\n";

        if ($response === false) {
            echo "CURL ERROR: $error\n";
            $all_success = false;
        }
    }

    return $all_success;
}
