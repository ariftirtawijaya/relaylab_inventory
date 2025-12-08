<?php

function getMainMenu()
{
    return [
        'keyboard' => [
            ['📦 Cek Stok'],
            ['📊 Low Stock', '📥 Stok Masuk', '📤 Stok Keluar']
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
}
