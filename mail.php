<?php
// Minimal PHPMailer wrapper for announcement email.
// If PHPMailer or SMTP config is missing, we block with a clear error.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function sendMail(string $to, string $subject, string $body): void
{
    // Try to load Composer autoloader if present.
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class) && file_exists($autoload)) {
        require_once $autoload;
    }

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        throw new RuntimeException('PHPMailer 未安裝（請先把 PHPMailer 安裝到專案/vendor）。');
    }

    // Read SMTP config from environment variables.
    // You can later set them in Windows environment variables or via a config file.
    $smtpHost = getenv('SMTP_HOST') ?: '';
    $smtpPort = getenv('SMTP_PORT') ?: '';
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: '';
    $fromName = getenv('SMTP_FROM_NAME') ?: 'LMS';

    if ($smtpHost === '' || $smtpPort === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
        throw new RuntimeException('SMTP 未設定，已阻擋送信。');
    }

    $mail = new PHPMailer(true);

    // SMTP configuration
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->Port = (int)$smtpPort;

    // TLS/SSL: keep flexible; you may adjust later.
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    // Charset / headers
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($to);

    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = $body;

    $mail->send();
}

