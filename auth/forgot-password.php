<?php
/**
 * Forgot Password Handler
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$generated_link = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Invalid CSRF token.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Remove existing reset tokens for user
            $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $delStmt->execute([$user['id']]);

            // Insert new token
            $insStmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $insStmt->execute([$user['id'], $token, $expires]);

            $generated_link = BASE_URL . 'auth/reset-password.php?token=' . $token;
            $message = 'Password reset request processed for <strong>' . e($email) . '</strong>.';
        } else {
            // Friendly message without leaking user existence
            $message = 'If an account exists for <strong>' . e($email) . '</strong>, a password reset link has been generated below.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - <?php echo e(APP_NAME); ?></title>
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
          <i class="bi bi-key-fill fs-2"></i>
        </div>
        <h2 class="fw-bold"><?php echo e(APP_NAME); ?></h2>
        <p class="text-secondary">Reset your account password</p>
      </div>

      <div class="card card-custom p-4 p-md-5 border-0 shadow-lg">
        
        <h4 class="fw-bold mb-3">Forgot Password</h4>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
          <div class="alert alert-success" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
          </div>

          <?php if (!empty($generated_link)): ?>
            <div class="p-3 bg-body-tertiary border rounded-3 mb-4">
              <label class="form-label fw-bold text-success small mb-1"><i class="bi bi-envelope-open me-1"></i> Simulated Email Dispatch (Localhost XAMPP Mode):</label>
              <p class="small text-muted mb-2">Click the button below to proceed to the password reset screen:</p>
              <a href="<?php echo e($generated_link); ?>" class="btn btn-sm btn-success w-100 mb-2">
                <i class="bi bi-link-45deg me-1"></i> Open Password Reset Link
              </a>
              <input type="text" class="form-control form-control-sm text-muted" value="<?php echo e($generated_link); ?>" readonly onclick="this.select();">
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="">
          <?php echo csrf_token_field(); ?>

          <div class="mb-4">
            <label for="email" class="form-label fw-medium">Enter your registered email address</label>
            <div class="input-group">
              <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
              <input type="email" class="form-control border-start-0" id="email" name="email" placeholder="name@example.com" required autofocus>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
            Generate Reset Link <i class="bi bi-send ms-1"></i>
          </button>
        </form>

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
