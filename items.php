<?php
// items.php - Master Barang (full CRUD + Batch Edit)
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$errors = [];
$success = '';

// ambil flash dari session (jika ada)
if (!empty($_SESSION['flash_success'])) {
  $success = $_SESSION['flash_success'];
  unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_errors']) && is_array($_SESSION['flash_errors'])) {
  $errors = $_SESSION['flash_errors'];
  unset($_SESSION['flash_errors']);
}

// Ambil kategori & satuan untuk dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$units = $pdo->query("SELECT id, code, name FROM units ORDER BY code")->fetchAll();

// ==========================
// Hapus item (single delete)
// ==========================
if (isset($_GET['delete'])) {
  $id = (int) $_GET['delete'];
  if ($id > 0) {
    try {
      $totalRef = 0;

      $stmt = $pdo->prepare("SELECT COUNT(*) AS jml FROM stock_movements WHERE item_id = :id");
      $stmt->execute([':id' => $id]);
      $row = $stmt->fetch();
      $totalRef += (int) ($row['jml'] ?? 0);

      $stmt = $pdo->prepare("SELECT COUNT(*) AS jml FROM bom WHERE product_item_id = :id OR component_item_id = :id");
      $stmt->execute([':id' => $id]);
      $row = $stmt->fetch();
      $totalRef += (int) ($row['jml'] ?? 0);

      $stmt = $pdo->prepare("SELECT COUNT(*) AS jml FROM package_components WHERE item_id = :id");
      $stmt->execute([':id' => $id]);
      $row = $stmt->fetch();
      $totalRef += (int) ($row['jml'] ?? 0);

      if ($totalRef > 0) {
        $_SESSION['flash_errors'] = ['Barang tidak bisa dihapus karena sudah dipakai di stok / BOM / Pack.'];
      } else {
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['flash_success'] = 'Barang berhasil dihapus.';
      }
    } catch (Throwable $e) {
      $_SESSION['flash_errors'] = ['Gagal menghapus barang: ' . $e->getMessage()];
    }
  }

  header('Location: items.php');
  exit;
}

// ==========================
// Tambah / Update / Batch Edit
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ---------- BATCH UPDATE ----------
  if ($action === 'batch_update') {
    $idsStr = $_POST['ids'] ?? '';
    $idArr = array_filter(array_map('intval', explode(',', $idsStr)));

    if (!$idArr) {
      $_SESSION['flash_errors'] = ['Tidak ada barang yang dipilih untuk batch edit.'];
      header('Location: items.php');
      exit;
    }

    $setParts = [];
    $values = [];

    // Ubah kategori?
    if (!empty($_POST['change_category'])) {
      $catId = (int) ($_POST['batch_category_id'] ?? 0);
      if ($catId > 0) {
        $setParts[] = "category_id = ?";
        $values[] = $catId;
      }
    }

    // Ubah satuan?
    if (!empty($_POST['change_unit'])) {
      $unitId = (int) ($_POST['batch_unit_id'] ?? 0);
      if ($unitId > 0) {
        $setParts[] = "unit_id = ?";
        $values[] = $unitId;
      }
    }

    // Ubah min stock?
    if (!empty($_POST['change_min_stock'])) {
      $minStock = (float) ($_POST['batch_min_stock'] ?? 0);
      if ($minStock < 0) {
        $_SESSION['flash_errors'] = ['Min stock (batch) tidak boleh negatif.'];
        header('Location: items.php');
        exit;
      }
      $setParts[] = "min_stock = ?";
      $values[] = $minStock;
    }

    // Ubah catatan?
    if (!empty($_POST['change_notes'])) {
      $notes = trim($_POST['batch_notes'] ?? '');
      $setParts[] = "notes = ?";
      $values[] = $notes;
    }

    if (!$setParts) {
      $_SESSION['flash_errors'] = ['Tidak ada field yang dipilih untuk diubah.'];
      header('Location: items.php');
      exit;
    }

    // Build query batch
    $placeholders = implode(',', array_fill(0, count($idArr), '?'));
    $sql = "UPDATE items SET " . implode(', ', $setParts) . " WHERE id IN ($placeholders)";
    $values = array_merge($values, $idArr);

    try {
      $stmt = $pdo->prepare($sql);
      $stmt->execute($values);
      $_SESSION['flash_success'] = 'Batch edit berhasil. Jumlah item diubah: ' . count($idArr) . '.';
    } catch (Throwable $e) {
      $_SESSION['flash_errors'] = ['Gagal melakukan batch edit: ' . $e->getMessage()];
    }

    header('Location: items.php');
    exit;
  }

  // ---------- ADD / UPDATE SINGLE ----------
  $id = (int) ($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $category_id = (int) ($_POST['category_id'] ?? 0);
  $unit_id = (int) ($_POST['unit_id'] ?? 0);
  $min_stock = (float) ($_POST['min_stock'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');

  if ($name === '') {
    $errors[] = 'Nama barang wajib diisi.';
  }
  if ($category_id <= 0) {
    $errors[] = 'Kategori wajib dipilih.';
  }
  if ($unit_id <= 0) {
    $errors[] = 'Satuan wajib dipilih.';
  }
  if ($min_stock < 0) {
    $errors[] = 'Min stock tidak boleh negatif.';
  }

  if (!$errors) {
    if ($action === 'add') {
      $stmt = $pdo->prepare("
                INSERT INTO items (category_id, name, unit_id, min_stock, notes)
                VALUES (:cat, :name, :unit, :min_stock, :notes)
            ");
      try {
        $stmt->execute([
          ':cat' => $category_id,
          ':name' => $name,
          ':unit' => $unit_id,
          ':min_stock' => $min_stock,
          ':notes' => $notes,
        ]);
        $_SESSION['flash_success'] = 'Barang baru berhasil ditambahkan.';
      } catch (Throwable $e) {
        $errors[] = 'Gagal menambah barang: ' . $e->getMessage();
      }
    } elseif ($action === 'update' && $id > 0) {
      $stmt = $pdo->prepare("
                UPDATE items
                SET category_id = :cat,
                    name        = :name,
                    unit_id     = :unit,
                    min_stock   = :min_stock,
                    notes       = :notes
                WHERE id = :id
            ");
      try {
        $stmt->execute([
          ':cat' => $category_id,
          ':name' => $name,
          ':unit' => $unit_id,
          ':min_stock' => $min_stock,
          ':notes' => $notes,
          ':id' => $id,
        ]);
        $_SESSION['flash_success'] = 'Data barang berhasil diupdate.';
      } catch (Throwable $e) {
        $errors[] = 'Gagal mengupdate barang: ' . $e->getMessage();
      }
    }

    if (!$errors) {
      header('Location: items.php');
      exit;
    } else {
      $_SESSION['flash_errors'] = $errors;
      header('Location: items.php');
      exit;
    }
  } else {
    $_SESSION['flash_errors'] = $errors;
    header('Location: items.php');
    exit;
  }
}

require_once __DIR__ . '/partials/header.php';

// Ambil semua barang untuk tabel
$sql = "
SELECT
  i.id,
  i.name,
  i.min_stock,
  i.notes,
  c.name AS category_name,
  c.id   AS category_id,
  u.code AS unit_code,
  u.id   AS unit_id
FROM items i
JOIN categories c ON c.id = i.category_id
JOIN units u      ON u.id = i.unit_id
ORDER BY c.name, i.name
";
$items = $pdo->query($sql)->fetchAll();
?>

<div class="row mb-3">
  <div class="col-12">
    <h1 class="h4">Master Barang</h1>
    <p class="text-muted mb-0">
      Daftar seluruh item bahan & produk jadi RelayLab (bisa tambah, edit, hapus, batch edit).
    </p>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success alert-sm py-2">
    <?= htmlspecialchars($success) ?>
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

<div class="row g-3">
  <!-- Form tambah barang -->
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header">
        <strong>Tambah Barang Baru</strong>
      </div>
      <div class="card-body">
        <?php if (!$categories || !$units): ?>
          <div class="alert alert-warning">
            Kategori atau satuan belum diisi di database.<br>
            Isi tabel <code>categories</code> dan <code>units</code> dulu.
          </div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="add">

            <div class="mb-3">
              <label class="form-label">Nama Barang</label>
              <input type="text" name="name" class="form-control form-control-sm" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Kategori</label>
              <select name="category_id" class="form-select form-select-sm" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>">
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Satuan</label>
              <select name="unit_id" class="form-select form-select-sm" required>
                <option value="">-- Pilih Satuan --</option>
                <?php foreach ($units as $u): ?>
                  <option value="<?= $u['id'] ?>">
                    <?= htmlspecialchars($u['code']) ?> — <?= htmlspecialchars($u['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Stok Minimum (Min)</label>
              <input type="number" step="0.01" name="min_stock" class="form-control form-control-sm" value="0">
            </div>

            <div class="mb-3">
              <label class="form-label">Catatan (opsional)</label>
              <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-sm">Tambah Barang</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Tabel barang -->
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Daftar Barang</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnBatchEdit">
          Batch Edit
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
            <thead class="table-light">
              <tr class="text-center">
                <th style="width:30px;">
                  <input type="checkbox" id="checkAll">
                </th>
                <th class="text-center" style="width:50px;">No</th>
                <th class="text-center">Nama</th>
                <th class="text-center">Kategori</th>
                <th class="text-center">Satuan</th>
                <th class="text-center" style="width:90px;">Min</th>
                <th class="text-center">Catatan</th>
                <th class="text-center" style="width:140px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($items): ?>
                <?php $no = 1;
                foreach ($items as $it): ?>
                  <tr>
                    <td class="text-center">
                      <input type="checkbox" class="row-check" value="<?= $it['id'] ?>">
                    </td>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($it['category_name']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($it['unit_code']) ?></td>
                    <td class="text-center">
                      <?= rtrim(rtrim(number_format($it['min_stock'], 2, '.', ''), '0'), '.') ?>
                    </td>
                    <td><?= htmlspecialchars($it['notes'] ?? '') ?></td>
                    <td class="text-center">
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-edit-item" data-bs-toggle="modal"
                          data-bs-target="#editItemModal" data-id="<?= $it['id'] ?>"
                          data-name="<?= htmlspecialchars($it['name'], ENT_QUOTES) ?>"
                          data-category-id="<?= $it['category_id'] ?>" data-unit-id="<?= $it['unit_id'] ?>"
                          data-min-stock="<?= rtrim(rtrim(number_format($it['min_stock'], 2, '.', ''), '0'), '.') ?>"
                          data-notes="<?= htmlspecialchars($it['notes'] ?? '', ENT_QUOTES) ?>">
                          Edit
                        </button>
                        <a href="items.php?delete=<?= $it['id'] ?>" class="btn btn-outline-danger btn-delete"
                          data-message="Hapus barang ini?">
                          Hapus
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <?php if (!$items): ?>
            <div class="p-3 text-center text-muted">
              Belum ada barang.
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Edit Barang (Single) -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="editItemModalLabel">Edit Barang</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit-item-id">

          <div class="mb-3">
            <label class="form-label">Nama Barang</label>
            <input type="text" name="name" id="edit-item-name" class="form-control form-control-sm" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Kategori</label>
            <select name="category_id" id="edit-item-category" class="form-select form-select-sm" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Satuan</label>
            <select name="unit_id" id="edit-item-unit" class="form-select form-select-sm" required>
              <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['code']) ?> — <?= htmlspecialchars($u['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Stok Minimum (Min)</label>
            <input type="number" step="0.01" name="min_stock" id="edit-item-min-stock"
              class="form-control form-control-sm">
          </div>

          <div class="mb-3">
            <label class="form-label">Catatan</label>
            <textarea name="notes" id="edit-item-notes" class="form-control form-control-sm" rows="2"></textarea>
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

<!-- Modal Batch Edit -->
<div class="modal fade" id="batchEditModal" tabindex="-1" aria-labelledby="batchEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="batchEditModalLabel">Batch Edit Barang</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="action" value="batch_update">
          <input type="hidden" name="ids" id="batch-ids">

          <p class="small text-muted">
            Barang terpilih: <span class="fw-bold" id="batch-count">0</span> item.
          </p>

          <hr class="my-2">

          <div class="mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="batch-change-category" name="change_category"
                value="1">
              <label class="form-check-label fw-semibold" for="batch-change-category">
                Ubah Kategori
              </label>
            </div>
            <select name="batch_category_id" class="form-select form-select-sm mt-1">
              <option value="">-- Pilih Kategori --</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="batch-change-unit" name="change_unit" value="1">
              <label class="form-check-label fw-semibold" for="batch-change-unit">
                Ubah Satuan
              </label>
            </div>
            <select name="batch_unit_id" class="form-select form-select-sm mt-1">
              <option value="">-- Pilih Satuan --</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['code']) ?> — <?= htmlspecialchars($u['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="batch-change-min" name="change_min_stock" value="1">
              <label class="form-check-label fw-semibold" for="batch-change-min">
                Ubah Min Stock
              </label>
            </div>
            <input type="number" step="0.01" name="batch_min_stock" class="form-control form-control-sm mt-1"
              placeholder="Contoh: 10">
          </div>

          <div class="mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="batch-change-notes" name="change_notes" value="1">
              <label class="form-check-label fw-semibold" for="batch-change-notes">
                Ubah Catatan
              </label>
            </div>
            <textarea name="batch_notes" class="form-control form-control-sm mt-1" rows="2"
              placeholder="Catatan baru untuk semua barang terpilih"></textarea>
          </div>

          <small class="text-muted">
            Field yang tidak dicentang tidak akan diubah.
          </small>
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
    // ============= Single Edit Modal =============
    var editModal = document.getElementById('editItemModal');
    if (editModal) {
      editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (!button) return;

        var id = button.getAttribute('data-id');
        var name = button.getAttribute('data-name');
        var catId = button.getAttribute('data-category-id');
        var unitId = button.getAttribute('data-unit-id');
        var minStock = button.getAttribute('data-min-stock');
        var notes = button.getAttribute('data-notes');

        document.getElementById('edit-item-id').value = id;
        document.getElementById('edit-item-name').value = name;
        document.getElementById('edit-item-category').value = catId;
        document.getElementById('edit-item-unit').value = unitId;
        document.getElementById('edit-item-min-stock').value = minStock;
        document.getElementById('edit-item-notes').value = notes;
      });
    }

    // ============= Batch Edit =============
    var checkAll = document.getElementById('checkAll');
    var batchBtn = document.getElementById('btnBatchEdit');
    var batchIdsInput = document.getElementById('batch-ids');
    var batchCountSpan = document.getElementById('batch-count');
    var batchModalEl = document.getElementById('batchEditModal');

    function getRowChecks() {
      return Array.prototype.slice.call(document.querySelectorAll('.row-check'));
    }

    if (checkAll) {
      checkAll.addEventListener('change', function () {
        var checks = getRowChecks();
        checks.forEach(function (cb) {
          cb.checked = checkAll.checked;
        });
      });
    }

    if (batchBtn && batchModalEl && batchIdsInput && batchCountSpan) {
      batchBtn.addEventListener('click', function () {
        var checks = getRowChecks();
        var selected = checks.filter(function (cb) { return cb.checked; })
          .map(function (cb) { return cb.value; });

        if (!selected.length) {
          alert('Pilih minimal satu barang dulu untuk batch edit.');
          return;
        }

        batchIdsInput.value = selected.join(',');
        batchCountSpan.textContent = selected.length;

        // Reset checkbox opsi di modal (supaya tidak nyangkut dari penggunaan sebelumnya)
        document.getElementById('batch-change-category').checked = false;
        document.getElementById('batch-change-unit').checked = false;
        document.getElementById('batch-change-min').checked = false;
        document.getElementById('batch-change-notes').checked = false;

        var modal = new bootstrap.Modal(batchModalEl);
        modal.show();
      });
    }
  });
</script>

<?php
require_once __DIR__ . '/partials/footer.php';
