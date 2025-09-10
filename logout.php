<?php
require_once 'config/auth.php';

logout();

header('Location: login.php');
exit();
?>
