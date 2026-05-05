<?php
require_once __DIR__ . '/auth.php';
require_teacher();

$teacherId = $_SESSION['userid'];
$courseId = $_GET['course_id'] ?? '';

if ($courseId === '') {
    header('Location: teacher_assignments.php');
    exit();
}

// Verify course belongs to the teacher
$stmt = $conn->prepare("SELECT Course_Name FROM course WHERE Course_ID = :cid AND Teacher_ID = :tid");
$stmt->bindParam(':cid', $courseId);
$stmt->bindParam(':tid', $teacherId);
$stmt->execute();
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die('找不到此課程，或您沒有權限訪問。');
}

// Fetch all assignments for this course
$stmt = $conn->prepare("SELECT Assign_ID, Title FROM assignment WHERE Course_ID = :cid ORDER BY Publish_Time ASC");
$stmt->bindParam(':cid', $courseId);
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all students enrolled in the course
$stmt = $conn->prepare("
    SELECT s.Student_ID, s.Name 
    FROM enrollment e 
    JOIN student s ON e.Student_ID = s.Student_ID 
    WHERE e.Course_ID = :cid 
    ORDER BY s.Student_ID ASC
");
$stmt->bindParam(':cid', $courseId);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all scores for this course
$stmt = $conn->prepare("
    SELECT sub.Student_ID, sub.Assign_ID, sub.Score 
    FROM submission sub
    JOIN assignment a ON sub.Assign_ID = a.Assign_ID
    WHERE a.Course_ID = :cid
");
$stmt->bindParam(':cid', $courseId);
$stmt->execute();
$scoresData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize scores into a 2D array [student_id][assign_id]
$scores = [];
foreach ($scoresData as $row) {
    $scores[$row['Student_ID']][$row['Assign_ID']] = $row['Score'];
}

// Handle Export
if (false && isset($_GET['action']) && in_array($_GET['action'], ['export_csv', 'export_excel'])) {
    $filename = $course['Course_Name'] . "_學期成績單";
    $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);
    
    if ($_GET['action'] === 'export_excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    }

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    
    $header = ['學號', '姓名'];
    foreach ($assignments as $a) {
        $header[] = $a['Title'];
    }
    fputcsv($output, $header);
    
    foreach ($students as $s) {
        $row = [$s['Student_ID'], $s['Name']];
        foreach ($assignments as $a) {
            $row[] = $scores[$s['Student_ID']][$a['Assign_ID']] ?? '-';
        }
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// Calculate averages for chart
$avgScores = [];
foreach ($assignments as $a) {
    $total = 0;
    $count = 0;
    foreach ($students as $s) {
        if (isset($scores[$s['Student_ID']][$a['Assign_ID']]) && $scores[$s['Student_ID']][$a['Assign_ID']] !== null) {
            $total += (float)$scores[$s['Student_ID']][$a['Assign_ID']];
            $count++;
        }
    }
    $avgScores[] = $count > 0 ? round($total / $count, 2) : 0;
}
$avgScoresJson = json_encode($avgScores);
$assignTitlesJson = json_encode(array_column($assignments, 'Title'));

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>學期成績統計 - <?php echo htmlspecialchars($course['Course_Name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media print {
            .no-print, .btn, .card-header, .navbar {
                display: none !important;
            }
            .container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .card {
                border: none !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
        }
    </style>
</head>
<body>
<div class="container" style="padding-top: 28px;">
    <div class="d-flex align-items-center justify-content-between mb-3 no-print">
        <div>
            <h3 class="mb-0">學期成績統計</h3>
            <div class="text-muted">課程：<?php echo htmlspecialchars($course['Course_Name'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    下載全班成績
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?course_id=<?php echo urlencode($courseId); ?>&action=export_csv">下載 CSV</a></li>
<!-- <li><a class="dropdown-item" href="?course_id=<?php echo urlencode($courseId); ?>&action=export_excel">下載 Excel</a></li> -->
                    <li><a class="dropdown-item" href="javascript:window.print()">下載 PDF (列印)</a></li>
                </ul>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="teacher_assignments.php">返回作業列表</a>
        </div>
    </div>

    <!-- 統計圖表 -->
    <div class="row mb-4 no-print">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">各項作業平均成績</div>
                <div class="card-body">
                    <div style="max-height: 300px;">
                        <canvas id="avgChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header no-print">學期成績彙整表</div>
        <div class="card-body p-0">
            <h4 class="d-none d-print-block text-center mb-4"><?php echo htmlspecialchars($course['Course_Name'], ENT_QUOTES, 'UTF-8'); ?> 學期成績單</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>學號</th>
                            <th>姓名</th>
                            <?php foreach ($assignments as $a): ?>
                                <th><?php echo htmlspecialchars($a['Title'], ENT_QUOTES, 'UTF-8'); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) === 0): ?>
                            <tr>
                                <td colspan="<?php echo count($assignments) + 2; ?>" class="text-center text-muted py-4">尚無學生選修此課程</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['Student_ID'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($s['Name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php foreach ($assignments as $a): ?>
                                        <td>
                                            <?php 
                                            $score = $scores[$s['Student_ID']][$a['Assign_ID']] ?? null;
                                            echo $score !== null ? htmlspecialchars($score, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">-</span>';
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const ctx = document.getElementById('avgChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo $assignTitlesJson; ?>,
            datasets: [{
                label: '平均成績',
                data: <?php echo $avgScoresJson; ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
</script>
</body>
</html>
