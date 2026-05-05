<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 過濾掉註解
        if (strpos(trim($line), '#') === 0) continue;
        // 確保每一行都有等號
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}
loadEnv(__DIR__ . '/.env');

function sendMail(string $to, string $subject, string $body): void
{
    // 從環境變數讀取，若讀不到則為空字串
    $smtpUser = $_ENV['MAIL_USER'] ?? '';
    $smtpPass = $_ENV['MAIL_PASS'] ?? '';
    $fromEmail = $_ENV['MAIL_FROM'] ?? '';

    // 檢查是否漏設設定檔
    if (empty($smtpUser) || empty($smtpPass)) {
        throw new RuntimeException('錯誤：請確認 .env 檔案中已設定 MAIL_USER 與 MAIL_PASS。');
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'student.nsysu.edu.tw'; // 中山大學伺服器
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;                   // 強制關閉自動 TLS 避免報錯
        $mail->Port       = 25;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom($fromEmail, 'LMS 公告系統');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true); 
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // 自動把 HTML 標籤拿掉當成純文字備份

        $mail->send();
    } catch (PHPMailerException $e) {
        throw new Exception("郵件發送失敗: {$mail->ErrorInfo}");
    }
}