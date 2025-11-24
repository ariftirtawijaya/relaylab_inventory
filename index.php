<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/partials/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-3">Dashboard Inventory RelayLab</h1>
        <p class="text-muted">
            Versi pertama: kita fokus ke pencatatan stok bahan (GOOD / REJECT / WASTE).
        </p>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Master Barang</h5>
                <p class="card-text">Tambah & kelola daftar bahan baku.</p>
                <a href="items.php" class="btn btn-primary btn-sm">Buka</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Barang Masuk</h5>
                <p class="card-text">Catat pembelian / stok masuk.</p>
                <a href="stock_in.php" class="btn btn-success btn-sm">Buka</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Stok Bahan</h5>
                <p class="card-text">Lihat stok terkini per item.</p>
                <a href="stock_view.php" class="btn btn-secondary btn-sm">Buka</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/partials/footer.php';
