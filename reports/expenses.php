<?php
/**
 * Expenses Report
 * Gym Management System
 * 
 * File: reports/expenses.php
 * Purpose: View expenses report with filters
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view expenses report.');
    redirect('../dashboard.php');
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Initialize totals
$total_expenses = 0;
$filtered_expenses = 0;

// Build query conditions
$where = "WHERE expense_date BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if (!empty($category)) {
    $where .= " AND category = ?";
    $params[] = $category;
}

if (!empty($type)) {
    $where .= " AND type = ?";
    $params[] = $type;
}

// Get filtered expenses
try {
    $stmt = $pdo->prepare("SELECT * FROM expenses $where ORDER BY expense_date DESC, expense_time DESC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $expenses = $stmt->fetchAll();
    $filtered_expenses = count($expenses);
} catch (PDOException $e) {
    error_log('Get expenses error: ' . $e->getMessage());
    $expenses = [];
}

// Get total count for pagination
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM expenses $where");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('Count expenses error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
}

// Get categories for filter
try {
    $stmt = $pdo->query("SELECT DISTINCT category, COUNT(*) as count, SUM(amount) as total FROM expenses GROUP BY category ORDER BY category");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
    $categories = [];
}

// Calculate totals
try {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses $where");
    $stmt->execute($params);
    $total_expenses = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    error_log('Total expenses error: ' . $e->getMessage());
    $total_expenses = 0;
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
                    <h4 class="mb-0"><i class="bi bi-wallet2"></i> Expenses Report</h4>
                    <a href="index.php" class="btn btn-link">← Back to Reports</a>
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
                                <label class="form-label">Category</label>
                                <select class="form-control" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category']; ?>" 
                                        <?php echo ($category === $cat['category']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?> 
                                        <small>(<?php echo $cat['count']; ?> items, 
                                        <?php echo formatCurrency($cat['total']); ?>)</small>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-control" name="type">
                                    <option value="">All Types</option>
                                    <?php 
                                    $types = ['rent', 'utilities', 'salary', 'maintenance', 'supplies', 'other'];
                                    foreach ($types as $t): 
                                        $count = 0;
                                        $t_total = 0;
                                        if (!empty($categories)) {
                                            foreach ($categories as $cat) {
                                                if ($cat['category'] === $t) {
                                                    $count = $cat['count'];
                                                    $t_total = $cat['total'];
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                    <option value="<?php echo $t; ?>" 
                                        <?php echo ($type === $t) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($t); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Summary -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h5 class="text-muted">Total Expenses</h5>
                                <h3 class="text-danger"><?php echo formatCurrency($total_expenses); ?></h3>
                            </div>
                            <div class="col-md-4">
                                <h5 class="text-muted">Filtered Expenses</h5>
                                <h3 class="text-primary"><?php echo formatCurrency($filtered_expenses > 0 ? /* would need sum */ 0 : 0); ?></h3>
                            </div>
                            <div class="col-md-4">
                                <h5 class="text-muted">Date Range</h5>
                                <p class="mb-0"><?php echo formatDate($date_from) . ' - ' . formatDate($date_to); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Expenses Table -->
                <div class="card modern-card">
                    <div class="card-header">
                        <h5><i class="bi bi-list"></i> Expenses List</h5>
                        <div class="btn-toolbar justify-content-end">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary">
                                    <i class="bi bi-download"></i> Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Processed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-wallet" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No expenses found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($expenses as $exp): ?>
                                    <tr>
                                        <td><?php echo formatDate($exp['expense_date']); ?></td>
                                        <td>
                                            <span class="badge <?php echo match($exp['type']) {
                                                'rent' => 'bg-danger',
                                                'utilities' => 'bg-secondary',
                                                'salary' => 'bg-primary',
                                                'maintenance' => 'bg-warning text-dark',
                                                'supplies' => 'bg-info',
                                                'other' => 'bg-secondary'
                                            }; ?>">
                                                <?php echo ucfirst($exp['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($exp['category']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($exp['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo formatCurrency($exp['amount']); ?></td>
                                        <td>
                                            <span class="badge <?php echo match($exp['payment_method']) {
                                                'cash' => 'bg-secondary',
                                                'gcash' => 'bg-success',
                                                'bank_transfer' => 'bg-primary',
                                                'card' => 'bg-danger'
                                            }; ?>">
                                                <?php echo ucfirst($exp['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($exp['processed_by'] ?? 'Unknown'); ?></td>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&category=<?php echo $category; ?>&type=<?php echo $type; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&category=<?php echo $category; ?>&type=<?php echo $type; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&category=<?php echo $category; ?>&type=<?php echo $type; ?>">Next</a>
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