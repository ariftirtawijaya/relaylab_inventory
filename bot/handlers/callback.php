<?php
require_once __DIR__ . '/../helpers.php';

function handleCallback($callback)
{
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];

    if ($data === 'cek_stok') {
        sendMessage($chat_id, "🔍 Masukkan nama item yang ingin dicek:");
        setState($chat_id, 'cek_stok_waiting_name');
    } elseif ($data === 'stock_in') {
        sendMessage($chat_id, "➕ Masukkan nama item untuk *Stock IN*:");
        setState($chat_id, 'stock_in_waiting_item');
    } elseif ($data === 'stock_out') {
        sendMessage($chat_id, "➖ Masukkan nama item untuk *Stock OUT*:");
        setState($chat_id, 'stock_out_waiting_item');
    } elseif ($data === 'low_stock') {
        sendMessage($chat_id, "📉 *Daftar stok rendah* akan kita sambungkan nanti.");
    }
}
