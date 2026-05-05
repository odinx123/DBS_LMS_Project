<?php
// 臨時腳本：修改資料庫結構
require_once 'db.php';

try {
    $sql = "ALTER TABLE announcement ADD COLUMN auto_publish TINYINT(1) DEFAULT 0";
    $conn->exec($sql);
    echo "成功：已在 announcement 表中增加 auto_publish 欄位。";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "提示：auto_publish 欄位已存在。";
    } else {
        echo "錯誤：" . $e->getMessage();
    }
}
?>