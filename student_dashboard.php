<?php
require_once __DIR__ . '/auth.php';
require_student();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>學生儀表板 - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- 引入 Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- 引入 Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .dashboard-card { 
            transition: transform 0.2s; 
            border: none; 
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .dashboard-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .icon-circle {
            width: 50px;
            height: 50px;
            background-color: #e7f1ff;
            color: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <!-- 將 href="#" 改為 href="student_dashboard.php" -->
        <a class="navbar-brand fw-bold" href="student_dashboard.php">NSYSU LMS</a>
        
        <div class="d-flex align-items-center">
            <span class="navbar-text me-3 text-white d-none d-sm-inline">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <a class="btn btn-outline-light btn-sm" href="logout.php">
                <i class="bi bi-box-arrow-right me-1"></i>登出
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-bold">學生前台儀表板</h2>
            <p class="text-muted">您好，歡迎使用課程管理系統。</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- 我的課程卡片 -->
        <div class="col-md-6 col-lg-4">
            <a href="student_course.php" class="text-decoration-none">
                <div class="card h-100 dashboard-card p-3">
                    <div class="card-body">
                        <div class="icon-circle">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <h5 class="card-title text-dark fw-bold">進入我的課程</h5>
                        <p class="card-text text-muted">檢視課程公告、下載講義、繳交作業並查詢成績分佈。</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 text-primary fw-bold text-end">
                        立即前往 <i class="bi bi-chevron-right"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- 個人資訊卡片 (顯示資料庫內容) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 dashboard-card p-3">
                <div class="card-body">
                    <div class="icon-circle">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <h5 class="card-title fw-bold text-dark">個人學籍資訊</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="text-muted">學號：<?php echo htmlspecialchars($_SESSION['user_id'] ?? 'S001', ENT_QUOTES, 'UTF-8'); ?></li>
                        <li class="text-muted">姓名：<?php echo htmlspecialchars($_SESSION['name'] ?? '未命名', ENT_QUOTES, 'UTF-8'); ?></li>
                        <li class="text-muted">班級：<?php echo htmlspecialchars($_SESSION['class'] ?? 'Default', ENT_QUOTES, 'UTF-8'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

