<?php
// partials/footer.php
?>
</div> <!-- /.container -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (wajib untuk DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Select2 CSS & JS (untuk dropdown dengan search) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
    rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- DataTables core + Bootstrap 5 integration -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Responsive -->
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
    $(function () {
        $('.datatable').DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [], // jangan auto order kolom pertama
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/id.json'
            }
        });
    });
</script>
<script>
    $(function () {
        // Select2 untuk dropdown barang (dan bisa dipakai di halaman lain juga)
        $('.select2-item').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Pilih Barang --',
            allowClear: true
        });
    });
</script>

<!-- Modal Konfirmasi Hapus (Global) -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmDeleteMessage" class="mb-0">
                    Apakah Anda yakin ingin menghapus data ini?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Hapus</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var deleteUrl = null;
        var modalEl = document.getElementById('confirmDeleteModal');
        var messageEl = document.getElementById('confirmDeleteMessage');
        var confirmBtn = document.getElementById('confirmDeleteBtn');
        var bsModal = modalEl && window.bootstrap
            ? new bootstrap.Modal(modalEl)
            : null;

        // Delegasi klik untuk semua .btn-delete
        document.body.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-delete');
            if (!btn) return;

            e.preventDefault();
            var url = btn.getAttribute('href');
            var msg = btn.dataset.message || 'Apakah Anda yakin ingin menghapus data ini?';

            // Kalau Bootstrap/Modal tidak ada, fallback ke confirm biasa
            if (!bsModal) {
                if (confirm(msg)) {
                    window.location.href = url;
                }
                return;
            }

            deleteUrl = url;
            if (messageEl) messageEl.textContent = msg;
            bsModal.show();
        });

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (deleteUrl) {
                    window.location.href = deleteUrl;
                }
            });
        }
    });
</script>

</body>

</html>