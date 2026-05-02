<?php
require_once __DIR__ . '/auth.php';
require_teacher();

$teacherId = $_SESSION['userid'];
$assignId = $_GET['id'] ?? '';

if ($assignId === '') {
    header('Location: teacher_assignments.php');
    exit();
}

// Verify assignment belongs to the teacher
$stmt = $conn->prepare("
    SELECT a.Title, c.Course_Name 
    FROM assignment a 
    JOIN course c ON a.Course_ID = c.Course_ID 
    WHERE a.Assign_ID = :aid AND c.Teacher_ID = :tid
");
$stmt->bindParam(':aid', $assignId);
$stmt->bindParam(':tid', $teacherId);
$stmt->execute();
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    die('找不到此作業，或您沒有權限訪問。');
}

// Fetch all students enrolled in the course and their submission status
$stmt = $conn->prepare("
    SELECT s.Student_ID, s.Name, sub.Submit_ID, sub.Score, sub.Comment, sub.Submit_Time
    FROM assignment a
    JOIN course c ON a.Course_ID = c.Course_ID
    JOIN enrollment e ON c.Course_ID = e.Course_ID
    JOIN student s ON e.Student_ID = s.Student_ID
    LEFT JOIN submission sub ON (sub.Assign_ID = a.Assign_ID AND sub.Student_ID = s.Student_ID)
    WHERE a.Assign_ID = :aid
    ORDER BY s.Name ASC
");
$stmt->bindParam(':aid', $assignId);
$stmt->execute();
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>繳交管理 - <?php echo htmlspecialchars($assignment['Title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container" style="padding-top: 28px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0"><?php echo htmlspecialchars($assignment['Title'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="text-muted">課程：<?php echo htmlspecialchars($assignment['Course_Name'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="teacher_assignments.php">返回作業列表</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">學生繳交清單</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>學生姓名</th>
                            <th>繳交狀態</th>
                            <th>繳交時間</th>
                            <th>得分</th>
                            <th>評語</th>
                            <th style="width: 100px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($submissions) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">此課程尚無學生</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($row['Submit_ID']): ?>
                                            <span class="badge bg-success">已繳交</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">未繳交</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['Submit_Time'] ? htmlspecialchars($row['Submit_Time'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td><?php echo $row['Score'] !== null ? htmlspecialchars($row['Score'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td><?php echo $row['Comment'] ? htmlspecialchars(mb_strimwidth($row['Comment'], 0, 30, "..."), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td>
                                        <?php if ($row['Submit_ID']): ?>
                                            <a class="btn btn-primary btn-sm" href="teacher_submission_detail.php?id=<?php echo urlencode($row['Submit_ID']); ?>">打分</a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>無法打分</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
