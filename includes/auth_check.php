<?php
/**
 * Auth Guard Check - Protects internal pages from unauthorized access
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'message' => 'Please sign in to access your expense tracker.'
    ];
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

// Fetch global current user array for header/footer components
require_once __DIR__ . '/functions.php';
$current_user = get_current_user_data($_SESSION['user_id']);

if (!$current_user) {
    // Session user no longer exists in DB
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$currency = $current_user['currency_preference'] ?? '$';
