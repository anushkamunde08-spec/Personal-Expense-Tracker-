<?php
/**
 * Transactions Management (Expenses & Income CRUD)
 * Personal Expense Tracker
 */

$page_title = 'Manage Transactions';
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Fetch categories for modal dropdowns
$categories = get_user_categories($user_id);

// Handling Form Submissions (Create / Edit / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'Security validation error. Invalid CSRF token.');
        header("Location: " . BASE_URL . "pages/expenses.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    // CREATE or EDIT TRANSACTION
    if ($action === 'add' || $action === 'edit') {
        $trans_id = isset($_POST['trans_id']) ? (int)$_POST['trans_id'] : 0;
        $type = $_POST['type'] ?? 'expense';
        $category_id = (int)($_POST['category_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        $recurring_frequency = $_POST['recurring_frequency'] ?? 'none';

        // Validation
        if ($amount <= 0) {
            set_flash_message('danger', 'Transaction amount must be a positive number greater than 0.');
        } elseif (empty($category_id)) {
            set_flash_message('danger', 'Please select a valid category.');
        } elseif (empty($transaction_date)) {
            set_flash_message('danger', 'Please provide a valid transaction date.');
        } else {
            // Receipt image upload processing if provided
            $receipt_filename = null;
            if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
                $upload_res = upload_file($_FILES['receipt_image'], RECEIPT_UPLOAD_DIR);
                if ($upload_res['success']) {
                    $receipt_filename = $upload_res['filename'];
                } else {
                    set_flash_message('warning', 'File Upload Warning: ' . $upload_res['error']);
                }
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO transactions 
                    (user_id, category_id, type, amount, description, transaction_date, payment_method, receipt_image, is_recurring, recurring_frequency) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $category_id, $type, $amount, $description, $transaction_date, 
                    $payment_method, $receipt_filename, $is_recurring, $recurring_frequency
                ]);
                set_flash_message('success', 'Transaction recorded successfully!');
            } else {
                // Update existing record
                if ($receipt_filename) {
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET category_id = ?, type = ?, amount = ?, description = ?, transaction_date = ?, 
                            payment_method = ?, receipt_image = ?, is_recurring = ?, recurring_frequency = ? 
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([
                        $category_id, $type, $amount, $description, $transaction_date, 
                        $payment_method, $receipt_filename, $is_recurring, $recurring_frequency, $trans_id, $user_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET category_id = ?, type = ?, amount = ?, description = ?, transaction_date = ?, 
                            payment_method = ?, is_recurring = ?, recurring_frequency = ? 
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([
                        $category_id, $type, $amount, $description, $transaction_date, 
                        $payment_method, $is_recurring, $recurring_frequency, $trans_id, $user_id
                    ]);
                }
                set_flash_message('success', 'Transaction updated successfully!');
            }
        }
        header("Location: " . BASE_URL . "pages/expenses.php");
        exit();
    }

    // DELETE TRANSACTION
    if ($action === 'delete') {
        $trans_id = (int)($_POST['trans_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$trans_id, $user_id]);
        set_flash_message('success', 'Transaction deleted.');
        header("Location: " . BASE_URL . "pages/expenses.php");
        exit();
    }
}

// ----------------------------------------------------
// Search, Filter & Pagination Processing
// ----------------------------------------------------
$search_query = trim($_GET['search'] ?? '');
$filter_type = $_GET['type'] ?? 'all';
$filter_category = (int)($_GET['category_id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'date_desc';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Construct SQL query dynamically with prepared statement parameters
$where_clauses = ["t.user_id = ?"];
$params = [$user_id];

if (!empty($search_query)) {
    $where_clauses[] = "t.description LIKE ?";
    $params[] = "%$search_query%";
}

if ($filter_type === 'income' || $filter_type === 'expense') {
    $where_clauses[] = "t.type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_category)) {
    $where_clauses[] = "t.category_id = ?";
    $params[] = $filter_category;
}

if (!empty($from_date)) {
    $where_clauses[] = "t.transaction_date >= ?";
    $params[] = $from_date;
}

if (!empty($to_date)) {
    $where_clauses[] = "t.transaction_date <= ?";
    $params[] = $to_date;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Sorting
$sort_sql = "ORDER BY t.transaction_date DESC, t.id DESC";
if ($sort_by === 'date_asc') $sort_sql = "ORDER BY t.transaction_date ASC, t.id ASC";
if ($sort_by === 'amount_desc') $sort_sql = "ORDER BY t.amount DESC";
if ($sort_by === 'amount_asc') $sort_sql = "ORDER BY t.amount ASC";

// Get Total Count for Pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t $where_sql");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Paginated Transactions
$query = "
    SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    $where_sql
    $sort_sql
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<!-- Page Header & Action Buttons -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
  <div>
    <h3 class="fw-bold mb-1">Income &amp; Expenses</h3>
    <p class="text-muted small mb-0">Record, filter, and track all your financial transactions</p>
  </div>
  <button type="button" class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#transactionModal" onclick="resetForm()">
    <i class="bi bi-plus-lg me-1"></i> Add New Transaction
  </button>
</div>

<!-- Search & Filter Card -->
<div class="card card-custom p-4 mb-4">
  <form method="GET" action="" class="row g-3">
    
    <div class="col-12 col-md-3">
      <label class="form-label small fw-medium">Search Keyword</label>
      <div class="input-group">
        <span class="input-group-text bg-body text-secondary border-end-0"><i class="bi bi-search"></i></span>
        <input type="text" id="table-live-search" name="search" class="form-control border-start-0" placeholder="Search description..." value="<?php echo e($search_query); ?>">
      </div>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label small fw-medium">Type</label>
      <select name="type" class="form-select">
        <option value="all">All Types</option>
        <option value="income" <?php echo ($filter_type === 'income') ? 'selected' : ''; ?>>Income</option>
        <option value="expense" <?php echo ($filter_type === 'expense') ? 'selected' : ''; ?>>Expense</option>
      </select>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label small fw-medium">Category</label>
      <select name="category_id" class="form-select">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?php echo $cat['id']; ?>" <?php echo ($filter_category === (int)$cat['id']) ? 'selected' : ''; ?>>
            <?php echo e($cat['name']); ?> (<?php echo ucfirst($cat['type']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label small fw-medium">From Date</label>
      <input type="date" name="from_date" class="form-control" value="<?php echo e($from_date); ?>">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label small fw-medium">To Date</label>
      <input type="date" name="to_date" class="form-control" value="<?php echo e($to_date); ?>">
    </div>

    <div class="col-12 col-md-1 d-flex align-items-end gap-2">
      <button type="submit" class="btn btn-primary w-100" title="Apply Filters">
        <i class="bi bi-funnel"></i>
      </button>
      <a href="<?php echo BASE_URL; ?>pages/expenses.php" class="btn btn-outline-secondary" title="Reset Filters">
        <i class="bi bi-arrow-counterclockwise"></i>
      </a>
    </div>

  </form>
</div>

<!-- Transactions List Table -->
<div class="card card-custom p-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="transactions-table">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>Type</th>
          <th>Category</th>
          <th>Description</th>
          <th>Payment</th>
          <th>Receipt</th>
          <th class="text-end">Amount</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">
              <i class="bi bi-search fs-1 d-block mb-2"></i>
              No transactions match your query parameters.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($transactions as $t): ?>
            <tr>
              <td class="text-nowrap text-secondary small">
                <?php echo date('M d, Y', strtotime($t['transaction_date'])); ?>
              </td>
              <td>
                <span class="badge <?php echo ($t['type'] === 'income') ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                  <?php echo ucfirst($t['type']); ?>
                </span>
              </td>
              <td>
                <span class="badge-category" style="background-color: <?php echo e($t['category_color']); ?>20; color: <?php echo e($t['category_color']); ?>;">
                  <i class="bi <?php echo e($t['category_icon']); ?>"></i> <?php echo e($t['category_name']); ?>
                </span>
              </td>
              <td>
                <span class="fw-medium"><?php echo e($t['description'] ?: '—'); ?></span>
                <?php if ($t['is_recurring']): ?>
                  <span class="badge bg-body-tertiary text-secondary border ms-1 small"><i class="bi bi-arrow-repeat"></i> <?php echo e(ucfirst($t['recurring_frequency'])); ?></span>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-body-tertiary text-dark border"><?php echo e($t['payment_method']); ?></span></td>
              <td>
                <?php if (!empty($t['receipt_image'])): ?>
                  <a href="<?php echo BASE_URL . 'uploads/receipts/' . e($t['receipt_image']); ?>" target="_blank" class="btn btn-sm btn-light border text-primary py-0 px-2" title="View Attachment">
                    <i class="bi bi-paperclip"></i> View
                  </a>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end fw-bold <?php echo ($t['type'] === 'income') ? 'text-success' : 'text-danger'; ?>">
                <?php echo ($t['type'] === 'income' ? '+' : '-') . ' ' . format_currency($t['amount'], $currency); ?>
              </td>
              <td class="text-center text-nowrap">
                <button type="button" class="btn btn-sm btn-outline-primary border-0 me-1" onclick='editTransaction(<?php echo json_encode($t); ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this transaction?');">
                  <?php echo csrf_token_field(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="trans_id" value="<?php echo $t['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger border-0">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination Controls -->
  <?php if ($total_pages > 1): ?>
    <nav class="d-flex justify-content-between align-items-center mt-4 border-top pt-3">
      <span class="text-muted small">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_rows; ?> total entries)</span>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
          <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
        </li>
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
          <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
          <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

</div>

<!-- CREATE / EDIT TRANSACTION MODAL -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form method="POST" action="" enctype="multipart/form-data">
        <?php echo csrf_token_field(); ?>
        <input type="hidden" name="action" id="modalAction" value="add">
        <input type="hidden" name="trans_id" id="modalTransId" value="0">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold" id="modalTitle">Add New Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          
          <!-- Transaction Type Toggle -->
          <div class="mb-3">
            <label class="form-label fw-medium">Transaction Type</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="type" id="type_expense" value="expense" checked onchange="filterCategoriesByType('expense')">
              <label class="btn btn-outline-danger" for="type_expense"><i class="bi bi-dash-circle me-1"></i> Expense</label>

              <input type="radio" class="btn-check" name="type" id="type_income" value="income" onchange="filterCategoriesByType('income')">
              <label class="btn btn-outline-success" for="type_income"><i class="bi bi-plus-circle me-1"></i> Income</label>
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label for="amount" class="form-label fw-medium">Amount (<?php echo e($currency); ?>)</label>
              <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required placeholder="0.00">
            </div>
            <div class="col-6 mb-3">
              <label for="transaction_date" class="form-label fw-medium">Date</label>
              <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="category_id" class="form-label fw-medium">Category</label>
            <select class="form-select" id="category_id" name="category_id" required>
              <option value="">-- Select Category --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" data-type="<?php echo $cat['type']; ?>">
                  <?php echo e($cat['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="description" class="form-label fw-medium">Description</label>
            <input type="text" class="form-control" id="description" name="description" placeholder="e.g. Grocery items, Monthly salary...">
          </div>

          <div class="mb-3">
            <label for="payment_method" class="form-label fw-medium">Payment Method</label>
            <select class="form-select" id="payment_method" name="payment_method">
              <option value="Cash">Cash</option>
              <option value="Card">Card / Debit / Credit</option>
              <option value="UPI">UPI / GPay / PhonePe</option>
              <option value="Bank Transfer">Bank Transfer</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="receipt_image" class="form-label fw-medium">Receipt Image (Optional)</label>
            <input type="file" class="form-control image-preview-input" id="receipt_image" name="receipt_image" accept="image/*,application/pdf" data-preview-target="modal-receipt-preview">
            <small class="text-muted">Allowed formats: JPG, PNG, PDF (Max: 5MB)</small>
            <img id="modal-receipt-preview" src="" alt="Receipt Preview" class="receipt-preview-img mt-2 d-none">
          </div>

          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="is_recurring" name="is_recurring" onchange="toggleRecurringOptions()">
            <label class="form-check-label fw-medium" for="is_recurring">Mark as Recurring Transaction</label>
          </div>

          <div class="mb-3 d-none" id="recurringFrequencyContainer">
            <label for="recurring_frequency" class="form-label small fw-medium">Recurring Frequency</label>
            <select class="form-select form-select-sm" id="recurring_frequency" name="recurring_frequency">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly" selected>Monthly</option>
              <option value="yearly">Yearly</option>
            </select>
          </div>

        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-semibold">Save Transaction</button>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
function filterCategoriesByType(selectedType) {
  const categorySelect = document.getElementById('category_id');
  const options = categorySelect.options;
  for (let i = 0; i < options.length; i++) {
    const opt = options[i];
    const catType = opt.getAttribute('data-type');
    if (!catType || catType === selectedType) {
      opt.style.display = '';
    } else {
      opt.style.display = 'none';
    }
  }
}

function toggleRecurringOptions() {
  const isChecked = document.getElementById('is_recurring').checked;
  const container = document.getElementById('recurringFrequencyContainer');
  if (isChecked) {
    container.classList.remove('d-none');
  } else {
    container.classList.add('d-none');
  }
}

function resetForm() {
  document.getElementById('modalAction').value = 'add';
  document.getElementById('modalTransId').value = '0';
  document.getElementById('modalTitle').innerText = 'Add New Transaction';
  document.getElementById('amount').value = '';
  document.getElementById('description').value = '';
  document.getElementById('type_expense').checked = true;
  filterCategoriesByType('expense');
  document.getElementById('is_recurring').checked = false;
  toggleRecurringOptions();
}

function editTransaction(data) {
  document.getElementById('modalAction').value = 'edit';
  document.getElementById('modalTransId').value = data.id;
  document.getElementById('modalTitle').innerText = 'Edit Transaction #' + data.id;
  
  if (data.type === 'income') {
    document.getElementById('type_income').checked = true;
    filterCategoriesByType('income');
  } else {
    document.getElementById('type_expense').checked = true;
    filterCategoriesByType('expense');
  }

  document.getElementById('amount').value = data.amount;
  document.getElementById('transaction_date').value = data.transaction_date;
  document.getElementById('category_id').value = data.category_id;
  document.getElementById('description').value = data.description;
  document.getElementById('payment_method').value = data.payment_method;

  if (data.is_recurring == 1) {
    document.getElementById('is_recurring').checked = true;
    document.getElementById('recurring_frequency').value = data.recurring_frequency;
  } else {
    document.getElementById('is_recurring').checked = false;
  }
  toggleRecurringOptions();

  const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
