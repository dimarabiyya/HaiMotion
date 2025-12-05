<?php
include 'db_connect.php';

// 1. Ambil ID yang dienkripsi dari URL
$encoded_id = $_GET['id'] ?? null;

// ➡️ 2. DECODE ID
// Fungsi decode_id() mengembalikan ID numerik atau null jika gagal.
$id = decode_id($encoded_id);

// 3. Verifikasi ID yang telah didekode
if (!is_numeric($id) || $id <= 0) {
    // Jika ID tidak valid (misalnya, diakses langsung tanpa hash atau hash salah)
    echo "<div class='p-3 text-center text-danger'>ID User tidak valid atau tidak ditemukan.</div>";
    exit;
}

// 4. Lanjutkan query menggunakan ID numerik yang aman
$qry = $conn->query("SELECT * FROM users WHERE id = " . $id);

if ($qry->num_rows === 0) {
    echo "<div class='p-3 text-center text-danger'>User tidak ditemukan di database.</div>";
    exit;
}

$data = $qry->fetch_array();
foreach($data as $k => $v){
    // Memuat variabel dari hasil query (e.g., $firstname, $lastname, $email, $nik, $address)
    $$k = $v;
}

// variabel tambahan agar tidak error jika nik/alamat kosong
$nik = isset($nik) ? $nik : '';
$alamat = isset($address) ? $address : '';

// 5. Sertakan new_user.php untuk tampilan form
include 'new_user.php';
?>