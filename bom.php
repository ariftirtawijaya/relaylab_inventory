<?php
// bom.php - CRUD BOM
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

$errors = [];
$success = '';

// Ambil ID kategori PRODUK JADI
$catProdStmt = $pdo->prepare("SELECT id FROM categories WHERE name = 'PRODUK JADI' LIMIT 1");
$catProdStmt->execute();
$catProd = $catProdStmt->fetch();
$product_category_id = $catProd['id'] ?? null;

// Ambil list produk jadi
$products = [];
if ($product_category_id) {
    $stmt = $pdo->prepare("
        SELECT i.id, i.name
        FROM items i
        WHERE i.category_id = :cat_id
        ORDER BY i.name
    ");
    $stmt->execute([':cat_id' => $product_category_id]);
    $products = $stmt->fetchAll();
}

// Produk yang sedang dipilih
$selected_product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;

// Hapus baris BOM
if (isset($_GET['delete'])) {
    $bom_id = (int) $_GET['delete'];
    if ($bom_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM bom WHERE id = :id");
        try {
            $stmt->execute([':id' => $bom_id]);
            $success = 'Komponen BOM berhasil dihapus.';
        } catch (Throwable $e) {
            $errors[] = 'Gagal menghapus komponen BOM: ' . $e->getMessage();
        }
    }
}

// Handle tambah / update BOM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $selected_product_id = (int) ($_POST['product_id'] ?? 0);
        $component_id = (int) ($_POST['component_id'] ?? 0);
        $qty_per_unit = (float) ($_POST['qty_per_unit'] ?? 0);

        if ($selected_product_id <= 0) {
            $errors[] = 'Pilih produk.';
        }
        if ($component_id <= 0) {
            $errors[] = 'Pilih komponen.';
        }
        if ($qty_per_unit <= 0) {
            $errors[] = 'Qty per unit harus lebih besar dari 0.';
        }
        if ($component_id === $selected_product_id) {
            $errors[] = 'Komponen tidak boleh sama dengan produk.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
                INSERT INTO bom (product_item_id, component_item_id, qty_per_unit)
                VALUES (:pid, :cid, :qty)
            ");
            try {
                $stmt->execute([
                    ':pid' => $selected_product_id,
                    ':cid' => $component_id,
                    ':qty' => $qty_per_unit,
                ]);
                $success = 'Komponen BOM berhasil ditambahkan.';
                header('Location: bom.php?product_id=' . $selected_product_id);
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Gagal menambah komponen BOM: ' . $e->getMessage();
            }
        }

    } elseif ($action === 'update_bom') {
        $selected_product_id = (int) ($_POST['product_id'] ?? 0);
        $bom_id = (int) ($_POST['bom_id'] ?? 0);
        $qty_per_unit = (float) ($_POST['qty_per_unit'] ?? 0);

        if ($bom_id <= 0 || $qty_per_unit <= 0) {
            $errors[] = 'Data update BOM tidak valid.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE bom
                SET qty_per_unit = :qty
                WHERE id = :id
            ");
            try {
                $stmt->execute([
                    ':qty' => $qty_per_unit,
                    ':id' => $bom_id,
                ]);
                $success = 'Qty BOM berhasil diupdate.';
                header('Location: bom.php?product_id=' . $selected_product_id);
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Gagal mengupdate BOM: ' . $e->getMessage();
            }
        }
    }
}

// Ambil BOM untuk produk yang dipilih
$current_bom = [];
if ($selected_product_id > 0) {
    $stmt = $pdo->prepare("
        SELECT b.id,
               c.name AS component_name,
               cat.name AS component_category,
               u.code AS unit_code,
               b.qty_per_unit
        FROM bom b
        JOIN items c      ON c.id = b.component_item_id
        JOIN categories cat ON cat.id = c.category_id
        JOIN units u      ON u.id = c.unit_id
        WHERE b.product_item_id = :pid
        ORDER BY cat.name, c.name
    ");
    $stmt->execute([':pid' => $selected_product_id]);
    $current_bom = $stmt->fetchAll();
}

// Ambil semua item bahan (kecuali PRODUK JADI) untuk dropdown komponen
$components = $pdo->query("
    SELECT i.id, i.name, cat.name AS category_name, u.code AS unit_code
    FROM items i
    JOIN categories cat ON cat.id = i.category_id
    JOIN units u ON u.id = i.unit_id
    WHERE cat.name <> 'PRODUK JADI'
    ORDER BY cat.name, i.name
")->fetchAll();
?>

<div class="row mb-3">
    <div class="col-12">
        <h1 class="h4">BOM Produk</h1>
        <p class="text-muted mb-0">
            Atur kebutuhan bahan per 1 pcs produk jadi (Relay Set) dan bisa edit/hapus komponennya.
        </p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-sm py-2"><?= htmlspecialchars($success) ?></div>
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

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <strong>Pilih Produk</strong>
            </div>
            <div class="card-body">
                <?php if (!$product_category_id): ?>
                    <div class="alert alert-warning">
                        Kategori <code>PRODUK JADI</code> belum dibuat atau belum ada itemnya.
                    </div>
                <?php elseif (!$products): ?>
                    <div class="alert alert-info">
                        Belum ada produk jadi. Tambahkan dulu di <strong>Master Barang</strong> dengan kategori
                        <code>PRODUK JADI</code>.
                    </div>
                <?php else: ?>
                    <form method="get">
                        <div class="mb-3">
                            <label class="form-label">Produk</label>
                            <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">-- Pilih Produk Jadi --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $selected_product_id == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_product_id && $components): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <strong>Tambah Komponen BOM</strong>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= $selected_product_id ?>">

                        <div class="mb-3">
                            <label class="form-label">Komponen</label>
                            <select name="component_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Komponen --</option>
                                <?php foreach ($components as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        [<?= htmlspecialchars($c['category_name']) ?>] <?= htmlspecialchars($c['name']) ?>
                                        (<?= $c['unit_code'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Qty per 1 pcs produk</label>
                            <input type="number" step="0.0001" name="qty_per_unit" class="form-control form-control-sm"
                                required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-sm">Tambah ke BOM</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <strong>Daftar BOM</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th style="width:60px;">No</th>
                                <th>Komponen</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th class="text-end" style="width:130px;">Qty / 1 pcs</th>
                                <th style="width:140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($selected_product_id && $current_bom): ?>
                                <?php $no = 1;
                                foreach ($current_bom as $b): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($b['component_name']) ?></td>
                                        <td><?= htmlspecialchars($b['component_category']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($b['unit_code']) ?></td>
                                        <td class="text-end">
                                            <?= rtrim(rtrim(number_format($b['qty_per_unit'], 4, '.', ''), '0'), '.') ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-edit-bom"
                                                    data-bs-toggle="modal" data-bs-target="#editBomModal"
                                                    data-bom-id="<?= $b['id'] ?>" data-product-id="<?= $selected_product_id ?>"
                                                    data-component-name="<?= htmlspecialchars($b['component_name'], ENT_QUOTES) ?>"
                                                    data-qty="<?= rtrim(rtrim(number_format($b['qty_per_unit'], 4, '.', ''), '0'), '.') ?>">
                                                    Edit
                                                </button>
                                                <a href="bom.php?product_id=<?= $selected_product_id ?>&delete=<?= $b['id'] ?>"
                                                    class="btn btn-outline-danger btn-delete"
                                                    data-message="Hapus komponen ini dari BOM?">
                                                    Hapus
                                                </a>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if (!$selected_product_id): ?>
                        <div class="p-3 text-center text-muted">
                            Pilih produk jadi terlebih dahulu.
                        </div>
                    <?php elseif ($selected_product_id && !$current_bom): ?>
                        <div class="p-3 text-center text-muted">
                            Belum ada komponen di BOM produk ini.
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal Edit Komponen BOM -->
<div class="modal fade" id="editBomModal" tabindex="-1" aria-labelledby="editBomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBomModalLabel">Edit Qty Komponen BOM</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="action" value="update_bom">
                    <input type="hidden" name="bom_id" id="bom-id">
                    <input type="hidden" name="product_id" id="bom-product-id">

                    <div class="mb-3">
                        <label class="form-label">Komponen</label>
                        <input type="text" class="form-control form-control-sm" id="bom-component-name" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Qty per 1 pcs produk</label>
                        <input type="number" step="0.0001" name="qty_per_unit" id="bom-qty"
                            class="form-control form-control-sm" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var bomModal = document.getElementById('editBomModal');
        if (!bomModal) return;

        bomModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;

            var bomId = button.getAttribute('data-bom-id');
            var productId = button.getAttribute('data-product-id');
            var compName = button.getAttribute('data-component-name');
            var qty = button.getAttribute('data-qty');

            document.getElementById('bom-id').value = bomId;
            document.getElementById('bom-product-id').value = productId;
            document.getElementById('bom-component-name').value = compName;
            document.getElementById('bom-qty').value = qty;
        });
    });
</script>

<?php
require_once __DIR__ . '/partials/footer.php';
