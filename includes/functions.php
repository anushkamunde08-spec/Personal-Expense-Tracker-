<?php
/**
 * Global Helper Functions & Utilities
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';

/**
 * XSS prevention helper - Escapes string for safe HTML output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF Token Management
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash Alert Messages (Session-based alerts)
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function display_flash_messages() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        $type = e($flash['type']);
        $icon = ($type === 'success') ? 'bi-check-circle-fill' : (($type === 'danger') ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');
        
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
            <i class="bi ' . $icon . ' me-2 fs-5"></i>
            <div>' . e($flash['message']) . '</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
}

/**
 * Currency Formatter
 */
function format_currency($amount, $symbol = '$') {
    $formatted = number_format((float)$amount, 2, '.', ',');
    return $symbol . ' ' . $formatted;
}

/**
 * Get User Info
 */
function get_current_user_data($user_id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name, email, profile_picture, currency_preference, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch() ?: null;
}

/**
 * Secure File Upload Handler
 */
function upload_file($file, $target_dir, $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error occurred.'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed limit (5MB).'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file format. Allowed: ' . implode(', ', $allowed_extensions)];
    }

    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf'
    ];

    if (!in_array($mime, array_values($allowed_mimes))) {
        return ['success' => false, 'error' => 'Security error: File content does not match allowed types.'];
    }

    // Generate safe randomized filename
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $target_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file to target folder.'];
    }

    return ['success' => true, 'filename' => $filename];
}

/**
 * Fetch Categories Helper
 */
function get_user_categories($user_id, $type = null) {
    $pdo = getDBConnection();
    if ($type) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE (user_id = ? OR user_id IS NULL) AND type = ? ORDER BY is_default DESC, name ASC");
        $stmt->execute([$user_id, $type]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE (user_id = ? OR user_id IS NULL) ORDER BY type ASC, is_default DESC, name ASC");
        $stmt->execute([$user_id]);
    }
    return $stmt->fetchAll();
}

/**
 * Auto Check Due Recurring Transactions & Alert Reminder
 */
function check_recurring_reminders($user_id) {
    $pdo = getDBConnection();
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name 
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ? AND t.is_recurring = 1
    ");
    $stmt->execute([$user_id]);
    $recurring = $stmt->fetchAll();
    
    $due_items = [];
    foreach ($recurring as $item) {
        // Simple logic for recurring reminders
        $last_date = $item['transaction_date'];
        $freq = $item['recurring_frequency'];
        
        $due = false;
        if ($freq === 'daily' && strtotime($last_date) < strtotime('-1 day')) $due = true;
        if ($freq === 'weekly' && strtotime($last_date) < strtotime('-7 days')) $due = true;
        if ($freq === 'monthly' && strtotime($last_date) < strtotime('-1 month')) $due = true;
        if ($freq === 'yearly' && strtotime($last_date) < strtotime('-1 year')) $due = true;
        
        if ($due) {
            $due_items[] = $item;
        }
    }
    return $due_items;
}
