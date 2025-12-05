<?php
require_once __DIR__ . '/config/db.php';

// Ambil semua item
$stmt = $pdo->query("
    SELECT 
        items.id,
        items.name,
        (SELECT name FROM categories WHERE categories.id = items.category_id) AS category_name
    FROM items
    ORDER BY category_id, name
");

$items = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>QR Print Sheet - A4</title>

    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            padding: 5mm;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-gap: 12mm;
        }

        .qr-cell {
            width: 100%;
            border: 1px solid #ddd;
            padding: 6mm;
            text-align: center;
            border-radius: 6px;
            page-break-inside: avoid;
        }

        .qr-img {
            width: 45mm;
            height: 45mm;
            margin-bottom: 4mm;
        }

        .name {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 2mm;
        }

        .cat {
            font-size: 11px;
            color: #666;
            margin-bottom: 1mm;
        }

        .id-tag {
            font-size: 10px;
            color: #444;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>

</head>

<body>

    <div class="no-print" style="padding: 15px; text-align:center;">
        <h2>QR Print Sheet (A4)</h2>
        <p>Cetak langsung menggunakan Ctrl + P</p>
        <a href="qr_list.php">⬅ Kembali ke Daftar QR</a>
    </div>

    <div class="sheet">

        <?php foreach ($items as $it): ?>

            <?php $qrLink = "qr.php?id=" . $it['id']; ?>

            <div class="qr-cell">
                <img src="<?= $qrLink ?>" class="qr-img">

                <div class="name"><?= htmlspecialchars($it['name']) ?></div>
                <div class="cat"><?= htmlspecialchars($it['category_name']) ?></div>
            </div>

        <?php endforeach; ?>

    </div>

</body>

</html>