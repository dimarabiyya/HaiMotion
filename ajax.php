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
    $save = $crud->delete_task(); // <-- Memanggil fungsi yang diperbaiki
    echo $save;
}
// ...

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


/* ========== NOTIFICATION MANAGEMENT ========== */
if ($action == 'fetch_notifications') {
    $user_id = $_SESSION['login_id'];
    
    // Ambil 5 notifikasi terbaru 
    $notifications_q = $conn->query("
        SELECT id, user_id, message, link, is_read, date_created, task_id 
        FROM notification_list 
        WHERE user_id = '$user_id' 
        ORDER BY date_created DESC 
        LIMIT 10
    ");

    
    $notifications = [];
    while ($row = $notifications_q->fetch_assoc()) {
        // ✅ Pastikan ada kolom 'id' walau nama aslinya 'notification_id'
        if (!isset($row['id'])) $row['id'] = $row['notification_id'] ?? null;
        $notifications[] = $row;
    }
    
    // Hitung notifikasi belum dibaca
    $unread_count_q = $conn->query("
        SELECT COUNT(*) AS total 
        FROM notification_list 
        WHERE user_id = '$user_id' AND is_read = 0
    ");
    $unread_count = $unread_count_q->fetch_assoc()['total'];

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 1,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
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
if(isset($_GET['action']) && $_GET['action'] == 'get_project_users'){
    include 'db_connect.php';
    $pid = intval($_POST['pid']);

    // Ambil user dari project
    $qry = $conn->query("SELECT user_ids FROM project_list WHERE id = $pid");
    $user_ids = '';
    if($qry->num_rows > 0){
        $user_ids = $qry->fetch_assoc()['user_ids'];
    }

    if(!empty($user_ids)){
        $users = $conn->query("SELECT id, CONCAT(firstname,' ',lastname) AS name FROM users WHERE id IN ($user_ids) ORDER BY name ASC");
        while($row = $users->fetch_assoc()){
            echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['name']).'</option>';
        }
    }else{
        echo '<option value="">No users assigned to this project</option>';
    }
    exit;
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

    
    // --- Tambahkan di dalam switch statement di ajax.php ---
    case 'get_all_users_for_chat':
        echo $crud->get_all_users_for_chat();
        break;
    case 'get_or_create_thread_id':
        echo $crud->get_or_create_thread_id();
        break;
    case 'save_personal_chat_message':
        echo $crud->save_personal_chat_message();
        break;
    case 'get_personal_chat_messages':
        echo $crud->get_personal_chat_messages();
        break;
    // ...
    
}

ob_end_flush();
?>

