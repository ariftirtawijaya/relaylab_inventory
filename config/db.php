<?php
// config/db.php

$host = 'localhost';
$db = 'relaylab_inventory';
$user = 'root';        // sesuaikan dengan XAMPP/hosting
$pass = '';            // sesuaikan

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}
