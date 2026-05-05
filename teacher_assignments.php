<?php
require_once __DIR__ . '/auth.php';
require_teacher();

$teacherId = $_SESSION['userid'];
$error = '';
$success = '';

function generateId(string $prefix): string
{
    return $prefix . bin2hex(random_bytes(8));
}

// Load teacher courses for dropdown
$coursesStmt = $conn->prepare("SELECT Course_ID, Course_Name FROM course WHERE Teacher_ID = :tid ORDER BY Course_Name");
$coursesStmt->bindParam(':tid', $teacherId);
$coursesStmt->execute();
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$editId = $_GET['id'] ?? '';
$formCourseId = '';
$formTitle = '';
$formDescription = '';
$formDueDate = '';

if ($editId !== '') {
    $stmt = $conn->prepare("
        SELECT a.Assign_ID, a.Course_ID, a.Title, a.Description, a.Due_Date
        FROM assignment a
        JOIN course c ON a.Course_ID = c.Course_ID
        WHERE a.Assign_ID = :aid AND c.Teacher_ID = :tid
        LIMIT 1
    ");
    $stmt->bindParam(':aid', $editId);
    $stmt->bindParam(':tid', $teacherId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $formCourseId = $row['Course_ID'];
        $formTitle = $row['Title'] ?? '';
        $formDescription = $row['Description'] ?? '';
        $formDueDate = $row['Due_Date'] ?? '';

        // Convert "YYYY-MM-DD HH:MM:SS" to "YYYY-MM-DDTHH:MM" for datetime-local input.
        if (is_string($formDueDate) && strlen($formDueDate) >= 16) {
            $formDueDate = substr($formDueDate, 0, 16);
            $formDueDate = str_replace(' ', 'T', $formDueDate);
        } else {
            $formDueDate = '';
        }
    } else {
        $error = '找不到此作業，或不屬於你的課程。';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save') {
    $announceId = trim($_POST['assign_id'] ?? '');
    $actionAssignId = $announceId;
    $courseId = trim($_POST['course_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueRaw = trim($_POST['due_date'] ?? '');

    if ($courseId === '' || $title === '' || $dueRaw === '') {
        $error = '請填寫完整欄位（課程 / 作業名稱 / 截止日）。';
    } else {
        $dueDate = str_replace('T', ' ', $dueRaw);

        $courseCheck = $conn->prepare("SELECT 1 FROM course WHERE Course_ID = :cid AND Teacher_ID = :tid LIMIT 1");
        $courseCheck->bindParam(':cid', $courseId);
        $courseCheck->bindParam(':tid', $teacherId);
        $courseCheck->execute();
        if (!$courseCheck->fetchColumn()) {
            $error = '所選課程不屬於你，請重新選擇。';
        } else {
            if ($actionAssignId === '') {
                $newId = generateId('asg_');
                $insert = $conn->prepare("
                    INSERT INTO assignment (Assign_ID, Course_ID, Teacher_ID, Title, Description, Due_Date)
                    VALUES (:aid, :cid, :tid, :title, :description, :due)
                ");
                $insert->bindParam(':aid', $newId);
                $insert->bindParam(':cid', $courseId);
                $insert->bindParam(':tid', $teacherId);
                $insert->bindParam(':title', $title);
                $insert->bindParam(':description', $description);
                $insert->bindParam(':due', $dueDate);
                $insert->execute();

                $success = '作業已建立。';
                $formCourseId = $courseId;
                $formTitle = $title;
                $formDescription = $description;
                $formDueDate = $dueRaw;
            } else {
                $update = $conn->prepare("
                    UPDATE assignment a
                    JOIN course c ON a.Course_ID = c.Course_ID
                    SET a.Course_ID = :cid,
                        a.Teacher_ID = :tid,
                        a.Title = :title,
                        a.Description = :description,
                        a.Due_Date = :due
                    WHERE a.Assign_ID = :aid AND c.Teacher_ID = :tid
                ");
                $update->bindParam(':aid', $actionAssignId);
                $update->bindParam(':cid', $courseId);
                $update->bindParam(':tid', $teacherId);
                $update->bindParam(':title', $title);
                $update->bindParam(':description', $description);
                $update->bindParam(':due', $dueDate);
                $update->execute();

                if ($update->rowCount() === 0) {
                    $error = '更新失敗：作業不存在或不屬於你的課程。';
                } else {
                    $success = '作業已更新。';
                }
                $formCourseId = $courseId;
                $formTitle = $title;
                $formDescription = $description;
                $formDueDate = $dueRaw;
            }

            if ($success !== '' && isset($_POST['publish_announcement'])) {
                $annId = generateId('ann_');
                $annTitle = '【新作業】' . $title;
                $annContent = "課程已發布新作業：\n名稱：" . $title . "\n說明：" . $description . "\n截止日期：" . $dueDate;
                $annInsert = $conn->prepare("
                    INSERT INTO announcement (Announce_ID, Course_ID, Teacher_ID, Title, Content)
                    VALUES (:aid, :cid, :tid, :title, :content)
                ");
                $annInsert->bindParam(':aid', $annId);
                $annInsert->bindParam(':cid', $courseId);
                $annInsert->bindParam(':tid', $teacherId);
                $annInsert->bindParam(':title', $annTitle);
                $annInsert->bindParam(':content', $annContent);
                $annInsert->execute();
            }
        }
    }
    } elseif ($action === 'delete') {
        $deleteId = trim($_POST['assign_id'] ?? '');
        if ($deleteId !== '') {
            $delStmt = $conn->prepare("
                DELETE a FROM assignment a
                JOIN course c ON a.Course_ID = c.Course_ID
                WHERE a.Assign_ID = :aid AND c.Teacher_ID = :tid
            ");
            $delStmt->bindParam(':aid', $deleteId);
            $delStmt->bindParam(':tid', $teacherId);
            $delStmt->execute();

            if ($delStmt->rowCount() > 0) {
                $success = '作業已成功刪除。';
            } else {
                $error = '刪除失敗：作業不存在或不屬於你的課程。';
            }
        }
    }
}

$filterCourseId = $_GET['filter_course_id'] ?? '';
$sql = "
    SELECT a.Assign_ID, a.Course_ID, c.Course_Name, a.Title, a.Description, a.Publish_Time, a.Due_Date
    FROM assignment a
    JOIN course c ON a.Course_ID = c.Course_ID
    WHERE c.Teacher_ID = :tid
";
if ($filterCourseId !== '') {
    $sql .= " AND a.Course_ID = :cid";
}
$sql .= " ORDER BY a.Due_Date ASC";

$listStmt = $conn->prepare($sql);
$listStmt->bindParam(':tid', $teacherId);
if ($filterCourseId !== '') {
    $listStmt->bindParam(':cid', $filterCourseId);
}
$listStmt->execute();
$assignments = $listStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>作業管理 - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
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
<div class="container" style="padding-top: 28px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">作業管理（截止日）</h3>
            <div class="text-muted">新增 / 編輯作業</div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="teacher_dashboard.php">返回</a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <?php echo $editId !== '' ? '編輯作業' : '新增作業'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="assign_id" value="<?php echo htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label class="form-label">課程</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">請選擇課程</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['Course_ID'], ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($formCourseId === $c['Course_ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['Course_Name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">作業名稱</label>
                            <input type="text" name="title" class="form-control" required
                                   value="<?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">作業說明</label>
                            <textarea name="description" class="form-control" rows="5"><?php echo htmlspecialchars($formDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">繳交期限</label>
                            <input type="datetime-local" name="due_date" class="form-control" required
                                    value="<?php echo htmlspecialchars($formDueDate, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="publish_announcement" class="form-check-input" id="publish_announcement">
                            <label class="form-check-label" for="publish_announcement">同步發布公告</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">儲存</button>

                        <?php if ($editId !== ''): ?>
                            <div class="mt-3">
                                <a class="btn btn-link p-0" href="teacher_assignments.php">取消編輯</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- 學期成績統計入口 -->
            <div class="card mt-3">
                <div class="card-header">學期成績統計</div>
                <div class="card-body">
                    <form action="teacher_course_grades.php" method="GET">
                        <div class="mb-3">
                            <label class="form-label">選擇課程</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">請選擇課程</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['Course_ID'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($c['Course_Name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-info w-100">查看學期成績統計</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span>作業列表</span>
                    <form method="GET" class="d-flex gap-2 align-items-center" style="margin:0;">
                        <select name="filter_course_id" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">全部課程</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['Course_ID'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo ($filterCourseId === $c['Course_ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['Course_Name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>課程</th>
                                    <th>作業</th>
                                    <th>截止日</th>
                                    <th style="width: 140px;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($assignments) === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">尚無作業</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $a): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($a['Course_Name'] ?? $a['Course_ID'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($a['Title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($a['Due_Date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="d-flex gap-1">
                                            <a class="btn btn-outline-primary btn-sm" href="teacher_assignments.php?id=<?php echo urlencode($a['Assign_ID']); ?>">編輯</a>
                                            <a class="btn btn-outline-success btn-sm" href="teacher_assignment_submissions.php?id=<?php echo urlencode($a['Assign_ID']); ?>">管理繳交</a>
                                            <form method="POST" style="margin:0;" onsubmit="return confirm('確定要刪除此作業嗎？');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="assign_id" value="<?php echo htmlspecialchars($a['Assign_ID'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">刪除</button>
                                            </form>
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
    </div>
</div>
</body>
</html>

