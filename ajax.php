<?php
ob_start();
session_start();
date_default_timezone_set("Asia/Jakarta");

include 'db_connect.php';
include 'admin_class.php';
$crud = new Action();

$action = $_GET['action'] ?? '';

/* ========== AUTH ========== */
if ($action == 'login') { echo $crud->login(); exit; }
if ($action == 'login2') { echo $crud->login2(); exit; }
if ($action == 'logout') { echo $crud->logout(); exit; }
if ($action == 'logout2') { echo $crud->logout2(); exit; }
if ($action == 'signup') { echo $crud->signup(); exit; }

/* ========== USER MANAGEMENT / CHAT ========== */
if ($action == 'save_user') { echo $crud->save_user(); exit; }
if ($action == 'update_user') { echo $crud->update_user(); exit; }
if ($action == 'delete_user') { echo $crud->delete_user(); exit; }
if ($action == 'create_new_group') { echo $crud->create_new_group(); exit; }
if ($action == 'get_all_chat_sidebar_data') { echo $crud->get_all_chat_sidebar_data(); exit; }
if ($action == 'save_group_chat_message') { echo $crud->save_group_chat_message(); exit; } 
if ($action == 'get_group_chat_messages') { echo $crud->get_group_chat_messages(); exit; }

// 💡 NEW ROUTING FOR GROUP SETTINGS & DELETION
if ($action == 'get_group_details') { echo $crud->get_group_details(); exit; }
if ($action == 'update_group_settings') { echo $crud->update_group_settings(); exit; }
if ($action == 'delete_group') { echo $crud->delete_group(); exit; }

if ($action == 'get_all_users_for_chat') { echo $crud->get_all_users_for_chat(); exit; } 
if ($action == 'get_or_create_thread_id') { echo $crud->get_or_create_thread_id(); exit; }
if ($action == 'save_personal_chat_message') { echo $crud->save_personal_chat_message(); exit; }
if ($action == 'get_personal_chat_messages') { echo $crud->get_personal_chat_messages(); exit; }

/* ========== PROJECT MANAGEMENT ========== */
if ($action == 'save_project') {
    $save = $crud->save_project();
    echo $save;
    exit;
}

if ($action == 'delete_project') {
    $save = $crud->delete_project();
    echo $save;
    exit;
}

/* ========== TASK MANAGEMENT ========== */
if ($action == 'save_task') {
    echo $crud->save_task();
    exit;
}

if ($action == 'delete_task') {
    $save = $crud->delete_task();
    echo $save;
    exit;
}
// ...

/* ========== PROGRESS MANAGEMENT ========== */
if ($action == 'save_progress') {
    $save = $crud->save_progress();
    echo $save;
    exit;
}

if ($action == 'delete_progress') {
    $save = $crud->delete_progress();
    echo $save;
    exit;
}

/* ========== REPORT / MISC ========== */
if ($action == 'get_report') { echo $crud->get_report(); exit; }

if ($action == 'check_email') {
    $email = $conn->real_escape_string($_POST['email']);
    $check = $conn->query("SELECT * FROM users WHERE email = '$email'"); // Menggunakan * untuk konsistensi, walau id sudah cukup
    echo $check->num_rows > 0 ? 1 : 0;
    exit;
}

/* ========== NOTIFICATION MANAGEMENT ========== */
if ($action == 'fetch_notifications') {
    $user_id = $_SESSION['login_id'];
    
    $notifications_q = $conn->query("
        SELECT id, user_id, message, link, is_read, date_created, task_id 
        FROM notification_list 
        WHERE user_id = '$user_id' 
        ORDER BY date_created DESC 
        LIMIT 10
    ");

    $notifications = [];
    while ($row = $notifications_q->fetch_assoc()) {
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

/* ========== GET PROJECT USERS FOR TASK ASSIGNMENT ========== */
if(isset($_GET['action']) && $_GET['action'] == 'get_project_users'){
    // (Kode ini sudah benar, langsung di dalamnya)
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

switch($action){
    // ...
    case 'save_task':
        // ... (Logic save task) ...
        if($save) {
            echo 1; // Success
        } else {
            echo 2; // Error
        }
        break;
    
    case 'delete_task':
        $id = decode_id($_POST['id']); // Menerima ID terenkripsi
        // ... (Logic delete task) ...
        if($delete) {
            echo 1; // Success
        } else {
            echo 2; // Error
        }
        break;

    case 'save_progress':
        // ... (Logic save comment/progress) ...
        if($save) {
            echo 1; // Success
        } else {
            echo 2; // Error
        }
        break;
    
    case 'delete_progress':
        $id = decode_id($_POST['id']); // Menerima ID terenkripsi
        // ... (Logic delete comment/progress) ...
        if($delete) {
            echo 1; // Success
        } else {
            echo 2; // Error
        }
        break;
}

ob_end_flush();
?>