<?php
// 資料庫設定
$host = '127.0.0.1'; // 伺服器名稱，通常是 localhost
$dbname = 'mydatabase'; // 請替換成您在 phpMyAdmin 建立的資料庫名稱
$username = 'root'; // XAMPP MySQL 預設帳號通常是 root
$password = ''; // XAMPP MySQL 預設密碼通常為空

try {
    // 建立 PDO 連線
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // 設定錯誤模式為例外處理，方便除錯
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 測試連線用，確認沒問題後可以把這行註解掉
    // echo "資料庫連線成功！"; 
} catch(PDOException $e) {
    echo "連線失敗: " . $e->getMessage();
}
?>