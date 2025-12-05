<?php
// FILE: admin_class.php

session_start();
// Menginclude db_connect.php untuk koneksi database
include('db_connect.php');
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
        $p_id = $project_id === null ? null : (int)$project_id;
        $t_id = $task_id === null ? null : (int)$task_id;

        $bind = $stmt->bind_param("iiiss", $user_id, $p_id, $t_id, $activity_type, $description);
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
        
        // 1. ITERASI DATA POST
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id', 'cpass', 'password')) && !is_numeric($k)) {
                $v = $this->db->real_escape_string($v);
                // Tambahkan pengecekan khusus untuk kolom 'type'
                if ($k == 'type') {
                    $v = (int)$v;
                }
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
        if ($check > 0) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Email sudah digunakan oleh user lain. 🛑'; // REVISI
            return 2; 
        }

        // 4. Proses Upload Avatar
        $is_update = !empty($id);
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
                if($is_update){
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


        // 5. Jalankan Query dan Notifikasi
        if (!$is_update) {
            $save = $this->db->query("INSERT INTO users SET $data");
            if ($save) {
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'User **'.htmlspecialchars($firstname ?? '').'** berhasil ditambahkan! 👤✨'; // REVISI
                return 1;
            }
        } else {
            $save = $this->db->query("UPDATE users SET $data WHERE id = $id");
            if ($save) {
                 $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'User **'.htmlspecialchars($firstname ?? '').'** berhasil diperbarui! 🔄'; // REVISI
                return 1;
            }
        }
        
        error_log("save_user SQL Error: " . $this->db->error);
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menyimpan user. 🛑'; // REVISI
        return 0; 
    }
    
    function delete_user() {
        extract($_POST);
        $id = intval($id);
        $user_name = $this->db->query("SELECT firstname FROM users WHERE id = $id")->fetch_assoc()['firstname'] ?? 'User';
        
        $delete = $this->db->query("DELETE FROM users WHERE id = " . $id);
        if ($delete) {
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'User **'.htmlspecialchars($user_name).'** berhasil dihapus. 🗑️'; // REVISI
            return 1;
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menghapus user. 🛑'; // REVISI
        return 0;
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
        $user_ids_clean = '';
        if (isset($user_ids)) {
            $user_ids_clean = array_map('intval', $user_ids);
            $data .= ", user_ids='" . implode(',', $user_ids_clean) . "' ";
        } else {
             $data .= ", user_ids='' ";
        }

        if (empty($id)) {
            $save = $this->db->query("INSERT INTO project_list SET $data");
            if ($save) {
                $pid = $this->db->insert_id;
                $this->log_activity($_SESSION['login_id'], $pid, null, 'project_add', 'Menambahkan project baru: ' . ($name ?? ''));
                
                // REVISI PESAN SUKSES TAMBAH PROJECT
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($name).'** berhasil ditambahkan! 🚀';
                return 1;
            }
        } else {
            $save = $this->db->query("UPDATE project_list SET $data WHERE id = $id");
            if ($save) {
                $this->log_activity($_SESSION['login_id'], $id, null, 'project_update', 'Mengupdate project: ' . ($name ?? ''));
                
                // REVISI PESAN SUKSES UPDATE PROJECT
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($name).'** berhasil diperbarui! 💾';
                return 1;
            }
        }
        
        // REVISI PESAN GAGAL
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Operasi proyek gagal. 🛑';
        return 0;
    }

    function delete_project() {
        extract($_POST);
        $id = intval($id);
        $qry = $this->db->query("SELECT name FROM project_list WHERE id = $id");
        $project_name = $qry->num_rows > 0 ? $qry->fetch_assoc()['name'] : 'Proyek';
        
        $delete = $this->db->query("DELETE FROM project_list WHERE id = $id");
        
        if ($delete) {
            $this->log_activity($_SESSION['login_id'], $id, null, 'project_delete', 'Menghapus project: ' . $project_name);
            
            // REVISI PESAN SUKSES HAPUS PROJECT
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($project_name).'** berhasil dihapus. 🗑️';
            return 1;
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menghapus proyek. 🛑';
        return 0;
    }


    // === TASK MANAGEMENT ===
    function save_task() {
        $task = $this->db->real_escape_string($_POST['task'] ?? '');
        $description = $this->db->real_escape_string(htmlentities(str_replace("'", "&#x2019;", $_POST['description'] ?? '')));
        $project_id = intval($_POST['project_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);

        $user_ids_array = [];
        $user_ids_string = '';
        if (isset($_POST['user_ids'])) {
            if (is_array($_POST['user_ids'])) {
                $user_ids_array = array_map('intval', $_POST['user_ids']);
                $user_ids_string = implode(',', $user_ids_array);
            } else {
                $user_ids_array = array_map('intval', array_filter(explode(',', $_POST['user_ids'])));
                $user_ids_string = implode(',', $user_ids_array);
            }
        }

        $start_date = $this->db->real_escape_string($_POST['start_date'] ?? '');
        $end_date   = $this->db->real_escape_string($_POST['end_date'] ?? '');
        $content_pillar = $this->db->real_escape_string(is_array($_POST['content_pillar'] ?? '') ? implode(',', array_map('trim', $_POST['content_pillar'])) : ($_POST['content_pillar'] ?? ''));
        $platform = $this->db->real_escape_string(is_array($_POST['platform'] ?? '') ? implode(',', array_map('trim', $_POST['platform'])) : ($_POST['platform'] ?? ''));
        $reference_links = $this->db->real_escape_string($_POST['reference_links'] ?? '');

        if (empty($task) || $project_id <= 0) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Nama tugas dan ID proyek tidak boleh kosong. 🛑'; // REVISI
            return 0;
        }

        $is_new = empty($_POST['id']);
        $sql = '';
        $task_id = 0;

        if ($is_new) {
            $created_by = isset($_SESSION['login_id']) ? intval($_SESSION['login_id']) : 0;

            $sql = "INSERT INTO task_list 
                (project_id, task, description, status, user_ids, start_date, end_date, content_pillar, platform, reference_links, created_by, date_created)
                VALUES
                ({$project_id}, '{$task}', '{$description}', {$status}, '{$this->db->real_escape_string($user_ids_string)}', '{$start_date}', '{$end_date}', '{$content_pillar}', '{$platform}', '{$reference_links}', {$created_by}, NOW())
                ";
        } else {
            $id = intval($_POST['id']);
            $task_id = $id;
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
             $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal menyimpan tugas. 🛑'; // REVISI
            return 0;
        }

        $task_id = $is_new ? $this->db->insert_id : $task_id;
        $action_type = $is_new ? 'task_add' : 'task_update';
        $message_prefix = $is_new ? "baru ditugaskan" : "diperbarui";

        // Fetch Data for Notification and Log
        $proj = $this->db->query("SELECT manager_id, name FROM project_list WHERE id = {$project_id}")->fetch_assoc();
        $project_name = $proj['name'] ?? 'Unknown Project';
        $manager_id = $proj['manager_id'];
        
        $log_desc = $action_type == 'task_add' ? "Menambahkan task baru: {$task} pada project: {$project_name}" : "Mengupdate task: {$task}";
        $this->log_activity($_SESSION['login_id'] ?? 0, $project_id, $task_id, $action_type, $log_desc);

        // --- NOTIFIKASI PUSH & EMAIL ---
        $link = "index.php?page=view_task&id=" . (function_exists('encode_id') ? encode_id($task_id) : $task_id);
        $message_text = "Tugas **".htmlspecialchars($task)."** telah {$message_prefix} di project **".htmlspecialchars($project_name)."**.";
        $email_subject = "[TASK] Tugas ".htmlspecialchars($task)." {$message_prefix}";
        
        $_SESSION['notification']['status'] = 'success';
        $_SESSION['notification']['message'] = 'Tugas **'.htmlspecialchars($task).'** berhasil disimpan! ✅'; // REVISI

        // Kumpulkan semua penerima: Manajer + Anggota Tugas
        $recipients_ids = array_unique(array_merge([$manager_id], $user_ids_array));
        
        if (!empty($recipients_ids)) {
            $ids_str = implode(',', $recipients_ids);
            $users_q = $this->db->query("SELECT id, email, notification_email, firstname, lastname FROM users WHERE id IN ({$ids_str})");
            
            while ($user = $users_q->fetch_assoc()) {
                // Jangan kirim notifikasi ke diri sendiri jika ini update task
                if ($user['id'] == $_SESSION['login_id'] && !$is_new) continue; 
                
                $full_name = ucwords($user['firstname'] . ' ' . $user['lastname']);
                $target_email = !empty($user['notification_email']) ? $user['notification_email'] : $user['email'];

                $email_details = [
                    'email' => $target_email,
                    'name'  => $full_name,
                    'subject' => $email_subject
                ];
                // Type 1: Task Assigned/Updated
                record_notification($user['id'], 1, $message_text, $link, $this->db, true, $email_details);
            }
        }
        
        return 1;
    }

    public function delete_task() {
        extract($_POST);
        $id = intval($id);
        
        $task_info_qry = $this->db->query("
            SELECT t.task, t.project_id, p.name as project_name
            FROM task_list t
            INNER JOIN project_list p ON p.id = t.project_id
            WHERE t.id = $id
        ");

        $project_id = null;
        $task_name = "Tugas ID: {$id}";
        $project_name = "Unknown Project";

        if ($task_info_qry && $task_info_qry->num_rows > 0) {
            $info = $task_info_qry->fetch_assoc();
            $project_id = intval($info['project_id']);
            $task_name = $this->db->real_escape_string($info['task']);
            $project_name = $this->db->real_escape_string($info['project_name']);
        }
        
        // HAPUS NOTIFIKASI TERKAIT & TUGAS UTAMA
        $this->db->begin_transaction();
        
        try {
            // Hapus notifikasi terkait
            $this->db->query("DELETE FROM notification_list WHERE link LIKE '%view_task.php?id=" . (function_exists('encode_id') ? encode_id($id) : $id) . "%'");
            
            // Hapus entri user_productivity/progress
            $this->db->query("DELETE FROM user_productivity WHERE task_id = $id");

            // Hapus tugas utama
            $delete_task = $this->db->query("DELETE FROM task_list WHERE id = $id");

            if (!$delete_task) {
                throw new Exception("Gagal menghapus tugas utama.");
            }
            
            $this->log_activity($_SESSION['login_id'] ?? 0, $project_id, $id, 'task_delete', 'Menghapus task: ' . $task_name);
            $this->db->commit();
            
            // REVISI PESAN SUKSES HAPUS TASK
            $_SESSION['notification']['status'] = 'success'; 
            $_SESSION['notification']['message'] = 'Tugas **'.htmlspecialchars($task_name).'** berhasil dihapus. 🗑️';
            return 1;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Task Deletion Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal menghapus tugas. 🛑'; // REVISI
            return 0;
        }
    }

    // === PROGRESS (TASK ACTIVITY) ===
    function save_progress() {
        // ... (Logika Validasi & Query tetap) ...
        
        $save = $this->db->query($sql);

        // 6. Penanganan Hasil Query (Kunci Diagnosis Error)
        if (!$save) {
            $error_message = $this->db->error;
            error_log("save_progress failed SQL: " . $error_message . " | Query: " . $sql);
            
            // Mengembalikan pesan error MySQL yang sebenarnya (untuk diagnosis)
            return "0: MySQL Error: " . $error_message; 
        }
        
        // 7. Log Aktivitas dan Notifikasi (Jika Sukses)
        $task_id_for_log = $is_new ? $this->db->insert_id : $task_id_val;

        $action_type = $is_new ? 'progress_add' : 'progress_update';
        $task_name_q = $this->db->query("SELECT task FROM task_list WHERE id = {$task_id_val}");
        $task_name = $task_name_q && $task_name_q->num_rows > 0 ? $task_name_q->fetch_assoc()['task'] : 'Unknown Task';
        
        $this->log_activity($user_id, $project_id_val, $task_id_for_log, $action_type, ($is_new ? "Menambah" : "Mengupdate") . " progress pada task: " . $task_name);

        // REVISI PESAN SUKSES SAVE PROGRESS/COMMENT
        $_SESSION['notification']['status'] = 'success';
        $_SESSION['notification']['message'] = 'Progres tugas berhasil disimpan! 💬👍';
        
        return 1; // Sukses
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
        $project_id = null;
        $task_name = '';

        if ($qry && $qry->num_rows > 0) {
            $row = $qry->fetch_assoc();
            $task_name = $row['task'] ?? 'Task';
            $project_id = $row['project_id'] ?? null;
            
            $delete = $this->db->query("DELETE FROM user_productivity WHERE id = $id");
            if ($delete) {
                $this->log_activity($_SESSION['login_id'], $project_id, $row['task_id'] ?? null, 'progress_delete', 'Menghapus progress pada task: ' . $task_name);
                
                // REVISI PESAN SUKSES HAPUS PROGRESS/COMMENT
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Progres tugas berhasil dihapus! 🗑️';
                return 1;
            }
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menghapus progres tugas. 🛑'; // REVISI
        return 0;
    }
    
    // === CHAT FUNCTIONS ===
    
    // 1. Mengambil daftar semua pengguna untuk sidebar chat
    function get_all_users_for_chat() {
        // ... (Logika tetap) ...
        
        return json_encode($users);
    }

    // 2. Mendapatkan ID thread yang ada atau membuat yang baru
    function get_or_create_thread_id() {
        // ... (Logika tetap) ...
    }

    // 3. Menyimpan pesan chat personal (Push Notifikasi Email)
    function save_personal_chat_message() {
        extract($_POST);
        $sender_id = $_SESSION['login_id'];
        $thread_id = $this->db->real_escape_string($thread_id);
        
        $message = $this->db->real_escape_string(htmlentities($message_content)); 
        
        if (empty($thread_id)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID thread chat tidak valid. 🛑'; // REVISI
            return 0;
        }

        // is_read = 1 karena pesan ini dikirim oleh pengguna saat ini
        $sql = "INSERT INTO chat_messages (thread_id, sender_id, message_content, is_read) VALUES ('{$thread_id}', '{$sender_id}', '{$message}', 1)";
        $save = $this->db->query($sql);

        if ($save) {
            $this->db->query("UPDATE chat_threads SET last_message_at = NOW() WHERE id = '{$thread_id}'");
            
            // Ambil Recipient & Kirim Notifikasi (Push & Email)
            $sql_thread = $this->db->query("SELECT user1_id, user2_id FROM chat_threads WHERE id = '{$thread_id}'");
            $thread_data = $sql_thread->fetch_assoc();
            $recipient_id = ($thread_data['user1_id'] == $sender_id) ? $thread_data['user2_id'] : $thread_data['user1_id'];
            
            $sender_name = $this->db->query("SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE id = '{$sender_id}'")->fetch_assoc()['name'];
            $preview_message = substr(html_entity_decode($message), 0, 50) . (strlen(html_entity_decode($message)) > 50 ? '...' : '');
            $notification_message = "Pesan baru dari **{$sender_name}**: " . $preview_message;
            
            $encoded_thread_id = function_exists('encode_id') ? encode_id($thread_id) : $thread_id;
            $link = "index.php?page=chat&thread_id={$encoded_thread_id}"; 
            
            $recipient_q = $this->db->query("SELECT id, email, notification_email, firstname, lastname FROM users WHERE id = '{$recipient_id}'");
            $recipient_user = $recipient_q->fetch_assoc();
            
            if ($recipient_user) {
                $full_name = ucwords($recipient_user['firstname'] . ' ' . $recipient_user['lastname']);
                $target_email = !empty($recipient_user['notification_email']) ? $recipient_user['notification_email'] : $recipient_user['email'];

                $email_details = [
                    'email' => $target_email,
                    'name'  => $full_name,
                    'subject' => "[CHAT BARU] Pesan dari {$sender_name}"
                ];
                
                // Type 4: Pesan Chat.
                record_notification($recipient_user['id'], 4, $notification_message, $link, $this->db, true, $email_details);
            }
            
            // REVISI PESAN SUKSES KIRIM PESAN PERSONAL
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pesan berhasil terkirim! ✉️';
            
            return 1;
        } else {
            error_log("save_personal_chat_message error: " . $this->db->error);
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal mengirim pesan chat. 🛑'; // REVISI
            return 0;
        }
    }

    // 4. Memuat pesan chat untuk thread tertentu (Tandai Dibaca)
    function get_personal_chat_messages() {
        // ... (Logika tetap) ...
    }
    
    // 1. Aksi Baru: Membuat Grup Chat
    function create_new_group() {
        extract($_POST);
        $name = $this->db->real_escape_string($name ?? 'New Group');
        $user_ids = $user_ids ?? [];
        $created_by = $_SESSION['login_id'];

        if (empty($name) || empty($user_ids)) {
             $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Nama grup dan anggota tidak boleh kosong. 🛑'; // REVISI
            return 0; 
        }

        $this->db->begin_transaction();

        try {
            // 1. Buat Grup
            $sql_group = "INSERT INTO chat_groups (name, created_by, last_message_at) VALUES ('{$name}', '{$created_by}', NOW())";
            $save_group = $this->db->query($sql_group);

            if (!$save_group) {
                throw new Exception("Gagal membuat entri grup chat.");
            }
            $group_id = $this->db->insert_id;
            $user_ids_clean = array_map('intval', $user_ids);
            
            // Pastikan pembuat grup masuk sebagai anggota dan admin
            if (!in_array($created_by, $user_ids_clean)) {
                $user_ids_clean[] = $created_by;
            }

            // 2. Tambahkan Anggota
            $member_values = [];
            foreach ($user_ids_clean as $user_id) {
                $is_admin = ($user_id == $created_by) ? 1 : 0;
                $member_values[] = "('{$group_id}', '{$user_id}', '{$is_admin}')";
            }
            
            if (!empty($member_values)) {
                $sql_members = "INSERT INTO group_members (group_id, user_id, is_admin) VALUES " . implode(', ', $member_values);
                $this->db->query($sql_members);
            }
            
            $this->db->commit();
            
            // REVISI PESAN SUKSES CREATE GROUP
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Grup chat **'.htmlspecialchars($name).'** berhasil dibuat! 👥✨';
            return 1;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Create Group Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal membuat grup chat. 🛑'; // REVISI
            return 0;
        }
    }


    // 2. Aksi Baru: Mengambil semua data sidebar (Grup + Personal)
    function get_all_chat_sidebar_data() {
        // ... (Logika tetap) ...
    }


    // 3. Menyimpan pesan Grup
    function save_group_chat_message() {
        extract($_POST);
        $sender_id = $_SESSION['login_id'];
        $group_id = $this->db->real_escape_string($group_id);
        $message = $this->db->real_escape_string(htmlentities($message_content)); 

        if (empty($group_id)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID grup chat tidak valid. 🛑'; // REVISI
            return 0;
        }

        $sql = "INSERT INTO group_messages (group_id, sender_id, message_content) VALUES ('{$group_id}', '{$sender_id}', '{$message}')";
        $save = $this->db->query($sql);

        if ($save) {
            $this->db->query("UPDATE chat_groups SET last_message_at = NOW() WHERE id = '{$group_id}'");
            
            // Notifikasi untuk semua anggota grup (kecuali diri sendiri)
            // ... (Logika notifikasi push/email tetap) ...

            // REVISI PESAN SUKSES KIRIM PESAN GRUP
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pesan grup berhasil terkirim! 💬';
            return 1;
        } else {
            error_log("save_group_chat_message error: " . $this->db->error);
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal mengirim pesan grup. 🛑'; // REVISI
            return 0;
        }
    }

    function get_group_chat_messages() {
        // ... (Logika tetap) ...
    }

    // 4. Aksi Baru: Mengambil Detail Grup (untuk Modal Settings)
    function get_group_details() {
        // ... (Logika tetap) ...
    }

    // 5. Aksi Baru: Mengupdate Nama dan Anggota Grup
    function update_group_settings() {
        extract($_POST);
        $group_id = $this->db->real_escape_string($group_id ?? null);
        $name = $this->db->real_escape_string($name ?? '');
        $user_ids = $user_ids ?? []; 
        $current_user_id = $_SESSION['login_id'];

        if (empty($group_id) || empty($name) || empty($user_ids)) {
             $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Nama grup dan anggota tidak boleh kosong. 🛑'; // REVISI
            return 0;
        }

        $this->db->begin_transaction();

        try {
            // A. Update Nama Grup
            // ... (Logika update database tetap) ...
            
            $this->db->commit();
            
            // REVISI PESAN SUKSES UPDATE GROUP
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pengaturan grup **'.htmlspecialchars($name).'** berhasil diperbarui! ⚙️';
            return 1;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Update Group Settings Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal memperbarui pengaturan grup. 🛑'; // REVISI
            return 0; 
        }
    }

    // 6. Aksi Baru: Menghapus Grup Chat
    function delete_group() {
        $group_id = $this->db->real_escape_string($_POST['group_id'] ?? null);
        $group_name = $this->db->query("SELECT name FROM chat_groups WHERE id = '{$group_id}'")->fetch_assoc()['name'] ?? 'Grup Chat';

        if (empty($group_id)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID grup chat tidak valid. 🛑'; // REVISI
            return 0;
        }
       
        $this->db->begin_transaction();

        try {
            // Hapus Anggota, Pesan, Status Baca
            // ... (Logika penghapusan database tetap) ...
            
            $this->db->commit();
            
            // REVISI PESAN SUKSES HAPUS GROUP
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Grup chat **'.htmlspecialchars($group_name).'** berhasil dihapus! 🗑️';
            return 1; 
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Grup Deletion Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal menghapus grup chat. 🛑'; // REVISI
            return 0; 
        }
    }
    
    // 6. Destructor
    function __destruct() {
        if ($this->db) $this->db->close();
        ob_end_flush();
    }
}