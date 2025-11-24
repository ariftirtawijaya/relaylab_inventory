<?php
// packs.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

$errors = [];
$success = '';

// Tambah / update pack
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_pack') {
        $name = trim($_POST['name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($name === '') {
            $errors[] = 'Nama pack wajib diisi.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO purchase_packages (name, notes) VALUES (:name, :notes)");
            try {
                $stmt->execute([':name' => $name, ':notes' => $notes]);
                $success = 'Pack baru berhasil dibuat.';
            } catch (Throwable $e) {
                $errors[] = 'Gagal menambah pack: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_pack') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($id <= 0 || $name === '') {
            $errors[] = 'Data pack tidak valid.';
        } else {
            $stmt = $pdo->prepare("UPDATE purchase_packages SET name = :name, notes = :notes WHERE id = :id");
            try {
                $stmt->execute([':name' => $name, ':notes' => $notes, ':id' => $id]);
                $success = 'Pack berhasil diupdate.';
            } catch (Throwable $e) {
                $errors[] = 'Gagal mengupdate pack: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'add_component') {
        $package_id = (int) ($_POST['package_id'] ?? 0);
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $qty = (float) ($_POST['qty_per_package'] ?? 0);
        if ($package_id <= 0 || $item_id <= 0 || $qty <= 0) {
            $errors[] = 'Data komponen tidak valid.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO package_components (package_id, item_id, qty_per_package)
                VALUES (:pid, :iid, :qty)
            ");
            try {
                $stmt->execute([
                    ':pid' => $package_id,
                    ':iid' => $item_id,
                    ':qty' => $qty
                ]);
                $success = 'Komponen pack berhasil ditambahkan.';
            } catch (Throwable $e) {
                $errors[] = 'Gagal menambah komponen: ' . $e->getMessage();
            }
        }
    }
} elseif (isset($_GET['delete_pack'])) {
    $id = (int) $_GET['delete_pack'];
    if ($id > 0) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM package_components WHERE package_id = :id");
            $stmt->execute([':id' => $id]);
            $stmt = $pdo->prepare("DELETE FROM purchase_packages WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $pdo->commit();
            $success = 'Pack dan semua komponennya berhasil dihapus.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal menghapus pack: ' . $e->getMessage();
        }
    }
} elseif (isset($_GET['delete_component'])) {
    $cid = (int) $_GET['delete_component'];
    if ($cid > 0) {
        $stmt = $pdo->prepare("DELETE FROM package_components WHERE id = :id");
        try {
            $stmt->execute([':id' => $cid]);
            $success = 'Komponen pack berhasil dihapus.';
        } catch (Throwable $e) {
            $errors[] = 'Gagal menghapus komponen: ' . $e->getMessage();
        }
    }
}

// Ambil semua pack
$packs = $pdo->query("SELECT id, name, notes FROM purchase_packages ORDER BY name")->fetchAll();

// Pack yang dipilih
$selected_pack_id = isset($_GET['pack_id']) ? (int) $_GET['pack_id'] : 0;
if ($selected_pack_id === 0 && $packs) {
    $selected_pack_id = $packs[0]['id']; // default pilih pertama
}

// Ambil komponen untuk pack terpilih
$components = [];
if ($selected_pack_id > 0) {
    $stmt = $pdo->prepare("
        SELECT pc.id, pc.qty_per_package,
               i.name AS item_name,
               c.name AS category_name,
               u.code AS unit_code
        FROM package_components pc
        JOIN items i      ON i.id = pc.item_id
        JOIN categories c ON c.id = i.category_id
        JOIN units u      ON u.id = i.unit_id
        WHERE pc.package_id = :pid
        ORDER BY c.name, i.name
    ");
    $stmt->execute([':pid' => $selected_pack_id]);
    $components = $stmt->fetchAll();
}

// Ambil semua item untuk dropdown komponen
$allItems = $pdo->query("
    SELECT i.id, i.name, c.name AS category_name, u.code AS unit_code
    FROM items i
    JOIN categories c ON c.id = i.category_id
    JOIN units u      ON u.id = i.unit_id
    ORDER BY c.name, i.name
")->fetchAll();
?>

<div class="row mb-3">
    <div class="col-12">
        <h1 class="h4">Jenis Pack</h1>
        <p class="text-muted mb-0">
            Kelola jenis PACK pembelian (misal: PACK H11 MALE, PACK HB3, PACK H4, dll)
            beserta komponen isi per pack.
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
                <strong>Tambah Pack Baru</strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add_pack">
                    <div class="mb-3">
                        <label class="form-label">Nama Pack</label>
                        <input type="text" name="name" class="form-control form-control-sm" required
                            placeholder="Misal: PACK H11 MALE">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm">Tambah Pack</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($packs): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <strong>Edit Pack</strong>
                </div>
                <div class="card-body">
                    <?php
                    $currentPack = null;
                    foreach ($packs as $p) {
                        if ($p['id'] == $selected_pack_id) {
                            $currentPack = $p;
                            break;
                        }
                    }
                    ?>
                    <?php if ($currentPack): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="update_pack">
                            <input type="hidden" name="id" value="<?= $currentPack['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Nama Pack</label>
                                <input type="text" name="name" class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($currentPack['name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan</label>
                                <textarea name="notes" class="form-control form-control-sm"
                                    rows="2"><?= htmlspecialchars($currentPack['notes'] ?? '') ?></textarea>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-success btn-sm">Simpan Perubahan</button>
                                <a href="packs.php?delete_pack=<?= $currentPack['id'] ?>" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Hapus pack beserta semua komponennya?')">Hapus Pack</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-muted">Pilih pack di sebelah kanan untuk mengedit.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <strong>Pilih Pack</strong>
            </div>
            <div class="card-body">
                <?php if (!$packs): ?>
                    <div class="alert alert-info">Belum ada pack. Tambahkan di panel kiri.</div>
                <?php else: ?>
                    <form method="get" class="row g-2 align-items-center">
                        <div class="col-12 col-md-8">
                            <select name="pack_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ($packs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $selected_pack_id == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 text-md-end">
                            <small class="text-muted">Pilih pack untuk melihat & atur komponennya.</small>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_pack_id && $packs): ?>
            <div class="card">
                <div class="card-header">
                    <strong>Komponen dalam Pack</strong>
                </div>
                <div class="card-body">
                    <?php if (!$allItems): ?>
                        <div class="alert alert-warning">
                            Belum ada item yang bisa dijadikan komponen. Tambah dulu di Master Barang.
                        </div>
                    <?php else: ?>
                        <form method="post" class="mb-3">
                            <input type="hidden" name="action" value="add_component">
                            <input type="hidden" name="package_id" value="<?= $selected_pack_id ?>">

                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label">Item</label>
                                    <select name="item_id" class="form-select form-select-sm" required>
                                        <option value="">-- Pilih Item --</option>
                                        <?php foreach ($allItems as $it): ?>
                                            <option value="<?= $it['id'] ?>">
                                                [<?= htmlspecialchars($it['category_name']) ?>] <?= htmlspecialchars($it['name']) ?>
                                                (<?= $it['unit_code'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Qty per Pack</label>
                                    <input type="number" step="0.01" name="qty_per_package" class="form-control form-control-sm"
                                        required>
                                </div>
                                <div class="col-md-3 d-grid">
                                    <button type="submit" class="btn btn-success btn-sm">Tambah Komponen</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th style="width:60px;">No</th>
                                    <th>Item</th>
                                    <th>Kategori</th>
                                    <th>Satuan</th>
                                    <th class="text-end">Qty per Pack</th>
                                    <th style="width:90px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($components): ?>
                                    <?php $no = 1;
                                    foreach ($components as $c): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($c['item_name']) ?></td>
                                            <td><?= htmlspecialchars($c['category_name']) ?></td>
                                            <td class="text-center"><?= htmlspecialchars($c['unit_code']) ?></td>
                                            <td class="text-end">
                                                <?= rtrim(rtrim(number_format($c['qty_per_package'], 2, '.', ''), '0'), '.') ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="packs.php?pack_id=<?= $selected_pack_id ?>&delete_component=<?= $c['id'] ?>"
                                                    class="btn btn-outline-danger btn-sm btn-delete"
                                                    data-message="Hapus komponen ini dari pack?">
                                                    Hapus
                                                </a>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <?php if (!$components): ?>
                            <div class="p-3 text-center text-muted">
                                Belum ada komponen dalam pack ini.
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>