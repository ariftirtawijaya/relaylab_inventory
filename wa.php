<?php
// wa_send_test.php
// Halaman untuk test kirim pesan WA ke group menggunakan Fonnte API

$token = "yNuNwRkmU8L4YDyF1NQi";   // Ganti dengan token Fonnte Anda
$group_id = "120363425659648608@g.us";    // Ganti dengan ID Group WhatsApp, format: 628xxxxxxx-xxxxx@g.us

// Pesan yang akan dikirim
$message = "Halo! Ini adalah *test pesan* dari sistem RelayLab Inventory via Fonnte WA API 🚀";

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.fonnte.com/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => array(
        'target' => $group_id,
        'message' => $message,
    ),
    CURLOPT_HTTPHEADER => array(
        'Authorization: ' . $token
    ),
));

$response = curl_exec($curl);
$error = curl_error($curl);
curl_close($curl);

// Tampilkan hasil
header("Content-Type: application/json");

if ($error) {
    echo json_encode([
        "status" => false,
        "error" => $error
    ]);
} else {
    echo $response;
}
