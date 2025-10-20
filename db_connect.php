<?php 
// FILE: db_connect.php - KODE KONEKSI DAN KEAMANAN ID (FINAL FIX)

// 1. KONEKSI DATABASE (Variabel $conn)
$conn = new mysqli('localhost','root','','tsm_db') or die("Could not connect to mysql".mysqli_error($conn)); 

// =======================================================
// FUNGSI KEAMANAN ID (Obfuscation) - FIX REDECLARE
// =======================================================

// 2. DEKLARASI KONSTANTA (ID_SALT)
if (!defined('ID_SALT')) {
    define('ID_SALT', 'kunci_rahasia_project_anda_2025_TSM'); 
}


/**
 * Mengubah ID numerik menjadi string yang disamarkan (Base64 URL-safe).
 * @param int $id ID numerik proyek/task.
 * @return string ID yang disamarkan.
 */
if (!function_exists('encode_id')) {
    function encode_id($id) {
        if (!is_numeric($id) || $id <= 0) {
            return '';
        }
        $combined_string = $id . ID_SALT;
        
        return rtrim(strtr(base64_encode($combined_string), '+/', '-_'), '=');
    }
}

/**
 * Mengembalikan ID string yang disamarkan kembali menjadi ID numerik.
 * @param string $encoded_id ID yang disamarkan (dari URL).
 * @return int|null ID numerik yang valid atau null jika gagal.
 */
if (!function_exists('decode_id')) {
    function decode_id($encoded_id) {
        $encoded_id = str_pad(strtr($encoded_id, '-_', '+/'), strlen($encoded_id) % 4, '=', STR_PAD_RIGHT);
        $decoded_string = base64_decode($encoded_id);
        
        if (strpos($decoded_string, ID_SALT) !== false) {
            $id_string = str_replace(ID_SALT, '', $decoded_string);
            
            if (is_numeric($id_string) && $id_string > 0) {
                return (int)$id_string;
            }
        }
        return null;
    }
}