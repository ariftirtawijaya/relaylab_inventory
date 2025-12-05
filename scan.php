<?php
require_once __DIR__ . '/config/db.php';

/**
 * Halaman error mobile-friendly ketika produk tidak ditemukan
 */
function tampilkan_error_produk()
{
    ?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Produk Tidak Ditemukan</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

        <style>
            body {
                background: #f8f9fc;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                text-align: center;
                padding: 20px;
            }

            .error-box {
                background: #ffffff;
                padding: 25px;
                border-radius: 14px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                max-width: 400px;
                width: 100%;
            }

            .error-icon {
                font-size: 3.5rem;
                color: #dc3545;
                margin-bottom: 10px;
            }

            .error-title {
                font-size: 1.4rem;
                font-weight: bold;
            }

            .error-text {
                font-size: 1rem;
                color: #6c757d;
                margin-bottom: 20px;
            }
        </style>
    </head>

    <body>

        <div class="error-box">
            <div class="error-icon">⚠️</div>
            <div class="error-title">Produk Tidak Ditemukan</div>
            <div class="error-text">
                Pastikan QR Code valid atau produk sudah terdaftar dalam sistem.
            </div>
        </div>

    </body>

    </html>
    <?php
}
function tampilkan_error_stok_habis($item)
{
    ?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Stok Habis - <?= htmlspecialchars($item['name']) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

        <style>
            body {
                background: #fff4f4;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                text-align: center;
                padding: 20px;
            }

            .error-box {
                background: #ffffff;
                padding: 25px;
                border-radius: 14px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                max-width: 420px;
                width: 100%;
            }

            .error-icon {
                font-size: 3.5rem;
                color: #dc3545;
                margin-bottom: 10px;
            }

            .error-title {
                font-size: 1.5rem;
                font-weight: bold;
                margin-bottom: 6px;
            }

            .error-text {
                font-size: 1rem;
                color: #6c757d;
            }
        </style>
    </head>

    <body>

        <div class="error-box">
            <?php if (!empty($_GET['ok'])): ?>
                <div class="alert alert-success text-center">
                    ✔ Stok berhasil dikurangi.
                </div>
            <?php endif; ?>
            <div class="error-icon">❌</div>

            <div class="error-title">Stok Habis</div>

            <div class="error-text">
                Stok untuk <strong><?= htmlspecialchars($item['name']) ?></strong> sudah habis.<br>
                Tidak bisa melakukan pengurangan.
            </div>
        </div>

    </body>

    </html>
    <?php
}


// Ambil ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    tampilkan_error_produk();
    exit;
}

// QUERY DETAIL ITEM + STOK GOOD
$stmt = $pdo->prepare("
SELECT 
    i.id,
    i.name,
    c.name AS category_name,
    u.code AS unit_code,
    COALESCE(SUM(
        IF(sm.stock_type='GOOD',
            IF(sm.movement_type='IN', sm.qty,
               IF(sm.movement_type='OUT', -sm.qty, sm.qty)
            ),
            0
        )
    ),0) AS stock_good
FROM items i
JOIN categories c ON c.id = i.category_id
JOIN units u      ON u.id = i.unit_id
LEFT JOIN stock_movements sm ON sm.item_id = i.id
WHERE i.id = ?
GROUP BY i.id, i.name, c.name, u.code
");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    tampilkan_error_produk();
    exit;
}

// CEK JIKA STOK HABIS
if ((float) $item['stock_good'] <= 0) {
    tampilkan_error_stok_habis($item);
    exit;
}

$errorMsg = '';

// HANDLE SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty = (float) ($_POST['qty'] ?? 0);
    $currentStock = (float) $item['stock_good'];

    if ($qty <= 0) {
        $errorMsg = 'Jumlah harus lebih besar dari 0.';
    } elseif ($currentStock <= 0) {
        $errorMsg = 'Stok sudah habis, tidak bisa dikurangi lagi.';
    } elseif ($qty > $currentStock) {
        $errorMsg = 'Stok tidak mencukupi. Stok saat ini: ' . rtrim(rtrim(number_format($currentStock, 2, '.', ''), '0'), '.');
    } else {
        // Stok cukup → catat pengurangan
        $ins = $pdo->prepare("
            INSERT INTO stock_movements
            (item_id, production_id, movement_type, stock_type, qty, description)
            VALUES (?, NULL, 'OUT', 'GOOD', ?, '')
        ");
        $ins->execute([$id, $qty]);

        header("Location: scan.php?id=$id&ok=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Ambil Stok - <?= htmlspecialchars($item['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 20px;
        }

        .card {
            border-radius: 14px;
            border: none;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.10);
        }

        .title {
            font-weight: 700;
            font-size: 1.4rem;
            text-align: center;
        }

        .stock-box {
            background: #e9f1ff;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 25px;
        }

        .btn-submit {
            font-size: 1.3rem;
            padding: 16px;
            border-radius: 12px;
        }
    </style>
</head>

<body>

    <div class="card p-4">

        <?php if (!empty($_GET['ok'])): ?>
            <div class="alert alert-success text-center">
                ✔ Stok berhasil dikurangi.
            </div>
        <?php endif; ?>


        <div class="title mb-2"><?= htmlspecialchars($item['name']) ?></div>
        <div class="text-center text-muted mb-2">
            <?= htmlspecialchars($item['category_name']) ?> (<?= htmlspecialchars($item['unit_code']) ?>)
        </div>

        <div class="stock-box">
            Stok GOOD: <?= (float) $item['stock_good'] ?>
        </div>

        <form method="POST">
            <label class="form-label fw-bold mb-2">Jumlah yang diambil</label>

            <div class="input-group mb-4">
                <input type="number" step="0.01" name="qty" min="1" class="form-control form-control-lg" placeholder="0"
                    required style="font-size:1.3rem; text-align:center; padding:14px;">

                <span class="input-group-text" style="font-size:1.2rem; font-weight:600;">
                    <?= htmlspecialchars($item['unit_code']) ?>
                </span>
            </div>

            <button class="btn btn-danger btn-submit w-100">
                Kurangi Stok
            </button>
        </form>

    </div>

</body>

</html>