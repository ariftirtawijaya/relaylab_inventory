<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
    $file = $_FILES['json_file']['tmp_name'];

    if (!file_exists($file)) {
        $error = 'File tidak ditemukan.';
    } else {
        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (!$data) {
            $error = 'Format JSON tidak valid.';
        } else {
            // Ambil kategori & satuan
            $categories = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);

            // units: key = code (PCS, PACK, ROL), value = id
            $units = $pdo->query("SELECT code, id FROM units")->fetchAll(PDO::FETCH_KEY_PAIR);

            // Unit default = PCS (kalau ada)
            $default_unit = $units['PCS'] ?? null;


            foreach ($data as $row) {
                $catName = strtoupper(trim($row['KATEGORI'] ?? ''));
                $itemName = trim($row['NAMA PRODUK'] ?? '');
                $notes = trim($row['JENIS PEMBELIAN'] ?? '');

                if ($catName === '' || $itemName === '')
                    continue;

                // Cari category_id
                $category_id = null;
                foreach ($categories as $id => $name) {
                    if (strtoupper($name) == $catName) {
                        $category_id = $id;
                        break;
                    }
                }
                if (!$category_id)
                    continue;

                // kalau default_unit tidak ketemu, skip item
                if (!$default_unit) {
                    continue;
                }

                // insert item
                $stmt = $pdo->prepare("
    INSERT INTO items (category_id, name, unit_id, notes)
    VALUES (:cat, :name, :unit, :notes)
");
                $stmt->execute([
                    ':cat' => $category_id,
                    ':name' => $itemName,
                    ':unit' => $default_unit,
                    ':notes' => $notes,
                ]);

            }

            $success = true;
        }
    }
}
?>

<div class="row mb-3">
    <div class="col-12">
        <h1 class="h4">Import Item dari File JSON</h1>
        <p class="text-muted">Gunakan file JSON yang Kang buat (list lengkap item).</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">Import item berhasil!</div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <strong>Upload File JSON</strong>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Pilih file JSON</label>
                <input type="file" name="json_file" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Import</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>