<?php
require_once __DIR__ . '/config/db.php';

// Ambil supplier & items untuk form
$supStmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC");
$suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);

$itemStmt = $pdo->query("
    SELECT i.id, i.name, c.name AS category_name, u.code AS unit_code
    FROM items i
    JOIN categories c ON c.id = i.category_id
    JOIN units u ON u.id = i.unit_id
    ORDER BY i.name ASC
");
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Buat PO baru
    if ($action === 'create_po') {
        $po_date = $_POST['po_date'] ?? date('Y-m-d');
        $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        // Ambil array item dari form (PARALEL: index-ke-i = 1 baris)
        $item_ids = $_POST['item_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $prices = $_POST['unit_price'] ?? [];

        // Biaya & diskon
        $shipping_cost = (float) ($_POST['shipping_cost'] ?? 0);
        $service_fee = (float) ($_POST['service_fee'] ?? 0);
        $store_discount = (float) ($_POST['store_discount'] ?? 0);
        $platform_discount = (float) ($_POST['platform_discount'] ?? 0);
        $adjustment = (float) ($_POST['adjustment'] ?? 0);

        if ($supplier_id > 0 && !empty($item_ids)) {
            // generate po_number sederhana: PO-YYYYMMDD-XXX
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE po_date = ?");
            $stmt->execute([$po_date]);
            $countToday = (int) $stmt->fetchColumn() + 1;
            $po_number = 'PO-' . date('Ymd', strtotime($po_date)) . '-' . str_pad($countToday, 3, '0', STR_PAD_LEFT);

            $pdo->beginTransaction();
            try {
                // Insert header dulu dengan total 0, nanti diupdate
                $stmt = $pdo->prepare("
                    INSERT INTO purchases (
                        po_number, supplier_id, po_date, status, notes,
                        total_amount, shipping_cost, service_fee,
                        store_discount, platform_discount, adjustment, grand_total
                    )
                    VALUES (?, ?, ?, 'DRAFT', ?, 0, 0, 0, 0, 0, 0, 0)
                ");
                $stmt->execute([
                    $po_number,
                    $supplier_id,
                    $po_date,
                    $notes
                ]);
                $purchase_id = (int) $pdo->lastInsertId();

                $total_items = 0;
                $stmtItem = $pdo->prepare("
                    INSERT INTO purchase_items (purchase_id, item_id, qty, unit_price, subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $rowCount = count($item_ids);
                for ($i = 0; $i < $rowCount; $i++) {
                    $item_id = (int) ($item_ids[$i] ?? 0);
                    $qty = (float) ($qtys[$i] ?? 0);
                    $unit_price = (float) ($prices[$i] ?? 0);

                    if ($item_id > 0 && $qty > 0) {
                        $subtotal = $qty * $unit_price;
                        $stmtItem->execute([$purchase_id, $item_id, $qty, $unit_price, $subtotal]);
                        $total_items += $subtotal;
                    }
                }

                // Hitung grand total
                $grand_total = $total_items
                    + $shipping_cost
                    + $service_fee
                    + $adjustment
                    - $store_discount
                    - $platform_discount;

                if ($grand_total < 0) {
                    $grand_total = 0;
                }

                // Update header dengan nilai final
                $stmtUpd = $pdo->prepare("
                    UPDATE purchases
                    SET total_amount = ?,
                        shipping_cost = ?,
                        service_fee = ?,
                        store_discount = ?,
                        platform_discount = ?,
                        adjustment = ?,
                        grand_total = ?
                    WHERE id = ?
                ");
                $stmtUpd->execute([
                    $total_items,
                    $shipping_cost,
                    $service_fee,
                    $store_discount,
                    $platform_discount,
                    $adjustment,
                    $grand_total,
                    $purchase_id
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        header('Location: purchases.php');
        exit;
    }

    // Terima PO -> masukan ke stok (qty tetap berdasarkan detail, biaya tidak mempengaruhi stok)
    if ($action === 'receive_po') {
        $purchase_id = (int) ($_POST['purchase_id'] ?? 0);
        if ($purchase_id > 0) {
            // Ambil header
            $stmt = $pdo->prepare("
                SELECT p.*, s.name AS supplier_name
                FROM purchases p
                JOIN suppliers s ON s.id = p.supplier_id
                WHERE p.id = ?
                LIMIT 1
            ");
            $stmt->execute([$purchase_id]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($po && $po['status'] === 'DRAFT') {
                // Ambil lines
                $stmt2 = $pdo->prepare("
                    SELECT pi.*, i.name AS item_name
                    FROM purchase_items pi
                    JOIN items i ON i.id = pi.item_id
                    WHERE pi.purchase_id = ?
                ");
                $stmt2->execute([$purchase_id]);
                $lines = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                if ($lines) {
                    $pdo->beginTransaction();
                    try {
                        // Insert ke stock_movements
                        $desc = 'PO ' . $po['po_number'] . ' - ' . $po['supplier_name'];
                        $stmv = $pdo->prepare("
                            INSERT INTO stock_movements
                                (item_id, movement_date, movement_type, stock_type, qty, description)
                            VALUES
                                (?, NOW(), 'IN', 'GOOD', ?, ?)
                        ");

                        foreach ($lines as $ln) {
                            $qty = (float) $ln['qty'];
                            if ($qty > 0) {
                                $stmv->execute([$ln['item_id'], $qty, $desc]);
                            }
                        }

                        // Update status PO
                        $upd = $pdo->prepare("UPDATE purchases SET status = 'RECEIVED' WHERE id = ?");
                        $upd->execute([$purchase_id]);

                        $pdo->commit();
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                }
            }
        }

        header('Location: purchases.php');
        exit;
    }
}

// Delete PO (hanya DRAFT)
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT status FROM purchases WHERE id = ?");
        $stmt->execute([$id]);
        $st = $stmt->fetchColumn();
        if ($st === 'DRAFT') {
            $pdo->prepare("DELETE FROM purchases WHERE id = ?")->execute([$id]);
        }
    }
    header('Location: purchases.php');
    exit;
}

// Ambil daftar PO
$poStmt = $pdo->query("
    SELECT 
        p.*,
        s.name AS supplier_name
    FROM purchases p
    JOIN suppliers s ON s.id = p.supplier_id
    ORDER BY p.po_date DESC, p.id DESC
");
$pos = $poStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Pembelian (PO)';
require_once __DIR__ . '/partials/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header py-2">
                <span class="fw-semibold">Buat Purchase Order Baru</span>
            </div>
            <div class="card-body">
                <form method="post" id="poForm">
                    <input type="hidden" name="action" value="create_po">

                    <div class="mb-2">
                        <label class="form-label">Tanggal PO</label>
                        <input type="date" name="po_date" class="form-control form-control-sm"
                            value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select form-select-sm select2-supplier" required>
                            <option value="">- Pilih Supplier -</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">Item PO</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddRow">
                            + Tambah Baris
                        </button>
                    </div>

                    <div class="table-responsive mb-2">
                        <table class="table table-sm align-middle" id="poItemsTable">
                            <thead>
                                <tr class="text-center">
                                    <th style="width:40%;">Item</th>
                                    <th style="width:15%;">Qty</th>
                                    <th style="width:20%;">Harga</th>
                                    <th style="width:20%;">Subtotal</th>
                                    <th style="width:5%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- baris template akan ditambahkan via JS -->
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Biaya Ongkir</label>
                            <input type="number" step="0.01" min="0" name="shipping_cost" id="shipping_cost"
                                class="form-control form-control-sm cost-field" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Biaya Layanan / Fee</label>
                            <input type="number" step="0.01" min="0" name="service_fee" id="service_fee"
                                class="form-control form-control-sm cost-field" value="0">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Diskon Toko</label>
                            <input type="number" step="0.01" min="0" name="store_discount" id="store_discount"
                                class="form-control form-control-sm cost-field" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Diskon Platform / Voucher</label>
                            <input type="number" step="0.01" min="0" name="platform_discount" id="platform_discount"
                                class="form-control form-control-sm cost-field" value="0">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Penyesuaian Lain (+/-)</label>
                        <input type="number" step="0.01" name="adjustment" id="adjustment"
                            class="form-control form-control-sm cost-field" value="0">
                        <div class="form-text small">
                            Bisa plus atau minus, misalnya pembulatan pembayaran, koreksi kecil, dll.
                        </div>
                    </div>

                    <hr>

                    <div class="mb-1 d-flex justify-content-between">
                        <span class="small text-muted">Subtotal Barang:</span>
                        <span class="small fw-semibold" id="poSubtotal">0</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="small text-muted">Total Dibayar (Grand Total):</span>
                        <span class="fw-bold" id="poGrandTotal">0</span>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Simpan PO
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header py-2">
                <span class="fw-semibold">Daftar Purchase Order</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width:60px;">No</th>
                            <th>No. PO</th>
                            <th>Tanggal</th>
                            <th>Supplier</th>
                            <th class="text-end">Total Barang</th>
                            <th class="text-end">Total Dibayar</th>
                            <th>Status</th>
                            <th style="width:180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pos): ?>
                            <?php $no = 1;
                            foreach ($pos as $p): ?>
                                <?php
                                $badge = 'secondary';
                                if ($p['status'] === 'RECEIVED')
                                    $badge = 'success';
                                elseif ($p['status'] === 'CANCELLED')
                                    $badge = 'danger';
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($p['po_number']) ?></td>
                                    <td><?= htmlspecialchars($p['po_date']) ?></td>
                                    <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                                    <td class="text-end">
                                        <?= number_format($p['total_amount'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?= number_format($p['grand_total'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($p['status']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="purchase_view.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary">
                                                Detail
                                            </a>

                                            <?php if ($p['status'] === 'DRAFT'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="receive_po">
                                                    <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-success"
                                                        onclick="return confirm('Terima PO ini dan tambahkan ke stok?')">
                                                        Terima
                                                    </button>
                                                </form>
                                                <a href="purchases.php?delete=<?= $p['id'] ?>"
                                                    class="btn btn-outline-danger btn-delete"
                                                    data-message="Hapus PO ini? (Hanya boleh DRAFT)">
                                                    Hapus
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!$pos): ?>
                    <div class="p-3 text-center text-muted">
                        Belum ada purchase order.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var itemsData = <?= json_encode($items) ?>;

        var tbody = document.querySelector('#poItemsTable tbody');
        var btnAdd = document.getElementById('btnAddRow');
        var poSubtotalEl = document.getElementById('poSubtotal');
        var poGrandTotalEl = document.getElementById('poGrandTotal');

        var shippingEl = document.getElementById('shipping_cost');
        var serviceEl = document.getElementById('service_fee');
        var storeDiscEl = document.getElementById('store_discount');
        var platDiscEl = document.getElementById('platform_discount');
        var adjEl = document.getElementById('adjustment');

        function parseNum(v) {
            var n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        }

        function formatIDR(n) {
            return (n || 0).toLocaleString('id-ID');
        }

        function recalcTotals() {
            var subtotalItems = 0;
            tbody.querySelectorAll('tr').forEach(function (tr) {
                var qtyInput = tr.querySelector('.po-qty');
                var priceInput = tr.querySelector('.po-price');
                if (!qtyInput || !priceInput) return;

                var qty = parseNum(qtyInput.value);
                var price = parseNum(priceInput.value);
                var sub = qty * price;
                subtotalItems += sub;

                var subText = tr.querySelector('.po-subtotal-text');
                if (subText) {
                    subText.textContent = formatIDR(sub);
                }
            });

            var shipping = parseNum(shippingEl.value);
            var service = parseNum(serviceEl.value);
            var storeDisc = parseNum(storeDiscEl.value);
            var platDisc = parseNum(platDiscEl.value);
            var adj = parseNum(adjEl.value);

            var grand = subtotalItems + shipping + service + adj - storeDisc - platDisc;
            if (grand < 0) grand = 0;

            poSubtotalEl.textContent = formatIDR(subtotalItems);
            poGrandTotalEl.textContent = formatIDR(grand);
        }

        // -----------------------------
        // SELECT2 INITIALIZER
        // -----------------------------
        function initSelect2() {
            // Dropdown item
            $('.select2-item').select2({
                width: '100%',
                placeholder: 'Cari item...',
                allowClear: true
            });

            // Dropdown supplier
            $('.select2-supplier').select2({
                width: '100%',
                placeholder: 'Cari supplier...',
                allowClear: true
            });
        }


        // -----------------------------
        // CREATE ROW
        // -----------------------------
        function createRow() {
            var tr = document.createElement('tr');

            tr.innerHTML = `
                <td>
                    <select name="item_id[]" class="form-select form-select-sm select2-item" required>
                        <option value="">- Pilih Item -</option>
                        ${itemsData.map(function (it) {
                var label = it.name + ' (' + it.unit_code + ')';
                return '<option value="' + it.id + '">' + label.replace(/"/g, '&quot;') + '</option>';
            }).join('')}
                    </select>
                </td>
                <td>
                    <input type="number" name="qty[]" class="form-control form-control-sm po-qty text-end"
                        step="0.0001" min="0">
                </td>
                <td>
                    <input type="number" name="unit_price[]" class="form-control form-control-sm po-price text-end"
                        step="0.01" min="0">
                </td>
                <td class="text-end">
                    <span class="po-subtotal-text">0</span>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger po-remove-row">&times;</button>
                </td>
            `;

            tbody.appendChild(tr);
            initSelect2(); // ⬅ SELECT2 AKTIF OTOMATIS
        }

        btnAdd.addEventListener('click', function () {
            createRow();
        });

        tbody.addEventListener('input', function (e) {
            if (e.target.classList.contains('po-qty') || e.target.classList.contains('po-price')) {
                recalcTotals();
            }
        });

        tbody.addEventListener('click', function (e) {
            if (e.target.classList.contains('po-remove-row')) {
                e.preventDefault();
                var tr = e.target.closest('tr');
                if (tr) {
                    tr.remove();
                    recalcTotals();
                }
            }
        });

        document.querySelectorAll('.cost-field').forEach(function (inp) {
            inp.addEventListener('input', recalcTotals);
        });

        // Buat satu baris awal
        createRow();
        recalcTotals();
        initSelect2();
    });
</script>


<?php
require_once __DIR__ . '/partials/footer.php';
