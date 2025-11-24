<?php
// stock_in_pack.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

$success = false;
$errors = [];

// Ambil list package
$packages = $pdo->query("
    SELECT id, name
    FROM purchase_packages
    ORDER BY name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = (int) ($_POST['package_id'] ?? 0);
    $qty_pack = (float) ($_POST['qty_pack'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($package_id <= 0) {
        $errors[] = 'Pilih jenis pack.';
    }
    if ($qty_pack <= 0) {
        $errors[] = 'Jumlah pack harus lebih besar dari 0.';
    }

    if (!$errors) {
        // Ambil isi pack
        $stmt = $pdo->prepare("
            SELECT pc.item_id, pc.qty_per_package, i.name AS item_name
            FROM package_components pc
            JOIN items i ON i.id = pc.item_id
            WHERE pc.package_id = ?
        ");
        $stmt->execute([$package_id]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            $errors[] = 'Pack ini belum memiliki komponen.';
        } else {
            // Insert stok IN untuk tiap komponen
            $pdo->beginTransaction();
            try {
                foreach ($rows as $r) {
                    $totalQty = $r['qty_per_package'] * $qty_pack;

                    $stmtIns = $pdo->prepare("
                        INSERT INTO stock_movements
                            (item_id, movement_type, stock_type, qty, description)
                        VALUES
                            (:item_id, 'IN', 'GOOD', :qty, :desc)
                    ");

                    $desc = $description !== ''
                        ? $description
                        : "Pembelian {$qty_pack} pack (auto)";

                    $stmtIns->execute([
                        ':item_id' => $r['item_id'],
                        ':qty' => $totalQty,
                        ':desc' => $desc . " - item: " . $r['item_name'],
                    ]);
                }
                $pdo->commit();
                $success = true;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Terjadi kesalahan saat menyimpan: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="row mb-3">
    <div class="col-12">
        <h1 class="h4">Barang Masuk (via PACK)</h1>
        <p class="text-muted mb-0">
            Untuk pembelian soket tipe pack (H11, HB3, H4, soket sikring) yang isinya otomatis dipecah menjadi housing +
            skun + karet seal.
        </p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-sm py-2">
        Stok berhasil ditambahkan berdasarkan pack yang dipilih.
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <strong>Form Barang Masuk (Pack)</strong>
    </div>
    <div class="card-body">
        <?php if (!$packages): ?>
            <div class="alert alert-warning">
                Belum ada definisi PACK di database. Buat dulu di tabel <code>purchase_packages</code>.
            </div>
        <?php else: ?>
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Jenis Pack</label>
                        <select name="package_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Pack --</option>
                            <?php foreach ($packages as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Jumlah Pack</label>
                        <input type="number" step="0.01" name="qty_pack" class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Keterangan (opsional)</label>
                        <input type="text" name="description" class="form-control form-control-sm"
                            placeholder="Contoh: beli dari supplier X, nota #123">
                    </div>
                </div>

                <div class="mt-3 d-grid">
                    <button type="submit" class="btn btn-success btn-sm">Tambah Stok via PACK</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/partials/footer.php';
