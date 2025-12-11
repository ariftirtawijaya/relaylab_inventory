<?php
/**
 * =========================================================
 *  FETCH WHATSAPP GROUP LIST - FONNTE
 *  File: fetch_group.php
 *  Fungsi: Mengupdate daftar grup WA di device Fonnte
 *  Wajib dijalankan 1x sebelum bisa mengambil group ID
 * =========================================================
 */

// Masukkan Token Fonnte
$TOKEN = "yNuNwRkmU8L4YDyF1NQi";

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.fonnte.com/fetch-group',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        "Authorization: $TOKEN"
    ]
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

// Tampilkan hasil
header("Content-Type: application/json");

if ($error) {
    echo json_encode([
        "status" => false,
        "message" => "CURL Error: $error"
    ], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    "status" => true,
    "message" => "Fetch Group Executed",
    "response" => json_decode($response, true)
], JSON_PRETTY_PRINT);
