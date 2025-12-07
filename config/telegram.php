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

    // Telegram limit per message = 4096 chars
    $MAX = 3900; // sedikit dikurangi untuk margin parse_mode

    // Pecah menjadi chunk
    $messages = [];
    $length = strlen($text);

    for ($i = 0; $i < $length; $i += $MAX) {
        $messages[] = substr($text, $i, $MAX);
    }

    $success = true;

    foreach ($chatIds as $chatId) {
        echo "\n=== KIRIM KE {$chatId} ===\n";

        foreach ($messages as $index => $msg) {
            $header = (count($messages) > 1)
                ? "**Bagian " . ($index + 1) . "/" . count($messages) . "**\n\n"
                : "";

            $payload = [
                'chat_id' => $chatId,
                'text' => $header . $msg,
                'parse_mode' => 'Markdown',
            ];

            $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $error = curl_error($ch);

            curl_close($ch);

            var_dump($response);
            echo "\n";

            if ($response === false) {
                echo "CURL ERROR: $error\n";
                $success = false;
            }
        }
    }

    return $success;
}
