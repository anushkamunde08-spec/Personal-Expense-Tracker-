<?php
/**
 * Dashboard Overview Page
 * Personal Expense Tracker
 */

$page_title = 'Dashboard Overview';
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Current month & year parameters
$current_month = date('n');
$current_year = date('Y');

// 1. Total Income
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income'");
$stmt->execute([$user_id]);
$total_income = $stmt->fetch()['total'] ?? 0;

// 2. Total Expenses
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense'");
$stmt->execute([$user_id]);
$total_expense = $stmt->fetch()['total'] ?? 0;

// 3. Current Net Balance
$net_balance = $total_income - $total_expense;

// 4. This Month's Spending
$stmt = $pdo->prepare("
    SELECT SUM(amount) as total 
    FROM transactions 
    WHERE user_id = ? AND type = 'expense' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?
");
$stmt->execute([$user_id, $current_month, $current_year]);
$this_month_expense = $stmt->fetch()['total'] ?? 0;

// 5. Budget Alert Notifications (Check if any category spent > 80% of budget)
$budget_alerts = [];
$stmt = $pdo->prepare("
    SELECT b.monthly_limit, c.name as category_name, c.color,
           COALESCE(SUM(t.amount), 0) as spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    LEFT JOIN transactions t ON t.category_id = c.id 
         AND t.user_id = b.user_id 
         AND t.type = 'expense' 
         AND MONTH(t.transaction_date) = b.month 
         AND YEAR(t.transaction_date) = b.year
    WHERE b.user_id = ? AND b.month = ? AND b.year = ?
    GROUP BY b.id, b.monthly_limit, c.name, c.color
");
$stmt->execute([$user_id, $current_month, $current_year]);
$budgets = $stmt->fetchAll();

foreach ($budgets as $b) {
    $pct = ($b['monthly_limit'] > 0) ? ($b['spent'] / $b['monthly_limit']) * 100 : 0;
    if ($pct >= 80) {
        $budget_alerts[] = [
            'category' => $b['category_name'],
            'spent' => $b['spent'],
            'limit' => $b['monthly_limit'],
            'pct' => round($pct, 1),
            'is_over' => ($pct > 100)
        ];
    }
}

// 6. Recent Transactions (Last 7)
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 7
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll();

// 7. Pie Chart Data: Expense breakdown by Category for Current Month
$stmt = $pdo->prepare("
    SELECT c.name as category_name, c.color, SUM(t.amount) as total_spent
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense' AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?
    GROUP BY c.id, c.name, c.color
    ORDER BY total_spent DESC
");
$stmt->execute([$user_id, $current_month, $current_year]);
$cat_chart_data = $stmt->fetchAll();

$pie_labels = [];
$pie_values = [];
$pie_colors = [];
foreach ($cat_chart_data as $row) {
    $pie_labels[] = $row['category_name'];
    $pie_values[] = (float)$row['total_spent'];
    $pie_colors[] = $row['color'] ?? '#4f46e5';
}

// 8. 6-Month Income vs Expense Trend Chart Data
$months_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('n', strtotime("-$i month"));
    $y = date('Y', strtotime("-$i month"));
    $m_name = date('M Y', strtotime("-$i month"));
    
    // Income sum
    $stmt1 = $pdo->prepare("SELECT SUM(amount) as inc FROM transactions WHERE user_id = ? AND type = 'income' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
    $stmt1->execute([$user_id, $m, $y]);
    $inc = $stmt1->fetch()['inc'] ?? 0;
    
    // Expense sum
    $stmt2 = $pdo->prepare("SELECT SUM(amount) as exp FROM transactions WHERE user_id = ? AND type = 'expense' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
    $stmt2->execute([$user_id, $m, $y]);
    $exp = $stmt2->fetch()['exp'] ?? 0;
    
    $months_trend[] = [
        'label' => $m_name,
        'income' => (float)$inc,
        'expense' => (float)$exp
    ];
}

// Check recurring reminders
$recurring_reminders = check_recurring_reminders($user_id);
?>

<!-- Budget & Recurring Notifications -->
<?php if (!empty($budget_alerts)): ?>
  <div class="row mb-4">
    <div class="col-12">
      <?php foreach ($budget_alerts as $alert): ?>
        <div class="alert alert-<?php echo $alert['is_over'] ? 'danger' : 'warning'; ?> d-flex align-items-center shadow-sm" role="alert">
          <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
          <div>
            <strong>Budget Notice for <?php echo e($alert['category']); ?>:</strong> 
            You have spent <?php echo format_currency($alert['spent'], $currency); ?> of your <?php echo format_currency($alert['limit'], $currency); ?> budget limit (<?php echo $alert['pct']; ?>%).
            <?php if ($alert['is_over']): ?>
              <span class="badge bg-danger ms-2">EXCEEDED BUDGET</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark ms-2">NEAR LIMIT</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Summary Cards Grid -->
<div class="row g-4 mb-4">
  
  <!-- Total Income -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-custom stat-card h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-secondary small fw-medium">Total Income</span>
          <div class="stat-value text-success"><?php echo format_currency($total_income, $currency); ?></div>
          <span class="text-muted small"><i class="bi bi-arrow-up-right text-success me-1"></i>All-time earnings</span>
        </div>
        <div class="stat-icon bg-success-subtle text-success">
          <i class="bi bi-graph-up-arrow"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Total Expenses -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-custom stat-card h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-secondary small fw-medium">Total Expenses</span>
          <div class="stat-value text-danger"><?php echo format_currency($total_expense, $currency); ?></div>
          <span class="text-muted small"><i class="bi bi-arrow-down-right text-danger me-1"></i>All-time spending</span>
        </div>
        <div class="stat-icon bg-danger-subtle text-danger">
          <i class="bi bi-graph-down-arrow"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Current Balance -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-custom stat-card h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-secondary small fw-medium">Net Balance</span>
          <div class="stat-value <?php echo ($net_balance >= 0) ? 'text-primary' : 'text-danger'; ?>">
            <?php echo format_currency($net_balance, $currency); ?>
          </div>
          <span class="text-muted small">Income minus Expense</span>
        </div>
        <div class="stat-icon bg-primary-subtle text-primary">
          <i class="bi bi-wallet2"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- This Month's Spending -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-custom stat-card h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-secondary small fw-medium">This Month's Spent</span>
          <div class="stat-value text-warning"><?php echo format_currency($this_month_expense, $currency); ?></div>
          <span class="text-muted small"><?php echo date('F Y'); ?></span>
        </div>
        <div class="stat-icon bg-warning-subtle text-warning">
          <i class="bi bi-calendar-check"></i>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
  
  <!-- 6-Month Income vs Expense Bar/Line Chart -->
  <div class="col-12 col-lg-8">
    <div class="card card-custom p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Income vs. Expense Trend</h5>
        <span class="text-muted small">Last 6 Months</span>
      </div>
      <div style="height: 300px; position: relative;">
        <canvas id="incomeVsExpenseChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Expense Breakdown Pie Chart -->
  <div class="col-12 col-lg-4">
    <div class="card card-custom p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Category Breakdown</h5>
        <span class="text-muted small"><?php echo date('M Y'); ?></span>
      </div>
      <div style="height: 270px; position: relative;">
        <?php if (empty($pie_values)): ?>
          <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
            <i class="bi bi-pie-chart fs-1 mb-2"></i>
            <p class="small">No expense data for this month yet.</p>
          </div>
        <?php else: ?>
          <canvas id="categoryPieChart"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Recent Transactions Table -->
<div class="card card-custom p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">Recent Transactions</h5>
    <a href="<?php echo BASE_URL; ?>pages/expenses.php" class="btn btn-sm btn-outline-primary fw-semibold">
      View All Transactions <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>Category</th>
          <th>Description</th>
          <th>Payment Method</th>
          <th>Receipt</th>
          <th class="text-end">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent_transactions)): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2"></i>
              No transactions recorded yet. Click <strong><a href="<?php echo BASE_URL; ?>pages/expenses.php">here</a></strong> to add your first transaction.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recent_transactions as $t): ?>
            <tr>
              <td class="text-nowrap text-secondary small">
                <?php echo date('M d, Y', strtotime($t['transaction_date'])); ?>
              </td>
              <td>
                <span class="badge-category" style="background-color: <?php echo e($t['category_color']); ?>20; color: <?php echo e($t['category_color']); ?>;">
                  <i class="bi <?php echo e($t['category_icon']); ?>"></i> <?php echo e($t['category_name']); ?>
                </span>
              </td>
              <td>
                <span class="fw-medium"><?php echo e($t['description'] ?: '—'); ?></span>
                <?php if ($t['is_recurring']): ?>
                  <span class="badge bg-secondary-subtle text-secondary ms-1 small"><i class="bi bi-arrow-repeat"></i> <?php echo e(ucfirst($t['recurring_frequency'])); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-body-tertiary text-dark border"><?php echo e($t['payment_method']); ?></span>
              </td>
              <td>
                <?php if (!empty($t['receipt_image'])): ?>
                  <a href="<?php echo BASE_URL . 'uploads/receipts/' . e($t['receipt_image']); ?>" target="_blank" class="btn btn-sm btn-light border text-primary py-0 px-2" title="View Receipt">
                    <i class="bi bi-paperclip"></i> Receipt
                  </a>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end fw-bold <?php echo ($t['type'] === 'income') ? 'text-success' : 'text-danger'; ?>">
                <?php echo ($t['type'] === 'income' ? '+' : '-') . ' ' . format_currency($t['amount'], $currency); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Chart.js Scripts Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  
  // 1. Income vs Expense 6-Month Chart
  const trendCtx = document.getElementById('incomeVsExpenseChart');
  if (trendCtx) {
    const trendLabels = <?php echo json_encode(array_column($months_trend, 'label')); ?>;
    const incomeData = <?php echo json_encode(array_column($months_trend, 'income')); ?>;
    const expenseData = <?php echo json_encode(array_column($months_trend, 'expense')); ?>;

    new Chart(trendCtx, {
      type: 'bar',
      data: {
        labels: trendLabels,
        datasets: [
          {
            label: 'Income',
            data: incomeData,
            backgroundColor: '#198754',
            borderRadius: 6
          },
          {
            label: 'Expense',
            data: expenseData,
            backgroundColor: '#dc3545',
            borderRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top' }
        },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  }

  // 2. Category Pie Chart
  const pieCtx = document.getElementById('categoryPieChart');
  if (pieCtx) {
    new Chart(pieCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($pie_labels); ?>,
        datasets: [{
          data: <?php echo json_encode($pie_values); ?>,
          backgroundColor: <?php echo json_encode($pie_colors); ?>,
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12 } }
        }
      }
    });
  }

});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
