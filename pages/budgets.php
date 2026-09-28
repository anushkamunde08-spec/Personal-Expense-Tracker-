<?php
/**
 * Category Budgets & Limit Management
 * Personal Expense Tracker
 */

$page_title = 'Monthly Budgets';
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Selected month and year filter
$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

// Handle Budget Upsert
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'Security validation error.');
        header("Location: " . BASE_URL . "pages/budgets.php");
        exit();
    }

    $category_id = (int)($_POST['category_id'] ?? 0);
    $monthly_limit = (float)($_POST['monthly_limit'] ?? 0);
    $b_month = (int)($_POST['month'] ?? $selected_month);
    $b_year = (int)($_POST['year'] ?? $selected_year);

    if ($category_id > 0 && $monthly_limit >= 0) {
        $stmt = $pdo->prepare("
            INSERT INTO budgets (user_id, category_id, monthly_limit, month, year) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)
        ");
        $stmt->execute([$user_id, $category_id, $monthly_limit, $b_month, $b_year]);
        set_flash_message('success', 'Category budget updated successfully!');
    }
    header("Location: " . BASE_URL . "pages/budgets.php?month=$b_month&year=$b_year");
    exit();
}

// Fetch Expense Categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE (user_id = ? OR user_id IS NULL) AND type = 'expense' ORDER BY name ASC");
$stmt->execute([$user_id]);
$expense_categories = $stmt->fetchAll();

// Fetch Existing Budgets & Calculate Spent for Selected Month & Year
$stmt = $pdo->prepare("
    SELECT c.id as category_id, c.name as category_name, c.color, c.icon,
           COALESCE(b.monthly_limit, 0) as monthly_limit,
           COALESCE(SUM(t.amount), 0) as spent
    FROM categories c
    LEFT JOIN budgets b ON b.category_id = c.id AND b.user_id = ? AND b.month = ? AND b.year = ?
    LEFT JOIN transactions t ON t.category_id = c.id 
         AND t.user_id = ? 
         AND t.type = 'expense' 
         AND MONTH(t.transaction_date) = ? 
         AND YEAR(t.transaction_date) = ?
    WHERE (c.user_id = ? OR c.user_id IS NULL) AND c.type = 'expense'
    GROUP BY c.id, c.name, c.color, c.icon, b.monthly_limit
    ORDER BY b.monthly_limit DESC, c.name ASC
");
$stmt->execute([$user_id, $selected_month, $selected_year, $user_id, $selected_month, $selected_year, $user_id]);
$budget_rows = $stmt->fetchAll();

$month_names = [
    1=>'January', 2=>'February', 3=>'March', 4=>'April', 5=>'May', 6=>'June',
    7=>'July', 8=>'August', 9=>'September', 10=>'October', 11=>'November', 12=>'December'
];
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
  <div>
    <h3 class="fw-bold mb-1">Monthly Budgets</h3>
    <p class="text-muted small mb-0">Set spending targets and monitor progress to keep costs under control</p>
  </div>

  <!-- Month & Year Selector Form -->
  <form method="GET" action="" class="d-flex gap-2">
    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($month_names as $num => $name): ?>
        <option value="<?php echo $num; ?>" <?php echo ($num === $selected_month) ? 'selected' : ''; ?>><?php echo $name; ?></option>
      <?php endforeach; ?>
    </select>

    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
        <option value="<?php echo $y; ?>" <?php echo ($y === $selected_year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
      <?php endfor; ?>
    </select>
  </form>
</div>

<!-- Budgets Cards Grid -->
<div class="row g-4 mb-4">
  <?php foreach ($budget_rows as $row): ?>
    <?php 
      $limit = (float)$row['monthly_limit'];
      $spent = (float)$row['spent'];
      $pct = ($limit > 0) ? min(100, ($spent / $limit) * 100) : 0;
      $real_pct = ($limit > 0) ? ($spent / $limit) * 100 : 0;

      $bar_color = 'bg-success';
      $badge_class = 'bg-success-subtle text-success';
      $badge_text = 'On Track';

      if ($real_pct >= 80 && $real_pct <= 100) {
        $bar_color = 'bg-warning';
        $badge_class = 'bg-warning-subtle text-warning-emphasis';
        $badge_text = 'Near Limit (≥80%)';
      } elseif ($real_pct > 100) {
        $bar_color = 'bg-danger';
        $badge_class = 'bg-danger-subtle text-danger';
        $badge_text = 'Exceeded Limit!';
      }
    ?>

    <div class="col-12 col-md-6 col-xl-4">
      <div class="card card-custom p-4 h-100 position-relative">
        
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background-color: <?php echo e($row['color']); ?>20; color: <?php echo e($row['color']); ?>;">
              <i class="bi <?php echo e($row['icon']); ?> fs-5"></i>
            </div>
            <div>
              <h6 class="fw-bold mb-0"><?php echo e($row['category_name']); ?></h6>
              <span class="badge <?php echo $badge_class; ?> small"><?php echo $badge_text; ?></span>
            </div>
          </div>

          <button type="button" class="btn btn-sm btn-outline-secondary border-0" onclick='openSetBudgetModal(<?php echo $row["category_id"]; ?>, "<?php echo e(addslashes($row["category_name"])); ?>", <?php echo $limit; ?>)'>
            <i class="bi bi-pencil-square"></i>
          </button>
        </div>

        <div class="d-flex justify-content-between align-items-baseline mb-2">
          <span class="text-secondary small">Spent: <strong><?php echo format_currency($spent, $currency); ?></strong></span>
          <span class="text-secondary small">Limit: <strong><?php echo ($limit > 0) ? format_currency($limit, $currency) : 'Not Set'; ?></strong></span>
        </div>

        <div class="progress mb-2" style="height: 10px;">
          <div class="progress-bar <?php echo $bar_color; ?>" role="progressbar" style="width: <?php echo $pct; ?>%" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center text-muted small mt-1">
          <span><?php echo round($real_pct, 1); ?>% used</span>
          <span>
            <?php if ($limit > 0): ?>
              <?php echo ($limit - $spent >= 0) ? format_currency($limit - $spent, $currency) . ' remaining' : format_currency(abs($limit - $spent), $currency) . ' over'; ?>
            <?php else: ?>
              Set limit to track
            <?php endif; ?>
          </span>
        </div>

      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- SET BUDGET LIMIT MODAL -->
<div class="modal fade" id="budgetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form method="POST" action="">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
        <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
        <input type="hidden" name="category_id" id="budgetCategoryId" value="0">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold" id="budgetModalTitle">Set Category Budget</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <p class="text-muted small">Setting budget limit for <strong><?php echo $month_names[$selected_month] . ' ' . $selected_year; ?></strong>.</p>

          <div class="mb-3">
            <label for="monthly_limit" class="form-label fw-medium">Monthly Budget Target (<?php echo e($currency); ?>)</label>
            <div class="input-group">
              <span class="input-group-text"><?php echo e($currency); ?></span>
              <input type="number" step="0.01" min="0" class="form-control" id="monthly_limit" name="monthly_limit" required placeholder="500.00">
            </div>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-semibold">Save Budget Target</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openSetBudgetModal(catId, catName, currentLimit) {
  document.getElementById('budgetCategoryId').value = catId;
  document.getElementById('budgetModalTitle').innerText = 'Set Budget: ' + catName;
  document.getElementById('monthly_limit').value = currentLimit > 0 ? currentLimit : '';
  const modal = new bootstrap.Modal(document.getElementById('budgetModal'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
