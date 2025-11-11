<?php
// Pastikan sesi dimulai untuk mendapatkan $_SESSION['login_id']
session_start();
header('Content-Type: application/json');

// --- 1. Konfigurasi dan Koneksi DB ---
require_once 'db_connect.php'; // Ganti dengan path ke db_connect.php Anda
$pdo = $conn; // Asumsi $conn adalah objek PDO atau koneksi MySQLi

$current_user_id = $_SESSION['login_id'] ?? 0;
// Jika user tidak login, tolak akses ke semua API 
if ($current_user_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access.']);
    exit;
}

// Lokasi penyimpanan root untuk semua user
define('STORAGE_ROOT', __DIR__ . '/storage/user_files/');

// --- 2. Fungsi Utility ---

/**
 * Mendapatkan ID Folder Root (utama) user.
 * Jika belum ada, akan dibuatkan.
 */
function get_root_folder_id($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM folders WHERE user_id = ? AND parent_id IS NULL");
    $stmt->execute([$user_id]);
    $root = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($root) {
        return $root['id'];
    }

    // Jika belum ada, buat folder root baru
    $stmt = $pdo->prepare("INSERT INTO folders (name, user_id, parent_id) VALUES (?, ?, NULL)");
    // Gunakan nama unik untuk root user
    $stmt->execute(['Root User ' . $user_id, $user_id]);
    $root_id = $pdo->lastInsertId();
    
    // Buat folder fisik di server
    $user_dir = STORAGE_ROOT . $user_id . '/';
    if (!is_dir($user_dir)) {
        mkdir($user_dir, 0777, true); // Gunakan 0755 jika memungkinkan
    }
    
    return $root_id;
}


// --- 3. Penanganan Request API ---

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $folder_id = $_GET['folder_id'] ?? get_root_folder_id($pdo, $current_user_id);
            $data = ['folders' => [], 'files' => []];

            // 1. Cek Izin (Di sini kita hanya mengecek apakah user adalah pemilik)
            // Lógica perizinan yang lebih kompleks (sharing) harus ditambahkan di sini.
            $stmt_check = $pdo->prepare("SELECT user_id FROM folders WHERE id = ?");
            $stmt_check->execute([$folder_id]);
            $owner_id = $stmt_check->fetchColumn();

            if ($owner_id !== $current_user_id) {
                 // Untuk demo, kita batasi hanya pemilik yang bisa melihat
                 // Anda harus menambahkan logic untuk user sharing di sini.
                 throw new Exception("Access Denied: You are not the owner of this folder or do not have sufficient permissions.");
            }

            // 2. Ambil Folders
            $stmt_f = $pdo->prepare("SELECT id, name, created_at FROM folders WHERE parent_id = ? AND user_id = ? ORDER BY name ASC");
            $stmt_f->execute([$folder_id, $current_user_id]);
            $data['folders'] = $stmt_f->fetchAll(PDO::FETCH_ASSOC);

            // 3. Ambil Files
            $stmt_file = $pdo->prepare("SELECT id, name, size, mime_type, created_at FROM files WHERE folder_id = ? AND user_id = ? ORDER BY name ASC");
            $stmt_file->execute([$folder_id, $current_user_id]);
            $data['files'] = $stmt_file->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'create_folder':
            $parent_id = $_POST['parent_id'] ?? get_root_folder_id($pdo, $current_user_id);
            $name = trim($_POST['name']);

            if (empty($name)) {
                throw new Exception("Nama folder tidak boleh kosong.");
            }
            
            // Cek duplikasi
            $stmt_dup = $pdo->prepare("SELECT id FROM folders WHERE parent_id = ? AND user_id = ? AND name = ?");
            $stmt_dup->execute([$parent_id, $current_user_id, $name]);
            if ($stmt_dup->fetchColumn()) {
                throw new Exception("Folder dengan nama yang sama sudah ada.");
            }


            $stmt = $pdo->prepare("INSERT INTO folders (name, parent_id, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $parent_id, $current_user_id]);

            echo json_encode(['status' => 'success', 'message' => 'Folder berhasil dibuat.']);
            break;

        case 'upload_file':
            $folder_id = $_POST['folder_id'] ?? get_root_folder_id($pdo, $current_user_id);
            
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                 throw new Exception("Gagal mengunggah file. Kode error: " . ($_FILES['file']['error'] ?? 'N/A'));
            }

            $file = $_FILES['file'];
            $file_name = basename($file['name']);
            $file_size = $file['size'];
            $file_mime = $file['type'];
            
            $user_dir = STORAGE_ROOT . $current_user_id . '/';
            if (!is_dir($user_dir)) {
                mkdir($user_dir, 0777, true);
            }

            // Buat nama file unik untuk penyimpanan
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $filename_on_disk = uniqid() . '_' . time() . '.' . $file_extension;
            $full_path = $user_dir . $filename_on_disk;

            if (move_uploaded_file($file['tmp_name'], $full_path)) {
                // Simpan metadata ke DB
                $stmt = $pdo->prepare("INSERT INTO files (name, mime_type, size, storage_path, folder_id, user_id) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$file_name, $file_mime, $file_size, $full_path, $folder_id, $current_user_id]);

                echo json_encode(['status' => 'success', 'message' => 'File berhasil diunggah.']);
            } else {
                throw new Exception("Gagal memindahkan file ke direktori server.");
            }
            break;
            
        case 'download':
            // Ini akan dipanggil langsung dari <a> tag, bukan AJAX, jadi kita harus kirim header file
            $file_id = $_GET['file_id'] ?? 0;
            $download_user_id = $_GET['user_id'] ?? 0; // User yang meminta download

            if (empty($file_id)) {
                 header("HTTP/1.1 400 Bad Request");
                 echo "File ID is required.";
                 exit;
            }

            $stmt = $pdo->prepare("SELECT name, storage_path, user_id, is_public FROM files WHERE id = ?");
            $stmt->execute([$file_id]); 
            $file_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$file_data || !file_exists($file_data['storage_path'])) {
                 header("HTTP/1.1 404 Not Found");
                 echo "File not found.";
                 exit;
            }

            // Check Izin Download
            $can_download = false;
            // 1. Pemilik selalu bisa download
            if ($file_data['user_id'] == $download_user_id) {
                $can_download = true;
            }
            // 2. Public file bisa didownload oleh siapapun yang login (atau bahkan tidak login, tergantung implementasi session)
            if ($file_data['is_public'] == 1) {
                $can_download = true;
            }
            // TODO: Tambahkan check table permissions untuk user_id tertentu
            
            if (!$can_download) {
                 header("HTTP/1.1 403 Forbidden");
                 echo "Access Denied: You do not have permission to download this file.";
                 exit;
            }

            // Kirim File
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_data['name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_data['storage_path']));
            ob_clean();
            flush();
            readfile($file_data['storage_path']);
            exit;

        // Tambahkan case untuk: delete_file, delete_folder, rename, manage_permission, dll.
        // ...

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid Action.']);
            break;
    }
} catch (Exception $e) {
    // Tangani semua pengecualian dengan pesan error JSON
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Pastikan koneksi ditutup jika menggunakan koneksi non-persistent
$pdo = null;
?>