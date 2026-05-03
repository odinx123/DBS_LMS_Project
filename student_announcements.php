<?php
require_once __DIR__ . '/auth.php';
require_student();

$studentId = $_SESSION['userid'];

$stmt = $conn->prepare("
    SELECT
        c.Course_ID,
        c.Course_Name,
        a.Announce_ID,
        a.Title,
        a.Content,
        a.Publish_Time,
        a.Update_Time
    FROM enrollment e
    JOIN course c ON e.Course_ID = c.Course_ID
    LEFT JOIN announcement a ON a.Course_ID = c.Course_ID
    WHERE e.Student_ID = :sid
    ORDER BY a.Publish_Time DESC
");
$stmt->bindParam(':sid', $studentId);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter out null announcements caused by LEFT JOIN.
$announcements = array_values(array_filter($rows, function ($r) {
    return !empty($r['Announce_ID']);
}));
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>公告瀏覽 - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container" style="padding-top: 28px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">公告瀏覽</h3>
            <div class="text-muted">顯示你已選課的公告</div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="student_dashboard.php">返回</a>
        </div>
    </div>

    <?php if (count($announcements) === 0): ?>
        <div class="alert alert-info">目前沒有公告。</div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($announcements as $index => $a): ?>
                <?php $collapseId = 'collapse-' . $index; ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted small">
                                課程：<?php echo htmlspecialchars($a['Course_Name'] ?? $a['Course_ID'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="fw-semibold">
                                <?php echo htmlspecialchars($a['Title'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                        <div class="text-muted small text-end">
                            <?php echo htmlspecialchars($a['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>

                    <?php if (!empty($a['Content'])): ?>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                                顯示/隱藏內容
                            </button>
                            <div class="collapse" id="<?php echo $collapseId; ?>">
                                <div class="card card-body" style="white-space: pre-wrap;">
                                    <?php echo nl2br(htmlspecialchars($a['Content'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

