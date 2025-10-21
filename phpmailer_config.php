<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Pastikan autoload Composer dipanggil (wajib kalau install via composer)
require_once __DIR__ . '/vendor/autoload.php';

// GANTI KONFIGURASI DI BAWAH INI dengan detail SMTP Anda yang sebenarnya!
define('SMTP_HOST', 'mail.haimotion.com');
define('SMTP_USERNAME', 'dashboard@haimotion.com'); 
define('SMTP_PASSWORD', 'e[M)1hjKN-30(Ety'); 
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl'); 
define('EMAIL_FROM', 'dashboard@haimotion.com');
define('EMAIL_FROM_NAME', 'HaiMotion Dashboard');
define('APP_BASE_URL', 'https://dashboard.haimotion.com/');
define('MAIL_NAME', 'HaiMotion');

/**
 * Mengirim notifikasi email.
 * @param string 
 * @param string 
 * @param string 
 * @param string 
 * @return bool
 */
function send_task_notification_email($recipient_email, $recipient_name, $subject, $body_html) {
    // Cek apakah PHPMailer ada (misalnya jika tidak menggunakan Composer)
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Fallback atau log error
        // error_log("PHPMailer class not found. Email not sent to {$recipient_email}."); 
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP(); 
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

         // ✅ tambahan biar aman di localhost (skip verifikasi SSL)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($recipient_email, $recipient_name); 

        $mail->isHTML(true); 
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = strip_tags($body_html); 

        $mail->send();
        return true;
    } catch (Exception $e) {
        // error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Mencatat notifikasi ke database dan mengirim email jika diminta.
 * @param int $user_id ID penerima
 * @param int $type Tipe notifikasi (sesuai DB COMMENT)
 * @param string $message Pesan notifikasi
 * @param string $link Link relatif ke halaman (misalnya: index.php?page=view_task&id=1)
 * @param mysqli $conn Objek koneksi database
 * @param bool $send_email Apakah akan mengirim email
 * @param array $email_details Array berisi 'email', 'name', 'subject' untuk email
 */
function record_notification($user_id, $type, $message, $link, $conn, $send_email = false, $email_details = []) {
    $message_db = $conn->real_escape_string($message);
    $link_db = $conn->real_escape_string($link);
    $user_id_db = intval($user_id);
    $type_db = intval($type);

    $query = "INSERT INTO notification_list (user_id, type, message, link) 
              VALUES ('{$user_id_db}', '{$type_db}', '{$message_db}', '{$link_db}')";
              
    $insert_success = $conn->query($query);
    
    if ($insert_success && $send_email && !empty($email_details) && isset($email_details['email'])) {
        $full_link = APP_BASE_URL . $link;
        $email_body = "Halo " . ($email_details['name'] ?? 'User') . ",<br><br>"
                    . "Anda mendapat notifikasi baru: " . $message . "<br>"
                    . "Lihat detail di sini: <a href='{$full_link}'>{$full_link}</a>";
        send_task_notification_email($email_details['email'], $email_details['name'] ?? '', $email_details['subject'] ?? 'Notifikasi Tugas Baru', $email_body);
    }
}
?>