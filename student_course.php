<?php
require_once __DIR__ . '/auth.php';
require_student();

$studentId = $_SESSION['userid'];
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
    header('Content-Type: application/json');
    $response = [
        'announcements_html' => '',
        'assignments_html' => '',
        'grades_html' => ''
    ];

    ob_start(); // Start output buffering

    if ($activeInnerTab === 'announcements') {
        $annStmt = $conn->prepare("SELECT Announce_ID, Title, Content, Publish_Time, Update_Time FROM announcement WHERE Course_ID = :cid ORDER BY Publish_Time DESC");
        $annStmt->bindParam(':cid', $selectedCourseId);
        $annStmt->execute();
        $announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($announcements) === 0) {
            echo '<div class="alert alert-info">此課程目前沒有公告。</div>';
        } else {
            echo '<div class="list-group">';
            foreach ($announcements as $ann) {
                echo '<div class="list-group-item">';
                echo '<div class="d-flex justify-content-between">';
                echo '<strong>' . htmlspecialchars($ann['Title'], ENT_QUOTES, 'UTF-8') . '</strong>';
                echo '<span class="text-muted small">' . htmlspecialchars($ann['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
                echo '</div>';
                echo '<div class="mt-2" style="white-space: pre-wrap;">' . nl2br(htmlspecialchars($ann['Content'] ?? '', ENT_QUOTES, 'UTF-8')) . '</div>';
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
                echo '<td>' . htmlspecialchars($a['Title'], ENT_QUOTES, 'UTF-8') . '</td>';
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

if ($selectedCourseId !== '') {

// Upload handling in "assignments" tab context
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_submit'])) {
    $activeInnerTab = 'assignments';
    $courseId = trim($_POST['course_id'] ?? '');
    $assignId = trim($_POST['assign_id'] ?? '');
    $file = $_FILES['submission_file'] ?? null;
    $selectedCourseId = $courseId;
    $selectedAssignId = $assignId;

    if ($courseId === '' || $assignId === '') {
        $error = '請先選擇作業再上傳。';
    } elseif (!in_array($courseId, $enrolledCourseIds, true)) {
        $error = '無法上傳：課程不在你的加選清單。';
    } else {
        $stmt = $conn->prepare("
            SELECT a.Assign_ID, a.Due_Date
            FROM assignment a
            JOIN enrollment e ON e.Course_ID = a.Course_ID
            WHERE a.Assign_ID = :aid
              AND a.Course_ID = :cid
              AND e.Student_ID = :sid
            LIMIT 1
        ");
        $stmt->bindParam(':aid', $assignId);
        $stmt->bindParam(':cid', $courseId);
        $stmt->bindParam(':sid', $studentId);
        $stmt->execute();
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            $error = '無法上傳：作業不存在或不屬於目前課程。';
        }
    }

    if ($error === '') {
        $dueTs = strtotime((string)$assignment['Due_Date']);
        if ($dueTs === false) {
            $error = '無法上傳：作業截止日資料異常。';
        } elseif (time() > $dueTs) {
            $error = '此作業已超過繳交期限，無法上傳。';
        } elseif (!$file || !isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            $error = '上傳失敗：請確認檔案並重試。';
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
                    $check = $conn->prepare("
                        SELECT Submit_ID
                        FROM submission
                        WHERE Assign_ID = :aid AND Student_ID = :sid
                        LIMIT 1
                    ");
                    $check->bindParam(':aid', $assignId);
                    $check->bindParam(':sid', $studentId);
                    $check->execute();
                    $existing = $check->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        $update = $conn->prepare("
                            UPDATE submission
                            SET File_Path = :fp,
                                Submit_Time = NOW(),
                                Score = NULL,
                                Comment = NULL
                            WHERE Submit_ID = :submitId
                        ");
                        $update->bindParam(':fp', $relativePath);
                        $update->bindParam(':submitId', $existing['Submit_ID']);
                        $update->execute();
                    } else {
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
                    }

                    $success = '上傳完成（已寫入/覆寫繳交紀錄）。';
                    $selectedAssignId = '';
                }
            }
        }
    }
}

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
</head>
<body>
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

            <div id="tab-content-container">
                <div id="announcements-content" class="tab-pane fade <?php echo $activeInnerTab === 'announcements' ? 'show active' : ''; ?>">
                    <?php if ($activeInnerTab === 'announcements'): // Only render if active, or it will be loaded via AJAX ?>
                        <?php if (count($announcements) === 0): ?>
                            <div class="alert alert-info">此課程目前沒有公告。</div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($announcements as $ann): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($ann['Title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="text-muted small"><?php echo htmlspecialchars($ann['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="mt-2" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($ann['Content'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div id="assignments-content" class="tab-pane fade <?php echo $activeInnerTab === 'assignments' ? 'show active' : ''; ?>">
                    <?php if ($activeInnerTab === 'assignments'): // Only render if active, or it will be loaded via AJAX ?>
                        <?php if (count($assignments) === 0): ?>
                            <div class="alert alert-warning">此課程目前沒有作業可上傳。</div>
                        <?php else: ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                                        <input type="hidden" name="tab" value="assignments">
                                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($selectedCourseId, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="col-md-7">
                                            <label class="form-label">作業</label>
                                            <select class="form-select" name="assign_id" required>
                                                <option value="">請選擇作業</option>
                                                <?php foreach ($assignments as $a): ?>
                                                    <?php
                                                        $label = $a['Title'] . '（截止：' . ($a['Due_Date'] ?? '-') . '）';
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($a['Assign_ID'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        <?php echo $selectedAssignId === $a['Assign_ID'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">上傳檔案</label>
                                            <input type="file" name="submission_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.txt,.png,.jpg,.jpeg" required>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="upload_submit" value="1" class="btn btn-primary">上傳作業</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

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
                                                <td><?php echo htmlspecialchars($a['Title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($a['Due_Date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo $isOverdue ? '<span class="text-danger">已超期</span>' : '<span class="text-success">可上傳</span>'; ?></td>
                                                <td><?php echo htmlspecialchars($a['Submit_Time'] ?? '尚未繳交', ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div id="grades-content" class="tab-pane fade <?php echo $activeInnerTab === 'grades' ? 'show active' : ''; ?>">
                    <?php if ($activeInnerTab === 'grades'): // Only render if active, or it will be loaded via AJAX ?>
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
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
    document.addEventListener(\'DOMContentLoaded\', function() {
        const innerTabs = document.getElementById(\'inner-tabs\
        const tabContentContainer = document.getElementById(\'tab-content-container\

        if (!innerTabs || !tabContentContainer) return;

        innerTabs.addEventListener(\'click\', function(event) {
            const target = event.target;
            if (target.tagName === \'A\' && target.dataset.tab) {
                event.preventDefault();

                const selectedTab = target.dataset.tab;
                const selectedCourseId = \'<?php echo htmlspecialchars($selectedCourseId, ENT_QUOTES, \'UTF-8\'); ?>\';

                // Update active tab styling
                innerTabs.querySelectorAll(\".nav-link\").forEach(link => {
                    link.classList.remove(\'active\');
                });
                target.classList.add(\'active\');

                // Hide all tab panes
                tabContentContainer.querySelectorAll(\".tab-pane\").forEach(pane => {
                    pane.classList.remove(\'show\', \'active\');
                });

                const targetContentId = selectedTab + \'-content\';
                const targetContent = document.getElementById(targetContentId);

                if (targetContent) {
                    targetContent.classList.add(\'show\', \'active\');
                    targetContent.innerHTML = \'<div class="text-center py-5">載入中...</div>\

                    fetch(\`student_course.php?course_id=${selectedCourseId}&tab=${selectedTab}&fetch_data=true\`)
                        .then(response => response.json())
                        .then(data => {
                            if (selectedTab === \'announcements\') {
                                targetContent.innerHTML = data.announcements_html;
                            } else if (selectedTab === \'assignments\') {
                                // For assignments, we need to consider the upload form as well
                                targetContent.innerHTML = \`<div class="alert alert-info">上傳作業功能需重新載入頁面，請<a href="student_course.php?course_id=${selectedCourseId}&tab=assignments">點此重新載入</a>。</div><hr/>${data.assignments_html}\
                            } else if (selectedTab === \'grades\') {
                                targetContent.innerHTML = data.grades_html;
                            }
                        })
                        .catch(error => {
                            console.error(\'Error fetching tab content:\', error);
                            targetContent.innerHTML = \'<div class="alert alert-danger">載入失敗，請稍後再試。</div>\
                        });
                }
            }
        });

        // Initial load for the active tab content via AJAX (if not already rendered by PHP)
        const initialActiveTab = \'<?php echo $activeInnerTab; ?>\';
        const initialSelectedCourseId = \'<?php echo htmlspecialchars($selectedCourseId, ENT_QUOTES, \'UTF-8\'); ?>\';
        const initialContentElement = document.getElementById(initialActiveTab + \'-content\

        // Only fetch if content is not already present (i.e., not rendered by PHP initially)
        if (initialContentElement && initialContentElement.innerHTML.trim() === \'\') {
             initialContentElement.innerHTML = \'<div class="text-center py-5">載入中...</div>\
             fetch(\`student_course.php?course_id=${initialSelectedCourseId}&tab=${initialActiveTab}&fetch_data=true\`)
                .then(response => response.json())
                .then(data => {
                    if (initialActiveTab === \'announcements\') {
                        initialContentElement.innerHTML = data.announcements_html;
                    } else if (initialActiveTab === \'assignments\') {
                        initialContentElement.innerHTML = \`<div class="alert alert-info">上傳作業功能需重新載入頁面，請<a href="student_course.php?course_id=${initialSelectedCourseId}&tab=assignments">點此重新載入</a>。</div><hr/>${data.assignments_html}\
                    } else if (initialActiveTab === \'grades\') {
                        initialContentElement.innerHTML = data.grades_html;
                    }
                })
                .catch(error => {
                    console.error(\'Error fetching initial tab content:\', error);
                    initialContentElement.innerHTML = \'<div class="alert alert-danger">載入失敗，請稍後再試。</div>\
                });
        }
    });
</script>
</body>
</html>

