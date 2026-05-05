<?php
require_once __DIR__ . '/auth.php';
require_student();

$studentId = $_SESSION['userid'];
// 提前釋放 Session 鎖，避免阻塞後續的 AJAX 請求
session_write_close();
$error = '';
$success = '';

$activeInnerTab = trim($_POST['tab'] ?? $_GET['tab'] ?? 'announcements');
$allowedTabs = ['announcements', 'assignments', 'grades'];
if (!in_array($activeInnerTab, $allowedTabs, true)) {
    $activeInnerTab = 'announcements';
}

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

// Load student enrolled courses (first-layer tabs)
$courseStmt = $conn->prepare("
    SELECT c.Course_ID, c.Course_Name
    FROM enrollment e
    JOIN course c ON e.Course_ID = c.Course_ID
    WHERE e.Student_ID = :sid
    ORDER BY c.Course_Name ASC
");
$courseStmt->bindParam(':sid', $studentId);
$courseStmt->execute();
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
$enrolledCourseIds = array_column($courses, 'Course_ID');

$selectedCourseId = trim($_POST['course_id'] ?? $_GET['course_id'] ?? '');
if ($selectedCourseId === '' && count($courses) > 0) {
    $selectedCourseId = $courses[0]['Course_ID'];
}
if ($selectedCourseId !== '' && !in_array($selectedCourseId, $enrolledCourseIds, true)) {
    $selectedCourseId = '';
    $error = '你無權存取此課程。';
}

$selectedAssignId = trim($_POST['assign_id'] ?? '');

// Handle AJAX requests for tab content
if ($selectedCourseId !== '' && isset($_GET['fetch_data']) && $_GET['fetch_data'] === 'true') {
    // 關閉錯誤顯示以防干擾 JSON 輸出
    error_reporting(0);
    header('Content-Type: application/json');
    $response = [
        'announcements_html' => '',
        'assignments_html' => '',
        'grades_html' => ''
    ];

    ob_start();

    if ($activeInnerTab === 'announcements') {
        $annStmt = $conn->prepare("SELECT Announce_ID, Title, Content, Publish_Time, Update_Time FROM announcement WHERE Course_ID = :cid ORDER BY Publish_Time DESC");
        $annStmt->bindParam(':cid', $selectedCourseId);
        $annStmt->execute();
        $announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($announcements) === 0) {
            echo '<div class="alert alert-info">此課程目前沒有公告。</div>';
        } else {
            echo '<div class="list-group">';
            foreach ($announcements as $index => $ann) {
                $collapseId = 'collapse-ajax-' . $index;
                echo '<div class="list-group-item">';
                echo '<div class="d-flex justify-content-between">';
                echo '<strong>' . htmlspecialchars($ann['Title'], ENT_QUOTES, 'UTF-8') . '</strong>';
                echo '<span class="text-muted small">' . htmlspecialchars($ann['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
                echo '</div>';
                if (!empty($ann['Content'])) {
                    echo '<div class="mt-2">';
                    echo '<button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '">';
                    echo '查看內容';
                    echo '</button>';
                    echo '<div class="collapse" id="' . $collapseId . '">';
                    echo '<div class="card card-body" style="white-space: pre-wrap;">';
                    echo nl2br(htmlspecialchars($ann['Content'], ENT_QUOTES, 'UTF-8'));
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        $response['announcements_html'] = ob_get_clean();
    } elseif ($activeInnerTab === 'assignments') {
        $asgStmt = $conn->prepare("SELECT a.Assign_ID, a.Title, a.Description, a.Due_Date, s.Submit_ID, s.Submit_Time, s.Score, s.Comment FROM assignment a LEFT JOIN submission s ON s.Assign_ID = a.Assign_ID AND s.Student_ID = :sid WHERE a.Course_ID = :cid ORDER BY a.Due_Date ASC");
        $asgStmt->bindParam(':sid', $studentId);
        $asgStmt->bindParam(':cid', $selectedCourseId);
        $asgStmt->execute();
        $assignments = $asgStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($assignments) === 0) {
            echo '<div class="alert alert-warning">此課程目前沒有作業可上傳。</div>';
        } else {
            // Render assignment upload form and table
            // This part needs to be dynamically generated or loaded via separate AJAX if complex
            // For simplicity, let's just render the table for now
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped">';
            echo '<thead><tr><th>作業</th><th>截止日</th><th>狀態</th><th>已繳交時間</th></tr></thead>';
            echo '<tbody>';
            foreach ($assignments as $a) {
                $dueTs = strtotime((string)$a['Due_Date']);
                $isOverdue = $dueTs !== false ? (time() > $dueTs) : false;
                echo '<tr>';
                echo '<td><a href="student_assignment_detail.php?assign_id=' . urlencode($a['Assign_ID']) . '&course_id=' . urlencode($selectedCourseId) . '">' . htmlspecialchars($a['Title'], ENT_QUOTES, 'UTF-8') . '</a></td>';
                echo '<td>' . htmlspecialchars($a['Due_Date'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . ($isOverdue ? '<span class="text-danger">已超期</span>' : '<span class="text-success">可上傳</span>') . '</td>';
                echo '<td>' . htmlspecialchars($a['Submit_Time'] ?? '尚未繳交', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        $response['assignments_html'] = ob_get_clean();
    } elseif ($activeInnerTab === 'grades') {
        $asgStmt = $conn->prepare("SELECT a.Assign_ID, a.Title, a.Description, a.Due_Date, s.Submit_ID, s.Submit_Time, s.Score, s.Comment FROM assignment a LEFT JOIN submission s ON s.Assign_ID = a.Assign_ID AND s.Student_ID = :sid WHERE a.Course_ID = :cid ORDER BY a.Due_Date ASC");
        $asgStmt->bindParam(':sid', $studentId);
        $asgStmt->bindParam(':cid', $selectedCourseId);
        $asgStmt->execute();
        $grades = $asgStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($grades) === 0) {
            echo '<div class="alert alert-info">此課程目前沒有成績資料。</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-bordered">';
            echo '<thead><tr><th>作業</th><th>分數</th><th>評語</th><th>最後繳交時間</th></tr></thead>';
            echo '<tbody>';
            foreach ($grades as $g) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($g['Title'], ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($g['Score'] !== null ? (string)$g['Score'] : '未評分', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($g['Comment'] ?? '—', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($g['Submit_Time'] ?? '尚未繳交', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        $response['grades_html'] = ob_get_clean();
    }

    echo json_encode($response);
    exit();
}

// Data fetching for full page load
$announcements = [];
$assignments = [];
$grades = [];


// Upload handling in "assignments" tab context

// Data fetching based on active tab - Moved after POST handling to ensure latest data
$announcements = [];
$assignments = [];
$grades = [];
$selectedAssignId = $selectedAssignId ?: trim($_POST['assign_id'] ?? '');

if ($selectedCourseId !== '') {
    if ($activeInnerTab === 'announcements') {
        $annStmt = $conn->prepare("
            SELECT Announce_ID, Title, Content, Publish_Time, Update_Time
            FROM announcement
            WHERE Course_ID = :cid
            ORDER BY Publish_Time DESC
        ");
        $annStmt->bindParam(':cid', $selectedCourseId);
        $annStmt->execute();
        $announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($activeInnerTab === 'assignments' || $activeInnerTab === 'grades') {
        $asgStmt = $conn->prepare("
            SELECT
                a.Assign_ID,
                a.Title,
                a.Description,
                a.Due_Date,
                s.Submit_ID,
                s.Submit_Time,
                s.Score,
                s.Comment
            FROM assignment a
            LEFT JOIN submission s ON s.Assign_ID = a.Assign_ID AND s.Student_ID = :sid
            WHERE a.Course_ID = :cid
            ORDER BY a.Due_Date ASC
        ");
        $asgStmt->bindParam(':sid', $studentId);
        $asgStmt->bindParam(':cid', $selectedCourseId);
        $asgStmt->execute();
        $assignments = $asgStmt->fetchAll(PDO::FETCH_ASSOC);
        $grades = $assignments;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>我的課程 - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            <h3 class="mb-0">我的課程</h3>
            <div class="text-muted">先選課程，再查看公告 / 作業 / 成績</div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="student_dashboard.php">返回</a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (count($courses) === 0): ?>
        <div class="alert alert-info">你目前沒有已加選課程。</div>
    <?php else: ?>
        <ul class="nav nav-tabs mb-3">
            <?php foreach ($courses as $course): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $selectedCourseId === $course['Course_ID'] ? 'active' : ''; ?>"
                       href="student_course.php?course_id=<?php echo urlencode($course['Course_ID']); ?>&tab=<?php echo urlencode($activeInnerTab); ?>">
                        <?php echo htmlspecialchars($course['Course_Name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($selectedCourseId !== ''): ?>
            <ul class="nav nav-pills mb-3" id="inner-tabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeInnerTab === 'announcements' ? 'active' : ''; ?>" href="#" data-tab="announcements">公告</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeInnerTab === 'assignments' ? 'active' : ''; ?>" href="#" data-tab="assignments">作業</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeInnerTab === 'grades' ? 'active' : ''; ?>" href="#" data-tab="grades">成績</a>
                </li>
            </ul>

            <div id="tab-content-container" class="tab-content">
                <!-- 修正1：所有的 tab-pane 加上 pt-3 統一間距 -->
                <div id="announcements-content" class="tab-pane fade pt-3 <?php echo $activeInnerTab === 'announcements' ? 'show active' : ''; ?>">
                    <?php if ($activeInnerTab === 'announcements'): ?>
                        <?php if (count($announcements) === 0): ?>
                            <div class="alert alert-info">此課程目前沒有公告。</div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($announcements as $index => $ann): ?>
                                    <?php $collapseId = 'collapse-' . $index; ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($ann['Title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="text-muted small"><?php echo htmlspecialchars($ann['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <?php if (!empty($ann['Content'])): ?>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                                                    查看內容
                                                </button>
                                                <div class="collapse" id="<?php echo $collapseId; ?>">
                                                    <div class="card card-body" style="white-space: pre-wrap;">
                                                        <?php echo nl2br(htmlspecialchars($ann['Content'], ENT_QUOTES, 'UTF-8')); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: // Content will be loaded via AJAX ?>
                        <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">載入中...</div></div>
                    <?php endif; ?>
                </div>

                <div id="assignments-content" class="tab-pane fade pt-3 <?php echo $activeInnerTab === 'assignments' ? 'show active' : ''; ?>">
                    <?php if ($activeInnerTab === 'assignments'): ?>
                        <?php if (count($assignments) === 0): ?>
                            <div class="alert alert-warning">此課程目前沒有作業可上傳。</div>
                        <?php else: ?>

                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>作業</th>
                                            <th>截止日</th>
                                            <th>狀態</th>
                                            <th>已繳交時間</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $a): ?>
                                            <?php
                                                $dueTs = strtotime((string)$a['Due_Date']);
                                                $isOverdue = $dueTs !== false ? (time() > $dueTs) : false;
                                            ?>
                                            <tr>
                                                <td>
                                                    <a href="student_assignment_detail.php?assign_id=<?php echo urlencode($a['Assign_ID']); ?>&course_id=<?php echo urlencode($selectedCourseId); ?>">
                                                        <?php echo htmlspecialchars($a['Title'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($a['Due_Date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo $isOverdue ? '<span class="text-danger">已超期</span>' : '<span class="text-success">可上傳</span>'; ?></td>
                                                <td><?php echo htmlspecialchars($a['Submit_Time'] ?? '尚未繳交', ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php else: // Content will be loaded via AJAX ?>
                        <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">載入中...</div></div>
                    <?php endif; ?>
                </div>

                <div id="grades-content" class="tab-pane fade pt-3 <?php echo $activeInnerTab === 'grades' ? 'show active' : ''; ?>">
                    <?php if ($activeInnerTab === 'grades'): ?>
                        <?php if (count($grades) === 0): ?>
                            <div class="alert alert-info">此課程目前沒有成績資料。</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>作業</th>
                                            <th>分數</th>
                                            <th>評語</th>
                                            <th>最後繳交時間</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $g): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($g['Title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($g['Score'] !== null ? (string)$g['Score'] : '未評分', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($g['Comment'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($g['Submit_Time'] ?? '尚未繳交', ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php else: // Content will be loaded via AJAX ?>
                        <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">載入中...</div></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const innerTabs = document.getElementById('inner-tabs');
    const tabContentContainer = document.getElementById('tab-content-container');

    if (!innerTabs || !tabContentContainer) return;

    innerTabs.addEventListener('click', function(event) {
        const target = event.target;
        if (target.tagName === 'A' && target.dataset.tab) {
            event.preventDefault();

            const selectedTab = target.dataset.tab;
            const selectedCourseId = '<?php echo $selectedCourseId; ?>';

            // 更新樣式
            innerTabs.querySelectorAll(".nav-link").forEach(link => link.classList.remove('active'));
            target.classList.add('active');

            // 切換 Pane
            tabContentContainer.querySelectorAll(".tab-pane").forEach(pane => pane.classList.remove('show', 'active'));
            const targetContent = document.getElementById(selectedTab + '-content');

            if (targetContent) {
                console.log('Fetching data for tab:', selectedTab, 'Course:', selectedCourseId);
                targetContent.classList.add('show', 'active');
                targetContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">載入中...</div></div>';

                fetch(`student_course.php?course_id=${selectedCourseId}&tab=${selectedTab}&fetch_data=true`)
                    .then(response => {
                        console.log('Response received status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('JSON data parsed:', data);
                        // 修正2：移除重複包裹的 pt-3 及多餘的 alert，直接填入對應的 html
                        if (selectedTab === 'announcements') {
                            targetContent.innerHTML = data.announcements_html;
                        }
                        else if (selectedTab === 'assignments') {
                            targetContent.innerHTML = data.assignments_html;
                        }
                        else if (selectedTab === 'grades') {
                            targetContent.innerHTML = data.grades_html;
                        }
                    })
                    .catch(error => {
                        targetContent.innerHTML = '<div class="alert alert-danger">載入失敗。</div>';
                    });
            }
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>