<?php
/**
 * Form Handler Helper
 * Implements POST-Redirect-GET pattern to prevent form resubmission
 */

function handleFormSubmission($action, $successMessage = '', $redirectUrl = null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === $action) {
        // Process the form submission here
        $result = processFormAction($action);
        
        if ($result['success']) {
            // Set success message in session
            if ($successMessage) {
                $_SESSION['success_message'] = $successMessage;
            }
            
            // Redirect to prevent form resubmission
            $redirectUrl = $redirectUrl ?: $_SERVER['PHP_SELF'];
            header("Location: $redirectUrl");
            exit();
        } else {
            // Set error message in session
            $_SESSION['error_message'] = $result['message'];
        }
    }
}

function processFormAction($action) {
    // This function should be implemented in each page
    // to handle the specific form action
    return ['success' => false, 'message' => 'Action not implemented'];
}

function getSessionMessage($type = 'success') {
    $key = $type . '_message';
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]); // Clear the message after displaying
        return $message;
    }
    return null;
}

function clearSessionMessages() {
    unset($_SESSION['success_message']);
    unset($_SESSION['error_message']);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
