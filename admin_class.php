<?php
ini_set('display_errors', 1);
require_once 'phpmailer_config.php'; // TAMBAHKAN BARIS INI

class Action {
    private $db;

    public function __construct() {
        ob_start();
        include 'db_connect.php';
        $this->db = $conn;
    }

    // === LOG AKTIVITAS (fixed) ===
    function log_activity($user_id, $project_id = null, $task_id = null, $activity_type = '', $description = '') {
        if (!$this->db) {
            error_log("log_activity: no db connection");
            return false;
        }
        $stmt = $this->db->prepare("
            INSERT INTO activity_log (user_id, project_id, task_id, activity_type, description, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            error_log("log_activity prepare error: " . $this->db->error);
            return false;
        }
        // types: i = int, s = string
        $bind = $stmt->bind_param("iiiss", $user_id, $project_id, $task_id, $activity_type, $description);
        if (!$bind) {
            error_log("log_activity bind_param error: " . $stmt->error);
            $stmt->close();
            return false;
        }
        $exec = $stmt->execute();
        if (!$exec) {
            error_log("log_activity execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }
        $stmt->close();
        return true;
    }

    // === LOGIN / LOGOUT ===
    function login() {
        extract($_POST);
        $email = $this->db->real_escape_string($email ?? '');
        $password = md5($password ?? '');
        $qry = $this->db->query("SELECT *, concat(firstname,' ',lastname) as name FROM users WHERE email = '{$email}' AND password = '{$password}'");
        if ($qry->num_rows > 0) {
            foreach ($qry->fetch_array() as $key => $value) {
                if ($key != 'password' && !is_numeric($key))
                    $_SESSION['login_' . $key] = $value;
            }
            return 1;
        } else {
            return 2;
        }
    }

    function logout() {
        session_destroy();
        foreach ($_SESSION as $key => $value) {
            unset($_SESSION[$key]);
        }
        header("location:login.php");
    }

    // === USER MANAGEMENT ===
    function save_user() {
        extract($_POST);
        $data = "";
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id', 'cpass', 'password')) && !is_numeric($k)) {
                $v = $this->db->real_escape_string($v);
                $data .= (empty($data)) ? " $k='{$v}' " : ", $k='{$v}' ";
            }
        }

        if (!empty($password)) {
            $pwd = md5($password);
            $data .= ", password='{$pwd}' ";
        }

        $email = $this->db->real_escape_string($email ?? '');
        $check = $this->db->query("SELECT * FROM users WHERE email ='{$email}' " . (!empty($id) ? " AND id != {$id} " : ''))->num_rows;
        if ($check > 0) return 2;

        if (isset($_FILES['img']) && $_FILES['img']['tmp_name'] != '') {
            // Amankan nama file
            $original_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $_FILES['img']['name']);
            $fname = time() . '_' . $original_name;
            $upload_path = 'assets/uploads/' . $fname;

            // Pastikan folder ada
            if (!is_dir('assets/uploads')) {
                mkdir('assets/uploads', 0777, true);
            }

            // Pindahkan file
            if (move_uploaded_file($_FILES['img']['tmp_name'], $upload_path)) {
                $data .= ", avatar = '{$fname}' ";
            } else {
                error_log("Gagal upload avatar: " . $_FILES['img']['error']);
            }
        }


        if (empty($id)) {
            $save = $this->db->query("INSERT INTO users SET $data");
        } else {
            $save = $this->db->query("UPDATE users SET $data WHERE id = $id");
        }

        if ($save) return 1;
    }

    function delete_user() {
        extract($_POST);
        $delete = $this->db->query("DELETE FROM users WHERE id = " . intval($id));
        if ($delete) return 1;
    }

    // === PROJECT MANAGEMENT ===
    function save_project() {
        extract($_POST);
        $data = "";
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id', 'user_ids')) && !is_numeric($k)) {
                if ($k == 'description') $v = htmlentities(str_replace("'", "&#x2019;", $v));
                $v = $this->db->real_escape_string($v);
                $data .= (empty($data)) ? " $k='{$v}' " : ", $k='{$v}' ";
            }
        }
        if (isset($user_ids)) {
            $user_ids_clean = array_map('intval', $user_ids);
            $data .= ", user_ids='" . implode(',', $user_ids_clean) . "' ";
        }

        if (empty($id)) {
            $save = $this->db->query("INSERT INTO project_list SET $data");
            if ($save) {
                $pid = $this->db->insert_id;
                $this->log_activity($_SESSION['login_id'], $pid, null, 'project_add', 'Menambahkan project baru: ' . ($name ?? ''));
            }
        } else {
            $save = $this->db->query("UPDATE project_list SET $data WHERE id = $id");
            if ($save) {
                $this->log_activity($_SESSION['login_id'], $id, null, 'project_update', 'Mengupdate project: ' . ($name ?? ''));
            }
        }

        if ($save) return 1;
    }

    function delete_project() {
        extract($_POST);
        $id = intval($id);
        $qry = $this->db->query("SELECT name FROM project_list WHERE id = $id");
        if ($qry && $qry->num_rows > 0) {
            $row = $qry->fetch_assoc();
            $project_name = $row['name'];
            $delete = $this->db->query("DELETE FROM project_list WHERE id = $id");
            if ($delete) {
                $this->log_activity($_SESSION['login_id'], $id, null, 'project_delete', 'Menghapus project: ' . $project_name);
                return 1;
            }
        }
        return 0;
    }

    // === TASK MANAGEMENT ===
    function save_task() {
        // ambil dari $_POST tapi tidak menggunakan extract() untuk keamanan
        $task = $this->db->real_escape_string($_POST['task'] ?? '');
        $description = $this->db->real_escape_string($_POST['description'] ?? '');
        $project_id = intval($_POST['project_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);

        // user_ids bisa berupa array (multi select) atau string
        $user_ids_array = [];
        $user_ids_string = '';
        if (isset($_POST['user_ids'])) {
            if (is_array($_POST['user_ids'])) {
                $user_ids_array = array_map('intval', $_POST['user_ids']);
                $user_ids_string = implode(',', $user_ids_array);
            } else {
                // jika datang sebagai string (contoh: "1,2,3")
                $user_ids_array = array_map('intval', array_filter(explode(',', $_POST['user_ids'])));
                $user_ids_string = implode(',', $user_ids_array);
            }
        }

        $start_date = $this->db->real_escape_string($_POST['start_date'] ?? '');
        $end_date   = $this->db->real_escape_string($_POST['end_date'] ?? '');

        // content_pillar & platform bisa checkbox array atau string input
        $content_pillar = '';
        if (isset($_POST['content_pillar'])) {
            if (is_array($_POST['content_pillar'])) {
                $content_pillar = $this->db->real_escape_string(implode(',', array_map('trim', $_POST['content_pillar'])));
            } else {
                $content_pillar = $this->db->real_escape_string($_POST['content_pillar']);
            }
        }

        $platform = '';
        if (isset($_POST['platform'])) {
            if (is_array($_POST['platform'])) {
                $platform = $this->db->real_escape_string(implode(',', array_map('trim', $_POST['platform'])));
            } else {
                $platform = $this->db->real_escape_string($_POST['platform']);
            }
        }

        $reference_links = $this->db->real_escape_string($_POST['reference_links'] ?? '');

        if (empty($task) || $project_id <= 0) {
            // missing required
            return 0;
        }

        $is_new = empty($_POST['id']);
        $sql = '';

        if ($is_new) {
            // gunakan created_by dari session (lebih aman dibanding rely on form input)
            $created_by = isset($_SESSION['login_id']) ? intval($_SESSION['login_id']) : 0;

            $sql = "INSERT INTO task_list 
                (project_id, task, description, status, user_ids, start_date, end_date, content_pillar, platform, reference_links, created_by, date_created)
                VALUES
                ({$project_id}, '{$task}', '{$description}', {$status}, '{$this->db->real_escape_string($user_ids_string)}', '{$start_date}', '{$end_date}', '{$content_pillar}', '{$platform}', '{$reference_links}', {$created_by}, NOW()
                )";
        } else {
            $id = intval($_POST['id']);
            $sql = "UPDATE task_list SET
                project_id = {$project_id},
                task = '{$task}',
                description = '{$description}',
                status = {$status},
                user_ids = '{$this->db->real_escape_string($user_ids_string)}',
                start_date = '{$start_date}',
                end_date = '{$end_date}',
                content_pillar = '{$content_pillar}',
                platform = '{$platform}',
                reference_links = '{$reference_links}'
                WHERE id = {$id}";
        }

        $save = $this->db->query($sql);
        if (!$save) {
            error_log("save_task failed SQL: {$sql} -- Error: " . $this->db->error);
            return 0;
        }

        $task_id = $is_new ? $this->db->insert_id : $id;
        $action_type = $is_new ? 'task_add' : 'task_update';

        // Log aktivitas
        $proj = $this->db->query("SELECT name FROM project_list WHERE id = {$project_id}")->fetch_assoc();
        $project_name = $proj['name'] ?? 'Unknown Project';
        $log_desc = $action_type == 'task_add' ? "Menambahkan task baru: {$task} pada project: {$project_name}" : "Mengupdate task: {$task}";
        $this->log_activity($_SESSION['login_id'] ?? 0, $project_id, $task_id, $action_type, $log_desc);

        // push notifikasi seperti sebelumnya (tetap gunakan user_ids_array)
        $link = "index.php?page=view_task&id=" . (function_exists('encode_id') ? encode_id($task_id) : $task_id);
        $message_prefix = $action_type == 'task_add' ? "baru ditugaskan" : "diupdate";
        $message = "Task {$task} telah {$message_prefix} di project {$project_name}.";
        $email_subject = "[TASK] Tugas {$task} {$message_prefix}";

        if (!empty($user_ids_array)) {
            $ids_str = implode(',', $user_ids_array);
            $users_q = $this->db->query("SELECT id, email, firstname, lastname FROM users WHERE id IN ({$ids_str})");
            while ($user = $users_q->fetch_assoc()) {
                $full_name = ucwords($user['firstname'] . ' ' . $user['lastname']);
                $email_details = [
                    'email' => $user['email'],
                    'name'  => $full_name,
                    'subject' => $email_subject
                ];
                record_notification($user['id'], 1, $message, $link, $this->db, true, $email_details);
            }
        }

        return 1;
    }


    public function delete_task() {
        // Memastikan koneksi database tersedia (seperti yang dilakukan oleh include 'db_connect.php')
        global $conn; 
        
        // 1. Ambil ID tugas
        extract($_POST);
        $id = intval($id);
        
        // 2. HAPUS NOTIFIKASI TERKAIT (SOLUSI BUG)
        // Notifikasi merujuk ke link: view_task.php?id=[ID_TUGAS]
        $delete_notification = $conn->query("
            DELETE FROM notification_list 
            WHERE link = 'view_task.php?id=$id' 
        ");

        if (!$delete_notification) {
            // Log error jika penghapusan notifikasi gagal
            error_log("Gagal menghapus notifikasi terkait tugas $id: " . $conn->error);
        }
        
        // 3. HAPUS TUGAS UTAMA
        $delete_task = $conn->query("
            DELETE FROM task_list 
            WHERE id = $id
        ");

        if ($delete_task) {
            // Jika tugas utama berhasil dihapus
            return 1; // Success
        } else {
            // Jika tugas utama gagal dihapus
            return "Gagal menghapus tugas: " . $conn->error;
        }
    }

// === PROGRESS (TASK ACTIVITY) ===
    function save_progress() {
        extract($_POST);
        $data = "";
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id')) && !is_numeric($k)) {
                if ($k == 'comment') $v = htmlentities(str_replace("'", "&#x2019;", $v));
                $v = $this->db->real_escape_string($v);
                $data .= (empty($data)) ? " $k='{$v}' " : ", $k='{$v}' ";
            }
        }
        
        // ... kode perhitungan durasi (Line 324)
        $dur = abs(strtotime("2020-01-01 " . ($end_time ?? '00:00'))) - abs(strtotime("2020-01-01 " . ($start_time ?? '00:00')));
        $dur = $dur / (60 * 60);
        $data .= ", time_rendered='{$dur}' ";
        // ...

        if (empty($id)) {
            // ensure task_id present
            $data .= ", user_id=" . intval($_SESSION['login_id']) . " ";
            $sql = "INSERT INTO user_productivity SET $data";
            $save = $this->db->query($sql);
            if ($save) {
                $insert_id = $this->db->insert_id;
                $task_id = intval($task_id ?? 0);
                
                // --- Fetch Data for Notification ---
                $task_details_q = $this->db->query("
                    SELECT t.task, t.project_id, p.manager_id, t.user_ids AS task_users 
                    FROM task_list t 
                    INNER JOIN project_list p ON p.id = t.project_id 
                    WHERE t.id = {$task_id}
                ");
                
                $project_id = null; $task_name = '';
                if ($task_details_q && $task_details_q->num_rows > 0) {
                    $tr = $task_details_q->fetch_assoc();
                    $project_id = $tr['project_id'];
                    $task_name = $tr['task'];
                    $manager_id = $tr['manager_id'];
                    $task_user_ids = array_map('intval', array_filter(explode(',', $tr['task_users'])));

                    // Recipients: Manager + All Task Assignees (unique and exclude current user)
                    $recipients_ids = array_unique(array_merge([$manager_id], $task_user_ids));
                    
                    // Notification Logic (Comment Added)
                    if (!empty($recipients_ids)) {
                        $ids_str = implode(',', array_map('intval', $recipients_ids));
                        $users_q = $this->db->query("SELECT id, email, firstname, lastname FROM users WHERE id IN ({$ids_str})");
                        
                        $current_user_name = ucwords($_SESSION['login_firstname'] . ' ' . $_SESSION['login_lastname']);
                        $link = "index.php?page=view_task&id=" . encode_id($task_id);
                        $message = "Task **{$task_name}** mendapat komentar baru dari {$current_user_name}.";
                        $email_subject = "[KOMENTAR BARU] Task: {$task_name}";
                        
                        while ($user = $users_q->fetch_assoc()) {
                            // record_notification() akan mengabaikan notifikasi jika user_id == login_id
                            $full_name = ucwords($user['firstname'] . ' ' . $user['lastname']);
                            $email_details = [
                                'email' => $user['email'], 
                                'name' => $full_name,
                                'subject' => $email_subject
                            ];
                            // Type 4: Comment Added
                            record_notification($user['id'], 4, $message, $link, $this->db, true, $email_details);
                        }
                    }
                }
                
                $this->log_activity($_SESSION['login_id'], $project_id, $task_id, 'progress_add', 'Menambahkan progress pada task: ' . $task_name);
                return 1;
            } else {
                error_log("save_progress insert error: " . $this->db->error . " -- SQL: " . $sql);
                return 0;
            }
        } else {
            // ... (kode update progress yang sudah ada)
            $id = intval($id);
            $sql = "UPDATE user_productivity SET $data WHERE id = $id";
            $save = $this->db->query($sql);
            if ($save) return 1;
            error_log("save_progress update error: " . $this->db->error . " -- SQL: " . $sql);
            return 0;
        }
    }

    function delete_progress() {
        extract($_POST);
        $id = intval($id);
        $qry = $this->db->query("
            SELECT p.*, t.task, t.project_id 
            FROM user_productivity p 
            LEFT JOIN task_list t ON p.task_id = t.id 
            WHERE p.id = $id
        ");
        if ($qry && $qry->num_rows > 0) {
            $row = $qry->fetch_assoc();
            $progress_name = $row['comment'] ?? ($row['progress'] ?? '');
            $task_name = $row['task'] ?? '';
            $project_id = $row['project_id'] ?? null;
            $delete = $this->db->query("DELETE FROM user_productivity WHERE id = $id");
            if ($delete) {
                $this->log_activity($_SESSION['login_id'], $project_id, $row['task_id'] ?? null, 'progress_delete', 'Menghapus progress pada task: ' . $task_name);
                return 1;
            }
        }
        return 0;
    }

    // === DESTRUCT ===
    function __destruct() {
        if ($this->db) $this->db->close();
        ob_end_flush();
    }
}
?>
