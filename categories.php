<?php
// categories.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';

$errors = [];
$success = '';

// Tambah / Update / Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'add') {
        if ($name === '') {
            $errors[] = 'Nama kategori wajib diisi.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
            try {
                $stmt->execute([':name' => $name]);
                $success = 'Kategori berhasil ditambahkan.';
            } catch (Throwable $e) {
                $errors[] = 'Gagal menambah kategori: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        if ($id <= 0 || $name === '') {
            $errors[] = 'Data tidak valid untuk update.';
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET name = :name WHERE id = :id");
            try {
                $stmt->execute([':name' => $name, ':id' => $id]);
                $success = 'Kategori berhasil diupdate.';
            } catch (Throwable $e) {
                $errors[] = 'Gagal mengupdate kategori: ' . $e->getMessage();
            }
        }
    }
} elseif (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        // Cek dulu apakah kategori dipakai item
        $check = $pdo->prepare("SELECT COUNT(*) AS jml FROM items WHERE category_id = :id");
        $check->execute([':id' => $id]);
        $row = $check->fetch();
        if ($row && $row['jml'] > 0) {
            $errors[] = 'Kategori tidak bisa dihapus karena masih dipakai oleh item.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            try {
                $stmt->execute([':id' => $id]);
                $success = 'Kategori berhasil dihapus.';
            } catch (Throwable $e) {
                $errors[] = 'Gagal menghapus kategori: ' . $e->getMessage();
            }
        }
    }
}

// Ambil semua kategori
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
?>

<div class="row mb-3">
    <div class="col-12">
        <h1 class="h4">Kategori</h1>
        <p class="text-muted mb-0">Kelola kategori barang (SOKET, SKUN, KABEL, ATK, dll).</p>
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
                <strong>Tambah Kategori</strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="name" class="form-control form-control-sm" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <strong>Daftar Kategori</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle datatable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px;" class="text-center">No</th>
                                <th>Nama</th>
                                <th style="width:160px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($cats): ?>
                                <?php $no = 1;
                                foreach ($cats as $c): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($c['name']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-edit-category"
                                                    data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                    data-id="<?= $c['id'] ?>"
                                                    data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>">
                                                    Edit
                                                </button>
                                                <a href="categories.php?delete=<?= $c['id'] ?>"
                                                    class="btn btn-outline-danger btn-delete"
                                                    data-message="Hapus kategori ini?">
                                                    Hapus
                                                </a>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if (!$cats): ?>
                        <div class="p-3 text-center text-muted">
                            Belum ada kategori.
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Kategori -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="cat-id">

                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="name" id="cat-name" class="form-control form-control-sm" required>
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
        var catModal = document.getElementById('editCategoryModal');
        if (!catModal) return;

        catModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;

            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');

            document.getElementById('cat-id').value = id;
            document.getElementById('cat-name').value = name;
        });
    });
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>