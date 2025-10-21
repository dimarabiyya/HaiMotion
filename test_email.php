<?php
require_once 'phpmailer_config.php';

$to = 'contact.haimotion@gmail.com';
$name = 'Tester';
$subject = 'Tes Email dari HaiMotion';
$body = '<h3>Halo!</h3><p>Email ini dikirim dari localhost via PHPMailer 🎉</p>';

if (send_task_notification_email($to, $name, $subject, $body)) {
    echo "✅ Email berhasil dikirim ke $to";
} else {
    echo "❌ Gagal mengirim email.";
}
?>
