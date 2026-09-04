<?php
/**
 * Profit/Loss Report
 * Gym Management System
 * 
 * File: reports/profit-loss.php
 * Purpose: Generate profit/loss report
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to view this report.');
    redirect('../dashboard.php');
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

// Get revenue data
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(total) as total_revenue,
            SUM(CASE WHEN transaction_type = 'membership' THEN total ELSE 0 END) as membership_revenue,
            SUM(CASE WHEN transaction_type = 'renewal' THEN total ELSE 0 END) as renewal_revenue,
            SUM(CASE WHEN transaction_type = 'walkin' THEN total ELSE 0 END) as walkin_revenue,
            SUM(CASE WHEN transaction_type = 'pt_package' THEN total ELSE 0 END) as pt_package_revenue,
            SUM(CASE WHEN transaction_type = 'pt_session' THEN total ELSE 0 END) as pt_session_revenue,
            SUM(CASE WHEN transaction_type = 'other' THEN total ELSE 0 END) as other_revenue
        FROM payments 
        WHERE payment_date BETWEEN ? AND ? AND status = 'completed'
    ");
    $stmt->execute([$date_from, $date_to]);
    $revenue = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Revenue data error: ' . $e->getMessage());
    $revenue = [
        'total_revenue' => 0,
        'membership_revenue' => 0,
        'renewal_revenue' => 0,
        'walkin_revenue' => 0,
        'pt_package_revenue' => 0,
        'pt_session_revenue' => 0,
        'other_revenue' => 0
    ];
}

// Get expense data
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as total_expenses,
            ec.category_name,
            SUM(e.amount) as category_total
        FROM expenses e
        INNER JOIN expense_categories ec ON e.category_id = ec.category_id
        WHERE e.expense_date BETWEEN ? AND ?
        GROUP BY e.category_id
        ORDER BY category_total DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $expense_categories = $stmt->fetchAll();
    
    // Get total expenses
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $total_expenses = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    error_log('Expense data error: ' . $e->getMessage());
    $expense_categories = [];
    $total_expenses = 0;
}

// Calculate profit
$total_revenue = $revenue['total_revenue'] ?? 0;
$net_profit = $total_revenue - $total_expenses;
$profit_margin = $total_revenue > 0 ? ($net_profit / $total_revenue) * 100 : 0;

// Include header
include '../includes/header.php';
?>

<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/navbar.php'; ?>
            
            <div class="content-wrapper">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h4 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Profit/Loss Report</h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card border-success">
                            <div class="stat-icon revenue">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Total Revenue</h5>
                                <h2 class="text-success"><?php echo formatCurrency($total_revenue); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card border-danger">
                            <div class="stat-icon expenses">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Total Expenses</h5>
                                <h2 class="text-danger"><?php echo formatCurrency($total_expenses); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card border-primary">
                            <div class="stat-icon profit">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Net Profit</h5>
                                <h2 class="<?php echo $net_profit >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                    <?php echo formatCurrency($net_profit); ?>
                                </h2>
                                <small><?php echo number_format($profit_margin, 1); ?>% Profit Margin</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Revenue Breakdown -->
                    <div class="col-md-6">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Revenue Breakdown</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Amount</th>
                                                <th>%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Memberships</td>
                                                <td><?php echo formatCurrency($revenue['membership_revenue'] ?? 0); ?></td>
                                                <td><?php echo $total_revenue > 0 ? number_format(($revenue['membership_revenue'] / $total_revenue) * 100, 1) : 0; ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Renewals</td>
                                                <td><?php echo formatCurrency($revenue['renewal_revenue'] ?? 0); ?></td>
                                                <td><?php echo $total_revenue > 0 ? number_format(($revenue['renewal_revenue'] / $total_revenue) * 100, 1) : 0; ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Walk-ins</td>
                                                <td><?php echo formatCurrency($revenue['walkin_revenue'] ?? 0); ?></td>
                                                <td><?php echo $total_revenue > 0 ? number_format(($revenue['walkin_revenue'] / $total_revenue) * 100, 1) : 0; ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>PT Packages</td>
                                                <td><?php echo formatCurrency($revenue['pt_package_revenue'] ?? 0); ?></td>
                                                <td><?php echo $total_revenue > 0 ? number_format(($revenue['pt_package_revenue'] / $total_revenue) * 100, 1) : 0; ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>PT Sessions</td>
                                                <td><?php echo formatCurrency($revenue['pt_session_revenue'] ?? 0); ?></td>
                                                <td><?php echo $total_revenue > 0 ? number_format(($revenue['pt_session_revenue'] / $total_revenue) * 100, 1) : 0; ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Other</td>
                                                <td><?php echo formatCurrency($revenue['other_revenue'] ?? 0); ?></td>
                                                <td><?php echo $total_revenue > 0 ? number_format(($revenue['other_revenue'] / $total_revenue) * 100, 1) : 0; ?>%</td>
                                            </tr>
                                            <tr class="fw-bold">
                                                <td>TOTAL</td>
                                                <td><?php echo formatCurrency($total_revenue); ?></td>
                                                <td>100%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Expense Breakdown -->
                    <div class="col-md-6">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Expense Breakdown</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Amount</th>
                                                <th>%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($expense_categories)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No expenses recorded</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($expense_categories as $cat): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                                    <td><?php echo formatCurrency($cat['category_total']); ?></td>
                                                    <td><?php echo $total_expenses > 0 ? number_format(($cat['category_total'] / $total_expenses) * 100, 1) : 0; ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <tr class="fw-bold">
                                                    <td>TOTAL</td>
                                                    <td><?php echo formatCurrency($total_expenses); ?></td>
                                                    <td>100%</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>