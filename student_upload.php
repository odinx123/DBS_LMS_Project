<?php
require_once __DIR__ . '/auth.php';
require_student();

$studentId = $_SESSION['userid'];
$selectedCourseId = trim($_POST['course_id'] ?? $_GET['course_id'] ?? '');
$selectedAssignId = trim($_POST['assign_id'] ?? '');

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

// Load enrolled courses
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
if ($selectedCourseId !== '' && !in_array($selectedCourseId, $enrolledCourseIds, true)) {
    $selectedCourseId = '';
}

// Load assignments for selected course only
$assignments = [];
if ($selectedCourseId !== '') {
    $assignStmt = $conn->prepare("
        SELECT
            a.Assign_ID,
            a.Title,
            a.Description,
            a.Due_Date,
            s.Submit_ID,
            s.Submit_Time
        FROM assignment a
        JOIN enrollment e ON e.Course_ID = a.Course_ID
        LEFT JOIN submission s ON s.Assign_ID = a.Assign_ID AND s.Student_ID = :sid
        WHERE e.Student_ID = :sid AND a.Course_ID = :cid
        ORDER BY a.Due_Date ASC
    ");
    $assignStmt->bindParam(':sid', $studentId);
    $assignStmt->bindParam(':cid', $selectedCourseId);
    $assignStmt->execute();
    $assignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle upload (course + assignment + file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_submit'])) {
    $courseId = trim($_POST['course_id'] ?? '');
    $assignId = trim($_POST['assign_id'] ?? '');
    $file = $_FILES['submission_file'] ?? null;

    $selectedCourseId = $courseId;
    $selectedAssignId = $assignId;

    if ($courseId === '' || $assignId === '') {
        $error = '請先選擇課程與作業。';
    } else {
        // Validate selected course belongs to student enrollment
        $courseCheck = $conn->prepare("
            SELECT 1
            FROM enrollment
            WHERE Student_ID = :sid AND Course_ID = :cid
            LIMIT 1
        ");
        $courseCheck->bindParam(':sid', $studentId);
        $courseCheck->bindParam(':cid', $courseId);
        $courseCheck->execute();
        if (!$courseCheck->fetchColumn()) {
            $error = '無法上傳：課程不屬於你的選修清單。';
        }
    }

    if ($error === '') {
        // Validate assignment belongs to selected course and student enrollment
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
            $error = '無法上傳：作業不存在或不屬於你選擇的課程。';
        }
    }

    if ($error === '') {
        $dueDate = $assignment['Due_Date'] ?? '';
        $dueTs = $dueDate ? strtotime($dueDate) : false;

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
                    // Replace Previous: update existing submission, and reset Score/Comment to NULL
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
    // Refresh assignments after upload or failed post to keep list synced
    if ($selectedCourseId !== '' && in_array($selectedCourseId, $enrolledCourseIds, true)) {
        $assignStmt = $conn->prepare("
            SELECT
                a.Assign_ID,
                a.Title,
                a.Description,
                a.Due_Date,
                s.Submit_ID,
                s.Submit_Time
            FROM assignment a
            JOIN enrollment e ON e.Course_ID = a.Course_ID
            LEFT JOIN submission s ON s.Assign_ID = a.Assign_ID AND s.Student_ID = :sid
            WHERE e.Student_ID = :sid AND a.Course_ID = :cid
            ORDER BY a.Due_Date ASC
        ");
        $assignStmt->bindParam(':sid', $studentId);
        $assignStmt->bindParam(':cid', $selectedCourseId);
        $assignStmt->execute();
        $assignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>上傳作業 - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container" style="padding-top: 28px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">上傳作業</h3>
            <div class="text-muted">檢查截止日，並支援覆寫上一份繳交</div>
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
        <div class="alert alert-info">你目前沒有已選修課程，無法上傳作業。</div>
    <?php else: ?>
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">選擇課程</label>
                        <select class="form-select" name="course_id" required>
                            <option value="">請選擇課程</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['Course_ID'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo $selectedCourseId === $course['Course_ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['Course_Name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-outline-primary">載入課程作業</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedCourseId === ''): ?>
            <div class="alert alert-info">請先選擇課程，再選擇該課程作業進行上傳。</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($selectedCourseId, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="col-md-6">
                            <label class="form-label">課程</label>
                            <input type="text" class="form-control" readonly value="<?php
                                $selectedCourseName = '';
                                foreach ($courses as $course) {
                                    if ($course['Course_ID'] === $selectedCourseId) {
                                        $selectedCourseName = $course['Course_Name'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($selectedCourseName, ENT_QUOTES, 'UTF-8');
                            ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">作業</label>
                            <select class="form-select" name="assign_id" required>
                                <option value="">請選擇作業</option>
                                <?php foreach ($assignments as $a): ?>
                                    <?php
                                        $due = $a['Due_Date'] ?? '';
                                        $dueTs = $due ? strtotime($due) : false;
                                        $isOverdue = $dueTs !== false ? (time() > $dueTs) : false;
                                        $label = $a['Title'] . '（截止：' . ($a['Due_Date'] ?? '-') . '）';
                                        if ($isOverdue) {
                                            $label .= ' [已超期]';
                                        }
                                    ?>
                                    <option value="<?php echo htmlspecialchars($a['Assign_ID'], ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $selectedAssignId === $a['Assign_ID'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">上傳檔案</label>
                            <input type="file" name="submission_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.txt,.png,.jpg,.jpeg" required>
                            <div class="form-text">允許副檔名：pdf/doc/docx/ppt/pptx/zip/txt/png/jpg/jpeg，檔案上限 25MB。</div>
                        </div>

                        <div class="col-12">
                            <button type="submit" name="upload_submit" value="1" class="btn btn-primary">上傳作業</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (count($assignments) === 0): ?>
                <div class="alert alert-warning mt-3">此課程目前沒有可選作業。</div>
            <?php else: ?>
                <div class="card mt-3">
                    <div class="card-header">該課程作業狀態</div>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>作業名稱</th>
                                    <th>截止日</th>
                                    <th>狀態</th>
                                    <th>已繳交時間</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $a): ?>
                                    <?php
                                        $due = $a['Due_Date'] ?? '';
                                        $dueTs = $due ? strtotime($due) : false;
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
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>

