<?php
require_once __DIR__ . '/auth.php';
require_student();

$studentId = $_SESSION['userid'];
session_write_close();
$error = '';
$success = '';

function generateId(string $prefix): string
{
    return $prefix . bin2hex(random_bytes(8));
}

function isAllowedExtension(string $ext): bool
{
    $ext = strtolower($ext);
    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt', 'png', 'jpg', 'jpeg'];
    return in_array($ext, $allowed, true);
}

// 接收 Assign_ID 和 Course_ID
$assignId = trim($_GET['assign_id'] ?? '');
$courseId = trim($_GET['course_id'] ?? '');

$assignment = null;
$submission = null;

if ($assignId === '' || $courseId === '') {
    $error = '作業ID或課程ID缺失。';
} else {
    // 驗證學生是否有權限存取此課程和作業
    $stmt = $conn->prepare("
        SELECT a.Assign_ID, a.Course_ID, c.Course_Name, a.Title, a.Description, a.Due_Date, st.Class
        FROM assignment a
        JOIN course c ON a.Course_ID = c.Course_ID
        JOIN enrollment e ON e.Course_ID = c.Course_ID
        JOIN student st ON e.Student_ID = st.Student_ID
        WHERE a.Assign_ID = :aid AND a.Course_ID = :cid AND e.Student_ID = :sid
        LIMIT 1
    ");
    $stmt->bindParam(':aid', $assignId);
    $stmt->bindParam(':cid', $courseId);
    $stmt->bindParam(':sid', $studentId);
    $stmt->execute();
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        $error = '找不到此作業，或你無權存取。';
    } else {
        // 查詢學生的繳交記錄
        $subStmt = $conn->prepare("
            SELECT Submit_ID, File_Path, Submit_Time, Score, Comment
            FROM submission
            WHERE Assign_ID = :aid AND Student_ID = :sid
            LIMIT 1
        ");
        $subStmt->bindParam(':aid', $assignId);
        $subStmt->bindParam(':sid', $studentId);
        $subStmt->execute();
        $submission = $subStmt->fetch(PDO::FETCH_ASSOC);

        // 獲取同班級的成績分佈
        $studentClass = $assignment['Class'];
        $distStmt = $conn->prepare("
            SELECT s.Score
            FROM submission s
            JOIN student st ON s.Student_ID = st.Student_ID
            WHERE s.Assign_ID = :aid AND st.Class = :class AND s.Score IS NOT NULL
        ");
        $distStmt->bindParam(':aid', $assignId);
        $distStmt->bindParam(':class', $studentClass);
        $distStmt->execute();
        $allScores = $distStmt->fetchAll(PDO::FETCH_COLUMN);

        // 將成績分佈分桶 (0-10, 11-20, ..., 91-100)
        $bins = array_fill(0, 10, 0);
        foreach ($allScores as $score) {
            $binIndex = floor(($score - 0.01) / 10);
            if ($binIndex < 0) $binIndex = 0;
            if ($binIndex > 9) $binIndex = 9;
            $bins[$binIndex]++;
        }
        $scoreDistribution = [
            'labels' => ['0-10', '11-20', '21-30', '31-40', '41-50', '51-60', '61-70', '71-80', '81-90', '91-100'],
            'data' => $bins
        ];
    }
}

// 檔案上傳處理邏輯 (從 student_course.php 複製並修改)
if ($assignment && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_submit'])) {
    $file = $_FILES['submission_file'] ?? null;

    if (!$file || !isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        $error = '上傳失敗：請確認檔案並重試。';
    } else {
        $dueTs = strtotime((string)$assignment['Due_Date']);
        if ($dueTs === false) {
            $error = '無法上傳：作業截止日資料異常。';
        } elseif (time() > $dueTs) {
            $error = '此作業已超過繳交期限，無法上傳。';
        } else {
            $originalName = $file['name'] ?? '';
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($ext === '' || !isAllowedExtension($ext)) {
                $error = '不支援的檔案格式。';
            } elseif ((int)$file['size'] <= 0 || (int)$file['size'] > 25 * 1024 * 1024) {
                $error = '檔案大小不正確（建議小於 25MB）。';
            } else {
                $uploadDir = __DIR__ . '/uploads/submissions';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
                $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
                $relativePath = 'uploads/submissions/' . $safeName;

                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $error = '上傳失敗：伺服器無法保存檔案。';
                } else {
                    if ($submission) {
                        // 更新現有繳交記錄
                        $update = $conn->prepare("
                            UPDATE submission
                            SET File_Path = :fp,
                                Submit_Time = NOW(),
                                Score = NULL,
                                Comment = NULL
                            WHERE Submit_ID = :submitId
                        ");
                        $update->bindParam(':fp', $relativePath);
                        $update->bindParam(':submitId', $submission['Submit_ID']);
                        $update->execute();
                        $success = '作業已更新繳交（已覆寫）。';
                    } else {
                        // 插入新的繳交記錄
                        $newSubmitId = generateId('sub_');
                        $insert = $conn->prepare("
                            INSERT INTO submission (Submit_ID, Assign_ID, Student_ID, File_Path, Score, Comment)
                            VALUES (:submitId, :aid, :sid, :fp, NULL, NULL)
                        ");
                        $insert->bindParam(':submitId', $newSubmitId);
                        $insert->bindParam(':aid', $assignId);
                        $insert->bindParam(':sid', $studentId);
                        $insert->bindParam(':fp', $relativePath);
                        $insert->execute();
                        $success = '作業已成功繳交。';
                    }
                    // 重新查詢繳交記錄以更新顯示
                    $subStmt = $conn->prepare("
                        SELECT Submit_ID, File_Path, Submit_Time, Score, Comment
                        FROM submission
                        WHERE Assign_ID = :aid AND Student_ID = :sid
                        LIMIT 1
                    ");
                    $subStmt->bindParam(':aid', $assignId);
                    $subStmt->bindParam(':sid', $studentId);
                    $subStmt->execute();
                    $submission = $subStmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        }
    }
}

// 判斷目前是否已超過截止日期
$isPastDeadline = false;
if ($assignment) {
    $dueTs = strtotime((string)$assignment['Due_Date']);
    if ($dueTs !== false && time() > $dueTs) {
        $isPastDeadline = true;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title><?php echo $assignment ? htmlspecialchars($assignment['Title'], ENT_QUOTES, 'UTF-8') : '作業'; ?> - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
<div class="container" style="padding-top: 28px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">作業詳細：<?php echo $assignment ? htmlspecialchars($assignment['Title'], ENT_QUOTES, 'UTF-8') : ''; ?></h3>
            <div class="text-muted"><?php echo $assignment ? htmlspecialchars($assignment['Course_Name'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="student_course.php?course_id=<?php echo urlencode($courseId); ?>&tab=assignments">返回課程作業列表</a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($assignment): ?>
        <div class="card mb-3">
            <div class="card-header">作業資訊</div>
            <div class="card-body">
                <p><strong>標題:</strong> <?php echo htmlspecialchars($assignment['Title'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>課程:</strong> <?php echo htmlspecialchars($assignment['Course_Name'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>繳交期限:</strong> <?php echo htmlspecialchars($assignment['Due_Date'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($submission): ?>
                        <span class="badge bg-success ms-2">已繳交</span>
                    <?php elseif ($isPastDeadline): ?>
                        <span class="badge bg-danger ms-2">已逾期</span>
                    <?php endif; ?>
                </p>
                <p><strong>描述:</strong></p>
                <div class="alert alert-info" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($assignment['Description'] ?? '無描述', ENT_QUOTES, 'UTF-8')); ?></div>
            </div>
    
            <?php if (isset($scoreDistribution)): ?>
                <div class="card mb-3">
                    <div class="card-header">全班成績分佈 (班級: <?php echo htmlspecialchars($assignment['Class'], ENT_QUOTES, 'UTF-8'); ?>)</div>
                    <div class="card-body">
                        <canvas id="scoreChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
    
                <script>
                    (function() {
                        const ctx = document.getElementById('scoreChart').getContext('2d');
                        const labels = <?php echo json_encode($scoreDistribution['labels']); ?>;
                        const data = <?php echo json_encode($scoreDistribution['data']); ?>;
    
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: '人數',
                                    data: data,
                                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                }
                            }
                        });
                    })();
                </script>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="card-header">我的繳交狀態</div>
            <div class="card-body">
                <?php if ($submission): ?>
                    <p><strong>繳交時間:</strong> <?php echo htmlspecialchars($submission['Submit_Time'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>檔案:</strong> <a href="<?php echo htmlspecialchars($submission['File_Path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">下載已上傳檔案</a></p>
                    <p><strong>分數:</strong> <?php echo htmlspecialchars($submission['Score'] ?? '未評分', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>評語:</strong> <?php echo htmlspecialchars($submission['Comment'] ?? '無', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (!($isPastDeadline)): ?>
                        <div class="alert alert-success">你已繳交此作業。您可以重新上傳來更新。</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning">你尚未繳交此作業。</div>
                <?php endif; ?>

                <?php if (!$isPastDeadline): ?>
                    <hr>
                    <h5>上傳作業</h5>
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="assign_id" value="<?php echo htmlspecialchars($assignId, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($courseId, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="col-md-8">
                            <label for="submissionFile" class="form-label">選擇檔案</label>
                            <input type="file" name="submission_file" id="submissionFile" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.txt,.png,.jpg,.jpeg" required>
                            <div class="form-text">支援檔案格式：pdf, doc, docx, ppt, pptx, zip, txt, png, jpg, jpeg (最大 25MB)</div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="upload_submit" value="1" class="btn btn-primary w-100">上傳作業</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger mt-3">已超過繳交期限，無法上傳。</div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-danger">無法載入作業詳細資訊。</div>
    <?php endif; ?>
</div>
</body>
</html>