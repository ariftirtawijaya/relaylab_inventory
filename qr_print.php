<?php
require_once __DIR__ . '/config/db.php';

// Ambil kategori kecuali "PRODUK JADI"
$stmt = $pdo->prepare("
    SELECT id, name
    FROM categories
    WHERE name <> 'PRODUK JADI'
    ORDER BY name
");
$stmt->execute();
$cats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>QR Print Sheet - Kategori</title>

    <style>
        @page {
            size: A4;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        /* Container untuk 1 halaman penuh */
        .page {
            width: 100vw;
            height: 100vh;

            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;

            page-break-after: always;
        }

        /* QR besar */
        .qr-img {
            width: 90mm;
            height: 90mm;
            margin-bottom: 10mm;
        }

        .name {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>

</head>

<body>

    <div class="no-print" style="padding: 20px; text-align:center;">
        <h2>QR Print Sheet Kategori (1 QR per Halaman)</h2>
        <p>Kategori <b>PRODUK JADI</b> otomatis di-skip</p>
        <a href="qr_list.php">⬅ Kembali ke Daftar</a>
        <hr style="margin-top:20px;">
    </div>

    <?php foreach ($cats as $c): ?>
        <?php $qrLink = "qr.php?id=" . $c['id']; ?>

        <div class="page">
            <img src="<?= $qrLink ?>" class="qr-img">
            <div class="name"><?= htmlspecialchars($c['name']) ?></div>
        </div>

    <?php endforeach; ?>

</body>

</html>