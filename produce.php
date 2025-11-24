<?php
// produce.php - CRUD Produksi
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

// Hapus produksi (hapus header + semua mutasi stok yang terkait)
if (isset($_GET['delete'])) {
  $prod_id = (int) $_GET['delete'];
  if ($prod_id > 0) {
    try {
      $pdo->beginTransaction();
      $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE production_id = :pid");
      $stmt->execute([':pid' => $prod_id]);

      $stmt = $pdo->prepare("DELETE FROM productions WHERE id = :pid");
      $stmt->execute([':pid' => $prod_id]);

      $pdo->commit();
      $success = 'Produksi berhasil dihapus dan stok dikembalikan (secara perhitungan).';
    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = 'Gagal menghapus produksi: ' . $e->getMessage();
    }
  }
}

// Mode edit? (ambil data header produksi)
$edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$edit_row = null;
if ($edit_id > 0) {
  $stmt = $pdo->prepare("
        SELECT p.*, i.name AS product_name
        FROM productions p
        JOIN items i ON i.id = p.product_item_id
        WHERE p.id = :id
    ");
  $stmt->execute([':id' => $edit_id]);
  $edit_row = $stmt->fetch();
}

// Handle submit (tambah / update produksi)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $prod_id = (int) ($_POST['production_id'] ?? 0);
  $product_id = (int) ($_POST['product_id'] ?? 0);
  $qty_prod = (float) ($_POST['qty_prod'] ?? 0);
  $description = trim($_POST['description'] ?? '');

  if ($product_id <= 0) {
    $errors[] = 'Pilih produk jadi.';
  }
  if ($qty_prod <= 0) {
    $errors[] = 'Jumlah produksi harus lebih besar dari 0.';
  }

  // Ambil BOM produk
  if (!$errors) {
    $stmt = $pdo->prepare("
            SELECT b.component_item_id, b.qty_per_unit,
                   c.name AS component_name
            FROM bom b
            JOIN items c ON c.id = b.component_item_id
            WHERE b.product_item_id = :pid
        ");
    $stmt->execute([':pid' => $product_id]);
    $bomRows = $stmt->fetchAll();

    if (!$bomRows) {
      $errors[] = 'Produk ini belum memiliki BOM. Atur dulu di menu BOM Produk.';
    }
  }

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      if ($prod_id > 0) {
        // UPDATE: hapus dulu mutasi lama, update header, lalu insert mutasi baru
        $stmt = $pdo->prepare("
                    UPDATE productions
                    SET product_item_id = :pid,
                        qty             = :qty,
                        description     = :desc
                    WHERE id = :id
                ");
        $stmt->execute([
          ':pid' => $product_id,
          ':qty' => $qty_prod,
          ':desc' => $description,
          ':id' => $prod_id,
        ]);

        // hapus semua mutasi stok lama untuk produksi ini
        $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE production_id = :pid");
        $stmt->execute([':pid' => $prod_id]);

        $production_id = $prod_id;
      } else {
        // INSERT produksi baru
        $stmt = $pdo->prepare("
                    INSERT INTO productions (product_item_id, qty, description)
                    VALUES (:pid, :qty, :desc)
                ");
        $stmt->execute([
          ':pid' => $product_id,
          ':qty' => $qty_prod,
          ':desc' => $description,
        ]);
        $production_id = (int) $pdo->lastInsertId();
      }

      // 1) Kurangi stok bahan (OUT GOOD) berdasarkan BOM
      foreach ($bomRows as $row) {
        $totalNeed = $row['qty_per_unit'] * $qty_prod;

        $stmtOut = $pdo->prepare("
                    INSERT INTO stock_movements
                        (item_id, production_id, movement_type, stock_type, qty, description)
                    VALUES
                        (:item_id, :prod_id, 'OUT', 'GOOD', :qty, :desc)
                ");

        $desc = $description !== ''
          ? $description
          : "Produksi {$qty_prod} pcs - konsumsi bahan";

        $stmtOut->execute([
          ':item_id' => $row['component_item_id'],
          ':prod_id' => $production_id,
          ':qty' => $totalNeed,
          ':desc' => $desc . ' - ' . $row['component_name'],
        ]);
      }

      // 2) Tambah stok produk jadi (IN GOOD)
      $stmtIn = $pdo->prepare("
                INSERT INTO stock_movements
                    (item_id, production_id, movement_type, stock_type, qty, description)
                VALUES
                    (:item_id, :prod_id, 'IN', 'GOOD', :qty, :desc)
            ");
      $descProd = $description !== ''
        ? $description
        : "Produksi {$qty_prod} pcs produk jadi";

      $stmtIn->execute([
        ':item_id' => $product_id,
        ':prod_id' => $production_id,
        ':qty' => $qty_prod,
        ':desc' => $descProd,
      ]);

      $pdo->commit();
      $success = $prod_id > 0 ? 'Produksi berhasil diupdate.' : 'Produksi berhasil dicatat.';
      header('Location: produce.php');
      exit;
    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = 'Terjadi kesalahan saat produksi: ' . $e->getMessage();
    }
  }
}

// Ambil daftar produksi (header) untuk ditampilkan
$productions = $pdo->query("
    SELECT p.id, p.created_at, p.qty, p.description,
           i.name AS product_name
    FROM productions p
    JOIN items i ON i.id = p.product_item_id
    ORDER BY p.created_at DESC, p.id DESC
    LIMIT 50
")->fetchAll();
?>

<div class="row mb-3">
  <div class="col-12">
    <h1 class="h4">Produksi Produk Jadi</h1>
    <p class="text-muted mb-0">
      Catat, edit, dan hapus produksi. Semua mutasi stok bahan & produk jadi akan mengikuti.
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
        <strong><?= $edit_row ? 'Edit Produksi' : 'Produksi Baru' ?></strong>
      </div>
      <div class="card-body">
        <?php if (!$products): ?>
          <div class="alert alert-info">
            Belum ada produk jadi. Tambahkan di <strong>Master Barang</strong> (kategori <code>PRODUK JADI</code>).
          </div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="production_id" value="<?= $edit_row['id'] ?? 0 ?>">

            <div class="mb-3">
              <label class="form-label">Produk Jadi</label>
              <select name="product_id" class="form-select form-select-sm" required>
                <option value="">-- Pilih Produk Jadi --</option>
                <?php foreach ($products as $p): ?>
                  <option value="<?= $p['id'] ?>" <?= isset($edit_row['product_item_id']) && $edit_row['product_item_id'] == $p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Jumlah Produksi (pcs)</label>
              <input type="number" step="1" name="qty_prod" class="form-control form-control-sm"
                value="<?= isset($edit_row['qty']) ? rtrim(rtrim(number_format($edit_row['qty'], 2, '.', ''), '0'), '.') : '' ?>"
                required>
            </div>

            <div class="mb-3">
              <label class="form-label">Keterangan (opsional)</label>
              <input type="text" name="description" class="form-control form-control-sm"
                value="<?= htmlspecialchars($edit_row['description'] ?? '') ?>"
                placeholder="Contoh: produksi PO #123, shift pagi">
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-sm">
                <?= $edit_row ? 'Simpan Perubahan' : 'Catat Produksi' ?>
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
        <strong>Daftar Produksi (50 terakhir)</strong>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0 align-middle datatable">
            <thead class="table-light">
              <tr class="text-center">
                <th style="width:60px;">No</th>
                <th>Waktu</th>
                <th>Produk</th>
                <th class="text-end">Qty</th>
                <th>Keterangan</th>
                <th style="width:140px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($productions): ?>
                <?php $no = 1;
                foreach ($productions as $pr): ?>
                  <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($pr['created_at']) ?></td>
                    <td><?= htmlspecialchars($pr['product_name']) ?></td>
                    <td class="text-end">
                      <?= rtrim(rtrim(number_format($pr['qty'], 2, '.', ''), '0'), '.') ?>
                    </td>
                    <td><?= htmlspecialchars($pr['description']) ?></td>
                    <td class="text-center">
                      <div class="btn-group btn-group-sm">
                        <a href="produce.php?edit=<?= $pr['id'] ?>" class="btn btn-outline-primary">Edit</a>
                        <a href="produce.php?delete=<?= $pr['id'] ?>" class="btn btn-outline-danger btn-delete"
                          data-message="Hapus produksi ini? Stok akan dikoreksi (secara perhitungan).">
                          Hapus
                        </a>

                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <?php if (!$productions): ?>
            <div class="p-3 text-center text-muted">
              Belum ada data produksi.
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/partials/footer.php';
