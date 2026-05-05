<?php
require_once __DIR__ . '/auth.php';
require_teacher();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>教師管理後台 - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- 引入 Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background-color: #fff3cd; /* 使用黃色調區分教師端 */
            color: #856404;
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

<!-- 導覽列：點擊回首頁，包含登出按鈕 -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="teacher_dashboard.php">NSYSU LMS <span class="badge bg-warning text-dark ms-2" style="font-size: 0.6em;">管理端</span></a>
        
        <div class="d-flex align-items-center">
            <span class="navbar-text me-3 text-white d-none d-sm-inline">
                <i class="bi bi-person-badge me-1"></i>
                <?php echo htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> 
                (<?php echo htmlspecialchars($_SESSION['teacher_role'] ?? 'teacher', ENT_QUOTES, 'UTF-8'); ?>)
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
            <h2 class="fw-bold">教師/助教儀表板</h2>
            <p class="text-muted">歡迎使用教學管理功能，您可以管理公告、作業與成績。</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- 公告管理卡片 -->
        <div class="col-md-6 col-lg-4">
            <a href="teacher_announcements.php" class="text-decoration-none">
                <div class="card h-100 dashboard-card p-3">
                    <div class="card-body">
                        <div class="icon-circle">
                            <i class="bi bi-megaphone"></i>
                        </div>
                        <h5 class="card-title text-dark fw-bold">公告管理</h5>
                        <p class="card-text text-muted">發布新公告，系統將自動同步寄信通知修課學生。</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 text-warning fw-bold text-end">
                        進入管理 <i class="bi bi-chevron-right"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- 作業管理卡片 -->
        <div class="col-md-6 col-lg-4">
            <a href="teacher_assignments.php" class="text-decoration-none">
                <div class="card h-100 dashboard-card p-3">
                    <div class="card-body">
                        <div class="icon-circle">
                            <i class="bi bi-file-earmark-check"></i>
                        </div>
                        <h5 class="card-title text-dark fw-bold">作業管理</h5>
                        <p class="card-text text-muted">管理截止日期、批改學生作業、輸入成績與評語。</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 text-warning fw-bold text-end">
                        進入管理 <i class="bi bi-chevron-right"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- 成績統計卡片 (對應作業 B/C 需求) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 dashboard-card p-3">
                <div class="card-body">
                    <div class="icon-circle">
                        <i class="bi bi-bar-chart"></i>
                    </div>
                    <h5 class="card-title fw-bold text-dark">數據統計</h5>
                    <p class="card-text text-muted">產生成績分佈直方圖，並匯出 Excel/PDF 全班成績。</p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

