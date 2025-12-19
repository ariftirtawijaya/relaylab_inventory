<?php
// stock_out.php
require_once __DIR__ . '/config/db.php';


$success = '';
$errors = [];

// Hapus mutasi OUT
if (isset($_GET['delete'])) {
  $id = (int) $_GET['delete'];
  if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE id = :id AND movement_type = 'OUT'");
    try {
      $stmt->execute([':id' => $id]);
      $success = 'Data barang keluar berhasil dihapus.';
    } catch (Throwable $e) {
      $errors[] = 'Gagal menghapus data: ' . $e->getMessage();
    }
  }
}

// Ambil item untuk dropdown
$items = $pdo->query("
    SELECT i.id, i.name, c.name AS category_name, u.code AS unit_code
    FROM items i
    JOIN categories c ON c.id = i.category_id
    JOIN units u      ON u.id = i.unit_id
    ORDER BY c.name, i.name
")->fetchAll();

// Mode edit?
$edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$edit_row = null;
if ($edit_id > 0) {
  $stmt = $pdo->prepare("
        SELECT sm.*, i.name AS item_name
        FROM stock_movements sm
        JOIN items i ON i.id = sm.item_id
        WHERE sm.id = :id AND sm.movement_type = 'OUT'
    ");
  $stmt->execute([':id' => $edit_id]);
  $edit_row = $stmt->fetch();
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $movement_id = (int) ($_POST['movement_id'] ?? 0);
  $item_id = (int) ($_POST['item_id'] ?? 0);
  $qty = (float) ($_POST['qty'] ?? 0);
  $stock_type = $_POST['stock_type'] ?? 'GOOD';
  $description = trim($_POST['description'] ?? '');

  if ($item_id <= 0) {
    $errors[] = 'Pilih item.';
  }
  if ($qty <= 0) {
    $errors[] = 'Jumlah keluar harus lebih besar dari 0.';
  }
  if (!in_array($stock_type, ['GOOD', 'REJECT', 'WASTE'], true)) {
    $errors[] = 'Jenis stok tidak valid.';
  }

  if (!$errors) {
    if ($movement_id > 0) {
      // update
      $stmt = $pdo->prepare("
                UPDATE stock_movements
                SET item_id = :item_id,
                    stock_type = :stock_type,
                    qty = :qty,
                    description = :description
                WHERE id = :id AND movement_type = 'OUT'
            ");
      $stmt->execute([
        ':item_id' => $item_id,
        ':stock_type' => $stock_type,
        ':qty' => $qty,
        ':description' => $description,
        ':id' => $movement_id,
      ]);
      $success = 'Data barang keluar berhasil diupdate.';
    } else {
      // insert baru
      $stmt = $pdo->prepare("
                INSERT INTO stock_movements (item_id, movement_type, stock_type, qty, description)
                VALUES (:item_id, 'OUT', :stock_type, :qty, :description)
            ");
      $stmt->execute([
        ':item_id' => $item_id,
        ':stock_type' => $stock_type,
        ':qty' => $qty,
        ':description' => $description,
      ]);
      $success = 'Barang keluar berhasil dicatat.';
    }

    header('Location: stock_out.php');
    exit;
  }
}
require_once __DIR__ . '/partials/header.php';

// Ambil 50 data OUT terakhir
$logs = $pdo->query("
    SELECT sm.id, sm.movement_date, sm.qty, sm.stock_type, sm.description,
           i.name AS item_name, c.name AS category_name, u.code AS unit_code
    FROM stock_movements sm
    JOIN items i      ON i.id = sm.item_id
    JOIN categories c ON c.id = i.category_id
    JOIN units u      ON u.id = i.unit_id
    WHERE sm.movement_type = 'OUT'
    ORDER BY sm.movement_date DESC, sm.id DESC
    LIMIT 50
")->fetchAll();
?>

<div class="row mb-3">
  <div class="col-12">
    <h1 class="h4">Barang Keluar</h1>
    <p class="text-muted mb-0">Catat & kelola pemakaian bahan, rusak, atau penyesuaian stok keluar.</p>
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
        <strong><?= $edit_row ? 'Edit Barang Keluar' : 'Tambah Barang Keluar' ?></strong>
      </div>
      <div class="card-body">
        <?php if (!$items): ?>
          <div class="alert alert-warning">
            Belum ada item. Tambahkan dulu di <strong>Master Barang</strong>.
          </div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="movement_id" value="<?= $edit_row['id'] ?? 0 ?>">

            <div class="mb-3">
              <label class="form-label">Pilih Barang</label>
              <select name="item_id" class="form-select form-select-sm" required>
                <option value="">-- Pilih Barang --</option>
                <?php foreach ($items as $it): ?>
                  <option value="<?= $it['id'] ?>" <?= isset($edit_row['item_id']) && $edit_row['item_id'] == $it['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($it['category_name']) ?> —
                    <?= htmlspecialchars($it['name']) ?> (<?= $it['unit_code'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Jenis Stok</label>
              <?php $st = $edit_row['stock_type'] ?? 'GOOD'; ?>
              <select name="stock_type" class="form-select form-select-sm">
                <option value="GOOD" <?= $st === 'GOOD' ? 'selected' : '' ?>>GOOD</option>
                <option value="REJECT" <?= $st === 'REJECT' ? 'selected' : '' ?>>REJECT</option>
                <option value="WASTE" <?= $st === 'WASTE' ? 'selected' : '' ?>>WASTE</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Jumlah</label>
              <input type="number" step="0.01" name="qty" class="form-control form-control-sm"
                value="<?= isset($edit_row['qty']) ? rtrim(rtrim(number_format($edit_row['qty'], 2, '.', ''), '0'), '.') : '' ?>"
                required>
            </div>

            <div class="mb-3">
              <label class="form-label">Keterangan (opsional)</label>
              <input type="text" name="description" class="form-control form-control-sm"
                value="<?= htmlspecialchars($edit_row['description'] ?? '') ?>">
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-danger btn-sm">
                <?= $edit_row ? 'Simpan Perubahan' : 'Catat Barang Keluar' ?>
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header">
        <strong>Riwayat Barang Keluar (50 terakhir)</strong>
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
                <th>Stok</th>
                <th class="text-end">Qty</th>
                <th>Keterangan</th>
                <th style="width:120px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($logs): ?>
                <?php $no = 1;
                foreach ($logs as $log): ?>
                  <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($log['movement_date']) ?></td>
                    <td><?= htmlspecialchars($log['item_name']) ?></td>
                    <td><?= htmlspecialchars($log['category_name']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($log['unit_code']) ?></td>
                    <td class="text-center">
                      <span class="badge bg-dark"><?= htmlspecialchars($log['stock_type']) ?></span>
                    </td>
                    <td class="text-end">
                      <?= rtrim(rtrim(number_format($log['qty'], 2, '.', ''), '0'), '.') ?>
                    </td>
                    <td><?= htmlspecialchars($log['description']) ?></td>
                    <td class="text-center">
                      <div class="btn-group btn-group-sm">
                        <a href="stock_out.php?edit=<?= $log['id'] ?>" class="btn btn-outline-primary">Edit</a>
                        <a href="stock_out.php?delete=<?= $log['id'] ?>" class="btn btn-outline-danger btn-delete"
                          data-message="Hapus data barang keluar ini?">
                          Hapus
                        </a>

                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <?php if (!$logs): ?>
            <div class="p-3 text-center text-muted">
              Belum ada data barang keluar.
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>