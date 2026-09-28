<?php
/**
 * User Profile Management
 * Personal Expense Tracker
 */

$page_title = 'User Profile';
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'Security validation failed.');
        header("Location: " . BASE_URL . "pages/profile.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    // UPDATE PROFILE INFO & AVATAR
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash_message('danger', 'Please enter a valid name and email address.');
        } else {
            // Check if email taken by another user
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $user_id]);
            if ($chk->fetch()) {
                set_flash_message('danger', 'Email address is already in use by another account.');
            } else {
                // Process profile picture upload if provided
                $avatar_filename = $current_user['profile_picture'];
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $upload = upload_file($_FILES['profile_picture'], AVATAR_UPLOAD_DIR, ['jpg', 'jpeg', 'png']);
                    if ($upload['success']) {
                        $avatar_filename = $upload['filename'];
                    } else {
                        set_flash_message('warning', 'Avatar upload notice: ' . $upload['error']);
                    }
                }

                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$name, $email, $avatar_filename, $user_id]);
                
                $_SESSION['user_name'] = $name;
                set_flash_message('success', 'Profile information updated successfully!');
            }
        }
        header("Location: " . BASE_URL . "pages/profile.php");
        exit();
    }

    // CHANGE PASSWORD
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Fetch stored password hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_row = $stmt->fetch();

        if (!password_verify($current_password, $user_row['password'])) {
            set_flash_message('danger', 'Current password entered is incorrect.');
        } elseif (strlen($new_password) < 6) {
            set_flash_message('danger', 'New password must be at least 6 characters long.');
        } elseif ($new_password !== $confirm_password) {
            set_flash_message('danger', 'New password confirmation does not match.');
        } else {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $upStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upStmt->execute([$new_hash, $user_id]);
            set_flash_message('success', 'Password changed successfully!');
        }
        header("Location: " . BASE_URL . "pages/profile.php");
        exit();
    }
}

// Refresh User Data
$current_user = get_current_user_data($user_id);
$avatar_url = !empty($current_user['profile_picture']) && file_exists(AVATAR_UPLOAD_DIR . $current_user['profile_picture']) 
  ? BASE_URL . 'uploads/avatars/' . e($current_user['profile_picture']) 
  : 'https://ui-avatars.com/api/?name=' . urlencode($current_user['name']) . '&background=4f46e5&color=fff';
?>

<div class="row g-4 justify-content-center">
  
  <!-- Profile Summary Card -->
  <div class="col-12 col-md-4">
    <div class="card card-custom p-4 text-center">
      <div class="mb-3">
        <img src="<?php echo $avatar_url; ?>" alt="Profile Picture" class="rounded-circle border shadow-sm" width="110" height="110" style="object-fit: cover;">
      </div>
      <h5 class="fw-bold mb-1"><?php echo e($current_user['name']); ?></h5>
      <p class="text-muted small mb-2"><?php echo e($current_user['email']); ?></p>
      <span class="badge bg-primary-subtle text-primary mb-3">Member since <?php echo date('M Y', strtotime($current_user['created_at'])); ?></span>
    </div>
  </div>

  <!-- Profile Edit & Password Forms -->
  <div class="col-12 col-md-8">
    
    <!-- Profile Info Form -->
    <div class="card card-custom p-4 mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-person-gear me-2"></i>Personal Details</h5>
      
      <form method="POST" action="" enctype="multipart/form-data">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="mb-3">
          <label for="name" class="form-label fw-medium">Full Name</label>
          <input type="text" class="form-control" id="name" name="name" value="<?php echo e($current_user['name']); ?>" required>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label fw-medium">Email Address</label>
          <input type="email" class="form-control" id="email" name="email" value="<?php echo e($current_user['email']); ?>" required>
        </div>

        <div class="mb-4">
          <label for="profile_picture" class="form-label fw-medium">Change Profile Picture</label>
          <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png">
          <small class="text-muted">Allowed formats: JPG, PNG (Max 5MB)</small>
        </div>

        <button type="submit" class="btn btn-primary fw-semibold">Save Profile Changes</button>
      </form>
    </div>

    <!-- Security & Password Change Form -->
    <div class="card card-custom p-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2"></i>Security &amp; Password</h5>
      
      <form method="POST" action="">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="action" value="change_password">

        <div class="mb-3">
          <label for="current_password" class="form-label fw-medium">Current Password</label>
          <input type="password" class="form-control" id="current_password" name="current_password" required>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="new_password" class="form-label fw-medium">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Min 6 chars" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="confirm_password" class="form-label fw-medium">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required>
          </div>
        </div>

        <button type="submit" class="btn btn-outline-primary fw-semibold">Update Password</button>
      </form>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
