<?php
/**
 * Expense Categories
 * Gym Management System
 * 
 * File: expenses/categories.php
 * Purpose: Manage expense categories
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions - only owner can manage categories
if (!isOwner()) {
    displayError('You do not have permission to manage expense categories.');
    redirect('../dashboard.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$is_active = isset($_GET['is_active']) ? filter_var($_GET['is_active'], FILTER_VALIDATE_BOOLEAN) : null;

// Initialize counters
$total_categories = 0;
$active_count = 0;
$inactive_count = 0;

// Get categories
try {
    $where = '';
    $params = [];
    
    if (!empty($search)) {
        $where = 'WHERE category_name LIKE ?';
        $params[] = '%' . $search . '%';
    }
    
    if ($is_active !== null) {
        if (!empty($where)) $where .= ' AND ';
        $where .= 'is_active = ?';
        $params[] = $is_active ? 1 : 0;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM expense_categories $where ORDER BY display_order, category_name");
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    // Count totals
    $total_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM expense_categories" . ($where ? ' ' . $where : ''));
    $total_stmt->execute($params);
    $total_categories = $total_stmt->fetch()['count'] ?? 0;
    
    // Count active/inactive
    $active_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM expense_categories WHERE is_active = 1");
    $active_stmt->execute();
    $active_count = $active_stmt->fetch()['count'] ?? 0;
    
    $inactive_count = $total_categories - $active_count;
    
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
    $categories = [];
    $total_categories = 0;
    $active_count = 0;
    $inactive_count = 0;
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
                    <h4 class="mb-0"><i class="bi bi-tags"></i> Expense Categories</h4>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Category
                    </a>
                </div>
                
                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card modern-card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo number_format($active_count); ?></h3>
                                <h5 class="text-muted">Active</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card modern-card text-center">
                            <div class="card-body">
                                <h3 class="text-secondary"><?php echo number_format($inactive_count); ?></h3>
                                <h5 class="text-muted">Inactive</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card modern-card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo number_format($total_categories); ?></h3>
                                <h5 class="text-muted">Total</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <!-- Search form -->
                        <form method="GET" action="" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" 
                                   placeholder="Search categories" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Categories Table -->
                <div class="card modern-card">
                    <div class="card-header">
                        <h5><i class="bi bi-list-ul"></i> Categories List</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Expenses</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            <i class="bi bi-list-ul" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No categories found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($categories as $cat): 
                                        $status_class = $cat['is_active'] ? 'bg-success' : 'bg-secondary';
                                        $status_text = $cat['is_active'] ? 'Active' : 'Inactive';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cat['category_name']); ?></strong>
                                            <?php if (!empty($cat['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($cat['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            // Count expenses in this category
                                            $cat_stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM expenses WHERE category_id = ? AND is_active = 1");
                                            $cat_stmt->execute([$cat['category_id']]);
                                            $cat_data = $cat_stmt->fetch();
                                            ?>
                                            <span class="text-muted small">
                                                <?php echo number_format($cat_data['count'] ?? 0); ?> expenses
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($cat['is_active']): ?>
                                                <a href="edit.php?id=<?php echo $cat['category_id']; ?>" class="btn btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-danger" onclick="toggleCategory(<?php echo $cat['category_id']; ?>, <?php echo $cat['is_active'] ? '0' : '1'; ?>)">
                                                    <i class="bi bi-eye-off"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <script>
    function toggleCategory(id, currentStatus) {
        if (confirm('Are you sure you want to ' + (currentStatus === '1' ? 'deactivate' : 'activate') + ' this category?')) {
            window.location.href = 'toggle.php?id=' + id + '&status=' + currentStatus;
        }
    }
    </script>
</body>
</html>