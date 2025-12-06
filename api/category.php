<?php
require_once "../config/db.php";

header("Content-Type: application/json; charset=utf-8");

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["error" => true, "message" => "Invalid category id"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
$stmt->execute([$id]);
$cat = $stmt->fetch();

if (!$cat) {
    echo json_encode(["error" => true, "message" => "Kategori tidak ditemukan"]);
    exit;
}

echo json_encode([
    "id" => (int) $cat['id'],
    "name" => $cat['name'],
]);
