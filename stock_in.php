<?php
// stock_in.php
require_once __DIR__ . '/config/db.php';

$success = '';
$errors = [];

// ============================
// SINGLE DELETE
// ============================
if (isset($_GET['delete'])) {
  $id = (int) $_GET['delete'];
  if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE id = :id AND movement_type = 'IN'");
    try {
      $stmt->execute([':id' => $id]);
      $success = 'Data barang masuk berhasil dihapus.';
    } catch (Throwable $e) {
      $errors[] = 'Gagal menghapus data: ' . $e->getMessage();
    }
  }
}

// ============================
// BATCH DELETE
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batch_delete') {
  $idsStr = $_POST['ids'] ?? '';
  $idArr = array_filter(array_map('intval', explode(',', $idsStr)));

  if (!$idArr) {
    $errors[] = 'Tidak ada data yang dipilih untuk batch delete.';
  } else {
    $placeholders = implode(',', array_fill(0, count($idArr), '?'));
    $sql = "DELETE FROM stock_movements WHERE movement_type = 'IN' AND id IN ($placeholders)";

    try {
      $stmt = $pdo->prepare($sql);
      $stmt->execute($idArr);
      $success = "Batch delete berhasil. Total " . count($idArr) . " data dihapus.";
    } catch (Throwable $e) {
      $errors[] = "Gagal batch delete: " . $e->getMessage();
    }
  }

  // redirect agar tidak resubmit
  header("Location: stock_in.php");
  exit;
}

// ============================
// DATA ITEM
// ============================
$items = $pdo->query("
    SELECT i.id, i.name, c.name AS category_name, u.code AS unit_code
    FROM items i
    JOIN categories c ON c.id = i.category_id
    JOIN units u      ON u.id = i.unit_id
    ORDER BY c.name, i.name
")->fetchAll();

// ============================
// MODE EDIT
// ============================
$edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$edit_row = null;
if ($edit_id > 0) {
  $stmt = $pdo->prepare("
        SELECT sm.*, i.name AS item_name
        FROM stock_movements sm
        JOIN items i ON i.id = sm.item_id
        WHERE sm.id = :id AND sm.movement_type = 'IN'
    ");
  $stmt->execute([':id' => $edit_id]);
  $edit_row = $stmt->fetch();
}

// ============================
// ADD / UPDATE
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'batch_delete') {
  $movement_id = (int) ($_POST['movement_id'] ?? 0);
  $item_id = (int) ($_POST['item_id'] ?? 0);
  $qty = (float) ($_POST['qty'] ?? 0);
  $stock_type = $_POST['stock_type'] ?? 'GOOD';
  $description = trim($_POST['description'] ?? '');

  if ($item_id <= 0) {
    $errors[] = 'Pilih item.';
  }
  if ($qty <= 0) {
    $errors[] = 'Jumlah masuk harus lebih besar dari 0.';
  }
  if (!in_array($stock_type, ['GOOD', 'REJECT', 'WASTE'], true)) {
    $errors[] = 'Jenis stok tidak valid.';
  }

  if (!$errors) {
    if ($movement_id > 0) {
      $stmt = $pdo->prepare("
                UPDATE stock_movements
                SET item_id = :item_id,
                    stock_type = :stock_type,
                    qty = :qty,
                    description = :description
                WHERE id = :id AND movement_type = 'IN'
            ");
      $stmt->execute([
        ':item_id' => $item_id,
        ':stock_type' => $stock_type,
        ':qty' => $qty,
        ':description' => $description,
        ':id' => $movement_id,
      ]);
      $success = 'Data barang masuk berhasil diupdate.';
    } else {
      $stmt = $pdo->prepare("
                INSERT INTO stock_movements (item_id, movement_type, stock_type, qty, description)
                VALUES (:item_id, 'IN', :stock_type, :qty, :description)
            ");
      $stmt->execute([
        ':item_id' => $item_id,
        ':stock_type' => $stock_type,
        ':qty' => $qty,
        ':description' => $description,
      ]);
      $success = 'Barang berhasil ditambahkan ke stok.';
    }

    header('Location: stock_in.php');
    exit;
  }
}

require_once __DIR__ . '/partials/header.php';

// ============================
// LOGS
// ============================
$logs = $pdo->query("
    SELECT sm.id, sm.movement_date, sm.qty, sm.stock_type, sm.description,
           i.name AS item_name, c.name AS category_name, u.code AS unit_code
    FROM stock_movements sm
    JOIN items i      ON i.id = sm.item_id
    JOIN categories c ON c.id = i.category_id
    JOIN units u      ON u.id = i.unit_id
    WHERE sm.movement_type = 'IN'
    ORDER BY sm.movement_date DESC, sm.id DESC
    LIMIT 50
")->fetchAll();
?>

<div class="row mb-3">
  <div class="col-12">
    <h1 class="h4">Barang Masuk</h1>
    <p class="text-muted mb-0">Catat & kelola pembelian / penambahan stok.</p>
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
  <!-- FORM -->
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header">
        <strong><?= $edit_row ? 'Edit Barang Masuk' : 'Tambah Barang Masuk' ?></strong>
      </div>
      <div class="card-body">
        <?php if (!$items): ?>
          <div class="alert alert-warning">Belum ada item.</div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="movement_id" value="<?= $edit_row['id'] ?? 0 ?>">

            <div class="mb-3">
              <label class="form-label">Pilih Barang</label>
              <select name="item_id" class="form-select form-select-sm select2-item" required>
                <option value="">-- Pilih Barang --</option>
                <?php foreach ($items as $it): ?>
                  <option value="<?= $it['id'] ?>" <?= isset($edit_row['item_id']) && $edit_row['item_id'] == $it['id'] ? 'selected' : '' ?>>
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
              <label class="form-label">Keterangan</label>
              <input type="text" name="description" class="form-control form-control-sm"
                value="<?= htmlspecialchars($edit_row['description'] ?? '') ?>">
            </div>

            <div class="d-grid">
              <button class="btn btn-success btn-sm"><?= $edit_row ? 'Simpan Perubahan' : 'Tambah Stok' ?></button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>


  <!-- TABLE -->
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Riwayat Barang Masuk (50 terakhir)</strong>

        <!-- Batch Delete Button -->
        <button class="btn btn-sm btn-outline-danger" id="btnBatchDelete">Batch Delete</button>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
            <thead class="table-light">
              <tr class="text-center">
                <th><input type="checkbox" id="checkAll"></th>
                <th>No</th>
                <th>Waktu</th>
                <th>Barang</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th>Stok</th>
                <th class="text-end">Qty</th>
                <th>Keterangan</th>
                <th>Aksi</th>
              </tr>
            </thead>

            <tbody>
              <?php if ($logs): ?>
                <?php $no = 1;
                foreach ($logs as $log): ?>
                  <tr>
                    <td class="text-center">
                      <input type="checkbox" class="row-check" value="<?= $log['id'] ?>">
                    </td>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($log['movement_date']) ?></td>
                    <td><?= htmlspecialchars($log['item_name']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($log['category_name']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($log['unit_code']) ?></td>
                    <td class="text-center"><span class="badge bg-dark"><?= htmlspecialchars($log['stock_type']) ?></span>
                    </td>
                    <td class="text-end"><?= rtrim(rtrim(number_format($log['qty'], 2, '.', ''), '0'), '.') ?></td>
                    <td><?= htmlspecialchars($log['description']) ?></td>

                    <td class="text-center">
                      <div class="btn-group btn-group-sm">
                        <a href="stock_in.php?edit=<?= $log['id'] ?>" class="btn btn-outline-primary">Edit</a>
                        <a href="stock_in.php?delete=<?= $log['id'] ?>" class="btn btn-outline-danger btn-delete">Hapus</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <?php if (!$logs): ?>
            <div class="p-3 text-center text-muted">Belum ada data.</div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>


<!-- ============================
     MODAL BATCH DELETE
============================ -->
<div class="modal fade" id="batchDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="batch_delete">
        <input type="hidden" name="ids" id="batch-ids">

        <div class="modal-header">
          <h5 class="modal-title text-danger">Konfirmasi Batch Delete</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <p>Anda akan menghapus <strong><span id="batch-count">0</span></strong> data barang masuk.</p>
          <p class="text-danger fw-bold">Tindakan ini tidak bisa dibatalkan.</p>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-danger btn-sm">Hapus Semua</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
  document.addEventListener('DOMContentLoaded', function () {

    // CHECK ALL
    const checkAll = document.getElementById('checkAll');
    const checks = Array.from(document.querySelectorAll('.row-check'));

    if (checkAll) {
      checkAll.addEventListener('change', () => {
        checks.forEach(cb => cb.checked = checkAll.checked);
      });
    }

    // BATCH DELETE
    const batchBtn = document.getElementById('btnBatchDelete');
    const batchIds = document.getElementById('batch-ids');
    const batchCount = document.getElementById('batch-count');
    const modalEl = document.getElementById('batchDeleteModal');

    if (batchBtn) {
      batchBtn.addEventListener('click', () => {
        const selected = checks.filter(c => c.checked).map(c => c.value);

        if (!selected.length) {
          alert('Pilih minimal satu data untuk batch delete.');
          return;
        }

        batchIds.value = selected.join(',');
        batchCount.textContent = selected.length;

        new bootstrap.Modal(modalEl).show();
      });
    }

  });
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>