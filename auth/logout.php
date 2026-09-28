<?php
/**
 * Logout Handler
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Unset all session values
$_SESSION = array();

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh session for flash message
session_start();
set_flash_message('info', 'You have been successfully logged out.');

header("Location: " . BASE_URL . "auth/login.php");
exit();
