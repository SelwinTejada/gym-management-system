<?php
/**
 * Attendance List
 * Gym Management System
 * 
 * File: attendance/index.php
 * Purpose: View attendance records
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view attendance.');
    redirect('../dashboard.php');
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get attendance records
try {
    $sql = "SELECT a.*, 
            CASE 
                WHEN a.member_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name)
                ELSE a.walkin_name
            END as customer_name,
            m.member_number,
            u.full_name as processed_by_name
            FROM attendance a
            LEFT JOIN members m ON a.member_id = m.member_id
            LEFT JOIN users u ON a.processed_by = u.user_id
            WHERE a.check_in_date BETWEEN ? AND ?
            ORDER BY a.check_in_date DESC, a.check_in_time DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_from, $date_to, $limit, $offset]);
    $attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get attendance error: ' . $e->getMessage());
    $attendance = [];
}

// Get total count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE check_in_date BETWEEN ? AND ?");
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
                    <h4 class="mb-0"><i class="bi bi-clock-history"></i> Attendance Records</h4>
                    <a href="checkin.php" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Check In
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
                
                <!-- Attendance Table -->
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
                                        <th>Member ID</th>
                                        <th>Processed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-clock" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No attendance records found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance as $record): ?>
                                        <tr>
                                            <td><?php echo formatDate($record['check_in_date']); ?></td>
                                            <td><?php echo formatTime($record['check_in_time']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($record['customer_name'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $record['customer_type'] === 'student' ? 'bg-info' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($record['customer_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['member_number'] ?? 'Walk-in'); ?></td>
                                            <td><?php echo htmlspecialchars($record['processed_by_name'] ?? 'Unknown'); ?></td>
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