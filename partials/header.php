<?php
// partials/header.php
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>RelayLab Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <!-- DataTables Responsive Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">




</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">RelayLab Inventory</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">

                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>

                    <!-- MASTER DATA -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="masterDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Master
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="masterDropdown">
                            <li><a class="dropdown-item" href="items.php">Master Barang</a></li>
                            <li><a class="dropdown-item" href="categories.php">Kategori</a></li>
                            <li><a class="dropdown-item" href="packs.php">Jenis Pack</a></li>
                            <li><a class="dropdown-item" href="bom.php">BOM Produk</a></li>
                            <li><a class="dropdown-item" href="suppliers.php">Supplier</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="import_items.php">Import Item</a></li>
                        </ul>
                    </li>

                    <!-- TRANSAKSI -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="transDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Transaksi
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="transDropdown">
                            <li><a class="dropdown-item" href="stock_in.php">Barang Masuk</a></li>
                            <li><a class="dropdown-item" href="stock_in_pack.php">Barang Masuk (Pack)</a></li>
                            <li><a class="dropdown-item" href="stock_out.php">Barang Keluar</a></li>
                            <li><a class="dropdown-item" href="purchases.php">Pembelian (PO)</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="produce.php">Produksi</a></li>
                        </ul>
                    </li>


                    <!-- LAPORAN -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Laporan
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="reportDropdown">
                            <li><a class="dropdown-item" href="stock_view.php">Posisi Stok</a></li>
                            <li><a class="dropdown-item" href="stock_log.php">Log Mutasi Stok</a></li>
                        </ul>
                    </li>

                </ul>
            </div>
        </div>
    </nav>


    <div class="container mb-4">