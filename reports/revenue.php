<?php
/**
 * Revenue Report
 * Gym Management System
 * 
 * File: reports/revenue.php
 * Purpose: View revenue breakdown and analysis
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view revenue report.');
    redirect('../dashboard.php');
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$payment_method = isset($_GET['method']) ? sanitize($_GET['method']) : '';
$min_amount = isset($_GET['min']) ? (float)$_GET['min'] : 0;

// Build query conditions
$where = "WHERE payment_date BETWEEN ? AND ? AND status = 'completed'";
$params = [$date_from, $date_to];

if (!empty($payment_method)) {
    $where .= " AND payment_method = ?";
    $params[] = $payment_method;
}

// Get revenue data
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.member_number,
               pm.name as payment_method_name
        FROM payments p
        LEFT JOIN members m ON p.member_id = m.member_id
        LEFT JOIN payment_methods pm ON p.payment_method = pm.code
        $where
        ORDER BY p.payment_date DESC, p.payment_time DESC
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get revenue error: ' . $e->getMessage());
    $payments = [];
}

// Get total count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payments $where");
    $stmt->execute($params);
    $total_payments = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total_payments / ITEMS_PER_PAGE);
} catch (PDOException $e) {
    error_log('Count revenue error: ' . $e->getMessage());
    $total_payments = 0;
    $total_pages = 1;
}

// Calculate totals
try {
    // Overall total
    $stmt = $pdo->prepare("SELECT SUM(total) as total FROM payments $where");
    $stmt->execute($params);
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
    // By payment method
    $method_totals = [];
    if (!empty($payment_method)) {
        $stmt = $pdo->prepare("SELECT payment_method, SUM(total) as total FROM payments $where GROUP BY payment_method");
        $stmt->execute($params);
        $method_totals = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Revenue totals error: ' . $e->getMessage());
    $total_revenue = 0;
    $method_totals = [];
}

// Get payment methods for filter
try {
    $stmt = $pdo->query("SELECT * FROM payment_methods ORDER BY name");
    $payment_methods = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get payment methods error: ' . $e->getMessage());
    $payment_methods = [];
}

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
                    <h4 class="mb-0"><i class="bi bi-currency-dollar"></i> Revenue Report</h4>
                    <small class="text-muted">Period: <?php echo formatDate($date_from) . ' - ' . formatDate($date_to); ?></small>
                </div>
                
                <!-- Summary -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h5 class="text-muted">Total Revenue</h5>
                                <h3 class="text-success"><?php echo formatCurrency($total_revenue); ?></h3>
                            </div>
                            <div class="col-md-4">
                                <h5 class="text-muted">Total Transactions</h5>
                                <h3 class="text-primary"><?php echo number_format($total_payments); ?></h3>
                            </div>
                            <div class="col-md-4">
                                <h5 class="text-muted">Average per Transaction</h5>
                                <h3 class="text-info">
                                    <?php echo $total_payments > 0 ? formatCurrency($total_revenue / $total_payments) : '₱0.00'; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-control" name="method">
                                    <option value="">All Methods</option>
                                    <?php foreach ($payment_methods as $pm): ?>
                                    <option value="<?php echo $pm['code']; ?>" 
                                        <?php echo ($payment_method === $pm['code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pm['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Minimum Amount</label>
                                <input type="number" class="form-control" name="min" step="0.01" value="<?php echo $min_amount; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Revenue by Payment Method -->
                <?php if (!empty($method_totals)): ?>
                <div class="card modern-card mb-4">
                    <div class="card-header">
                        <h5><i class="bi bi-bar-chart"></i> Revenue by Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Payment Method</th>
                                        <th>Transactions</th>
                                        <th>Amount</th>
                                        <th>Average</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($method_totals as $mt): 
                                        $avg = $mt['total'] / (/* would need count */ 1);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($mt['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($avg); ?></td>
                                        <td><?php echo formatCurrency($mt['total']); ?></td>
                                        <td><?php echo formatCurrency($avg); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Payments Table -->
                <div class="card modern-card">
                    <div class="card-header">
                        <h5><i class="bi bi-receipt"></i> Payments List</h5>
                        <span class="badge bg-success"><?php echo number_format($total_payments); ?> transactions</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-receipt" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No payments found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($payments as $pay): 
                                        $status_class = $pay['status'] === 'completed' ? 'bg-success' : 'bg-secondary';
                                    ?>
                                    <tr>
                                        <td><?php echo formatDate($pay['payment_date']); ?></td>
                                        <td><?php echo formatTime($pay['payment_time']); ?></td>
                                        <td>
                                            <?php if (!empty($pay['member_name'])): ?>
                                            <?php echo htmlspecialchars($pay['member_name']); ?> 
                                            <br><small class="text-muted"><?php echo $pay['member_number'] ?? ''; ?></small>
                                            <?php else: ?>
                                            Walk-in
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatCurrency($pay['total']); ?></td>
                                        <td>
                                            <span class="badge <?php echo match($pay['payment_method_name'] ?? '') {
                                                'cash' => 'bg-secondary',
                                                'gcash' => 'bg-success',
                                                'bank_transfer' => 'bg-primary',
                                                'card' => 'bg-danger'
                                            }; ?>">
                                                <?php echo htmlspecialchars($pay['payment_method_name'] ?? ucfirst($pay['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $pay['status'] === 'completed' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($pay['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-transparent">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&method=<?php echo $payment_method; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&method=<?php echo $payment_method; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&method=<?php echo $payment_method; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>