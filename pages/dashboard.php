<?php
require_once '../includes/header.php';
?>
<h2>Welcome, <?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'User'; ?>!</h2>
<p>Select an option from the sidebar to proceed.</p>
<?php require_once '../includes/footer.php'; ?>