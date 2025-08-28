<?php
require_once 'db.php'; // Ensures session_start()

function login($username, $password) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM kayttajat WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['rooli'];
        $_SESSION['student_id'] = $user['opiskelija_id'];
        $_SESSION['teacher_id'] = $user['opettaja_id'];
        error_log("Login successful for user: $username, role: {$user['rooli']}");
        return true;
    }
    error_log("Login failed for user: $username");
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isStudent() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function isTeacher() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

function restrictAccess($allowedRoles) {
    if (!isLoggedIn() || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        error_log("Access restricted for role: " . ($_SESSION['role'] ?? 'none'));
        header("Location: dashboard.php");
        exit();
    }
}
?>