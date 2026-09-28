<?php
/**
 * Categories Management Page
 * Personal Expense Tracker
 */

$page_title = 'Category Management';
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Form Submissions (Create / Edit / Delete Category)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'Security validation failed.');
        header("Location: " . BASE_URL . "pages/categories.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    // CREATE CATEGORY
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'expense';
        $color = $_POST['color'] ?? '#6c757d';
        $icon = $_POST['icon'] ?? 'bi-tag';

        if (empty($name)) {
            set_flash_message('danger', 'Category name is required.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type, color, icon, is_default) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$user_id, $name, $type, $color, $icon]);
            set_flash_message('success', 'Custom category created!');
        }
        header("Location: " . BASE_URL . "pages/categories.php");
        exit();
    }

    // DELETE CATEGORY (WITH TRANSACTION GUARD)
    if ($action === 'delete') {
        $cat_id = (int)($_POST['category_id'] ?? 0);

        // Check if default category
        $stmt = $pdo->prepare("SELECT user_id, is_default FROM categories WHERE id = ?");
        $stmt->execute([$cat_id]);
        $cat = $stmt->fetch();

        if (!$cat || $cat['is_default'] == 1 || $cat['user_id'] != $user_id) {
            set_flash_message('danger', 'System default categories cannot be deleted.');
        } else {
            // Check if any transaction exists for this category
            $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ?");
            $chkStmt->execute([$cat_id]);
            $transCount = $chkStmt->fetchColumn();

            if ($transCount > 0) {
                set_flash_message('warning', "Cannot delete category: $transCount transaction(s) are associated with it. Delete or reassign those transactions first.");
            } else {
                $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
                $delStmt->execute([$cat_id, $user_id]);
                set_flash_message('success', 'Category deleted successfully.');
            }
        }
        header("Location: " . BASE_URL . "pages/categories.php");
        exit();
    }
}

// Fetch all system and user categories
$all_categories = get_user_categories($user_id);

$income_cats = array_filter($all_categories, function($c) { return $c['type'] === 'income'; });
$expense_cats = array_filter($all_categories, function($c) { return $c['type'] === 'expense'; });

// Preset Icon Options
$icon_options = [
    'bi-tag' => 'Tag',
    'bi-cup-hot' => 'Food / Coffee',
    'bi-car-front' => 'Transport / Vehicle',
    'bi-house-door' => 'Housing / Rent',
    'bi-lightning-charge' => 'Utilities / Power',
    'bi-controller' => 'Entertainment',
    'bi-heart-pulse' => 'Healthcare / Medical',
    'bi-bag-check' => 'Shopping',
    'bi-cash-stack' => 'Salary / Cash',
    'bi-laptop' => 'Freelance / Work',
    'bi-graph-up-arrow' => 'Investments',
    'bi-journal-bookmark' => 'Education',
    'bi-airplane' => 'Travel / Flight',
    'bi-gift' => 'Gift / Donation'
];
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
  <div>
    <h3 class="fw-bold mb-1">Categories</h3>
    <p class="text-muted small mb-0">Organize your finances with income and expense categories</p>
  </div>
  <button type="button" class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#categoryModal">
    <i class="bi bi-plus-lg me-1"></i> Add Custom Category
  </button>
</div>

<div class="row g-4">
  
  <!-- Expense Categories List -->
  <div class="col-12 col-md-6">
    <div class="card card-custom p-4 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
        <h5 class="fw-bold text-danger mb-0"><i class="bi bi-dash-circle me-2"></i>Expense Categories</h5>
        <span class="badge bg-danger-subtle text-danger rounded-pill"><?php echo count($expense_cats); ?> Items</span>
      </div>

      <div class="list-group list-group-flush">
        <?php foreach ($expense_cats as $cat): ?>
          <div class="list-group-item d-flex align-items-center justify-content-between px-0 py-3">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: <?php echo e($cat['color']); ?>20; color: <?php echo e($cat['color']); ?>;">
                <i class="bi <?php echo e($cat['icon']); ?> fs-5"></i>
              </div>
              <div>
                <h6 class="fw-semibold mb-0"><?php echo e($cat['name']); ?></h6>
                <?php if ($cat['is_default']): ?>
                  <span class="badge bg-secondary-subtle text-secondary small">System Default</span>
                <?php else: ?>
                  <span class="badge bg-primary-subtle text-primary small">Custom</span>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <?php if (!$cat['is_default']): ?>
                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this custom category?');">
                  <?php echo csrf_token_field(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Income Categories List -->
  <div class="col-12 col-md-6">
    <div class="card card-custom p-4 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
        <h5 class="fw-bold text-success mb-0"><i class="bi bi-plus-circle me-2"></i>Income Categories</h5>
        <span class="badge bg-success-subtle text-success rounded-pill"><?php echo count($income_cats); ?> Items</span>
      </div>

      <div class="list-group list-group-flush">
        <?php foreach ($income_cats as $cat): ?>
          <div class="list-group-item d-flex align-items-center justify-content-between px-0 py-3">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: <?php echo e($cat['color']); ?>20; color: <?php echo e($cat['color']); ?>;">
                <i class="bi <?php echo e($cat['icon']); ?> fs-5"></i>
              </div>
              <div>
                <h6 class="fw-semibold mb-0"><?php echo e($cat['name']); ?></h6>
                <?php if ($cat['is_default']): ?>
                  <span class="badge bg-secondary-subtle text-secondary small">System Default</span>
                <?php else: ?>
                  <span class="badge bg-primary-subtle text-primary small">Custom</span>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <?php if (!$cat['is_default']): ?>
                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this custom category?');">
                  <?php echo csrf_token_field(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- ADD CUSTOM CATEGORY MODAL -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form method="POST" action="">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="action" value="add">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold">Create Custom Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          
          <div class="mb-3">
            <label class="form-label fw-medium">Category Type</label>
            <select class="form-select" name="type" required>
              <option value="expense">Expense</option>
              <option value="income">Income</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="cat_name" class="form-label fw-medium">Category Name</label>
            <input type="text" class="form-control" id="cat_name" name="name" placeholder="e.g. Subscriptions, Pet Care" required>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label for="color" class="form-label fw-medium">Color Badge</label>
              <input type="color" class="form-control form-control-color w-100" id="color" name="color" value="#4f46e5" title="Choose color">
            </div>
            <div class="col-6 mb-3">
              <label for="icon" class="form-label fw-medium">Icon</label>
              <select class="form-select" id="icon" name="icon">
                <?php foreach ($icon_options as $iconClass => $iconLabel): ?>
                  <option value="<?php echo $iconClass; ?>"><?php echo $iconLabel; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-semibold">Save Category</button>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
