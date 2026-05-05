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

// Handle Export
if (false && isset($_GET['action']) && in_array($_GET['action'], ['export_csv', 'export_excel'])) {
    $filename = $assignment['Course_Name'] . "_" . $assignment['Title'] . "_成績單";
    $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);
    
    if ($_GET['action'] === 'export_excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    }

    $output = fopen('php://output', 'w');
    // Output BOM for Excel UTF-8 compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    if ($_GET['action'] === 'export_excel') {
        // Excel 需求：值方圖數據 + 表格資料
        fputcsv($output, ['成績分佈統計']);
        fputcsv($output, ['分數區間', '人數']);
        $labels = ['0-9', '10-19', '20-29', '30-39', '40-49', '50-59', '60-69', '70-79', '80-89', '90-100'];
        
        // 重新計算分佈數據
        $dist = array_fill(0, 10, 0);
        foreach ($submissions as $row) {
            if ($row['Score'] !== null) {
                $score = (float)$row['Score'];
                $index = min(floor($score / 10), 9);
                $dist[(int)$index]++;
            }
        }
        
        for ($i = 0; $i < 10; $i++) {
            fputcsv($output, [$labels[$i], $dist[$i]]);
        }
        fputcsv($output, []); // 空行分隔
    }
    
    fputcsv($output, ['學生姓名', '繳交狀態', '繳交時間', '得分', '評語']);
    
    foreach ($submissions as $row) {
        fputcsv($output, [
            $row['Name'],
            $row['Submit_ID'] ? '已繳交' : '未繳交',
            $row['Submit_Time'] ?? '-',
            $row['Score'] !== null ? $row['Score'] : '-',
            $row['Comment'] ?? '-'
        ]);
    }
    fclose($output);
    exit();
}

// Calculate Statistics for Chart
$distribution = array_fill(0, 10, 0); // 0-9, 10-19, ..., 90-99, 100
foreach ($submissions as $row) {
    if ($row['Score'] !== null) {
        $score = (float)$row['Score'];
        $index = min(floor($score / 10), 9);
        $distribution[(int)$index]++;
    }
}
$distributionJson = json_encode(array_values($distribution));
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>繳交管理 - <?php echo htmlspecialchars($assignment['Title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media print {
            .no-print, .btn, .navbar {
                display: none !important;
            }
            /* PDF 需求：隱藏成績表格，顯示直方圖 */
            .student-list-card {
                display: none !important;
            }
            .stats-card {
                display: block !important;
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
            <h3 class="mb-0"><?php echo htmlspecialchars($assignment['Title'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="text-muted">課程：<?php echo htmlspecialchars($assignment['Course_Name'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    下載成績
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?id=<?php echo urlencode($assignId); ?>&action=export_csv">下載 CSV</a></li>
                    <!-- <li><a class="dropdown-item" href="?id=<?php echo urlencode($assignId); ?>&action=export_excel">下載 Excel</a></li> -->
                    <li><a class="dropdown-item" href="javascript:window.print()">下載 PDF (列印)</a></li>
                </ul>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="teacher_assignments.php">返回作業列表</a>
        </div>
    </div>

    <!-- 統計區塊 -->
    <div class="row mb-4 no-print stats-card">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>成績分佈統計</span>
                    <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#statsCollapse">
                        切換顯示/隱藏
                    </button>
                </div>
                <div id="statsCollapse" class="collapse show">
                    <div class="card-body">
                        <div style="max-height: 300px;">
                            <canvas id="gradeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card student-list-card">
        <div class="card-header no-print">學生繳交清單</div>
        <div class="card-body p-0">
            <h4 class="d-none d-print-block text-center mb-4"><?php echo htmlspecialchars($assignment['Course_Name'] . " - " . $assignment['Title'], ENT_QUOTES, 'UTF-8'); ?> 成績單</h4>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>學生姓名</th>
                            <th>繳交狀態</th>
                            <th>繳交時間</th>
                            <th>得分</th>
                            <th>評語</th>
                            <th style="width: 100px;" class="no-print">操作</th>
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
                                    <td class="no-print">
                                        <?php if ($row['Submit_ID']): ?>
                                            <span class="badge bg-success">已繳交</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">未繳交</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['Submit_Time'] ? htmlspecialchars($row['Submit_Time'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td><?php echo $row['Score'] !== null ? htmlspecialchars($row['Score'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td><?php echo $row['Comment'] ? htmlspecialchars(mb_strimwidth($row['Comment'], 0, 30, "..."), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td class="no-print">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const ctx = document.getElementById('gradeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['0-9', '10-19', '20-29', '30-39', '40-49', '50-59', '60-69', '70-79', '80-89', '90-100'],
            datasets: [{
                label: '人數',
                data: <?php echo $distributionJson; ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
</script>
</body>
</html>
