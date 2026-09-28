<?php
/**
 * Password Reset Execution Page
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;

$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT pr.*, u.email 
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.token = ? AND pr.expires_at > NOW()
");
$stmt->execute([$token]);
$reset_record = $stmt->fetch();

if (!$reset_record) {
    $error = 'This password reset link is invalid or has expired. Please request a new link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset_record) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Invalid CSRF token.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters in length.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Hash new password
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        // Update user password
        $upStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upStmt->execute([$hashed, $reset_record['user_id']]);

        // Remove token
        $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $delStmt->execute([$reset_record['user_id']]);

        set_flash_message('success', 'Your password has been updated successfully! Please sign in with your new password.');
        header("Location: " . BASE_URL . "auth/login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - <?php echo e(APP_NAME); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light py-5">

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
      
      <div class="text-center mb-4">
        <div class="bg-primary text-white rounded-4 p-3 d-inline-flex align-items-center justify-content-center shadow mb-3" style="width: 60px; height: 60px;">
          <i class="bi bi-shield-lock-fill fs-2"></i>
        </div>
        <h2 class="fw-bold"><?php echo e(APP_NAME); ?></h2>
        <p class="text-secondary">Set a new password for <?php echo e($reset_record['email'] ?? 'your account'); ?></p>
      </div>

      <div class="card card-custom p-4 p-md-5 border-0 shadow-lg">
        
        <h4 class="fw-bold mb-3">Reset Password</h4>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
          </div>
          <?php if (!$reset_record): ?>
            <a href="<?php echo BASE_URL; ?>auth/forgot-password.php" class="btn btn-primary w-100 mt-2">Request New Reset Link</a>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($reset_record): ?>
          <form method="POST" action="">
            <?php echo csrf_token_field(); ?>

            <div class="mb-3">
              <label for="password" class="form-label fw-medium">New Password</label>
              <div class="input-group">
                <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="At least 6 characters" required autofocus>
              </div>
            </div>

            <div class="mb-4">
              <label for="confirm_password" class="form-label fw-medium">Confirm New Password</label>
              <div class="input-group">
                <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-lock-fill"></i></span>
                <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
              Update Password <i class="bi bi-check2-circle ms-1"></i>
            </button>
          </form>
        <?php endif; ?>

        <div class="text-center mt-4 pt-3 border-top">
          <a href="<?php echo BASE_URL; ?>auth/login.php" class="text-decoration-none fw-semibold text-primary">
            <i class="bi bi-arrow-left me-1"></i> Back to Sign In
          </a>
        </div>

      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
