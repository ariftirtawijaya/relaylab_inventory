<?php
require_once __DIR__ . '/config/db.php';


// ==============================
//  SUMMARY CARDS
// ==============================

// Total item
$stmt = $pdo->query("SELECT COUNT(*) FROM items");
$total_items = (int) $stmt->fetchColumn();

// Low stock: hitung stok GOOD vs min_stock
$sqlLow = "
    SELECT 
        t.id,
        t.name,
        t.min_stock,
        t.notes,
        t.category_name,
        t.unit_code,
        t.stock_good
    FROM (
        SELECT 
            i.id,
            i.name,
            i.min_stock,
            i.notes,
            c.name AS category_name,
            u.code AS unit_code,
            COALESCE(SUM(
                CASE 
                    WHEN sm.stock_type = 'GOOD' 
                         AND sm.movement_type IN ('IN', 'ADJ_PLUS') THEN sm.qty
                    WHEN sm.stock_type = 'GOOD' 
                         AND sm.movement_type IN ('OUT', 'ADJ_MINUS') THEN -sm.qty
                    ELSE 0
                END
            ), 0) AS stock_good
        FROM items i
        JOIN categories c ON c.id = i.category_id
        JOIN units u ON u.id = i.unit_id
        LEFT JOIN stock_movements sm ON sm.item_id = i.id
        GROUP BY 
            i.id,
            i.name,
            i.min_stock,
            i.notes,
            c.name,
            u.code
    ) AS t
    WHERE t.min_stock > 0 
      AND t.stock_good < t.min_stock
    ORDER BY t.stock_good / NULLIF(t.min_stock, 0) ASC, t.name
";

$low_stmt = $pdo->query($sqlLow);
$low_items = $low_stmt->fetchAll(PDO::FETCH_ASSOC);
$low_count = count($low_items);

// Hari ini
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

// Total transaksi hari ini (semua mutasi stok)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE movement_date BETWEEN ? AND ?");
$stmt->execute([$todayStart, $todayEnd]);
$today_transactions = (int) $stmt->fetchColumn();

// Produksi hari ini
$today_production_qty = 0;
if ($pdo->query("SHOW TABLES LIKE 'productions'")->rowCount() > 0) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qty),0) 
        FROM productions 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$todayStart, $todayEnd]);
    $today_production_qty = (float) $stmt->fetchColumn();
}

// ==============================
//  RECENT ACTIVITY (10 terakhir)
// ==============================

$recent_stmt = $pdo->query("
    SELECT 
        sm.id,
        sm.movement_date,
        sm.movement_type,
        sm.stock_type,
        sm.qty,
        sm.description,
        i.name AS item_name,
        c.name AS category_name,
        u.code AS unit_code
    FROM stock_movements sm
    JOIN items i ON i.id = sm.item_id
    JOIN categories c ON c.id = i.category_id
    JOIN units u ON u.id = i.unit_id
    ORDER BY sm.movement_date DESC, sm.id DESC
    LIMIT 10
");
$recent_logs = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// ==============================
//  CHART DATA: PEMAKAIAN 7 HARI
// ==============================

// Pemakaian bahan 7 hari terakhir per kategori (OUT + ADJ_MINUS, GOOD)
$usage_rows = [];
$usage_stmt = $pdo->query("
    SELECT 
        c.name AS category_name,
        SUM(
            CASE 
                WHEN sm.stock_type = 'GOOD' 
                     AND sm.movement_type IN ('OUT', 'ADJ_MINUS') 
                THEN sm.qty 
                ELSE 0 
            END
        ) AS qty_out
    FROM stock_movements sm
    JOIN items i ON i.id = sm.item_id
    JOIN categories c ON c.id = i.category_id
    WHERE sm.stock_type = 'GOOD'
      AND sm.movement_type IN ('OUT', 'ADJ_MINUS')
      AND sm.movement_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY c.id, c.name
    HAVING qty_out > 0
    ORDER BY qty_out DESC
");
$usage_rows = $usage_stmt->fetchAll(PDO::FETCH_ASSOC);

$usage_labels = [];
$usage_data = [];
foreach ($usage_rows as $row) {
    $usage_labels[] = $row['category_name'];
    $usage_data[] = (float) $row['qty_out'];
}

// ==============================
//  CHART DATA: PRODUKSI 7 HARI
// ==============================

$prod_labels = [];
$prod_data = [];

if ($pdo->query("SHOW TABLES LIKE 'productions'")->rowCount() > 0) {
    // buat template 7 hari terakhir
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} day"));
        $days[$d] = 0.0;
    }

    $prod_stmt = $pdo->query("
        SELECT DATE(created_at) AS d, SUM(qty) AS total_qty
        FROM productions
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY d ASC
    ");
    $prod_rows = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($prod_rows as $row) {
        $d = $row['d'];
        if (isset($days[$d])) {
            $days[$d] = (float) $row['total_qty'];
        }
    }

    foreach ($days as $d => $qty) {
        $prod_labels[] = $d;
        $prod_data[] = $qty;
    }
}

$page_title = 'Dashboard';
require_once __DIR__ . '/partials/header.php';
?>

<div class="row g-3 mb-3">
    <!-- Card: Total Item -->
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted mb-1">Total Item</div>
                <div class="h4 mb-0"><?= number_format($total_items) ?></div>
            </div>
        </div>
    </div>

    <!-- Card: Item Low Stock -->
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted mb-1">Item Low Stock</div>
                <div class="h4 mb-0"><?= number_format($low_count) ?></div>
                <div class="small text-muted">Min &gt; 0 dan stok GOOD &lt; Min</div>
            </div>
        </div>
    </div>

    <!-- Card: Transaksi Hari Ini -->
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted mb-1">Transaksi Stok Hari Ini</div>
                <div class="h4 mb-0"><?= number_format($today_transactions) ?></div>
                <div class="small text-muted"><?= date('d-m-Y') ?></div>
            </div>
        </div>
    </div>

    <!-- Card: Produksi Hari Ini -->
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted mb-1">Produksi Hari Ini</div>
                <div class="h4 mb-0">
                    <?= rtrim(rtrim(number_format($today_production_qty, 2, '.', ''), '0'), '.') ?>
                </div>
                <div class="small text-muted">Total qty dari tabel produksi</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Low Stock Table -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Item Low Stock</span>
                <a href="stock_view.php" class="small text-decoration-none">Lihat semua &raquo;</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width:60px;">No</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Satuan</th>
                            <th class="text-end">Min</th>
                            <th class="text-end">GOOD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($low_items): ?>
                            <?php $no = 1;
                            foreach ($low_items as $r): ?>
                                <tr class="table-danger">
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!$low_items): ?>
                    <div class="p-3 text-center text-muted">
                        Tidak ada item yang low stock.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Aktivitas Stok Terbaru</span>
                <a href="stock_log.php" class="small text-decoration-none">Lihat log &raquo;</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width:60px;">No</th>
                            <th>Waktu</th>
                            <th>Barang</th>
                            <th>Jenis</th>
                            <th>Stok</th>
                            <th class="text-end">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_logs): ?>
                            <?php $no = 1;
                            foreach ($recent_logs as $log): ?>
                                <?php
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
                                    <td><?= htmlspecialchars($log['item_name']) ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!$recent_logs): ?>
                    <div class="p-3 text-center text-muted">
                        Belum ada mutasi stok.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Chart: Pemakaian per Kategori -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2">
                <span class="fw-semibold">Pemakaian Bahan 7 Hari Terakhir (per Kategori)</span>
            </div>
            <div class="card-body">
                <canvas id="usageChart" height="180"></canvas>
                <?php if (!$usage_labels): ?>
                    <div class="small text-muted mt-2">Belum ada data pemakaian (OUT) dalam 7 hari terakhir.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chart: Produksi 7 Hari Terakhir -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2">
                <span class="fw-semibold">Produksi 7 Hari Terakhir</span>
            </div>
            <div class="card-body">
                <canvas id="productionChart" height="180"></canvas>
                <?php if (!$prod_labels): ?>
                    <div class="small text-muted mt-2">Belum ada data produksi dalam 7 hari terakhir.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js khusus halaman ini -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Data dari PHP
        var usageLabels = <?= json_encode($usage_labels) ?>;
        var usageData = <?= json_encode($usage_data) ?>;
        var prodLabels = <?= json_encode($prod_labels) ?>;
        var prodData = <?= json_encode($prod_data) ?>;

        // Chart Pemakaian per Kategori
        var usageCanvas = document.getElementById('usageChart');
        if (usageCanvas && usageLabels.length > 0) {
            new Chart(usageCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: usageLabels,
                    datasets: [{
                        label: 'Qty OUT (GOOD) 7 hari',
                        data: usageData
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { autoSkip: false } },
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Chart Produksi 7 hari
        var prodCanvas = document.getElementById('productionChart');
        if (prodCanvas && prodLabels.length > 0) {
            new Chart(prodCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: prodLabels,
                    datasets: [{
                        label: 'Qty Produksi',
                        data: prodData,
                        tension: 0.2,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/partials/footer.php';
