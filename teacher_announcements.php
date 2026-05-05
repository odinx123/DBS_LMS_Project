<?php
require_once __DIR__ . '/auth.php';
require_teacher();
require_once __DIR__ . '/mail.php';

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
$formContent = '';

if ($editId !== '') {
    // Verify announcement belongs to teacher via course.Teacher_ID
    $stmt = $conn->prepare("
        SELECT a.Announce_ID, a.Course_ID, a.Title, a.Content
        FROM announcement a
        JOIN course c ON a.Course_ID = c.Course_ID
        WHERE a.Announce_ID = :aid AND c.Teacher_ID = :tid
        LIMIT 1
    ");
    $stmt->bindParam(':aid', $editId);
    $stmt->bindParam(':tid', $teacherId);
    $stmt->execute();
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ann) {
        $formCourseId = $ann['Course_ID'];
        $formTitle = $ann['Title'] ?? '';
        $formContent = $ann['Content'] ?? '';
    } else {
        $error = '找不到此公告，或不屬於你的課程。';
    }
}

// Handle send email action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_announce_id'])) {
    $sendAnnounceId = trim($_POST['send_announce_id']);
    $stmt = $conn->prepare("
        SELECT a.Announce_ID, a.Course_ID, a.Title, a.Content
        FROM announcement a
        JOIN course c ON a.Course_ID = c.Course_ID
        WHERE a.Announce_ID = :aid AND c.Teacher_ID = :tid
        LIMIT 1
    ");
    $stmt->bindParam(':aid', $sendAnnounceId);
    $stmt->bindParam(':tid', $teacherId);
    $stmt->execute();
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ann) {
        $error = '無法送信：公告不存在或不屬於你的課程。';
    } else {
        $recipientsStmt = $conn->prepare("
            SELECT s.Email
            FROM enrollment e
            JOIN student s ON e.Student_ID = s.Student_ID
            WHERE e.Course_ID = :cid AND s.Email IS NOT NULL AND s.Email <> ''
        ");
        $recipientsStmt->bindParam(':cid', $ann['Course_ID']);
        $recipientsStmt->execute();
        $recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);

        $sentCount = 0;
        foreach ($recipients as $r) {
            $to = $r['Email'];
            try {
                sendMail($to, '【LMS 公告】' . $ann['Title'], (string)$ann['Content']);
                $sentCount++;
            } catch (Throwable $e) {
                // Stop early on SMTP/PHPMailer errors, but keep message for debugging
                $error = '送信失敗：' . $e->getMessage();
                break;
            }
        }

        if ($error === '') {
            $success = '送信完成：已送出 ' . (string)$sentCount . ' 封。';
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save') {
    $action = $_POST['action'];
    $announceId = trim($_POST['announce_id'] ?? '');
    $courseId = trim($_POST['course_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $autoPublish = isset($_POST['auto_publish']);

    if ($courseId === '' || $title === '' || $content === '') {
        $error = '請填寫完整欄位（課程 / 標題 / 內容）。';
    } else {
        // Verify the course belongs to this teacher
        $courseCheck = $conn->prepare("SELECT 1 FROM course WHERE Course_ID = :cid AND Teacher_ID = :tid LIMIT 1");
        $courseCheck->bindParam(':cid', $courseId);
        $courseCheck->bindParam(':tid', $teacherId);
        $courseCheck->execute();
        if (!$courseCheck->fetchColumn()) {
            $error = '所選課程不屬於你，請重新選擇。';
        } else {
            $finalAnnounceId = $announceId;
            if ($announceId === '') {
                // Create
                $finalAnnounceId = generateId('ann_');
                $insert = $conn->prepare("
                    INSERT INTO announcement (Announce_ID, Course_ID, Teacher_ID, Title, Content)
                    VALUES (:aid, :cid, :tid, :title, :content)
                ");
                $insert->bindParam(':aid', $finalAnnounceId);
                $insert->bindParam(':cid', $courseId);
                $insert->bindParam(':tid', $teacherId);
                $insert->bindParam(':title', $title);
                $insert->bindParam(':content', $content);
                $insert->execute();
                $success = '公告已建立。';
            } else {
                // Update: ensure announcement belongs to teacher via course join
                $update = $conn->prepare("
                    UPDATE announcement a
                    JOIN course c ON a.Course_ID = c.Course_ID
                    SET a.Course_ID = :cid,
                        a.Teacher_ID = :tid,
                        a.Title = :title,
                        a.Content = :content
                    WHERE a.Announce_ID = :aid AND c.Teacher_ID = :tid
                ");
                $update->bindParam(':aid', $finalAnnounceId);
                $update->bindParam(':cid', $courseId);
                $update->bindParam(':tid', $teacherId);
                $update->bindParam(':title', $title);
                $update->bindParam(':content', $content);
                $update->execute();

                if ($update->rowCount() === 0) {
                    $error = '更新失敗：公告不存在或不屬於你的課程。';
                } else {
                    $success = '公告已更新。';
                }
            }

            if ($error === '') {
                $formCourseId = $courseId;
                $formTitle = $title;
                $formContent = $content;

                // Auto publish logic: send email if checked
                if ($autoPublish) {
                    $recipientsStmt = $conn->prepare("
                        SELECT s.Email
                        FROM enrollment e
                        JOIN student s ON e.Student_ID = s.Student_ID
                        WHERE e.Course_ID = :cid AND s.Email IS NOT NULL AND s.Email <> ''
                    ");
                    $recipientsStmt->bindParam(':cid', $courseId);
                    $recipientsStmt->execute();
                    $recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);

                    $sentCount = 0;
                    $mailError = '';
                    foreach ($recipients as $r) {
                        try {
                            sendMail($r['Email'], '【LMS 公告】' . $title, (string)$content);
                            $sentCount++;
                        } catch (Throwable $e) {
                            $mailError = '發信過程中發生錯誤：' . $e->getMessage();
                            break;
                        }
                    }
                    if ($mailError === '') {
                        $success .= " 並已自動發送郵件給 {$sentCount} 位學生。";
                    } else {
                        $error = $mailError;
                    }
                }
            }
        }
    }
    } elseif ($action === 'delete') {
        $deleteId = trim($_POST['announce_id'] ?? '');
        if ($deleteId !== '') {
            $delStmt = $conn->prepare("
                DELETE a FROM announcement a
                JOIN course c ON a.Course_ID = c.Course_ID
                WHERE a.Announce_ID = :aid AND c.Teacher_ID = :tid
            ");
            $delStmt->bindParam(':aid', $deleteId);
            $delStmt->bindParam(':tid', $teacherId);
            $delStmt->execute();

            if ($delStmt->rowCount() > 0) {
                $success = '公告已成功刪除。';
            } else {
                $error = '刪除失敗：公告不存在或不屬於你的課程。';
            }
        }
    }
}

// Fetch announcements list for teacher
$filterCourseId = $_GET['filter_course_id'] ?? '';
$sql = "
    SELECT a.Announce_ID, a.Course_ID, c.Course_Name, a.Title, a.Content, a.Publish_Time, a.Update_Time
    FROM announcement a
    JOIN course c ON a.Course_ID = c.Course_ID
    WHERE c.Teacher_ID = :tid
";
if ($filterCourseId !== '') {
    $sql .= " AND a.Course_ID = :cid";
}
$sql .= " ORDER BY a.Publish_Time DESC";

$annStmt = $conn->prepare($sql);
$annStmt->bindParam(':tid', $teacherId);
if ($filterCourseId !== '') {
    $annStmt->bindParam(':cid', $filterCourseId);
}
$annStmt->execute();
$announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>公告管理 - LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container" style="padding-top: 28px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">公告管理</h3>
            <div class="text-muted">Teacher/TA：新增 / 編輯 / 送信</div>
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
                    <?php echo $editId !== '' ? '編輯公告' : '新增公告'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="announce_id" value="<?php echo htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>">

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
                            <label class="form-label">標題</label>
                            <input type="text" name="title" class="form-control" required
                                   value="<?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">內容</label>
                            <textarea name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($formContent, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="auto_publish" class="form-check-input" id="auto_publish">
                            <label class="form-check-label" for="auto_publish">儲存後自動發布 (發送郵件給所有學生)</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">儲存</button>
                    </form>
                    <?php if ($editId !== ''): ?>
                        <div class="mt-3">
                            <a class="btn btn-link p-0" href="teacher_announcements.php">取消編輯</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span>公告列表</span>
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
                                    <th>標題</th>
                                    <th>發布</th>
                                    <th style="width: 180px;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($announcements) === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">尚無公告</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $a): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($a['Course_Name'] ?? $a['Course_ID'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($a['Title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($a['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a class="btn btn-outline-primary btn-sm" href="teacher_announcements.php?id=<?php echo urlencode($a['Announce_ID']); ?>">編輯</a>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="send_announce_id" value="<?php echo htmlspecialchars($a['Announce_ID'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="btn btn-outline-success btn-sm">送信</button>
                                                </form>
                                                <form method="POST" style="margin:0;" onsubmit="return confirm('確定要刪除此公告嗎？');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="announce_id" value="<?php echo htmlspecialchars($a['Announce_ID'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">刪除</button>
                                                </form>
                                            </div>
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

