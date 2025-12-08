<?php
require_once __DIR__ . '/../helpers.php';

function handleMenu($chat_id)
{
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📦 Cek Stok', 'callback_data' => 'cek_stok']
            ],
            [
                ['text' => '➕ Stock In', 'callback_data' => 'stock_in'],
                ['text' => '➖ Stock Out', 'callback_data' => 'stock_out']
            ],
            [
                ['text' => '📉 Stok Rendah', 'callback_data' => 'low_stock']
            ],
        ]
    ];

    sendMessage($chat_id, "*Menu Utama Inventory RelayLab* 👇", $keyboard);
}
