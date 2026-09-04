<?php
/**
 * Walk-ins List
 * Gym Management System
 * 
 * File: walkins/index.php
 * Purpose: View walk-in records
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view walk-ins.');
    redirect('../dashboard.php');
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get walk-in records
try {
    $sql = "SELECT a.*, u.full_name as processed_by_name, p.transaction_number, p.amount_paid
            FROM attendance a
            LEFT JOIN users u ON a.processed_by = u.user_id
            LEFT JOIN payments p ON p.walkin_name = a.walkin_name AND p.payment_date = a.check_in_date
            WHERE a.member_id IS NULL AND a.check_in_date BETWEEN ? AND ?
            ORDER BY a.check_in_date DESC, a.check_in_time DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_from, $date_to, $limit, $offset]);
    $walkins = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get walkins error: ' . $e->getMessage());
    $walkins = [];
}

// Get total count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE member_id IS NULL AND check_in_date BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('Count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
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
                    <h4 class="mb-0"><i class="bi bi-person-walking"></i> Walk-in Records</h4>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Walk-in
                    </a>
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
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Walk-ins Table -->
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Processed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($walkins)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-person-walking" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No walk-in records found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($walkins as $walkin): ?>
                                        <tr>
                                            <td><?php echo formatDate($walkin['check_in_date']); ?></td>
                                            <td><?php echo formatTime($walkin['check_in_time']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($walkin['walkin_name'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $walkin['customer_type'] === 'student' ? 'bg-info' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($walkin['customer_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatCurrency($walkin['amount_paid'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($walkin['processed_by_name'] ?? 'Unknown'); ?></td>
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
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Next</a>
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
</body>
</html>