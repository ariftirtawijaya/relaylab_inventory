<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../config/db.php'; // akses MySQL RelayLab

function handleText($chat_id, $text)
{
    $state = getState($chat_id);

    if (!$state) {
        sendMessage($chat_id, "Perintah tidak dikenal. Tekan /menu untuk memulai.");
        return;
    }

    // === CEK STOK ===
    if ($state['state'] === 'cek_stok_waiting_name') {
        clearState($chat_id);

        $stmt = $GLOBALS['pdo']->prepare("
            SELECT i.name, 
                COALESCE(SUM(CASE WHEN sm.movement_type='IN' THEN sm.qty ELSE 0 END),0) -
                COALESCE(SUM(CASE WHEN sm.movement_type='OUT' THEN sm.qty ELSE 0 END),0)
                AS stock,
                i.min_stock,
                u.code
            FROM items i
            LEFT JOIN stock_movements sm ON sm.item_id = i.id
            JOIN units u ON u.id = i.unit_id
            WHERE i.name LIKE ?
            GROUP BY i.id
            LIMIT 1
        ");

        $stmt->execute(["%$text%"]);
        $item = $stmt->fetch();

        if (!$item) {
            sendMessage($chat_id, "❌ Barang tidak ditemukan.");
            return;
        }

        $status = ($item['stock'] < $item['min_stock']) ? "⚠ *KURANG*" : "✔ Cukup";

        sendMessage(
            $chat_id,
            "*{$item['name']}*\n" .
            "📦 Stok: *{$item['stock']}* {$item['code']}\n" .
            "📉 Minimum: {$item['min_stock']}\n" .
            "Status: $status"
        );
    }

}
