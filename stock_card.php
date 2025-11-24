<?php
// require_once __DIR__ . '/config.php';
// require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/db.php';

// pastikan user sudah login
// require_login();

$item_id = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
$item = null;
$logs = [];

if ($item_id > 0) {
    // ambil info barang
    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.name,
            i.min_stock,
            i.notes,
            c.name AS category_name,
            u.code AS unit_code,
            u.name AS unit_name
        FROM items i
        JOIN categories c ON c.id = i.category_id
        JOIN units u ON u.id = i.unit_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        // ambil semua mutasi stok untuk barang ini
        $stmt2 = $pdo->prepare("
            SELECT 
                sm.id,
                sm.movement_date,
                sm.movement_type,
                sm.stock_type,
                sm.qty,
                sm.description
            FROM stock_movements sm
            WHERE sm.item_id = ?
            ORDER BY sm.movement_date ASC, sm.id ASC
        ");
        $stmt2->execute([$item_id]);
        $logs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = 'Kartu Stok';
require_once __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Kartu Stok</h4>
        <small class="text-muted">Histori mutasi stok per barang</small>
    </div>
    <div>
        <a href="stock_view.php" class="btn btn-sm btn-outline-secondary">← Kembali ke Stok Posisi</a>
    </div>
</div>

<?php if (!$item_id || !$item): ?>
    <div class="alert alert-warning">
        Barang tidak ditemukan atau belum dipilih.
        Silakan pilih barang dari halaman <a href="stock_view.php" class="alert-link">Stok Posisi</a>.
    </div>
<?php else: ?>

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="small text-muted">
                        Kategori: <?= htmlspecialchars($item['category_name']) ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small">
                        Satuan: <span class="fw-semibold"><?= htmlspecialchars($item['unit_code']) ?></span>
                        <span class="text-muted">(<?= htmlspecialchars($item['unit_name']) ?>)</span>
                    </div>
                    <div class="small">
                        Min. Stok:
                        <span class="fw-semibold">
                            <?= rtrim(rtrim(number_format($item['min_stock'], 2, '.', ''), '0'), '.') ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4">
                    <?php if (!empty($item['notes'])): ?>
                        <div class="small text-muted">
                            Catatan: <?= htmlspecialchars($item['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    // hitung saldo berjalan per jenis stok
    $running_good = 0.0;
    $running_reject = 0.0;
    $running_waste = 0.0;

    // untuk tampilan akhir total
    ?>

    <div class="card">
        <div class="card-header py-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Histori Mutasi</span>
                <small class="text-muted">GOOD, REJECT, dan WASTE ditampilkan sebagai saldo berjalan</small>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width:60px;">No</th>
                        <th style="width:160px;">Waktu</th>
                        <th>Jenis</th>
                        <th>Stok</th>
                        <th class="text-end">Qty</th>
                        <th>Ket.</th>
                        <th class="text-end">Saldo GOOD</th>
                        <th class="text-end">Saldo REJECT</th>
                        <th class="text-end">Saldo WASTE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php $no = 1;
                        foreach ($logs as $log): ?>
                            <?php
                            $qty = (float) $log['qty'];
                            $factor = 1;
                            if ($log['movement_type'] === 'OUT') {
                                $factor = -1;
                            } elseif ($log['movement_type'] === 'ADJ_MINUS') {
                                $factor = -1;
                            } elseif ($log['movement_type'] === 'ADJ_PLUS') {
                                $factor = 1;
                            }
                            $delta = $factor * $qty;

                            switch ($log['stock_type']) {
                                case 'GOOD':
                                    $running_good += $delta;
                                    break;
                                case 'REJECT':
                                    $running_reject += $delta;
                                    break;
                                case 'WASTE':
                                    $running_waste += $delta;
                                    break;
                            }

                            $badgeType = 'secondary';
                            if ($log['movement_type'] === 'IN') {
                                $badgeType = 'success';
                            } elseif ($log['movement_type'] === 'OUT') {
                                $badgeType = 'danger';
                            }
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= htmlspecialchars($log['movement_date']) ?></td>
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
                                <td class="text-end">
                                    <?= rtrim(rtrim(number_format($running_good, 2, '.', ''), '0'), '.') ?>
                                </td>
                                <td class="text-end">
                                    <?= rtrim(rtrim(number_format($running_reject, 2, '.', ''), '0'), '.') ?>
                                </td>
                                <td class="text-end">
                                    <?= rtrim(rtrim(number_format($running_waste, 2, '.', ''), '0'), '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!$logs): ?>
                <div class="p-3 text-center text-muted">
                    Belum ada mutasi stok untuk barang ini.
                </div>
            <?php else: ?>
                <div class="border-top px-3 py-2 small">
                    <div class="row">
                        <div class="col-md-4">
                            <span class="text-muted">Saldo GOOD akhir:</span>
                            <span class="fw-semibold">
                                <?= rtrim(rtrim(number_format($running_good, 2, '.', ''), '0'), '.') ?>
                                <?= htmlspecialchars($item['unit_code']) ?>
                            </span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted">Saldo REJECT akhir:</span>
                            <span class="fw-semibold">
                                <?= rtrim(rtrim(number_format($running_reject, 2, '.', ''), '0'), '.') ?>
                                <?= htmlspecialchars($item['unit_code']) ?>
                            </span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted">Saldo WASTE akhir:</span>
                            <span class="fw-semibold">
                                <?= rtrim(rtrim(number_format($running_waste, 2, '.', ''), '0'), '.') ?>
                                <?= htmlspecialchars($item['unit_code']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php
require_once __DIR__ . '/partials/footer.php';
