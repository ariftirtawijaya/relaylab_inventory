<?php
require_once "../config/db.php";

$id = (int) ($_GET['id'] ?? 0);

$q = $pdo->prepare("
SELECT 
  i.id, 
  i.name, 
  c.name AS category,
  u.code AS unit,
  COALESCE(SUM(
    IF(sm.stock_type='GOOD',
      IF(sm.movement_type='IN', sm.qty,
      IF(sm.movement_type='OUT', -sm.qty, sm.qty)),
    0)
  ),0) AS stock_good
FROM items i
JOIN categories c ON c.id = i.category_id
JOIN units u ON u.id = i.unit_id
LEFT JOIN stock_movements sm ON sm.item_id = i.id
WHERE i.id = ?
GROUP BY i.id
");
$q->execute([$id]);
$item = $q->fetch();

header("Content-Type: application/json");
echo json_encode($item ?: null);
