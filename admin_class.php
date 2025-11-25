<?php
// FILE: view_project.php (REVISI LENGKAP - KPI TIM)

ini_set('display_errors', 1);
require_once 'phpmailer_config.php'; // Pastikan file ini berisi konfigurasi SMTP dan fungsi send_task_notification_email & record_notification

class Action {
    private $db;

    public function __construct() {
        ob_start();
        include 'db_connect.php';
        $this->db = $conn;
    }

    // === LOG AKTIVITAS ===
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
        
        // 1. ITERASI DATA POST (Termasuk 'notification_email')
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id', 'cpass', 'password')) && !is_numeric($k)) {
                $v = $this->db->real_escape_string($v);
                $data .= (empty($data)) ? " $k='{$v}' " : ", $k='{$v}' ";
            }
        }

        // 2. Tambahkan Password
        if (!empty($password)) {
            $pwd = md5($password);
            $data .= ", password='{$pwd}' ";
        }

        // 3. Cek Duplikasi Email Login
        $email = $this->db->real_escape_string($email ?? '');
        $check = $this->db->query("SELECT * FROM users WHERE email ='{$email}' " . (!empty($id) ? " AND id != {$id} " : ''))->num_rows;
        if ($check > 0) return 2; // Error 2: Email sudah digunakan

        // 4. Proses Upload Avatar
        if (isset($_FILES['img']) && $_FILES['img']['tmp_name'] != '') {
            $original_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $_FILES['img']['name']);
            $fname = time() . '_' . $original_name;
            $upload_path = 'assets/uploads/' . $fname;

            if (!is_dir('assets/uploads')) {
                mkdir('assets/uploads', 0777, true);
            }

            if (move_uploaded_file($_FILES['img']['tmp_name'], $upload_path)) {
                $data .= ", avatar = '{$fname}' ";
                
                // Hapus avatar lama saat update
                if(!empty($id)){
                    $old_avatar_qry = $this->db->query("SELECT avatar FROM users WHERE id = $id");
                    if($old_avatar_qry->num_rows > 0){
                        $old_avatar = $old_avatar_qry->fetch_assoc()['avatar'];
                        if(!empty($old_avatar) && file_exists('assets/uploads/'.$old_avatar)){
                            @unlink('assets/uploads/'.$old_avatar);
                        }
                    }
                }
            } else {
                error_log("Gagal upload avatar: " . $_FILES['img']['error']);
            }
        }


        // 5. Jalankan Query
        if (empty($id)) {
            $save = $this->db->query("INSERT INTO users SET $data");
        } else {
            $save = $this->db->query("UPDATE users SET $data WHERE id = $id");
        }

        if ($save) return 1;
        
        error_log("save_user SQL Error: " . $this->db->error);
        return 0; // Gagal simpan
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

    // === TASK MANAGEMENT (REVISI PENTING DI SINI) ===
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
            return 0;
        }

        $is_new = empty($_POST['id']);
        $sql = '';

        if ($is_new) {
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

        // push notifikasi
        $link = "index.php?page=view_task&id=" . (function_exists('encode_id') ? encode_id($task_id) : $task_id);
        $message_prefix = $action_type == 'task_add' ? "baru ditugaskan" : "diupdate";
        $message = "Task {$task} telah {$message_prefix} di project {$project_name}.";
        $email_subject = "[TASK] Tugas {$task} {$message_prefix}";

        if (!empty($user_ids_array)) {
            $ids_str = implode(',', $user_ids_array);
            // REVISI: Ambil kolom notification_email
            $users_q = $this->db->query("SELECT id, email, notification_email, firstname, lastname FROM users WHERE id IN ({$ids_str})");
            
            while ($user = $users_q->fetch_assoc()) {
                $full_name = ucwords($user['firstname'] . ' ' . $user['lastname']);
                
                // Prioritaskan notification_email, fallback ke email login
                $target_email = !empty($user['notification_email']) ? $user['notification_email'] : $user['email'];

                $email_details = [
                    'email' => $target_email, // GUNAKAN $target_email
                    'name'  => $full_name,
                    'subject' => $email_subject
                ];
                record_notification($user['id'], 1, $message, $link, $this->db, true, $email_details);
            }
        }

        return 1;
    }


    public function delete_task() {
        // Memastikan koneksi database tersedia
        global $conn; 
        
        // 1. Ambil ID tugas
        extract($_POST);
        $id = intval($id);
        
        // ✅ REVISI: Ambil Task dan Project ID sebelum menghapus
        $task_info_qry = $conn->query("
            SELECT t.task, t.project_id, p.name as project_name
            FROM task_list t
            INNER JOIN project_list p ON p.id = t.project_id
            WHERE t.id = $id
        ");

        $project_id = null;
        $task_name = "ID Task: {$id}";
        $project_name = "Unknown Project";

        if ($task_info_qry && $task_info_qry->num_rows > 0) {
            $info = $task_info_qry->fetch_assoc();
            $project_id = intval($info['project_id']);
            $task_name = $this->db->real_escape_string($info['task']);
            $project_name = $this->db->real_escape_string($info['project_name']);
        }
        
        // 2. HAPUS NOTIFIKASI TERKAIT
        $delete_notification = $conn->query("
            DELETE FROM notification_list 
            WHERE link LIKE '%view_task.php?id=$id%' 
        ");

        if (!$delete_notification) {
            error_log("Gagal menghapus notifikasi terkait tugas $id: " . $conn->error);
        }
        
        // 3. HAPUS TUGAS UTAMA
        $delete_task = $conn->query("
            DELETE FROM task_list 
            WHERE id = $id
        ");

        if ($delete_task) {
            // ✅ REVISI LOG_ACTIVITY: Menggunakan info yang sudah diambil
            $log_desc = "Menghapus task: '{$task_name}' dari Project: '{$project_name}'";
            $this->log_activity($_SESSION['login_id'] ?? 0, $project_id, $id, 'task_delete', $log_desc);
            return 1;
        } else {
            error_log("Gagal menghapus tugas: " . $conn->error);
            return 0; // Mengembalikan 0 atau kode error.
        }
    }

    // === PROGRESS (TASK ACTIVITY) (REVISI PENTING DI SINI) ===
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
        
        $dur = abs(strtotime("2020-01-01 " . ($end_time ?? '00:00'))) - abs(strtotime("2020-01-01 " . ($start_time ?? '00:00')));
        $dur = $dur / (60 * 60);
        $data .= ", time_rendered='{$dur}' ";
        

        if (empty($id)) {
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
                        
                        // REVISI: Ambil kolom notification_email
                        $users_q = $this->db->query("SELECT id, email, notification_email, firstname, lastname FROM users WHERE id IN ({$ids_str})");
                        
                        $current_user_name = ucwords($_SESSION['login_firstname'] . ' ' . $_SESSION['login_lastname']);
                        $link = "index.php?page=view_task&id=" . encode_id($task_id);
                        $message = "Task **{$task_name}** mendapat komentar baru dari {$current_user_name}.";
                        $email_subject = "[KOMENTAR BARU] Task: {$task_name}";
                        
                        while ($user = $users_q->fetch_assoc()) {
                            // Prioritaskan notification_email, fallback ke email login
                            $target_email = !empty($user['notification_email']) ? $user['notification_email'] : $user['email'];

                            $full_name = ucwords($user['firstname'] . ' ' . $user['lastname']);
                            $email_details = [
                                'email' => $target_email, // GUNAKAN $target_email
                                'name' => $full_name,
                                'subject' => $email_subject
                            ];
                            // Type 4: Comment Added
                            record_notification($user['id'], 4, $message, $link, $this->db, true, $email_details);
                        }
                    }
                }
                
                $this->log_activity($_SESSION['login_id'], $project_id, $task_id, 'progress_add', 'Menambahkan Komentar pada task: ' . $task_name);
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

    // --- Tambahkan di dalam class Actions di admin_class.php ---
// 1. Mengambil daftar semua pengguna (kecuali diri sendiri) untuk sidebar chat
    function get_all_users_for_chat() {
        $current_user_id = $_SESSION['login_id'];
        
        // SQL KRITIS: Menggunakan LEFT JOIN dan Subquery
        $sql = "
            SELECT 
                u.id, 
                CONCAT(u.firstname, ' ', u.lastname) AS name, 
                u.avatar,
                t.id AS thread_id,
                t.last_message_at AS last_message_timestamp,
                
                -- Subquery untuk mengambil konten pesan terakhir
                (
                    SELECT cm_last.message_content 
                    FROM chat_messages cm_last
                    WHERE cm_last.thread_id = t.id 
                    ORDER BY cm_last.created_at DESC 
                    LIMIT 1
                ) AS last_message_content,
                
                -- Subquery untuk menghitung pesan belum dibaca
                (
                    SELECT COUNT(cm_unread.id) 
                    FROM chat_messages cm_unread
                    WHERE cm_unread.thread_id = t.id 
                    AND cm_unread.sender_id != '{$current_user_id}' 
                    AND cm_unread.is_read = 0
                ) AS unread_count
            FROM users u
            LEFT JOIN chat_threads t 
                ON (t.user1_id = u.id AND t.user2_id = '{$current_user_id}') 
                OR (t.user2_id = u.id AND t.user1_id = '{$current_user_id}')
            WHERE u.id != '{$current_user_id}'
        ";
        
        $result = $this->db->query($sql);
        
        if (!$result) {
            error_log("SQL Error in get_all_users_for_chat: " . $this->db->error);
            // Mengembalikan JSON kosong atau error jika gagal
            return json_encode(['error' => 'SQL_FAILED', 'debug' => $this->db->error, 'users' => []]);
        }
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            // Konversi tipe data untuk konsistensi
            $row['unread_count'] = $row['unread_count'] === null ? 0 : (int)$row['unread_count'];
            $row['thread_id'] = $row['thread_id'] === null ? null : (int)$row['thread_id'];
            
            if ($row['last_message_content'] !== null) {
                 $row['last_message_content'] = html_entity_decode($row['last_message_content']);
            }
            
            $users[] = $row;
        }
        return json_encode($users);
    }

    // 2. Mendapatkan ID thread yang ada atau membuat yang baru
    function get_or_create_thread_id() {
        extract($_POST);
        $user1 = $_SESSION['login_id'];
        $user2 = $this->db->real_escape_string($user2_id);
        
        $id1 = min($user1, $user2);
        $id2 = max($user1, $user2);

        $sql_check = "SELECT id FROM chat_threads WHERE user1_id = '{$id1}' AND user2_id = '{$id2}'";
        $result = $this->db->query($sql_check);

        if ($result->num_rows > 0) {
            $thread = $result->fetch_assoc();
            return $thread['id'];
        } else {
            // Set last_message_at ke NOW() agar thread baru langsung muncul di daftar
            $sql_create = "INSERT INTO chat_threads (user1_id, user2_id, last_message_at) VALUES ('{$id1}', '{$id2}', NOW())";
            $save = $this->db->query($sql_create);

            if ($save) {
                return $this->db->insert_id;
            } else {
                error_log("Error creating thread: " . $this->db->error);
                return 0;
            }
        }
    }

    // 3. Menyimpan pesan chat personal (Push Notifikasi Email)
    function save_personal_chat_message() {
        extract($_POST);
        $sender_id = $_SESSION['login_id'];
        $thread_id = $this->db->real_escape_string($thread_id);
        
        $message = $this->db->real_escape_string(htmlentities($message_content)); 
        
        if (empty($thread_id)) {
            return 0;
        }

        // is_read = 1 karena pesan ini dikirim oleh pengguna saat ini
        $sql = "INSERT INTO chat_messages (thread_id, sender_id, message_content, is_read) VALUES ('{$thread_id}', '{$sender_id}', '{$message}', 1)";
        $save = $this->db->query($sql);

        if ($save) {
            // A. Update last_message_at
            $this->db->query("UPDATE chat_threads SET last_message_at = NOW() WHERE id = '{$thread_id}'");
            
            // B. Ambil Recipient & Kirim Notifikasi (Push & Email)
            $sql_thread = $this->db->query("SELECT user1_id, user2_id FROM chat_threads WHERE id = '{$thread_id}'");
            $thread_data = $sql_thread->fetch_assoc();
            $recipient_id = ($thread_data['user1_id'] == $sender_id) ? $thread_data['user2_id'] : $thread_data['user1_id'];
            
            $sender_name = $this->db->query("SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE id = '{$sender_id}'")->fetch_assoc()['name'];
            $preview_message = substr(html_entity_decode($message), 0, 50) . (strlen(html_entity_decode($message)) > 50 ? '...' : '');
            $notification_message = "Pesan baru dari **{$sender_name}**: " . $preview_message;
            
            $encoded_thread_id = function_exists('encode_id') ? encode_id($thread_id) : $thread_id;
            $link = "index.php?page=chat&thread_id={$encoded_thread_id}"; 
            
            $recipient_q = $this->db->query("SELECT email, notification_email, firstname, lastname FROM users WHERE id = '{$recipient_id}'");
            $recipient_user = $recipient_q->fetch_assoc();
            
            if ($recipient_user) {
                $full_name = ucwords($recipient_user['firstname'] . ' ' . $recipient_user['lastname']);
                $target_email = !empty($recipient_user['notification_email']) ? $recipient_user['notification_email'] : $recipient_user['email'];

                $email_details = [
                    'email' => $target_email,
                    'name'  => $full_name,
                    'subject' => "[CHAT BARU] Pesan dari {$sender_name}"
                ];
                
                // Tipe 4: Pesan Chat. Parameter ke-6 (true) mengaktifkan pengiriman email.
                record_notification($recipient_id, 4, $notification_message, $link, $this->db, true, $email_details);
            }

            return 1;
        } else {
            error_log("save_personal_chat_message error: " . $this->db->error);
            return 0;
        }
    }

    // 4. Memuat pesan chat untuk thread tertentu (Tandai Dibaca)
    function get_personal_chat_messages() {
        $encoder = function_exists('encode_id') ? 'encode_id' : function($id) { return $id; }; 
        $current_user_id = $_SESSION['login_id'];

        extract($_POST);
        $thread_id = $this->db->real_escape_string($thread_id);
        
        // --- A. Tandai semua pesan masuk sebagai sudah dibaca ---
        // Ini mengurangi 'unread_count' di get_all_users_for_chat() berikutnya
        $this->db->query("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE thread_id = '{$thread_id}' 
            AND sender_id != '{$current_user_id}' 
            AND is_read = 0
        ");
        
        // ... (Logika Lookup Data Users, Projects, Tasks SAMA) ...
        $data = ['users' => [], 'projects' => [], 'tasks' => []];
        $users_q = $this->db->query("SELECT id, CONCAT(firstname, ' ', lastname) as name FROM users");
        while($row = $users_q->fetch_assoc()) {
            $data['users'][$row['id']] = ['name' => $row['name'], 'encoded_id' => $encoder($row['id'])];
        }
        $projects_q = $this->db->query("SELECT id, name FROM project_list");
        while($row = $projects_q->fetch_assoc()) {
            $data['projects'][$row['id']] = ['name' => $row['name'], 'encoded_id' => $encoder($row['id'])];
        }
        $tasks_q = $this->db->query("SELECT id, task FROM task_list");
        while($row = $tasks_q->fetch_assoc()) {
            $data['tasks'][$row['id']] = ['name' => $row['task'], 'encoded_id' => $encoder($row['id'])];
        }

        // --- B. Ambil Pesan ---
        $messages_q = $this->db->query("
            SELECT 
                cm.*, 
                CONCAT(u.firstname, ' ', u.lastname) as sender_name,
                u.avatar
            FROM chat_messages cm
            JOIN users u ON u.id = cm.sender_id
            WHERE cm.thread_id = '{$thread_id}'
            ORDER BY cm.created_at ASC
        ");
        
        $messages = [];
        while($row = $messages_q->fetch_assoc()) {
            $row['message_content'] = html_entity_decode($row['message_content']);
            $messages[] = $row;
        }
        
        $data['messages'] = $messages;

        return json_encode($data);
    }
    
    function __destruct() {
        if ($this->db) $this->db->close();
        ob_end_flush();
    }
}
?>