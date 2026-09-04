<?php
/**
 * Expenses List
 * Gym Management System
 * 
 * File: expenses/index.php
 * Purpose: View and manage expenses
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to view expenses.');
    redirect('../dashboard.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

$where_conditions[] = "e.expense_date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

if (!empty($search)) {
    $where_conditions[] = "(e.description LIKE ? OR e.reference_number LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category > 0) {
    $where_conditions[] = "e.category_id = ?";
    $params[] = $category;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
try {
    $count_sql = "SELECT COUNT(*) as total FROM expenses e $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('Expense count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
}

// Get expenses
try {
    $sql = "SELECT e.*, ec.category_name, u.full_name as recorded_by_name
            FROM expenses e
            INNER JOIN expense_categories ec ON e.category_id = ec.category_id
            LEFT JOIN users u ON e.recorded_by = u.user_id
            $where_clause
            ORDER BY e.expense_date DESC, e.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Expense list error: ' . $e->getMessage());
    $expenses = [];
}

// Get categories for filter
try {
    $stmt = $pdo->query("SELECT category_id, category_name FROM expense_categories WHERE is_active = 1 ORDER BY display_order");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
    $categories = [];
}

// Get totals
try {
    $total_sql = "SELECT SUM(amount) as total_amount FROM expenses e $where_clause";
    $stmt = $pdo->prepare($total_sql);
    $stmt->execute(array_slice($params, 0, count($params)));
    $total_amount = $stmt->fetch()['total_amount'] ?? 0;
} catch (PDOException $e) {
    error_log('Expense totals error: ' . $e->getMessage());
    $total_amount = 0;
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
                    <h4 class="mb-0"><i class="bi bi-wallet2"></i> Expenses</h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                            <i class="bi bi-funnel"></i> Filters
                        </button>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Expense
                        </a>
                        <a href="categories.php" class="btn btn-outline-secondary">
                            <i class="bi bi-tags"></i> Categories
                        </a>
                    </div>
                </div>
                
                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-4 offset-md-8">
                        <div class="stat-card">
                            <div class="stat-icon expenses">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Total Expenses</h5>
                                <h2><?php echo formatCurrency($total_amount); ?></h2>
                                <small class="text-muted"><?php echo formatDate($date_from); ?> - <?php echo formatDate($date_to); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Bar -->
                <div class="collapse <?php echo (!empty($search) || $category > 0) ? 'show' : ''; ?>" id="filterCollapse">
                    <div class="card modern-card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Description or Reference" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category">
                                        <option value="0">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
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
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Expenses Table -->
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Reference</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-wallet2" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No expenses found.
                                            <?php if (!empty($search) || $category > 0): ?>
                                            <br>Try adjusting your filters.
                                            <?php else: ?>
                                            <br><a href="add.php" class="btn btn-primary btn-sm mt-2">Add Your First Expense</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?php echo formatDate($expense['expense_date']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($expense['category_name']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                            <td><strong><?php echo formatCurrency($expense['amount']); ?></strong></td>
                                            <td><?php echo ucfirst($expense['payment_method']); ?></td>
                                            <td><?php echo htmlspecialchars($expense['reference_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($expense['recorded_by_name'] ?? 'Unknown'); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $expense['expense_id']; ?>" class="btn btn-outline-secondary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button class="btn btn-outline-danger" onclick="deleteExpense(<?php echo $expense['expense_id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Next</a>
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
    function deleteExpense(id) {
        if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
            window.location.href = 'delete.php?id=' + id;
        }
    }
    </script>
</body>
</html>