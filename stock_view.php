<?php
// stock_view.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

// Query stok per item per jenis stok
$sql = "
SELECT
  i.id,
  i.name,
  i.min_stock,
  c.name AS category_name,
  u.code AS unit_code,
  COALESCE(SUM(
    IF(sm.stock_type = 'GOOD',
       IF(sm.movement_type = 'IN', sm.qty,
          IF(sm.movement_type = 'OUT', -sm.qty, sm.qty)
       ),
       0)
  ),0) AS stock_good,
  COALESCE(SUM(
    IF(sm.stock_type = 'REJECT',
       IF(sm.movement_type = 'IN', sm.qty,
          IF(sm.movement_type = 'OUT', -sm.qty, sm.qty)
       ),
       0)
  ),0) AS stock_reject,
  COALESCE(SUM(
    IF(sm.stock_type = 'WASTE',
       IF(sm.movement_type = 'IN', sm.qty,
          IF(sm.movement_type = 'OUT', -sm.qty, sm.qty)
       ),
       0)
  ),0) AS stock_waste
FROM items i
JOIN categories c ON c.id = i.category_id
JOIN units u      ON u.id = i.unit_id
LEFT JOIN stock_movements sm ON sm.item_id = i.id
GROUP BY i.id, i.name, i.min_stock, c.name, u.code
ORDER BY c.name, i.name
";


$rows = $pdo->query($sql)->fetchAll();
?>

<div class="row mb-3">
    <div class="col-12">
        <h1 class="h4">Stok Bahan</h1>
        <p class="text-muted mb-0">
            Rekap stok saat ini per item, dibagi per jenis stok: GOOD, REJECT, WASTE.
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Daftar Stok</strong>
        <span class="badge bg-secondary"><?= count($rows) ?> item</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width:60px;">No</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th class="text-end">Min</th>
                        <th class="text-end">GOOD</th>
                        <th class="text-end">REJECT</th>
                        <th class="text-end">WASTE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php $no = 1;
                        foreach ($rows as $r): ?>
                            <?php
                            $min = (float) $r['min_stock'];
                            $good = (float) $r['stock_good'];
                            $isLow = ($min > 0 && $good < $min);
                            $isMin = ($min > 0 && $good == $min);
                            ?>
                            <tr class="<?= $isLow ? 'table-danger' : ($isMin ? 'table-warning' : '') ?>">
                                <td class="text-center"><?= $no++ ?></td>
                                <td>
                                    <a href="stock_card.php?item_id=<?= $r['id'] ?>">
                                        <?= htmlspecialchars($r['name']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($r['category_name']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($r['unit_code']) ?></td>
                                <td class="text-end">
                                    <?= rtrim(rtrim(number_format($r['min_stock'], 2, '.', ''), '0'), '.') ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?= rtrim(rtrim(number_format($r['stock_good'], 2, '.', ''), '0'), '.') ?>
                                </td>
                                <td class="text-end text-warning">
                                    <?= rtrim(rtrim(number_format($r['stock_reject'], 2, '.', ''), '0'), '.') ?>
                                </td>
                                <td class="text-end text-danger">
                                    <?= rtrim(rtrim(number_format($r['stock_waste'], 2, '.', ''), '0'), '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!$rows): ?>
                <div class="p-3 text-center text-muted">
                    Belum ada data stok.
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/partials/footer.php';
