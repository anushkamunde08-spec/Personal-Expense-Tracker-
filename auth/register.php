<?php
/**
 * Registration Page
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already authenticated
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "pages/dashboard.php");
    exit();
}

$error = '';
$name = '';
$email = '';
$currency_preference = '$';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $currency_preference = $_POST['currency_preference'] ?? '$';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security validation failed. Invalid CSRF token.';
    } elseif (empty($name) || empty($email) || empty($password)) {
        $error = 'All required fields must be filled in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters in length.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and confirmation password do not match.';
    } else {
        $pdo = getDBConnection();
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            // Hash password securely
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert user into database
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, currency_preference) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password, $currency_preference])) {
                $user_id = $pdo->lastInsertId();

                // Log the user in immediately
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;

                set_flash_message('success', 'Account created successfully! Welcome to your Personal Expense Tracker.');
                header("Location: " . BASE_URL . "pages/dashboard.php");
                exit();
            } else {
                $error = 'Failed to register account due to a database error. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - <?php echo e(APP_NAME); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light py-5">

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-9 col-lg-6">
      
      <div class="text-center mb-4">
        <div class="bg-primary text-white rounded-4 p-3 d-inline-flex align-items-center justify-content-center shadow mb-3" style="width: 60px; height: 60px;">
          <i class="bi bi-wallet2 fs-2"></i>
        </div>
        <h2 class="fw-bold"><?php echo e(APP_NAME); ?></h2>
        <p class="text-secondary">Start tracking your income, expenses, and budgets</p>
      </div>

      <div class="card card-custom p-4 p-md-5 border-0 shadow-lg">
        
        <h4 class="fw-bold mb-3">Create New Account</h4>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <?php echo csrf_token_field(); ?>

          <div class="mb-3">
            <label for="name" class="form-label fw-medium">Full Name</label>
            <div class="input-group">
              <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-person"></i></span>
              <input type="text" class="form-control border-start-0" id="name" name="name" value="<?php echo e($name); ?>" placeholder="John Doe" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label for="email" class="form-label fw-medium">Email Address</label>
            <div class="input-group">
              <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
              <input type="email" class="form-control border-start-0" id="email" name="email" value="<?php echo e($email); ?>" placeholder="name@example.com" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="password" class="form-label fw-medium">Password</label>
              <div class="input-group">
                <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="Min. 6 chars" required>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="confirm_password" class="form-label fw-medium">Confirm Password</label>
              <div class="input-group">
                <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-lock-fill"></i></span>
                <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label for="currency_preference" class="form-label fw-medium">Preferred Currency</label>
            <select class="form-select" id="currency_preference" name="currency_preference">
              <option value="$" <?php echo ($currency_preference === '$') ? 'selected' : ''; ?>>$ USD (US Dollar)</option>
              <option value="€" <?php echo ($currency_preference === '€') ? 'selected' : ''; ?>>€ EUR (Euro)</option>
              <option value="£" <?php echo ($currency_preference === '£') ? 'selected' : ''; ?>>£ GBP (British Pound)</option>
              <option value="₹" <?php echo ($currency_preference === '₹') ? 'selected' : ''; ?>>₹ INR (Indian Rupee)</option>
              <option value="¥" <?php echo ($currency_preference === '¥') ? 'selected' : ''; ?>>¥ JPY / CNY</option>
              <option value="A$" <?php echo ($currency_preference === 'A$') ? 'selected' : ''; ?>>A$ AUD (Australian Dollar)</option>
              <option value="C$" <?php echo ($currency_preference === 'C$') ? 'selected' : ''; ?>>C$ CAD (Canadian Dollar)</option>
            </select>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
            Create Account <i class="bi bi-check-lg ms-1"></i>
          </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
          <span class="text-secondary small">Already have an account?</span>
          <a href="<?php echo BASE_URL; ?>auth/login.php" class="text-decoration-none fw-semibold text-primary ms-1">Sign In</a>
        </div>

      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
