<?php
/**
 * Header Layout Component
 * Personal Expense Tracker
 */
require_once __DIR__ . '/auth_check.php';

// Determine active page for navigation highlight
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($page_title) ? e($page_title) . ' - ' . APP_NAME : APP_NAME; ?></title>
  
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- Custom App CSS -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div id="wrapper">

  <!-- Sidebar Navigation -->
  <nav id="sidebar">
    <div class="sidebar-header">
      <a href="<?php echo BASE_URL; ?>pages/dashboard.php" class="brand-title">
        <div class="bg-primary text-white rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
          <i class="bi bi-wallet2 fs-5"></i>
        </div>
        <span>Expense Tracker</span>
      </a>
    </div>

    <div class="sidebar-nav py-3 flex-grow-1">
      <a href="<?php echo BASE_URL; ?>pages/dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      
      <a href="<?php echo BASE_URL; ?>pages/expenses.php" class="nav-link <?php echo ($current_page === 'expenses') ? 'active' : ''; ?>">
        <i class="bi bi-arrow-left-right"></i>
        <span>Transactions</span>
      </a>
      
      <a href="<?php echo BASE_URL; ?>pages/categories.php" class="nav-link <?php echo ($current_page === 'categories') ? 'active' : ''; ?>">
        <i class="bi bi-tags-fill"></i>
        <span>Categories</span>
      </a>
      
      <a href="<?php echo BASE_URL; ?>pages/budgets.php" class="nav-link <?php echo ($current_page === 'budgets') ? 'active' : ''; ?>">
        <i class="bi bi-piggy-bank-fill"></i>
        <span>Budgets</span>
      </a>
      
      <a href="<?php echo BASE_URL; ?>pages/reports.php" class="nav-link <?php echo ($current_page === 'reports') ? 'active' : ''; ?>">
        <i class="bi bi-pie-chart-fill"></i>
        <span>Reports & Charts</span>
      </a>
      
      <a href="<?php echo BASE_URL; ?>pages/settings.php" class="nav-link <?php echo ($current_page === 'settings') ? 'active' : ''; ?>">
        <i class="bi bi-gear-fill"></i>
        <span>Settings</span>
      </a>
    </div>

    <div class="p-3 border-top border-secondary border-opacity-25">
      <a href="<?php echo BASE_URL; ?>auth/logout.php" class="nav-link text-danger m-0">
        <i class="bi bi-box-arrow-right"></i>
        <span>Sign Out</span>
      </a>
    </div>
  </nav>

  <!-- Main Content Wrapper -->
  <div id="content-wrapper">
    
    <!-- Topbar Navigation Header -->
    <header class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button type="button" id="sidebar-toggle-btn" class="btn btn-light d-lg-none shadow-sm">
          <i class="bi bi-list fs-5"></i>
        </button>
        <h4 class="m-0 fw-bold"><?php echo isset($page_title) ? e($page_title) : 'Dashboard'; ?></h4>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- Theme Toggle Button -->
        <button type="button" id="theme-toggle-btn" class="btn btn-outline-secondary btn-icon rounded-circle p-2 border-0" title="Toggle Light/Dark Theme">
          <i id="theme-toggle-icon" class="bi bi-moon-stars-fill fs-5"></i>
        </button>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
          <button class="btn btn-link text-decoration-none dropdown-toggle d-flex align-items-center gap-2 p-1 text-reset" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?php 
              $avatar = !empty($current_user['profile_picture']) && file_exists(AVATAR_UPLOAD_DIR . $current_user['profile_picture']) 
                ? BASE_URL . 'uploads/avatars/' . e($current_user['profile_picture']) 
                : 'https://ui-avatars.com/api/?name=' . urlencode($current_user['name']) . '&background=4f46e5&color=fff';
            ?>
            <img src="<?php echo $avatar; ?>" alt="User Avatar" class="rounded-circle border" width="36" height="36">
            <span class="d-none d-md-inline fw-semibold"><?php echo e($current_user['name']); ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
            <li>
              <div class="dropdown-header">
                <div class="fw-bold"><?php echo e($current_user['name']); ?></div>
                <div class="text-muted small"><?php echo e($current_user['email']); ?></div>
              </div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
          </ul>
        </div>
      </div>
    </header>

    <!-- Main Content Container -->
    <main class="main-content">
      <?php display_flash_messages(); ?>
