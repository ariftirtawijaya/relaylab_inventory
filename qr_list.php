<?php
require_once __DIR__ . '/config/db.php';

// Ambil items
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

    <title>QR Code Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fc;
            padding: 20px;
        }

        .qr-box {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .qr-img {
            width: 160px;
            height: 160px;
            object-fit: contain;
        }

        .item-name {
            font-size: 1rem;
            font-weight: bold;
        }

        .item-cat {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>

<body>

    <div class="container">

        <h2 class="mb-4 text-center fw-bold">Daftar QR Code Produk</h2>

        <?php if (!$items): ?>
            <div class="alert alert-warning text-center">Belum ada item terdaftar.</div>
        <?php else: ?>

            <div class="row g-3">

                <?php foreach ($items as $it): ?>
                    <?php $qrLink = "qr.php?id=" . $it['id']; ?>

                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="qr-box">
                            <img src="<?= $qrLink ?>" class="qr-img mb-2">

                            <div class="item-name"><?= htmlspecialchars($it['name']) ?></div>
                            <div class="item-cat"><?= htmlspecialchars($it['category_name']) ?></div>

                            <a href="<?= $qrLink ?>" download="QR_<?= $it['id'] ?>.png" class="btn btn-sm btn-primary mt-2">
                                Download
                            </a>
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</body>

</html>