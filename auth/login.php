<?php
/**
 * Login Page
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "pages/dashboard.php");
    exit();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Invalid CSRF token. Please try submitting again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regeneration of session ID for security
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            // Flash success message
            set_flash_message('success', 'Welcome back, ' . $user['name'] . '!');

            header("Location: " . BASE_URL . "pages/dashboard.php");
            exit();
        } else {
            $error = 'Invalid email address or password combination.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - <?php echo e(APP_NAME); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
      
      <div class="text-center mb-4">
        <div class="bg-primary text-white rounded-4 p-3 d-inline-flex align-items-center justify-content-center shadow mb-3" style="width: 60px; height: 60px;">
          <i class="bi bi-wallet2 fs-2"></i>
        </div>
        <h2 class="fw-bold"><?php echo e(APP_NAME); ?></h2>
        <p class="text-secondary">Take control of your personal finances</p>
      </div>

      <div class="card card-custom p-4 p-md-5 border-0 shadow-lg">
        
        <h4 class="fw-bold mb-3">Sign In</h4>
        
        <?php display_flash_messages(); ?>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <?php echo csrf_token_field(); ?>

          <div class="mb-3">
            <label for="email" class="form-label fw-medium">Email Address</label>
            <div class="input-group">
              <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
              <input type="email" class="form-control border-start-0" id="email" name="email" value="<?php echo e($email); ?>" placeholder="name@example.com" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <label for="password" class="form-label fw-medium mb-0">Password</label>
              <a href="<?php echo BASE_URL; ?>auth/forgot-password.php" class="text-decoration-none small text-primary">Forgot password?</a>
            </div>
            <div class="input-group mt-1">
              <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-lock"></i></span>
              <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required>
            </div>
          </div>

          <div class="mb-4 form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" checked>
            <label class="form-check-label text-secondary small" for="remember">Remember this device</label>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
            Sign In <i class="bi bi-arrow-right ms-1"></i>
          </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
          <span class="text-secondary small">Don't have an account?</span>
          <a href="<?php echo BASE_URL; ?>auth/register.php" class="text-decoration-none fw-semibold text-primary ms-1">Create Account</a>
        </div>

        <div class="mt-4 p-3 rounded-3 bg-body-tertiary border text-center small text-muted">
          <strong>Demo Credentials:</strong><br>
          Email: <code>demo@example.com</code> | Password: <code>Password123!</code>
        </div>

      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
