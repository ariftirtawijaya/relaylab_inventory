<?php
// stock_reset.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

$success = '';
$errors = [];

// Ambil semua item + kategori + unit + stok berjalan
$items = $pdo->query("
    SELECT 
        i.id,
        i.name,
        c.name AS category_name,
        u.code AS unit_code,
        (
            COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='IN'),0)
            -
            COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='OUT'),0)
            +
            COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='ADJUST'),0)
        ) AS current_stock
    FROM items i
    JOIN categories c ON c.id = i.category_id
    JOIN units u ON u.id = i.unit_id
    ORDER BY c.name ASC, i.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Handle submit reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mode = $_POST['mode'] ?? 'single'; // single|bulk
    $note = trim($_POST['note'] ?? '');
    if ($note === '')
        $note = 'Stock opname reset';

    // ==========================
    // MODE: RESET 1 ITEM
    // ==========================
    if ($mode === 'single') {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $new_stock = $_POST['new_stock'] ?? null;

        if ($item_id <= 0)
            $errors[] = 'Pilih item.';
        if ($new_stock === null || $new_stock === '' || !is_numeric($new_stock))
            $errors[] = 'Stok baru harus angka.';
        $new_stock = (float) $new_stock;
        if ($new_stock < 0)
            $errors[] = 'Stok baru tidak boleh negatif.';

        if (!$errors) {
            // hitung stok sekarang
            $stmt = $pdo->prepare("
                SELECT 
                    i.name,
                    u.code AS unit_code,
                    (
                        COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='IN'),0)
                        -
                        COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='OUT'),0)
                        +
                        COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='ADJUST'),0)
                    ) AS current_stock
                FROM items i
                JOIN units u ON u.id = i.unit_id
                WHERE i.id = ?
                LIMIT 1
            ");
            $stmt->execute([$item_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $errors[] = 'Item tidak ditemukan.';
            } else {
                $current = (float) $row['current_stock'];
                $delta = $new_stock - $current;

                if (abs($delta) < 0.0000001) {
                    $success = "Tidak ada perubahan. Stok sudah sama dengan hasil opname.";
                } else {
                    $pdo->beginTransaction();
                    try {
                        $desc = $note . " | dari {$current} {$row['unit_code']} → {$new_stock} {$row['unit_code']}";
                        $ins = $pdo->prepare("
                            INSERT INTO stock_movements (item_id, movement_date, movement_type, stock_type, qty, description)
                            VALUES (?, NOW(), 'ADJUST', 'GOOD', ?, ?)
                        ");
                        $ins->execute([$item_id, $delta, $desc]);

                        $pdo->commit();
                        $success = "✅ Reset stok berhasil: *{$row['name']}* sekarang = {$new_stock} {$row['unit_code']}";
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $errors[] = "Gagal reset: " . $e->getMessage();
                    }
                }
            }
        }
    }

    // ==========================
    // MODE: BULK RESET (SEMUA ITEM)
    // Input dari textarea: id=stok_baru per baris
    // contoh:
    // 12=100
    // 15=0
    // ==========================
    if ($mode === 'bulk') {
        $bulk = trim($_POST['bulk_data'] ?? '');
        if ($bulk === '')
            $errors[] = 'Bulk data kosong.';

        if (!$errors) {
            $lines = preg_split("/\r\n|\n|\r/", $bulk);
            $pairs = [];

            foreach ($lines as $ln) {
                $ln = trim($ln);
                if ($ln === '')
                    continue;

                // format id=stok
                if (!str_contains($ln, '='))
                    continue;
                [$idStr, $stokStr] = array_map('trim', explode('=', $ln, 2));

                if ($idStr === '' || $stokStr === '')
                    continue;
                if (!ctype_digit($idStr))
                    continue;
                if (!is_numeric($stokStr))
                    continue;

                $id = (int) $idStr;
                $stok = (float) $stokStr;
                if ($id > 0 && $stok >= 0) {
                    $pairs[$id] = $stok; // terakhir menang
                }
            }

            if (!$pairs) {
                $errors[] = 'Format bulk tidak valid. Gunakan format per baris: id=stok_baru';
            } else {
                $pdo->beginTransaction();
                try {
                    // prepare query stok sekarang
                    $qCur = $pdo->prepare("
                        SELECT 
                            i.id,
                            i.name,
                            u.code AS unit_code,
                            (
                                COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='IN'),0)
                                -
                                COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='OUT'),0)
                                +
                                COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.item_id=i.id AND sm.movement_type='ADJUST'),0)
                            ) AS current_stock
                        FROM items i
                        JOIN units u ON u.id = i.unit_id
                        WHERE i.id = ?
                        LIMIT 1
                    ");

                    $ins = $pdo->prepare("
                        INSERT INTO stock_movements (item_id, movement_date, movement_type, stock_type, qty, description)
                        VALUES (?, NOW(), 'ADJUST', 'GOOD', ?, ?)
                    ");

                    $changed = 0;

                    foreach ($pairs as $item_id => $new_stock) {
                        $qCur->execute([$item_id]);
                        $row = $qCur->fetch(PDO::FETCH_ASSOC);
                        if (!$row)
                            continue;

                        $current = (float) $row['current_stock'];
                        $delta = $new_stock - $current;

                        if (abs($delta) < 0.0000001)
                            continue;

                        $desc = $note . " | dari {$current} {$row['unit_code']} → {$new_stock} {$row['unit_code']}";
                        $ins->execute([$item_id, $delta, $desc]);
                        $changed++;
                    }

                    $pdo->commit();
                    $success = "✅ Bulk reset selesai. Total item berubah: {$changed}";
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = "Gagal bulk reset: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="row mb-3">
    <div class="col-12">
        <h1 class="h4">Reset Stok (Stock Opname)</h1>
        <p class="text-muted mb-0">
            Reset stok dilakukan dengan membuat mutasi <strong>ADJUST</strong> agar tetap tercatat di log (audit trail).
        </p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success py-2">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li>
                    <?= htmlspecialchars($e) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- SINGLE -->
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header"><strong>Reset 1 Item</strong></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="mode" value="single">

                    <div class="mb-2">
                        <label class="form-label">Item</label>
                        <select name="item_id" class="form-select form-select-sm select2-item" required>
                            <option value="">-- Pilih Item --</option>
                            <?php foreach ($items as $it): ?>
                                <option value="<?= $it['id'] ?>">
                                    <?= htmlspecialchars($it['category_name']) ?> —
                                    <?= htmlspecialchars($it['name']) ?>
                                    (
                                    <?= htmlspecialchars($it['unit_code']) ?>) | stok:
                                    <?= rtrim(rtrim(number_format($it['current_stock'], 2, '.', ''), '0'), '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Stok Baru (hasil opname)</label>
                        <input type="number" step="0.01" min="0" name="new_stock" class="form-control form-control-sm"
                            required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Catatan Log (opsional)</label>
                        <input type="text" name="note" class="form-control form-control-sm"
                            placeholder="contoh: Opname Gudang / Opname Supplier / koreksi stok">
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-warning btn-sm"
                            onclick="return confirm('Reset stok item ini? Sistem akan membuat log ADJUST.')">
                            Reset Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- BULK -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header"><strong>Bulk Reset (banyak item)</strong></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="mode" value="bulk">

                    <div class="mb-2">
                        <label class="form-label">Bulk Data</label>
                        <textarea name="bulk_data" class="form-control form-control-sm" rows="10"
                            placeholder="Format per baris: id=stok_baru&#10;contoh:&#10;12=100&#10;15=0&#10;20=35.5"
                            required></textarea>
                        <div class="form-text">
                            Tip: export dari Excel lalu paste ke sini (id=stok).
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Catatan Log (opsional)</label>
                        <input type="text" name="note" class="form-control form-control-sm"
                            placeholder="contoh: Opname Mingguan / Reset awal bulan">
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-danger btn-sm"
                            onclick="return confirm('Bulk reset akan membuat banyak log ADJUST. Lanjutkan?')">
                            Jalankan Bulk Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="alert alert-info mt-3 mb-0">
            <strong>Kenapa pakai ADJUST?</strong><br>
            Karena histori IN/OUT tetap utuh, tapi stok bisa “diset ulang” tanpa menghapus jejak.
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
            jQuery('.select2-item').select2({
                width: '100%',
                placeholder: 'Cari barang...',
                allowClear: true
            });
        }
    });
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>