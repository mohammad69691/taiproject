<?php
require_once 'auth.php';
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oppilaitos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; color: #212529; }
        .sidebar { background-color: #fff; border-right: 1px solid #dee2e6; }
        .nav-link { color: #0d6efd; }
        .nav-link:hover { background-color: #e9ecef; }
        .warning-icon { color: #dc3545; font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar p-3" style="width: 250px; height: 100vh;">
            <h4>Oppilaitos</h4>
            <ul class="nav flex-column">
                <?php if (isAdmin()): ?>
                    <li class="nav-item"><a class="nav-link" href="students.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="teachers.php">Teachers</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link" href="rooms.php">Rooms</a></li>
                <?php elseif (isTeacher()): ?>
                    <li class="nav-item"><a class="nav-link" href="courses.php">My Courses</a></li>
                <?php elseif (isStudent()): ?>
                    <li class="nav-item"><a class="nav-link" href="students.php">My Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php">My Courses</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="timetable.php">Timetable</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="content p-4 w-100">