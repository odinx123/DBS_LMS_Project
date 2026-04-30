<?php
// Session-based access control helpers

// IMPORTANT: auth.php assumes db.php defines $conn (PDO).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function require_student(): void
{
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        header('Location: index.php');
        exit();
    }
}

function require_teacher(): void
{
    $conn = $GLOBALS['conn'] ?? null;

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
        header('Location: index.php');
        exit();
    }

    $teacherId = $_SESSION['userid'] ?? null;
    if (!$teacherId) {
        header('Location: index.php');
        exit();
    }

    if (!($conn instanceof PDO)) {
        header('Location: index.php');
        exit();
    }

    // Second-factor DB check to ensure teacher_id exists and role is valid.
    $stmt = $conn->prepare("SELECT Role FROM teacher WHERE Teacher_ID = :tid");
    $stmt->bindParam(':tid', $teacherId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !in_array($row['Role'], ['teacher', 'ta'], true)) {
        header('Location: index.php');
        exit();
    }

    $_SESSION['teacher_role'] = $row['Role'];
}

