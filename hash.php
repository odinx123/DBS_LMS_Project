<?php
// 您想設定的測試密碼
$my_password = "password123"; 

// 使用 PHP 內建函數產生雜湊值
$hash = password_hash($my_password, PASSWORD_DEFAULT);

echo "<h3>您的密碼： " . $my_password . "</h3>";
echo "<h3>請複製下方的雜湊值：</h3>";
echo "<p style='background-color: #eee; padding: 10px; font-family: monospace;'>" . $hash . "</p>";
?>