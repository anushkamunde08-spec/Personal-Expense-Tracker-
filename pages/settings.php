<?php
/**
 * Application Settings & Data Management
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// ----------------------------------------------------
// FULL DATA BACKUP DOWNLOAD ACTION
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'export_backup') {
    // Fetch all user transactions
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name 
        FROM transactions t 
        JOIN categories c ON t.category_id = c.id 
        WHERE t.user_id = ?
        ORDER BY t.transaction_date DESC
    ");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=expense_tracker_full_backup_' . date('Y-m-d') . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Transaction ID', 'Date', 'Type', 'Category', 'Amount', 'Payment Method', 'Description', 'Recurring', 'Frequency']);

    foreach ($transactions as $t) {
        fputcsv($out, [
            $t['id'],
            $t['transaction_date'],
            ucfirst($t['type']),
            $t['category_name'],
            $t['amount'],
            $t['payment_method'],
            $t['description'],
            $t['is_recurring'] ? 'Yes' : 'No',
            $t['recurring_frequency']
        ]);
    }
    fclose($out);
    exit();
}

$page_title = 'Application Settings';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'Security check failed.');
        header("Location: " . BASE_URL . "pages/settings.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    // UPDATE CURRENCY PREFERENCE
    if ($action === 'update_currency') {
        $currency_pref = $_POST['currency_preference'] ?? '$';
        $stmt = $pdo->prepare("UPDATE users SET currency_preference = ? WHERE id = ?");
        $stmt->execute([$currency_pref, $user_id]);
        set_flash_message('success', 'Currency preference updated!');
        header("Location: " . BASE_URL . "pages/settings.php");
        exit();
    }

    // DELETE ACCOUNT
    if ($action === 'delete_account') {
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_hash = $stmt->fetchColumn();

        if (password_verify($confirm_password, $user_hash)) {
            // Delete user (Foreign Keys with ON DELETE CASCADE will clean up transactions, categories, budgets)
            $delStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $delStmt->execute([$user_id]);

            session_unset();
            session_destroy();
            session_start();
            set_flash_message('info', 'Your account and all associated data have been permanently deleted.');
            header("Location: " . BASE_URL . "auth/login.php");
            exit();
        } else {
            set_flash_message('danger', 'Password confirmation failed. Account was not deleted.');
            header("Location: " . BASE_URL . "pages/settings.php");
            exit();
        }
    }
}

// Refresh User Data
$current_user = get_current_user_data($user_id);
?>

<div class="row g-4 justify-content-center">
  
  <div class="col-12 col-md-8">
    
    <!-- Currency Preference Card -->
    <div class="card card-custom p-4 mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-currency-exchange me-2"></i>Currency &amp; Display Settings</h5>
      
      <form method="POST" action="">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="action" value="update_currency">

        <div class="mb-3">
          <label for="currency_preference" class="form-label fw-medium">Preferred Currency Symbol</label>
          <select class="form-select" id="currency_preference" name="currency_preference">
            <option value="$" <?php echo ($currency['currency_preference'] ?? '$' === '$') ? 'selected' : ''; ?>>$ USD (US Dollar)</option>
            <option value="€" <?php echo ($current_user['currency_preference'] === '€') ? 'selected' : ''; ?>>€ EUR (Euro)</option>
            <option value="£" <?php echo ($current_user['currency_preference'] === '£') ? 'selected' : ''; ?>>£ GBP (British Pound)</option>
            <option value="₹" <?php echo ($current_user['currency_preference'] === '₹') ? 'selected' : ''; ?>>₹ INR (Indian Rupee)</option>
            <option value="¥" <?php echo ($current_user['currency_preference'] === '¥') ? 'selected' : ''; ?>>¥ JPY / CNY</option>
            <option value="A$" <?php echo ($current_user['currency_preference'] === 'A$') ? 'selected' : ''; ?>>A$ AUD (Australian Dollar)</option>
            <option value="C$" <?php echo ($current_user['currency_preference'] === 'C$') ? 'selected' : ''; ?>>C$ CAD (Canadian Dollar)</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary fw-semibold">Save Currency Preference</button>
      </form>
    </div>

    <!-- Data Backup & Export Card -->
    <div class="card card-custom p-4 mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-download me-2"></i>Data Export &amp; Backup</h5>
      <p class="text-muted small mb-3">Download a complete CSV backup of all your transactions and financial history.</p>

      <a href="?action=export_backup" class="btn btn-outline-success fw-semibold">
        <i class="bi bi-file-earmark-arrow-down me-1"></i> Download Full CSV Backup
      </a>
    </div>

    <!-- Account Deletion Zone -->
    <div class="card card-custom p-4 border-danger border-opacity-50">
      <h5 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-octagon me-2"></i>Danger Zone: Delete Account</h5>
      <p class="text-muted small mb-3">Permanently erase your account, transactions, category preferences, and budget targets. This action cannot be undone.</p>

      <button type="button" class="btn btn-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
        Delete Account &amp; All Data
      </button>
    </div>

  </div>

</div>

<!-- DELETE ACCOUNT CONFIRMATION MODAL -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form method="POST" action="">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="action" value="delete_account">

        <div class="modal-header border-bottom bg-danger text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Account Deletion</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <p class="fw-bold text-danger mb-2">Are you sure you want to permanently delete your account?</p>
          <p class="text-muted small">All your transactions, uploaded receipts, custom categories, and budgets will be erased completely.</p>

          <div class="mb-3">
            <label for="confirm_password" class="form-label fw-medium">Enter your password to confirm:</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger fw-semibold">Delete Everything Permanently</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
