<?php
session_start();

define('LOGIN_TIMEOUT', 3600); 

function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        return false;
    }
    
    if (time() - $_SESSION['last_activity'] > LOGIN_TIMEOUT) {
        logout();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}


function hasRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_array($requiredRole)) {
        return in_array($_SESSION['user_role'], $requiredRole);
    }
    
    return $_SESSION['user_role'] === $requiredRole;
}


function isAdmin() {
    return hasRole('admin');
}


function isTeacher() {
    return hasRole('opettaja');
}

function isStudent() {
    return hasRole('opiskelija');
}


function canManageCourses() {
    return hasRole(['admin', 'opettaja']);
}


function canEnrollStudents() {
    return isAdmin();
}

function canViewStudents() {
    return hasRole(['admin', 'opettaja']);
}


function canEditAll() {
    return isAdmin();
}


function canManageOwnCourses($courseTeacherId = null) {
    if (isAdmin()) {
        return true;
    }
    
    if (isTeacher() && $courseTeacherId) {
        return $_SESSION['teacher_id'] == $courseTeacherId;
    }
    
    return false;
}


function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}


function requireRole($requiredRole) {
    requireAuth();
    
    if (!hasRole($requiredRole)) {
        header('Location: access_denied.php');
        exit();
    }
}

function logout() {
    session_unset();
    session_destroy();
    session_start();
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}


function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}


function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}


function getCurrentTeacherId() {
    return $_SESSION['teacher_id'] ?? null;
}


function getCurrentStudentId() {
    return $_SESSION['student_id'] ?? null;
}
?>
