<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Hàm gửi email sử dụng PHPMailer và SMTP (mặc định cấu hình Gmail)
 * 
 * @param string $to Email người nhận
 * @param string $subject Tiêu đề email
 * @param string $body Nội dung email (hỗ trợ HTML)
 * @param string|null $fromName Tên người gửi (tùy chọn)
 * @return bool Trả về true nếu gửi thành công, false nếu thất bại
 */
function send_custom_mail($to, $subject, $body, $fromName = null) {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Cấu hình Server
        $mail->isSMTP();
        $mail->Host       = env_value('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = env_value('MAIL_USERNAME');
        $mail->Password   = env_value('MAIL_PASSWORD');
        $mail->SMTPSecure = env_value('MAIL_ENCRYPTION', 'tls');
        $mail->Port       = (int)env_value('MAIL_PORT', 587);
        $mail->CharSet    = 'UTF-8';

        // Người gửi & Người nhận
        $fromAddress = env_value('MAIL_FROM_ADDRESS');
        $defaultFromName = env_value('MAIL_FROM_NAME', 'Gấu Bakery');
        $mail->setFrom($fromAddress, $fromName ?? $defaultFromName);
        $mail->addAddress($to);

        // Nội dung
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        // Plain text version for non-HTML mail clients
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
