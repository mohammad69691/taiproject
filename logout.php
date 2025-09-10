<?php
require_once 'config/auth.php';

// Logout user
logout();

// Redirect to login page
header('Location: login.php');
exit();
?>
