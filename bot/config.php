<?php

define('BOT_TOKEN', '7968450422:AAHyoyMT6OUcw_fk9iCeQ7h78P42mBJvFS0');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// ChatID yang boleh mengakses fitur admin
define('ADMINS', [
    '318416641',
    '8300371133',
]);

// Folder penyimpanan session percakapan
define('SESSION_PATH', __DIR__ . '/sessions/');

// Fungsi simple untuk cek admin
function is_admin($chat_id)
{
    return in_array($chat_id, ADMINS);
}
