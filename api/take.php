<?php
require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$id = (int) ($data['id'] ?? 0);
$qty = (float) ($data['qty'] ?? 0);

if ($id <= 0 || $qty <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$ins = $pdo->prepare("
INSERT INTO stock_movements 
(item_id, movement_type, stock_type, qty, description)
VALUES (?, 'OUT', 'GOOD', ?, '')
");
$ins->execute([$id, $qty]);

echo json_encode(["success" => true]);
