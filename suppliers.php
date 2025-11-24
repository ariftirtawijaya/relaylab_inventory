<?php
require_once __DIR__ . '/config/db.php';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'Offline';
        $phone = trim($_POST['phone'] ?? '');

        if ($name !== '') {
            // Kalau bukan Offline, nomor HP kosongkan saja
            if ($type !== 'Offline') {
                $phone = null;
            }

            $stmt = $pdo->prepare("
                INSERT INTO suppliers (name, type, phone)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $type, $phone]);
        }

        header('Location: suppliers.php');
        exit;
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'Offline';
        $phone = trim($_POST['phone'] ?? '');

        if ($id > 0 && $name !== '') {
            if ($type !== 'Offline') {
                $phone = null;
            }

            $stmt = $pdo->prepare("
                UPDATE suppliers
                SET name = ?, type = ?, phone = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $type, $phone, $id]);
        }

        header('Location: suppliers.php');
        exit;
    }
}

// Handle GET delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: suppliers.php');
    exit;
}

// Ambil data supplier
$stmt = $pdo->query("
    SELECT id, name, type, phone, created_at
    FROM suppliers
    ORDER BY name ASC
");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Supplier';
require_once __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Supplier</h4>
        <small class="text-muted">Master data supplier (Offline / Tiktok Shop / Tokopedia / Shopee)</small>
    </div>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSupplierModal">
        + Tambah Supplier
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0 align-middle datatable">
            <thead class="table-light">
                <tr class="text-center">
                    <th style="width:60px;">No</th>
                    <th>Nama Supplier / Toko</th>
                    <th>Jenis</th>
                    <th>Nomor HP</th>
                    <th style="width:150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($suppliers): ?>
                    <?php $no = 1;
                    foreach ($suppliers as $s): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($s['type']) ?></td>
                            <td><?= $s['type'] === 'Offline' ? htmlspecialchars($s['phone']) : '-' ?></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-supplier"
                                        data-bs-toggle="modal" data-bs-target="#editSupplierModal" data-id="<?= $s['id'] ?>"
                                        data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                                        data-type="<?= htmlspecialchars($s['type'], ENT_QUOTES) ?>"
                                        data-phone="<?= htmlspecialchars($s['phone'] ?? '', ENT_QUOTES) ?>">
                                        Edit
                                    </button>
                                    <a href="suppliers.php?delete=<?= $s['id'] ?>" class="btn btn-outline-danger btn-delete"
                                        data-message="Hapus supplier ini?">
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!$suppliers): ?>
            <div class="p-3 text-center text-muted">
                Belum ada supplier.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="createSupplierModal" tabindex="-1" aria-labelledby="createSupplierModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="createSupplierModalLabel">Tambah Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-2">
                        <label class="form-label">Nama Supplier / Toko</label>
                        <input type="text" name="name" class="form-control form-control-sm" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Jenis</label>
                        <select name="type" id="sup-type-create" class="form-select form-select-sm" required>
                            <option value="Offline">Offline</option>
                            <option value="Tiktok Shop">Tiktok Shop</option>
                            <option value="Tokopedia">Tokopedia</option>
                            <option value="Shopee">Shopee</option>
                        </select>
                        </select>
                    </div>

                    <div class="mb-2" id="sup-phone-create-group">
                        <label class="form-label">Nomor HP (hanya Offline)</label>
                        <input type="text" name="phone" class="form-control form-control-sm">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="sup-id">

                    <div class="mb-2">
                        <label class="form-label">Nama Supplier / Toko</label>
                        <input type="text" name="name" id="sup-name" class="form-control form-control-sm" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Jenis</label>
                        <select name="type" id="sup-type-edit" class="form-select form-select-sm" required>
                            <option value="Offline">Offline</option>
                            <option value="Tiktok Shop">Tiktok Shop</option>
                            <option value="Tokopedia">Tokopedia</option>
                        </select>
                    </div>

                    <div class="mb-2" id="sup-phone-edit-group">
                        <label class="form-label">Nomor HP (hanya Offline)</label>
                        <input type="text" name="phone" id="sup-phone" class="form-control form-control-sm">
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
        // ====== HANDLE EDIT MODAL FILL ======
        var editModal = document.getElementById('editSupplierModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                if (!btn) return;

                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name') || '';
                var type = btn.getAttribute('data-type') || 'Offline';
                var phone = btn.getAttribute('data-phone') || '';

                document.getElementById('sup-id').value = id;
                document.getElementById('sup-name').value = name;
                document.getElementById('sup-type-edit').value = type;
                document.getElementById('sup-phone').value = phone;

                togglePhoneField('sup-type-edit', 'sup-phone-edit-group');
            });
        }

        // ====== SHOW/HIDE PHONE FIELD ======
        function togglePhoneField(selectId, groupId) {
            var select = document.getElementById(selectId);
            var group = document.getElementById(groupId);
            if (!select || !group) return;

            if (select.value === 'Offline') {
                group.style.display = '';
            } else {
                group.style.display = 'none';
                var input = group.querySelector('input');
                if (input) input.value = '';
            }
        }

        // Create modal: jenis supplier
        var createTypeSelect = document.getElementById('sup-type-create');
        if (createTypeSelect) {
            createTypeSelect.addEventListener('change', function () {
                togglePhoneField('sup-type-create', 'sup-phone-create-group');
            });
            // initial
            togglePhoneField('sup-type-create', 'sup-phone-create-group');
        }

        // Edit modal: jenis supplier
        var editTypeSelect = document.getElementById('sup-type-edit');
        if (editTypeSelect) {
            editTypeSelect.addEventListener('change', function () {
                togglePhoneField('sup-type-edit', 'sup-phone-edit-group');
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/partials/footer.php';
