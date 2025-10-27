<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Wajib: Autoload Composer PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

/* ==============================
   KONFIGURASI SMTP SERVER
   ============================== */
define('SMTP_HOST', 'mail.haimotion.com');
define('SMTP_USERNAME', 'dashboard@haimotion.com'); 
define('SMTP_PASSWORD', 'e[M)1hjKN-30(Ety'); 
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl'); 

define('EMAIL_FROM', 'dashboard@haimotion.com');
define('EMAIL_FROM_NAME', 'HaiMotion Dashboard');
define('APP_BASE_URL', 'https://dashboard.haimotion.com/');
define('MAIL_NAME', 'HaiMotion');

/* ==============================
   FUNGSI KIRIM EMAIL
   ============================== */
function send_task_notification_email($recipient_email, $recipient_name, $subject, $body_html) {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer class not found.");
        return false;
    }
    
    $mail = new PHPMailer(true);

    try {
        // ========== SMTP CONFIG ==========
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // Return-Path sama dengan FROM
        $mail->Sender     = EMAIL_FROM;

        // Opsi tambahan SSL (dev mode)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // ========== IDENTITAS EMAIL ==========
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->addReplyTo('support@haimotion.com', 'HaiMotion Support');

        // Header tambahan untuk reputasi
        $mail->addCustomHeader('X-Mailer', 'HaiMotion Notifier 1.0');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        // ========== TEMPLATE EMAIL ==========
        $email_template = "
            <div style='font-family: Arial, sans-serif; color: #333; max-width:600px; margin:auto; border:1px solid #eee; border-radius:10px; overflow:hidden;'>
                <div style='background:#007bff; color:#fff; padding:15px 20px;'>
                    <h2 style='margin:0;'>HaiMotion Dashboard</h2>
                </div>
                <div style='padding:20px;'>
                    <p>Halo <strong>{$recipient_name}</strong>,</p>
                    <p>{$body_html}</p>
                    <p style='margin-top:20px;'>
                        <a href='" . APP_BASE_URL . "' 
                           style='background:#007bff; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>
                           Buka Dashboard
                        </a>
                    </p>
                </div>
                <div style='background:#f9f9f9; padding:15px; font-size:12px; color:#777; text-align:center;'>
                    Email ini dikirim otomatis oleh sistem <strong>HaiMotion Dashboard</strong>.<br>
                    Mohon jangan membalas langsung ke email ini.
                </div>
            </div>
        ";

        $mail->Subject = $subject;
        $mail->Body    = $email_template;
        $mail->AltBody = strip_tags($body_html);

        // ========== KIRIM EMAIL ==========
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/* ==============================
   CATAT DAN KIRIM NOTIFIKASI
   ============================== */
function record_notification($user_id, $type, $message, $link, $conn, $send_email = false, $email_details = []) {
    $message_db = $conn->real_escape_string($message);
    $link_db = $conn->real_escape_string($link);
    $user_id_db = intval($user_id);
    $type_db = intval($type);

    $query = "
        INSERT INTO notification_list (user_id, type, message, link)
        VALUES ('{$user_id_db}', '{$type_db}', '{$message_db}', '{$link_db}')
    ";

    $insert_success = $conn->query($query);
    
    // Jika email perlu dikirim
    if ($insert_success && $send_email && !empty($email_details) && isset($email_details['email'])) {
        $full_link = APP_BASE_URL . $link;
        $html_message = "
            Anda mendapat notifikasi baru: <strong>{$message}</strong><br>
            Lihat detail di sini: <a href='{$full_link}'>{$full_link}</a>
        ";

        send_task_notification_email(
            $email_details['email'],
            $email_details['name'] ?? 'User',
            $email_details['subject'] ?? 'Notifikasi Baru dari HaiMotion Dashboard',
            $html_message
        );
    }
}
?>
