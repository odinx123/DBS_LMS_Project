<?php
require_once __DIR__ . '/auth.php';
require_teacher();

$teacherId = $_SESSION['userid'];
$submitId = $_GET['id'] ?? '';

if ($submitId === '') {
    header('Location: teacher_assignments.php');
    exit();
}

// Verify submission belongs to a course taught by this teacher
$stmt = $conn->prepare("
    SELECT sub.*, s.Name as Student_Name, a.Title as Assignment_Title, c.Course_Name
    FROM submission sub
    JOIN student s ON sub.Student_ID = s.Student_ID
    JOIN assignment a ON sub.Assign_ID = a.Assign_ID
    JOIN course c ON a.Course_ID = c.Course_ID
    WHERE sub.Submit_ID = :sid AND c.Teacher_ID = :tid
");
$stmt->bindParam(':sid', $submitId);
$stmt->bindParam(':tid', $teacherId);
$stmt->execute();
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    die('找不到此繳交紀錄，或您沒有權限訪問。');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_grade') {
    $score = $_POST['score'] !== '' ? (float)$_POST['score'] : null;
    $comment = trim($_POST['comment'] ?? '');

    $update = $conn->prepare("
        UPDATE submission 
        SET Score = :score, Comment = :comment 
        WHERE Submit_ID = :sid
    ");
    $update->bindParam(':score', $score);
    $update->bindParam(':comment', $comment);
    $update->bindParam(':sid', $submitId);
    
    if ($update->execute()) {
        $success = '成績已更新。';
        // Refresh submission data
        $stmt->execute();
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = '更新失敗。';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>打分 - <?php echo htmlspecialchars($submission['Student_Name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="padding-top: 28px; max-width: 800px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">作業打分</h3>
            <div class="text-muted"><?php echo htmlspecialchars($submission['Course_Name'], ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($submission['Assignment_Title'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="teacher_assignment_submissions.php?id=<?php echo urlencode($submission['Assign_ID']); ?>">返回清單</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header">學生資訊</div>
                <div class="card-body">
                    <p><strong>姓名：</strong> <?php echo htmlspecialchars($submission['Student_Name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>繳交時間：</strong> <?php echo htmlspecialchars($submission['Submit_Time'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold">繳交檔案：</label>
                        <?php if ($submission['File_Path']): ?>
                            <a href="<?php echo htmlspecialchars($submission['File_Path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary w-100">
                                查看檔案 <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">無檔案</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header">評分與評語</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_grade">
                        
                        <div class="mb-3">
                            <label class="form-label">得分</label>
                            <input type="number" step="0.1" name="score" class="form-control" 
                                   value="<?php echo $submission['Score'] !== null ? htmlspecialchars($submission['Score'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">評語</label>
                            <textarea name="comment" class="form-control" rows="6"><?php echo htmlspecialchars($submission['Comment'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">儲存成績</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
