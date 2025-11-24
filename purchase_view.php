<?php
require_once __DIR__ . '/config/db.php';

$id = (int) ($_GET['id'] ?? 0);
$po = null;
$lines = [];

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            s.name AS supplier_name,
            s.type AS supplier_type,
            s.phone AS supplier_phone
        FROM purchases p
        JOIN suppliers s ON s.id = p.supplier_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($po) {
        $stmt2 = $pdo->prepare("
            SELECT 
                pi.*,
                i.name AS item_name,
                c.name AS category_name,
                u.code AS unit_code
            FROM purchase_items pi
            JOIN items i ON i.id = pi.item_id
            JOIN categories c ON c.id = i.category_id
            JOIN units u ON u.id = i.unit_id
            WHERE pi.purchase_id = ?
        ");
        $stmt2->execute([$id]);
        $lines = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = 'Detail Purchase Order';
require_once __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Detail Purchase Order</h4>
        <?php if ($po): ?>
            <small class="text-muted">No: <?= htmlspecialchars($po['po_number']) ?></small>
        <?php endif; ?>
    </div>
    <a href="purchases.php" class="btn btn-sm btn-outline-secondary">← Kembali ke PO</a>
</div>

<?php if (!$po): ?>
    <div class="alert alert-warning">
        Purchase order tidak ditemukan.
    </div>
<?php else: ?>

    <?php
    $badge = 'secondary';
    if ($po['status'] === 'RECEIVED')
        $badge = 'success';
    elseif ($po['status'] === 'CANCELLED')
        $badge = 'danger';
    ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div><strong>Supplier / Toko:</strong> <?= htmlspecialchars($po['supplier_name']) ?></div>
                    <div><strong>Jenis:</strong> <?= htmlspecialchars($po['supplier_type']) ?></div>
                    <?php if ($po['supplier_type'] === 'Offline' && !empty($po['supplier_phone'])): ?>
                        <div><strong>Nomor HP:</strong> <?= htmlspecialchars($po['supplier_phone']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <div><strong>Tanggal PO:</strong> <?= htmlspecialchars($po['po_date']) ?></div>
                    <div>
                        <strong>Status:</strong>
                        <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($po['status']) ?></span>
                    </div>
                    <div><strong>Dibuat:</strong> <?= htmlspecialchars($po['created_at']) ?></div>
                </div>
                <div class="col-md-4">
                    <div><strong>Subtotal Barang:</strong> Rp <?= number_format($po['total_amount'], 0, ',', '.') ?></div>
                    <div><strong>Total Dibayar:</strong> Rp <?= number_format($po['grand_total'], 0, ',', '.') ?></div>
                    <?php if (!empty($po['notes'])): ?>
                        <div class="small text-muted mt-1">Catatan: <?= nl2br(htmlspecialchars($po['notes'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Breakdown Biaya -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header py-2">
            <span class="fw-semibold">Rincian Biaya & Diskon</span>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td>Subtotal Barang</td>
                                <td class="text-end">Rp <?= number_format($po['total_amount'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>+ Ongkir</td>
                                <td class="text-end">Rp <?= number_format($po['shipping_cost'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>+ Biaya Layanan / Fee</td>
                                <td class="text-end">Rp <?= number_format($po['service_fee'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>+/- Penyesuaian Lain</td>
                                <td class="text-end">Rp <?= number_format($po['adjustment'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>- Diskon Toko</td>
                                <td class="text-end">Rp <?= number_format($po['store_discount'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>- Diskon Platform / Voucher</td>
                                <td class="text-end">Rp <?= number_format($po['platform_discount'], 0, ',', '.') ?></td>
                            </tr>
                            <tr class="table-light fw-semibold">
                                <td>Total Dibayar</td>
                                <td class="text-end">Rp <?= number_format($po['grand_total'], 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Item -->
    <div class="card border-0 shadow-sm">
        <div class="card-header py-2">
            <span class="fw-semibold">Daftar Item</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr class="text-center">
                        <th style="width:60px;">No</th>
                        <th>Item</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Harga</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lines): ?>
                        <?php $no = 1;
                        foreach ($lines as $ln): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= htmlspecialchars($ln['item_name']) ?></td>
                                <td><?= htmlspecialchars($ln['category_name']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($ln['unit_code']) ?></td>
                                <td class="text-end">
                                    <?= rtrim(rtrim(number_format($ln['qty'], 4, '.', ''), '0'), '.') ?>
                                </td>
                                <td class="text-end">
                                    <?= number_format($ln['unit_price'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end">
                                    <?= number_format($ln['subtotal'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Tidak ada item.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php
require_once __DIR__ . '/partials/footer.php';
