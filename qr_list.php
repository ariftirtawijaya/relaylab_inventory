<?php
require_once __DIR__ . '/config/db.php';

// Ambil semua kategori
$stmt = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name
");
$cats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>QR Code Kategori</title>
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

        .cat-name {
            font-size: 1rem;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2 class="mb-4 text-center fw-bold">Daftar QR Code Kategori</h2>

        <?php if (!$cats): ?>
            <div class="alert alert-warning text-center">Belum ada kategori.</div>
        <?php else: ?>

            <div class="row g-3">

                <?php foreach ($cats as $c): ?>
                    <?php $qrLink = "qr.php?id=" . $c['id']; ?>

                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="qr-box">
                            <img src="<?= $qrLink ?>" class="qr-img mb-2">

                            <div class="cat-name"><?= htmlspecialchars($c['name']) ?></div>

                            <a href="<?= $qrLink ?>" download="QR_CAT_<?= $c['id'] ?>.png" class="btn btn-sm btn-primary mt-2">
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