<?php

function handleFormSubmission($action, $successMessage = '', $redirectUrl = null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === $action) {
        $result = processFormAction($action);
        
        if ($result['success']) {
            if ($successMessage) {
                $_SESSION['success_message'] = $successMessage;
            }
            
            $redirectUrl = $redirectUrl ?: $_SERVER['PHP_SELF'];
            header("Location: $redirectUrl");
            exit();
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    }
}

function processFormAction($action) {
    return ['success' => false, 'message' => 'Action not implemented'];
}

function getSessionMessage($type = 'success') {
    $key = $type . '_message';
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

function clearSessionMessages() {
    unset($_SESSION['success_message']);
    unset($_SESSION['error_message']);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
