<?php
/**
 * Configuration & Auto-Initializing Database Connection
 * Personal Expense Tracker
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Database Credentials (XAMPP Default)
define('DB_HOST', 'localhost');
define('DB_NAME', 'expense_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Constants
define('APP_NAME', 'Personal Expense Tracker');
define('APP_VERSION', '1.0.0');

// Base URL calculation (autodetect scheme and subfolder path)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$base_path = preg_replace('#/(auth|pages|includes|uploads|assets|database).*$#i', '', dirname($script_name));
$base_url = rtrim($protocol . $host . $base_path, '/') . '/';
define('BASE_URL', $base_url);

// Upload Directories
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('RECEIPT_UPLOAD_DIR', UPLOAD_PATH . 'receipts/');
define('AVATAR_UPLOAD_DIR', UPLOAD_PATH . 'avatars/');

if (!file_exists(RECEIPT_UPLOAD_DIR)) {
    mkdir(RECEIPT_UPLOAD_DIR, 0755, true);
}
if (!file_exists(AVATAR_UPLOAD_DIR)) {
    mkdir(AVATAR_UPLOAD_DIR, 0755, true);
}

/**
 * Self-healing PDO Database Connection
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            // Attempt connection directly to target database
            $pdo = new PDO($dsn . ";dbname=" . DB_NAME, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Self-healing: if database does not exist, auto-create & import schema
            if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                try {
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `" . DB_NAME . "`");
                    
                    $schema_file = __DIR__ . '/../database/schema.sql';
                    if (file_exists($schema_file)) {
                        $sql = file_get_contents($schema_file);
                        $pdo->exec($sql);
                    }
                } catch (PDOException $ex) {
                    die("<div style='font-family:sans-serif; padding:2rem; background:#fff3f2; color:#a9201a; border-left:4px solid #f44336;'>
                        <h3>Database Initialization Error</h3>
                        <p>" . htmlspecialchars($ex->getMessage()) . "</p>
                    </div>");
                }
            } else {
                die("<div style='font-family:sans-serif; padding:2rem; background:#fff3f2; color:#a9201a; border-left:4px solid #f44336;'>
                    <h3>Database Connection Error</h3>
                    <p>Could not connect to MySQL server at <strong>" . DB_HOST . "</strong>.</p>
                    <p><small>" . htmlspecialchars($e->getMessage()) . "</small></p>
                </div>");
            }
        }
    }
    return $pdo;
}
