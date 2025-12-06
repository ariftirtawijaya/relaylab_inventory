<?php
require_once "../config/db.php";

header("Content-Type: application/json; charset=utf-8");

$cid = (int) ($_GET['category_id'] ?? 0);

if ($cid <= 0) {
    echo json_encode(["error" => true, "message" => "Invalid category id"]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        i.id,
        i.name,
        u.code AS unit,
        u.name AS satuan,
        COALESCE(SUM(
            IF(sm.stock_type='GOOD',
                IF(sm.movement_type='IN', sm.qty,
                   IF(sm.movement_type='OUT', -sm.qty, sm.qty)
                ),
                0
            )
        ),0) AS stock_good
    FROM items i
    JOIN units u ON u.id = i.unit_id
    LEFT JOIN stock_movements sm ON sm.item_id = i.id
    WHERE i.category_id = ?
    GROUP BY i.id, i.name, u.code
    ORDER BY i.name ASC
");
$stmt->execute([$cid]);
$items = $stmt->fetchAll();

echo json_encode([
    "category_id" => $cid,
    "items" => $items ?: [],
]);
