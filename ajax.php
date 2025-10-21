<?php
ob_start();
session_start();
date_default_timezone_set("Asia/Jakarta");

include 'db_connect.php';
include 'admin_class.php';
$crud = new Action();

$action = $_GET['action'] ?? '';

/* ========== AUTH ========== */
if ($action == 'login') echo $crud->login();
if ($action == 'login2') echo $crud->login2();
if ($action == 'logout') echo $crud->logout();
if ($action == 'logout2') echo $crud->logout2();
if ($action == 'signup') echo $crud->signup();

/* ========== USER MANAGEMENT ========== */
if ($action == 'save_user') echo $crud->save_user();
if ($action == 'update_user') echo $crud->update_user();
if ($action == 'delete_user') echo $crud->delete_user();

/* ========== PROJECT MANAGEMENT ========== */
if ($action == 'save_project') {
    // Logika dan logging sudah dipindahkan ke admin_class.php
    $save = $crud->save_project();
    echo $save;
}

if ($action == 'delete_project') {
    // Logika dan logging sudah dipindahkan ke admin_class.php
    $save = $crud->delete_project();
    echo $save;
}

/* ========== TASK MANAGEMENT ========== */
if ($action == 'save_task') {
    // Logika, logging, dan NOTIFIKASI sudah dipindahkan ke admin_class.php
    echo $crud->save_task();
}


if ($action == 'delete_task') {
    // Logika dan logging sudah dipindahkan ke admin_class.php
    $save = $crud->delete_task();
    echo $save;
}

/* ========== PROGRESS MANAGEMENT ========== */
if ($action == 'save_progress') {
    // Logika, logging, dan NOTIFIKASI sudah dipindahkan ke admin_class.php
    $save = $crud->save_progress();
    echo $save;
}

if ($action == 'delete_progress') {
    // Logika dan logging sudah dipindahkan ke admin_class.php
    $save = $crud->delete_progress();
    echo $save;
}

/* ========== REPORT / MISC ========== */
if ($action == 'get_report') echo $crud->get_report();

// ... sisa kode existing di bawah baris 112

// ... TAMBAHKAN KODE NOTIFIKASI BARU DI SINI

/* ========== NOTIFICATION MANAGEMENT ========== */
if ($action == 'fetch_notifications') {
    $user_id = $_SESSION['login_id'];
    
    // Ambil 5 notifikasi terbaru 
    $notifications_q = $conn->query("SELECT * FROM notification_list WHERE user_id = '$user_id' ORDER BY date_created DESC LIMIT 5");
    $notifications = [];
    while ($row = $notifications_q->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    // Hitung notifikasi belum dibaca
    $unread_count_q = $conn->query("SELECT COUNT(id) AS total FROM notification_list WHERE user_id = '$user_id' AND is_read = 0");
    $unread_count = $unread_count_q->fetch_assoc()['total'];

    header('Content-Type: application/json');
    echo json_encode(['status' => 1, 'notifications' => $notifications, 'unread_count' => $unread_count]);
    exit;

}

if ($action == 'mark_as_read') {
    $id = $conn->real_escape_string($_POST['id'] ?? null);
    $user_id = $_SESSION['login_id'];
    
    if ($id === 'all') {
        $qry = $conn->query("UPDATE notification_list SET is_read = 1 WHERE user_id = '$user_id' AND is_read = 0");
    } elseif (is_numeric($id) && $id > 0) {
        $qry = $conn->query("UPDATE notification_list SET is_read = 1 WHERE id = '$id' AND user_id = '$user_id'");
    } else {
        echo 0;
        exit;
    }
    
    if($qry){
        echo 1;
    } else {
        echo 0;
    }
    exit;
}

/* ========== REPORT / MISC ========== */
if ($action == 'get_report') echo $crud->get_report();

if ($action == 'check_email') {
    $email = $conn->real_escape_string($_POST['email']);
    $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
    echo $check->num_rows > 0 ? 1 : 0;
}

/* ========== GET PROJECT USERS FOR TASK ASSIGNMENT ========== */
if ($action == 'get_project_users') {
    $pid = intval($_POST['pid'] ?? 0);
    $options = '<option value="">Select Employee(s)</option>'; // Default option

    if ($pid > 0) {
        // 1. Ambil string user_ids (e.g., '5,12,8') dari project_list
        $project_query = $conn->query("SELECT user_ids FROM project_list WHERE id = $pid");
        
        if ($project_query->num_rows > 0) {
            $project = $project_query->fetch_assoc();
            $user_ids_string = $project['user_ids']; // Ini adalah string user ID yang dipisahkan koma

            if (!empty($user_ids_string)) {
                // 2. Query tabel users berdasarkan user_ids yang ditemukan
                // Asumsi tabel user Anda bernama 'users' dan memiliki kolom 'id', 'firstname', 'lastname'.
                $users_query = $conn->query("
                    SELECT 
                        id, 
                        CONCAT(firstname, ' ', lastname) AS name 
                    FROM 
                        users 
                    WHERE 
                        id IN ($user_ids_string) 
                    ORDER BY 
                        name ASC
                ");
                
                if ($users_query) {
                    while ($row = $users_query->fetch_assoc()) {
                        $options .= "<option value='{$row['id']}'>" . ucwords($row['name']) . "</option>";
                    }
                }
            } else {
                $options .= '<option value="" disabled>No users are assigned to this project yet.</option>';
            }
        }
    }
    
    // Kirimkan HTML options kembali ke frontend
    echo $options;
}

// Di dalam switch($_GET['action'])
switch($action){
    // ... existing cases ...
    
    case 'fetch_notifications':
        $user_id = $_SESSION['login_id'];
        
        // Ambil 5 notifikasi terbaru (bisa diubah LIMITnya)
        $notifications_q = $conn->query("SELECT * FROM notification_list WHERE user_id = '$user_id' ORDER BY date_created DESC LIMIT 5");
        $notifications = [];
        while ($row = $notifications_q->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        // Hitung notifikasi belum dibaca
        $unread_count_q = $conn->query("SELECT COUNT(id) AS total FROM notification_list WHERE user_id = '$user_id' AND is_read = 0");
        $unread_count = $unread_count_q->fetch_assoc()['total'];

        header('Content-Type: application/json');
        echo json_encode(['status' => 1, 'notifications' => $notifications, 'unread_count' => $unread_count]);
        exit;

    case 'mark_as_read':
        $id = $conn->real_escape_string($_POST['id'] ?? null);
        $user_id = $_SESSION['login_id'];
        
        if ($id === 'all') {
            $qry = $conn->query("UPDATE notification_list SET is_read = 1 WHERE user_id = '$user_id' AND is_read = 0");
        } elseif (is_numeric($id) && $id > 0) {
            $qry = $conn->query("UPDATE notification_list SET is_read = 1 WHERE id = '$id' AND user_id = '$user_id'");
        } else {
            echo 0;
            exit;
        }
        
        if($qry){
            echo 1;
        } else {
            echo 0;
        }
        exit;

    // ... sisa existing cases ...
}

ob_end_flush();
?>

