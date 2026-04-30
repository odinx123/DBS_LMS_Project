<?php
// 啟動 Session 以記錄登入狀態
session_start();

// 引入資料庫連線檔案
require_once 'db.php';

$error_msg = '';

// 檢查是否透過 POST 傳送表單資料
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userid = trim($_POST['userid']);
    $password = $_POST['password'];
    $role = $_POST['role']; // 取得使用者選擇的角色：student 或 teacher

    try {
        // 根據角色決定查詢哪一個資料表
        if ($role == 'student') {
            $stmt = $conn->prepare("SELECT * FROM STUDENT WHERE Student_ID = :userid");
        } else {
            $stmt = $conn->prepare("SELECT * FROM TEACHER WHERE Teacher_ID = :userid");
        }
        
        $stmt->bindParam(':userid', $userid);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 驗證密碼 (由於資料表設計為 Password_Hash，這裡使用 password_verify 來比對雜湊值)
        if ($user && password_verify($password, $user['Password_Hash'])) {
            // 登入成功，將使用者資訊存入 Session
            session_regenerate_id(true);
            $_SESSION['userid'] = $userid;
            $_SESSION['role'] = $role;
            $_SESSION['name'] = $user['Name'];

            // Teacher/TA 分流：後台用 $_SESSION['role'] 判斷可進入；teacher_role 用來區分 teacher/ta
            if ($role === 'teacher') {
                $_SESSION['teacher_role'] = $user['Role'] ?? 'teacher';
            } else {
                unset($_SESSION['teacher_role']);
            }
            
            // 依據角色導向不同的頁面
            if ($role == 'student') {
                header("Location: student_dashboard.php");
            } else {
                header("Location: teacher_dashboard.php");
            }
            exit();
        } else {
            $error_msg = "帳號或密碼錯誤，請重新輸入！";
        }
    } catch(PDOException $e) {
        $error_msg = "系統錯誤: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>學習管理系統 - 登入</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container" style="max-width: 520px; padding-top: 48px;">
        <h2 class="mb-4">學習管理系統 (LMS) 登入</h2>

        <?php if ($error_msg != ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST" class="card p-4">
            <div class="mb-3">
                <label for="role" class="form-label">身分：</label>
                <select name="role" id="role" class="form-select" required>
                    <option value="student">學生</option>
                    <option value="teacher">教師/助教</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="userid" class="form-label">帳號 (學號/教職員編號)：</label>
                <input type="text" name="userid" id="userid" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">密碼：</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">登入系統</button>
        </form>
    </div>
</body>
</html>