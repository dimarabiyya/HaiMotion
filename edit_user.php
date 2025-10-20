<?php
include 'db_connect.php';

$qry = $conn->query("SELECT * FROM users WHERE id = " . $_GET['id'])->fetch_array();
foreach($qry as $k => $v){
    $$k = $v;
}

// variabel tambahan agar tidak error jika nik/alamat kosong
$nik = isset($nik) ? $nik : '';
$alamat = isset($address) ? $address : '';

include 'new_user.php';
?>
