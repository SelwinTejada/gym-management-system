<?php
/**
 * Payment List
 * Gym Management System
 * 
 * File: payments/index.php
 * Purpose: Display and manage all payments
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!canProcessPayments()) {
    displayError('You do not have permission to view payments.');
    redirect('../dashboard.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'completed';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

$where_conditions[] = "p.payment_date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

if (!empty($search)) {
    $where_conditions[] = "(p.transaction_number LIKE ? OR p.walkin_name LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($type !== 'all') {
    $where_conditions[] = "p.transaction_type = ?";
    $params[] = $type;
}

if ($status !== 'all') {
    $where_conditions[] = "p.status = ?";
    $params[] = $status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
try {
    $count_sql = "SELECT COUNT(*) as total 
                  FROM payments p
                  LEFT JOIN members m ON p.member_id = m.member_id
                  $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('Payment count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
}

// Get payments
try {
    $sql = "SELECT p.*, 
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            u.full_name as processed_by_name
            FROM payments p
            LEFT JOIN members m ON p.member_id = m.member_id
            LEFT JOIN users u ON p.processed_by = u.user_id
            $where_clause
            ORDER BY p.payment_date DESC, p.payment_time DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Payment list error: ' . $e->getMessage());
    $payments = [];
}

// Get totals
try {
    $total_sql = "SELECT 
                    SUM(total) as total_amount,
                    SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_method = 'gcash' THEN total ELSE 0 END) as gcash_total,
                    SUM(CASE WHEN payment_method IN ('bank_transfer', 'card', 'other') THEN total ELSE 0 END) as other_total
                  FROM payments p
                  $where_clause";
    $stmt = $pdo->prepare($total_sql);
    $stmt->execute(array_slice($params, 0, count($params) - 2)); // Remove limit and offset
    $totals = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Payment totals error: ' . $e->getMessage());
    $totals = ['total_amount' => 0, 'cash_total' => 0, 'gcash_total' => 0, 'other_total' => 0];
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
                <!-- Page Header -->
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                    <h4 class="mb-0"><i class="bi bi-credit-card"></i> Payments</h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                            <i class="bi bi-funnel"></i> Filters
                        </button>
                        <a href="create.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> New Payment
                        </a>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon revenue">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Total Revenue</h5>
                                <h2><?php echo formatCurrency($totals['total_amount'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                <i class="bi bi-cash"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Cash</h5>
                                <h2><?php echo formatCurrency($totals['cash_total'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                                <i class="bi bi-phone"></i>
                            </div>
                            <div class="stat-content">
                                <h5>GCash</h5>
                                <h2><?php echo formatCurrency($totals['gcash_total'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(108, 117, 125, 0.1); color: #6c757d;">
                                <i class="bi bi-credit-card"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Other</h5>
                                <h2><?php echo formatCurrency($totals['other_total'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Bar -->
                <div class="collapse <?php echo (!empty($search) || $type !== 'all' || $status !== 'all') ? 'show' : ''; ?>" id="filterCollapse">
                    <div class="card modern-card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Transaction #, Customer" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Type</label>
                                    <select class="form-select" name="type">
                                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                        <option value="membership" <?php echo $type === 'membership' ? 'selected' : ''; ?>>Membership</option>
                                        <option value="renewal" <?php echo $type === 'renewal' ? 'selected' : ''; ?>>Renewal</option>
                                        <option value="walkin" <?php echo $type === 'walkin' ? 'selected' : ''; ?>>Walk-in</option>
                                        <option value="pt_package" <?php echo $type === 'pt_package' ? 'selected' : ''; ?>>PT Package</option>
                                        <option value="pt_session" <?php echo $type === 'pt_session' ? 'selected' : ''; ?>>PT Session</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="void" <?php echo $status === 'void' ? 'selected' : ''; ?>>Void</option>
                                        <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Date From</label>
                                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Date To</label>
                                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Table -->
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Transaction #</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-credit-card" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No payments found.
                                            <?php if (!empty($search) || $type !== 'all'): ?>
                                            <br>Try adjusting your filters.
                                            <?php else: ?>
                                            <br><a href="create.php" class="btn btn-primary btn-sm mt-2">Process First Payment</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($payment['transaction_number']); ?></code></td>
                                            <td>
                                                <?php if (!empty($payment['member_name'])): ?>
                                                    <?php echo htmlspecialchars($payment['member_name']); ?>
                                                <?php elseif (!empty($payment['walkin_name'])): ?>
                                                    <?php echo htmlspecialchars($payment['walkin_name']); ?>
                                                    <br><small class="text-muted">(Walk-in)</small>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst(str_replace('_', ' ', $payment['transaction_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo formatDate($payment['payment_date']); ?>
                                                <br><small class="text-muted"><?php echo formatTime($payment['payment_time']); ?></small>
                                            </td>
                                            <td><strong><?php echo formatCurrency($payment['total']); ?></strong></td>
                                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                            <td>
                                                <?php if ($payment['status'] === PAYMENT_COMPLETED): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php elseif ($payment['status'] === PAYMENT_VOID): ?>
                                                    <span class="badge bg-danger">Void</span>
                                                <?php elseif ($payment['status'] === PAYMENT_REFUNDED): ?>
                                                    <span class="badge bg-warning text-dark">Refunded</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-outline-primary" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="receipt.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-outline-secondary" title="Receipt" target="_blank">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                    <?php if (isOwner() && $payment['status'] === PAYMENT_COMPLETED): ?>
                                                    <button class="btn btn-outline-danger" title="Void" onclick="voidPayment(<?php echo $payment['payment_id']; ?>)">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-transparent">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <script>
    function voidPayment(id) {
        if (confirm('Are you sure you want to void this payment? This action cannot be undone.')) {
            window.location.href = 'void.php?id=' + id;
        }
    }
    </script>
</body>
</html>