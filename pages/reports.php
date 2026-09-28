<?php
/**
 * Reports & Analytics Page with CSV Export
 * Personal Expense Tracker
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// ----------------------------------------------------
// CSV EXPORT ACTION
// ----------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exp_from = $_GET['from_date'] ?? '';
    $exp_to = $_GET['to_date'] ?? '';
    $exp_type = $_GET['type'] ?? 'all';

    $where = ["t.user_id = ?"];
    $params = [$user_id];

    if (!empty($exp_from)) {
        $where[] = "t.transaction_date >= ?";
        $params[] = $exp_from;
    }
    if (!empty($exp_to)) {
        $where[] = "t.transaction_date <= ?";
        $params[] = $exp_to;
    }
    if ($exp_type === 'income' || $exp_type === 'expense') {
        $where[] = "t.type = ?";
        $params[] = $exp_type;
    }

    $where_sql = "WHERE " . implode(" AND ", $where);
    $stmt = $pdo->prepare("
        SELECT t.id, t.transaction_date, t.type, c.name as category, t.amount, t.payment_method, t.description, t.is_recurring
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        $where_sql
        ORDER BY t.transaction_date DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Stream CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=expense_tracker_export_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    // Header Row
    fputcsv($output, ['ID', 'Date', 'Type', 'Category', 'Amount', 'Payment Method', 'Description', 'Recurring']);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'],
            $r['transaction_date'],
            ucfirst($r['type']),
            $r['category'],
            $r['amount'],
            $r['payment_method'],
            $r['description'],
            $r['is_recurring'] ? 'Yes' : 'No'
        ]);
    }
    fclose($output);
    exit();
}

// ----------------------------------------------------
// Normal Page View & Analytics Calculation
// ----------------------------------------------------
$page_title = 'Reports & Analytics';
require_once __DIR__ . '/../includes/header.php';

$from_date = $_GET['from_date'] ?? date('Y-01-01');
$to_date = $_GET['to_date'] ?? date('Y-12-31');

// Total Period Income
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$user_id, $from_date, $to_date]);
$period_income = $stmt->fetch()['total'] ?? 0;

// Total Period Expense
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$user_id, $from_date, $to_date]);
$period_expense = $stmt->fetch()['total'] ?? 0;

$savings = $period_income - $period_expense;
$savings_rate = ($period_income > 0) ? min(100, max(0, ($savings / $period_income) * 100)) : 0;

// Category Breakdown Query
$stmt = $pdo->prepare("
    SELECT c.name as category_name, c.color, SUM(t.amount) as total
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date BETWEEN ? AND ?
    GROUP BY c.id, c.name, c.color
    ORDER BY total DESC
");
$stmt->execute([$user_id, $from_date, $to_date]);
$category_breakdown = $stmt->fetchAll();

$pie_labels = array_column($category_breakdown, 'category_name');
$pie_values = array_map('floatval', array_column($category_breakdown, 'total'));
$pie_colors = array_column($category_breakdown, 'color');

// Monthly Breakdown Query (Aggregated by Month)
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(transaction_date, '%Y-%m') as ym,
           SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as inc,
           SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as exp
    FROM transactions
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
    GROUP BY ym
    ORDER BY ym ASC
");
$stmt->execute([$user_id, $from_date, $to_date]);
$monthly_data = $stmt->fetchAll();

$trend_labels = [];
$trend_income = [];
$trend_expense = [];
foreach ($monthly_data as $m) {
    $trend_labels[] = date('M Y', strtotime($m['ym'] . '-01'));
    $trend_income[] = (float)$m['inc'];
    $trend_expense[] = (float)$m['exp'];
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
  <div>
    <h3 class="fw-bold mb-1">Reports &amp; Analytics</h3>
    <p class="text-muted small mb-0">Visualize income trends, expense categories, and export reports</p>
  </div>
  
  <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-outline-success fw-semibold shadow-sm">
    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Report to CSV
  </a>
</div>

<!-- Date Filter Card -->
<div class="card card-custom p-4 mb-4">
  <form method="GET" action="" class="row g-3 align-items-end">
    <div class="col-12 col-sm-4">
      <label class="form-label small fw-medium">Report From Date</label>
      <input type="date" name="from_date" class="form-control" value="<?php echo e($from_date); ?>">
    </div>
    <div class="col-12 col-sm-4">
      <label class="form-label small fw-medium">Report To Date</label>
      <input type="date" name="to_date" class="form-control" value="<?php echo e($to_date); ?>">
    </div>
    <div class="col-12 col-sm-4 d-flex gap-2">
      <button type="submit" class="btn btn-primary w-100 fw-semibold">
        <i class="bi bi-funnel me-1"></i> Generate Report
      </button>
      <a href="<?php echo BASE_URL; ?>pages/reports.php" class="btn btn-outline-secondary">Reset</a>
    </div>
  </form>
</div>

<!-- Key Performance Indicators Row -->
<div class="row g-4 mb-4">
  
  <div class="col-12 col-md-4">
    <div class="card card-custom p-4 text-center">
      <span class="text-secondary small fw-medium">Period Income</span>
      <h3 class="fw-bold text-success mt-1 mb-0"><?php echo format_currency($period_income, $currency); ?></h3>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <div class="card card-custom p-4 text-center">
      <span class="text-secondary small fw-medium">Period Expense</span>
      <h3 class="fw-bold text-danger mt-1 mb-0"><?php echo format_currency($period_expense, $currency); ?></h3>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <div class="card card-custom p-4 text-center">
      <span class="text-secondary small fw-medium">Savings Rate</span>
      <h3 class="fw-bold text-primary mt-1 mb-0"><?php echo round($savings_rate, 1); ?>%</h3>
    </div>
  </div>

</div>

<!-- Interactive Charts Grid -->
<div class="row g-4 mb-4">
  
  <div class="col-12 col-lg-7">
    <div class="card card-custom p-4 h-100">
      <h5 class="fw-bold mb-3">Monthly Income vs. Expense Trend</h5>
      <div style="height: 320px; position: relative;">
        <canvas id="reportTrendChart"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="card card-custom p-4 h-100">
      <h5 class="fw-bold mb-3">Expense Distribution</h5>
      <div style="height: 320px; position: relative;">
        <?php if (empty($pie_values)): ?>
          <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
            <i class="bi bi-pie-chart fs-1 mb-2"></i>
            <p class="small">No expense data found for selected period.</p>
          </div>
        <?php else: ?>
          <canvas id="reportPieChart"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Breakdown Table -->
<div class="card card-custom p-4">
  <h5 class="fw-bold mb-3">Category Breakdown Table</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Category</th>
          <th class="text-end">Total Spent</th>
          <th class="text-end">% of Total Expense</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($category_breakdown)): ?>
          <tr><td colspan="3" class="text-center py-4 text-muted">No data available</td></tr>
        <?php else: ?>
          <?php foreach ($category_breakdown as $row): ?>
            <?php $pct = ($period_expense > 0) ? ($row['total'] / $period_expense) * 100 : 0; ?>
            <tr>
              <td>
                <span class="badge-category" style="background-color: <?php echo e($row['color']); ?>20; color: <?php echo e($row['color']); ?>;">
                  <?php echo e($row['category_name']); ?>
                </span>
              </td>
              <td class="text-end fw-semibold"><?php echo format_currency($row['total'], $currency); ?></td>
              <td class="text-end"><?php echo round($pct, 1); ?>%</td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  
  // 1. Report Trend Line Chart
  const trendCtx = document.getElementById('reportTrendChart');
  if (trendCtx) {
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: <?php echo json_encode($trend_labels); ?>,
        datasets: [
          {
            label: 'Income',
            data: <?php echo json_encode($trend_income); ?>,
            borderColor: '#198754',
            backgroundColor: '#19875415',
            fill: true,
            tension: 0.3
          },
          {
            label: 'Expense',
            data: <?php echo json_encode($trend_expense); ?>,
            borderColor: '#dc3545',
            backgroundColor: '#dc354515',
            fill: true,
            tension: 0.3
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  // 2. Category Pie Chart
  const pieCtx = document.getElementById('reportPieChart');
  if (pieCtx) {
    new Chart(pieCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($pie_labels); ?>,
        datasets: [{
          data: <?php echo json_encode($pie_values); ?>,
          backgroundColor: <?php echo json_encode($pie_colors); ?>
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
      }
    });
  }

});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
