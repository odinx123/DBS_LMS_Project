<?php
require_once __DIR__ . '/auth.php';
require_student();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>Student 前台 - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container" style="padding-top: 32px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">Student 前台</h3>
            <div class="text-muted">
                歡迎，<?php echo htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="logout.php">登出</a>
        </div>
    </div>

    <div class="list-group">
        <a class="list-group-item list-group-item-action" href="student_course.php">進入我的課程（公告 / 作業 / 成績）</a>
    </div>
</div>
</body>
</html>

