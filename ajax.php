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
    $save = $crud->save_project();
    if ($save == 1) {
        $project_id = $conn->insert_id ?: ($_POST['id'] ?? null);
        $project_name = $_POST['name'] ?? '';
        $action_type = isset($_POST['id']) && $_POST['id'] ? 'project_update' : 'project_add';
        $desc = $action_type == 'project_add'
            ? "Menambahkan project baru: $project_name"
            : "Mengubah project: $project_name";
        $crud->log_activity($_SESSION['login_id'], $project_id, null, $action_type, $desc);
    }
    echo $save;
}

if ($action == 'delete_project') {
    $project_id = $_POST['id'] ?? 0;
    $pname = $conn->query("SELECT name FROM project_list WHERE id = $project_id")->fetch_assoc()['name'] ?? '';
    $save = $crud->delete_project();
    if ($save == 1) {
        $crud->log_activity($_SESSION['login_id'], $project_id, null, 'project_delete', "Menghapus project: $pname");
    }
    echo $save;
}

/* ========== TASK MANAGEMENT ========== */
if ($action == 'save_task') {
    $id          = intval($_POST['id'] ?? 0);
    $task        = $conn->real_escape_string($_POST['task'] ?? '');
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $status      = intval($_POST['status'] ?? 0);
    $project_id  = intval($_POST['project_id'] ?? 0);
    $user_ids    = isset($_POST['user_ids']) ? implode(',', $_POST['user_ids']) : '';
    $start_date  = $conn->real_escape_string($_POST['start_date'] ?? '');
    $end_date    = $conn->real_escape_string($_POST['end_date'] ?? '');
    $content_pillar  = isset($_POST['content_pillar']) ? implode(',', $_POST['content_pillar']) : '';
    $platform        = isset($_POST['platform']) ? implode(',', $_POST['platform']) : '';
    $reference_links = $conn->real_escape_string($_POST['reference_links'] ?? '');

    if (empty($task) || !$project_id) {
        echo "Task name dan project wajib diisi.";
        exit;
    }

    if (!$id) {
        $created_by = $_SESSION['login_id'];
        $sql = "INSERT INTO task_list 
                (project_id, task, description, status, user_ids, start_date, end_date, content_pillar, platform, reference_links, date_created, created_by)
                VALUES ($project_id, '$task', '$description', $status, '$user_ids', '$start_date', '$end_date', '$content_pillar', '$platform', '$reference_links', NOW(), '$created_by')";
        if ($conn->query($sql)) {
            $task_id = $conn->insert_id;
            $crud->log_activity($_SESSION['login_id'], $project_id, $task_id, 'task_add', 'Menambahkan task baru: ' . $task);
            echo 1;
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        $created_by = $_SESSION['login_id'];
        $sql = "UPDATE task_list SET 
                    task = '$task',
                    description = '$description',
                    status = $status,
                    user_ids = '$user_ids',
                    start_date = '$start_date',
                    end_date = '$end_date',
                    content_pillar = '$content_pillar',
                    platform = '$platform',
                    reference_links = '$reference_links',
                    created_by = '$created_by'
                WHERE id = $id";
        if ($conn->query($sql)) {
            $crud->log_activity($_SESSION['login_id'], $project_id, $id, 'task_update', 'Mengubah task: ' . $task);
            echo 1;
        } else {
            echo "Error: " . $conn->error;
        }
    }
}


if ($action == 'delete_task') {
    $task_id = $_POST['id'] ?? 0;
    $tinfo = $conn->query("SELECT t.task, t.project_id, p.name AS project_name 
                           FROM task_list t 
                           LEFT JOIN project_list p ON t.project_id = p.id 
                           WHERE t.id = $task_id")->fetch_assoc();
    $save = $crud->delete_task();
    if ($save == 1) {
        $crud->log_activity($_SESSION['login_id'], $tinfo['project_id'], $task_id, 'task_delete', "Menghapus task: {$tinfo['task']}");
    }
    echo $save;
}

/* ========== PROGRESS MANAGEMENT ========== */
if ($action == 'save_progress') {
    $save = $crud->save_progress();
    if ($save == 1) {
        $task_id = $_POST['task_id'] ?? 0;
        $project_id = $conn->query("SELECT project_id FROM task_list WHERE id = $task_id")->fetch_assoc()['project_id'] ?? 0;
        $crud->log_activity($_SESSION['login_id'], $project_id, $task_id, 'progress_add', 'Menambahkan progress baru pada task.');
    }
    echo $save;
}

if ($action == 'delete_progress') {
    $progress_id = $_POST['id'] ?? 0;
    $tinfo = $conn->query("SELECT p.task_id, t.project_id, t.task 
                           FROM user_productivity p 
                           LEFT JOIN task_list t ON p.task_id = t.id 
                           WHERE p.id = $progress_id")->fetch_assoc();
    $save = $crud->delete_progress();
    if ($save == 1) {
        $crud->log_activity($_SESSION['login_id'], $tinfo['project_id'], $tinfo['task_id'], 'progress_delete', "Menghapus progress pada task: {$tinfo['task']}");
    }
    echo $save;
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

ob_end_flush();
?>

