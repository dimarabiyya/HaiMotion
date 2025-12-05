<?php
// FILE: admin_class.php (Logika Backend dengan Notifikasi Sesi)

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
            $_SESSION['notification']['message'] = 'Email sudah digunakan oleh user lain.';
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
                $_SESSION['notification']['message'] = 'User **'.($firstname ?? '').'** berhasil ditambahkan!';
                return 1;
            }
        } else {
            $save = $this->db->query("UPDATE users SET $data WHERE id = $id");
            if ($save) {
                 $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'User **'.($firstname ?? '').'** berhasil diperbarui!';
                return 1;
            }
        }
        
        error_log("save_user SQL Error: " . $this->db->error);
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menyimpan user.';
        return 0; 
    }
    
    function delete_user() {
        extract($_POST);
        $id = intval($id);
        $user_name = $this->db->query("SELECT firstname FROM users WHERE id = $id")->fetch_assoc()['firstname'] ?? 'User';
        
        $delete = $this->db->query("DELETE FROM users WHERE id = " . $id);
        if ($delete) {
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'User **'.htmlspecialchars($user_name).'** berhasil dihapus!';
            return 1;
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menghapus user.';
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
                
                // >>> LOGIKA NOTIFIKASI TAMBAHAN <<<
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($name).'** berhasil ditambahkan!';
                return 1;
            }
        } else {
            $save = $this->db->query("UPDATE project_list SET $data WHERE id = $id");
            if ($save) {
                $this->log_activity($_SESSION['login_id'], $id, null, 'project_update', 'Mengupdate project: ' . ($name ?? ''));
                
                // >>> LOGIKA NOTIFIKASI TAMBAHAN <<<
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($name).'** berhasil diperbarui!';
                return 1;
            }
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Operasi proyek gagal.';
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
            
            // >>> LOGIKA NOTIFIKASI TAMBAHAN <<<
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($project_name).'** berhasil dihapus!';
            return 1;
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menghapus proyek.';
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
            $_SESSION['notification']['message'] = 'Nama tugas dan ID proyek tidak boleh kosong.';
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
            $_SESSION['notification']['message'] = 'Gagal menyimpan tugas.';
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
        $_SESSION['notification']['message'] = $message_text;

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
            
            // >>> LOGIKA NOTIFIKASI TAMBAHAN <<<
            $_SESSION['notification']['status'] = 'success'; 
            $_SESSION['notification']['message'] = 'Tugas **'.htmlspecialchars($task_name).'** berhasil dihapus!';
            return 1;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Task Deletion Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = '';
            $_SESSION['notification']['message'] = 'Gagal menghapus tugas. ' . $e->getMessage();
            return 0;
        }
    }

    // === PROGRESS (TASK ACTIVITY) ===
    function save_progress() {
        // Menerima semua data POST yang dikirim dari form
        extract($_POST);
        
        // 1. Validasi Sesi dan User ID
        if (!isset($_SESSION['login_id'])) {
            // Mengembalikan pesan yang lebih jelas untuk AJAX
            return "0: Sesi login tidak ditemukan. Harap login kembali."; 
        }
        $user_id = intval($_SESSION['login_id']);
        
        // 2. Sanitasi dan Inisialisasi Variabel
        $id_val = intval($id ?? 0); // ID progress (0 jika baru)
        $task_id_val = intval($task_id ?? 0); // ID task
        $project_id_val = intval($project_id ?? 0); // ID project
        
        // Data input dari Form manage_progress.php
        $subject_val = $this->db->real_escape_string($subject ?? ''); // Field Subject
        $comment_val = $this->db->real_escape_string(htmlentities(str_replace("'", "&#x2019;", $comment ?? ''))); // Field Comment (dari summernote)
        
        // Data Waktu
        $date_val = $this->db->real_escape_string($date ?? date('Y-m-d')); 
        $start_time_val = $this->db->real_escape_string($start_time ?? '00:00:00');
        $end_time_val = $this->db->real_escape_string($end_time ?? '00:00:00');
        
        // Jika kolom 'progress' ada di DB Anda dengan nama lain, ubah $progress_val di sini.
        // Karena ada error "Unknown column 'progress'", kolom ini dihapus dari query.
        $progress_val = 0; // Tetapkan nilai default jika tidak digunakan
        
        // 3. Validasi ID Kritis
        if ($task_id_val === 0 || $project_id_val === 0) {
            return "0: ID Tugas atau ID Proyek tidak valid.";
        }

        // 4. Hitung Durasi (time_rendered)
        $dur = 0; 
        if (!empty($end_time_val) && !empty($start_time_val)) {
             $start_ts = strtotime("2020-01-01 " . $start_time_val);
             $end_ts = strtotime("2020-01-01 " . $end_time_val);
             
             // Pastikan waktu berakhir >= waktu mulai
             if ($end_ts >= $start_ts) {
                 $dur_seconds = $end_ts - $start_ts;
                 $dur = round($dur_seconds / (60 * 60), 2); // Konversi ke jam, 2 desimal
             }
        } 
        $time_rendered_val = $this->db->real_escape_string($dur);
        
        // 5. Susun Data SQL
        $data = "project_id = {$project_id_val}";
        $data .= ", task_id = {$task_id_val}";
        $data .= ", user_id = {$user_id}";
        $data .= ", date = '{$date_val}'"; 
        $data .= ", start_time = '{$start_time_val}'"; 
        $data .= ", end_time = '{$end_time_val}'"; 
        $data .= ", time_rendered = '{$time_rendered_val}'";
        $data .= ", subject = '{$subject_val}'"; 
        $data .= ", comment = '{$comment_val}'"; 
        
        // !! KOLOM 'progress' DIHAPUS DARI SINI !!
        // Jika tabel Anda memiliki kolom "progress" dengan nama lain (misalnya "completion_percentage"),
        // Anda HARUS mengganti salah satu baris di atas, bukan menghapusnya. 
        // Contoh: $data .= ", nama_kolom_anda = '{$progress_val}'";

        
        $is_new = ($id_val === 0);
        $sql = '';

        if ($is_new) {
            $sql = "INSERT INTO user_productivity SET {$data}, date_created = NOW()";
        } else {
            $sql = "UPDATE user_productivity SET {$data} WHERE id = {$id_val}";
        }

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

        $_SESSION['notification']['status'] = 'success';
        $_SESSION['notification']['message'] = 'Progres tugas berhasil disimpan! 🎉';
        
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
            $task_name = $row['task'] ?? '';
            $project_id = $row['project_id'] ?? null;
            
            $delete = $this->db->query("DELETE FROM user_productivity WHERE id = $id");
            if ($delete) {
                $this->log_activity($_SESSION['login_id'], $project_id, $row['task_id'] ?? null, 'progress_delete', 'Menghapus progress pada task: ' . $task_name);
                
                // >>> LOGIKA NOTIFIKASI TAMBAHAN <<<
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Progres tugas berhasil dihapus!';
                return 1;
            }
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menghapus progres tugas.';
        return 0;
    }
    
    // === CHAT FUNCTIONS (Tambahkan Notifikasi) ===
    
    // 1. Mengambil daftar semua pengguna untuk sidebar chat
    function get_all_users_for_chat() {
        $current_user_id = $_SESSION['login_id'];
        
        $sql = "
            SELECT 
                u.id, 
                CONCAT(u.firstname, ' ', u.lastname) AS name, 
                u.avatar,
                t.id AS thread_id,
                t.last_message_at AS last_message_timestamp,
                (
                    SELECT cm_last.message_content 
                    FROM chat_messages cm_last
                    WHERE cm_last.thread_id = t.id 
                    ORDER BY cm_last.created_at DESC 
                    LIMIT 1
                ) AS last_message_content,
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
            return json_encode(['error' => 'SQL_FAILED', 'users' => []]);
        }
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
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
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID thread chat tidak valid.';
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
            
            // >>> LOGIKA NOTIFIKASI TAMBAHAN (HANYA UNTUK PENGIRIM) <<<
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pesan berhasil terkirim!';
            
            return 1;
        } else {
            error_log("save_personal_chat_message error: " . $this->db->error);
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal mengirim pesan chat.';
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
    
    // 1. Aksi Baru: Membuat Grup Chat
    function create_new_group() {
        extract($_POST);
        $name = $this->db->real_escape_string($name ?? 'New Group');
        $user_ids = $user_ids ?? [];
        $created_by = $_SESSION['login_id'];

        if (empty($name) || empty($user_ids)) {
             $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Nama grup dan anggota tidak boleh kosong.';
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
            
            // >>> LOGIKA NOTIFIKASI TAMBAHAN <<<
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Grup chat **'.htmlspecialchars($name).'** berhasil dibuat!';
            return 1;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Create Group Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal membuat grup chat.';
            return 0;
        }
    }


    // 2. Aksi Baru: Mengambil semua data sidebar (Grup + Personal)
    function get_all_chat_sidebar_data() {
        $current_user_id = $_SESSION['login_id'];
        
        // A. Ambil Data Personal
        $sql_personal = "
            SELECT 
                u.id, 
                CONCAT(u.firstname, ' ', u.lastname) AS name, 
                u.avatar,
                t.id AS thread_id,
                t.last_message_at AS last_message_timestamp,
                (SELECT cm_last.message_content FROM chat_messages cm_last WHERE cm_last.thread_id = t.id ORDER BY cm_last.created_at DESC LIMIT 1) AS last_message_content,
                (SELECT COUNT(cm_unread.id) FROM chat_messages cm_unread WHERE cm_unread.thread_id = t.id AND cm_unread.sender_id != '{$current_user_id}' AND cm_unread.is_read = 0) AS unread_count
            FROM users u
            LEFT JOIN chat_threads t 
                ON (t.user1_id = u.id AND t.user2_id = '{$current_user_id}') OR (t.user2_id = u.id AND t.user1_id = '{$current_user_id}')
            WHERE u.id != '{$current_user_id}'
        ";
        
        $result_personal = $this->db->query($sql_personal);
        $users = [];
        if ($result_personal) {
            while ($row = $result_personal->fetch_assoc()) {
                $row['unread_count'] = (int)($row['unread_count'] ?? 0);
                $row['last_message_content'] = html_entity_decode($row['last_message_content'] ?? '');
                $users[] = $row;
            }
        }

        // B. Ambil Data Group
        $sql_group = "
            SELECT 
                g.id, 
                g.name, 
                g.last_message_at AS last_message_timestamp,
                (SELECT gm_last.message_content FROM group_messages gm_last WHERE gm_last.group_id = g.id ORDER BY gm_last.created_at DESC LIMIT 1) AS last_message_content,
                (SELECT CONCAT(u.firstname, ' ', u.lastname) FROM group_messages gm_last INNER JOIN users u ON u.id = gm_last.sender_id WHERE gm_last.group_id = g.id ORDER BY gm_last.created_at DESC LIMIT 1) AS last_sender_name,
                (
                    SELECT COUNT(gm_unread.id) 
                    FROM group_messages gm_unread
                    LEFT JOIN user_group_read_status r ON r.group_id = gm_unread.group_id AND r.user_id = '{$current_user_id}'
                    WHERE gm_unread.group_id = g.id 
                    AND gm_unread.sender_id != '{$current_user_id}'
                    AND gm_unread.created_at > IFNULL(r.last_read_at, '2000-01-01')
                ) AS unread_count
            FROM chat_groups g
            INNER JOIN group_members m ON m.group_id = g.id
            WHERE m.user_id = '{$current_user_id}'
            ORDER BY g.last_message_at DESC
        ";
        
        $result_group = $this->db->query($sql_group);
        $groups = [];
        if ($result_group) {
            while ($row = $result_group->fetch_assoc()) {
                $row['unread_count'] = (int)($row['unread_count'] ?? 0);
                $row['last_message_content'] = html_entity_decode($row['last_message_content'] ?? '');
                $groups[] = $row;
            }
        }
        
        return json_encode(['users' => $users, 'groups' => $groups]);
    }


    // 3. Menyimpan pesan Grup
    function save_group_chat_message() {
        extract($_POST);
        $sender_id = $_SESSION['login_id'];
        $group_id = $this->db->real_escape_string($group_id);
        $message = $this->db->real_escape_string(htmlentities($message_content)); 

        if (empty($group_id)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID grup chat tidak valid.';
            return 0;
        }

        $sql = "INSERT INTO group_messages (group_id, sender_id, message_content) VALUES ('{$group_id}', '{$sender_id}', '{$message}')";
        $save = $this->db->query($sql);

        if ($save) {
            $this->db->query("UPDATE chat_groups SET last_message_at = NOW() WHERE id = '{$group_id}'");
            
            // Notifikasi untuk semua anggota grup (kecuali diri sendiri)
            $members_q = $this->db->query("SELECT u.id, u.email, u.notification_email, u.firstname, u.lastname FROM group_members m INNER JOIN users u ON u.id = m.user_id WHERE m.group_id = '{$group_id}' AND u.id != '{$sender_id}'");
            $group_name = $this->db->query("SELECT name FROM chat_groups WHERE id = '{$group_id}'")->fetch_assoc()['name'];
            $sender_name = $this->db->query("SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE id = '{$sender_id}'")->fetch_assoc()['name'];
            
            $preview_message = substr(html_entity_decode($message), 0, 50) . (strlen(html_entity_decode($message)) > 50 ? '...' : '');
            $notification_message = "Pesan baru di grup **{$group_name}** dari {$sender_name}: " . $preview_message;
            $encoded_group_id = function_exists('encode_id') ? encode_id($group_id) : $group_id;
            $link = "index.php?page=chat&group_id={$encoded_group_id}"; 
            $email_subject = "[GROUP CHAT] Pesan di {$group_name}";

            while ($user = $members_q->fetch_assoc()) {
                $target_email = !empty($user['notification_email']) ? $user['notification_email'] : $user['email'];
                $email_details = [
                    'email' => $target_email, 'name'  => ucwords($user['firstname'] . ' ' . $user['lastname']), 'subject' => $email_subject
                ];
                // Type 5: Group Message
                record_notification($user['id'], 5, $notification_message, $link, $this->db, true, $email_details);
            }

            // >>> LOGIKA NOTIFIKASI TAMBAHAN (HANYA UNTUK PENGIRIM) <<<
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pesan grup berhasil terkirim!';
            return 1;
        } else {
            error_log("save_group_chat_message error: " . $this->db->error);
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal mengirim pesan grup.';
            return 0;
        }
    }

    function get_group_chat_messages() {
        $encoder = function_exists('encode_id') ? 'encode_id' : function($id) { return $id; }; 
        $current_user_id = $_SESSION['login_id'];
        extract($_POST);
        $group_id = $this->db->real_escape_string($group_id);

        $this->db->query("
            INSERT INTO user_group_read_status (group_id, user_id, last_read_at)
            VALUES ('{$group_id}', '{$current_user_id}', NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ");

        $data = ['users' => [], 'projects' => [], 'tasks' => []]; 

        $messages_q = $this->db->query("
            SELECT gm.*, CONCAT(u.firstname, ' ', u.lastname) as sender_name, u.avatar
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            WHERE gm.group_id = '{$group_id}'
            ORDER BY gm.created_at ASC
        ");
        
        $messages = [];
        while($row = $messages_q->fetch_assoc()) {
            $row['message_content'] = html_entity_decode($row['message_content']);
            $messages[] = $row;
        }
        
        $data['messages'] = $messages;
        return json_encode($data);
    }

    // 4. Aksi Baru: Mengambil Detail Grup (untuk Modal Settings)
    function get_group_details() {
        $group_id = $this->db->real_escape_string($_POST['group_id'] ?? null);
        $current_user_id = $_SESSION['login_id'];

        if (empty($group_id)) {
            return json_encode(['error' => 'Invalid ID']);
        }

        $group_q = $this->db->query("SELECT id, name, created_by FROM chat_groups WHERE id = '{$group_id}'");
        if ($group_q->num_rows == 0) {
            return json_encode(['error' => 'Group not found']);
        }
        $group_data = $group_q->fetch_assoc();

        $members_q = $this->db->query("
            SELECT gm.user_id, CONCAT(u.firstname, ' ', u.lastname) AS name, gm.is_admin
            FROM group_members gm
            INNER JOIN users u ON u.id = gm.user_id
            WHERE gm.group_id = '{$group_id}'
        ");
        
        $members = [];
        while ($row = $members_q->fetch_assoc()) {
            $members[] = $row;
        }

        $group_data['members'] = $members;
        return json_encode($group_data);
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
            $_SESSION['notification']['message'] = 'Nama grup dan anggota tidak boleh kosong.';
            return 0;
        }

        $this->db->begin_transaction();

        try {
            // A. Update Nama Grup
            $update_name = $this->db->query("UPDATE chat_groups SET name = '{$name}' WHERE id = '{$group_id}'");
            if (!$update_name) {
                throw new Exception("Gagal update nama.");
            }

            $delete_members = $this->db->query("DELETE FROM group_members WHERE group_id = '{$group_id}'");
            if (!$delete_members) {
                 throw new Exception("Gagal menghapus anggota lama.");
            }

            // C. Tambahkan anggota baru (ID yang dicentang)
            $user_ids_clean = array_unique(array_map('intval', $user_ids));
            $member_values = [];
            
            $created_by_q = $this->db->query("SELECT created_by FROM chat_groups WHERE id = '{$group_id}'")->fetch_assoc();
            $creator_id = $created_by_q ? $created_by_q['created_by'] : 0;
            
            foreach ($user_ids_clean as $user_id) {
                $is_admin = ($user_id == $creator_id) ? 1 : 0;
                $member_values[] = "('{$group_id}', '{$user_id}', '{$is_admin}')";
            }
            
            if (!empty($member_values)) {
                $sql_members = "INSERT INTO group_members (group_id, user_id, is_admin) VALUES " . implode(', ', $member_values);
                $this->db->query($sql_members);
            }
            
            $this->db->commit();
            
            // >>> LOGIKA NOTIFIKASI TAMBAHAN <<<
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pengaturan grup **'.htmlspecialchars($name).'** berhasil diperbarui!';
            return 1;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Update Group Settings Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal memperbarui pengaturan grup.';
            return 0; 
        }
    }

    // 6. Aksi Baru: Menghapus Grup Chat
    function delete_group() {
        $group_id = $this->db->real_escape_string($_POST['group_id'] ?? null);
        $group_name = $this->db->query("SELECT name FROM chat_groups WHERE id = '{$group_id}'")->fetch_assoc()['name'] ?? 'Grup Chat';

        if (empty($group_id)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID grup chat tidak valid.';
            return 0;
        }
       
        $this->db->begin_transaction();

        try {
            // Hapus Anggota, Pesan, Status Baca
            $this->db->query("DELETE FROM group_members WHERE group_id = '{$group_id}'");
            $this->db->query("DELETE FROM group_messages WHERE group_id = '{$group_id}'");
            $this->db->query("DELETE FROM user_group_read_status WHERE group_id = '{$group_id}'");
            
            // Hapus Grup Utama
            $delete_group = $this->db->query("DELETE FROM chat_groups WHERE id = '{$group_id}'");
            if (!$delete_group) {
                throw new Exception("Gagal menghapus entri grup utama.");
            }
            
            $this->db->commit();
            
            // >>> LOGIKA NOTIFIKASI TAMBAHAN <<<
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Grup chat **'.htmlspecialchars($group_name).'** berhasil dihapus!';
            return 1; 
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Grup Deletion Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal menghapus grup chat.';
            return 0; 
        }
    }
    
    // 6. Destructor
    function __destruct() {
        if ($this->db) $this->db->close();
        ob_end_flush();
    }
}
?>