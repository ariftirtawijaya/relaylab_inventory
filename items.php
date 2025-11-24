<?php
// items.php - Master Barang (full CRUD)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

$errors = [];
$success = '';

// Ambil kategori & satuan untuk dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$units = $pdo->query("SELECT id, code, name FROM units ORDER BY code")->fetchAll();

// Hapus item
if (isset($_GET['delete'])) {
  $id = (int) $_GET['delete'];
  if ($id > 0) {
    try {
      // cek apakah item sudah dipakai di stock_movements, bom, atau package_components
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
        $errors[] = 'Barang tidak bisa dihapus karena sudah dipakai di stok / BOM / Pack.';
      } else {
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $success = 'Barang berhasil dihapus.';
      }
    } catch (Throwable $e) {
      $errors[] = 'Gagal menghapus barang: ' . $e->getMessage();
    }
  }
}

// Tambah / Update item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
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
      // insert baru
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
        $success = 'Barang baru berhasil ditambahkan.';
      } catch (Throwable $e) {
        $errors[] = 'Gagal menambah barang: ' . $e->getMessage();
      }
    } elseif ($action === 'update' && $id > 0) {
      // update
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
        $success = 'Data barang berhasil diupdate.';
      } catch (Throwable $e) {
        $errors[] = 'Gagal mengupdate barang: ' . $e->getMessage();
      }
    }

    if (!$errors) {
      // redirect untuk hindari resubmit form
      header('Location: items.php');
      exit;
    }
  }
}

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
      Daftar seluruh item bahan & produk jadi RelayLab (bisa tambah, edit, hapus).
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

  <!-- Tabel barang + form inline update -->
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header">
        <strong>Daftar Barang</strong>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
            <thead class="table-light">
              <tr class="text-center">
                <th style="width:50px;">No</th>
                <th>Nama</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th class="text-end" style="width:90px;">Min</th>
                <th>Catatan</th>
                <th style="width:140px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($items): ?>
                <?php $no = 1;
                foreach ($items as $it): ?>
                  <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td><?= htmlspecialchars($it['category_name']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($it['unit_code']) ?></td>
                    <td class="text-end">
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

<!-- Modal Edit Barang -->
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

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var editModal = document.getElementById('editItemModal');
    if (!editModal) return;

    editModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      if (!button) return;

      var id = button.getAttribute('data-id');
      var name = button.getAttribute('data-name');
      var catId = button.getAttribute('data-category-id');
      var unitId = button.getAttribute('data-unit-id');
      var minStock = button.getAttribute('data-min-stock');
      var notes = button.getAttribute('data-notes');

      // isi field di modal
      document.getElementById('edit-item-id').value = id;
      document.getElementById('edit-item-name').value = name;
      document.getElementById('edit-item-category').value = catId;
      document.getElementById('edit-item-unit').value = unitId;
      document.getElementById('edit-item-min-stock').value = minStock;
      document.getElementById('edit-item-notes').value = notes;
    });
  });
</script>


<?php
require_once __DIR__ . '/partials/footer.php';
