<?php
// get_groups.php
// Ambil daftar grup dari Fonnte setelah fetch-group sukses

$token = "yNuNwRkmU8L4YDyF1NQi"; // ganti token Fonnte Anda

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.fonnte.com/get-whatsapp-group',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
        'Authorization: ' . $token
    ),
));

$response = curl_exec($curl);
curl_close($curl);

header("Content-Type: application/json");
echo $response;
