<?php
// stock_log.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

// Ambil 100 mutasi terakhir (bisa diubah)
$stmt = $pdo->query("
    SELECT sm.id,
           sm.movement_date,
           sm.movement_type,
           sm.stock_type,
           sm.qty,
           sm.description,
           i.name AS item_name,
           c.name AS category_name,
           u.code AS unit_code
    FROM stock_movements sm
    JOIN items i      ON i.id = sm.item_id
    JOIN categories c ON c.id = i.category_id
    JOIN units u      ON u.id = i.unit_id
    ORDER BY sm.movement_date DESC, sm.id DESC
    LIMIT 100
");
$logs = $stmt->fetchAll();
?>

<div class="row mb-3">
    <div class="col-12">
        <h1 class="h4">Log Mutasi Stok</h1>
        <p class="text-muted mb-0">
            Menampilkan 100 pergerakan stok terakhir (IN / OUT / ADJUST).
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Riwayat Stok Terbaru</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width:60px;">No</th>
                        <th>Waktu</th>
                        <th>Barang</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th>Jenis</th>
                        <th>Stok</th>
                        <th class="text-end">Qty</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php $no = 1;
                        foreach ($logs as $log): ?>
                            <?php
                            $badgeType = $log['movement_type'] === 'IN' ? 'success' :
                                ($log['movement_type'] === 'OUT' ? 'danger' : 'secondary');
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= htmlspecialchars($log['movement_date']) ?></td>
                                <td><?= htmlspecialchars($log['item_name']) ?></td>
                                <td><?= htmlspecialchars($log['category_name']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($log['unit_code']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $badgeType ?>">
                                        <?= htmlspecialchars($log['movement_type']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-dark">
                                        <?= htmlspecialchars($log['stock_type']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?= rtrim(rtrim(number_format($log['qty'], 2, '.', ''), '0'), '.') ?>
                                </td>
                                <td><?= htmlspecialchars($log['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!$logs): ?>
                <div class="p-3 text-center text-muted">
                    Belum ada mutasi stok.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/partials/footer.php';
