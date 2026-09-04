<?php
/**
 * Trainer Report
 * Gym Management System
 * 
 * File: reports/trainers.php
 * Purpose: View trainer performance and statistics
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view trainer report.');
    redirect('../dashboard.php');
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$trainer_id = isset($_GET['trainer']) ? (int)$_GET['trainer'] : 0;

// Build trainer condition
$trainer_where = '';
if ($trainer_id > 0) {
    $trainer_where = 'AND u.user_id = ?';
}

// Get trainer data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name,
               COUNT(ps.session_id) as total_sessions,
               COUNT(CASE WHEN ps.status = 'completed' THEN 1 END) as completed_sessions,
               COUNT(CASE WHEN ps.status = 'scheduled' THEN 1 END) as scheduled_sessions,
               COUNT(CASE WHEN ps.status = 'cancelled' THEN 1 END) as cancelled_sessions,
               COALESCE(SUM(CASE WHEN ps.status = 'completed' THEN ps.amount ELSE 0 END), 0) as total_revenue,
               COALESCE(AVG(CASE WHEN ps.status = 'completed' THEN ps.amount ELSE NULL END), 0) as avg_session_price
        FROM users u
        LEFT JOIN pt_sessions ps ON u.user_id = ps.trainer_id
        WHERE u.role = 'trainer' $trainer_where
        AND ps.session_date BETWEEN ? AND ?
        GROUP BY u.user_id, u.first_name, u.last_name
        ORDER BY total_revenue DESC
    ");
    $params = [$date_from, $date_to];
    if ($trainer_id > 0) {
        $params = [$trainer_id, $date_from, $date_to];
    }
    $stmt->execute($params);
    $trainers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get trainers error: ' . $e->getMessage());
    $trainers = [];
}

// Get all trainers for filter
try {
    $stmt = $pdo->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'trainer' ORDER BY first_name");
    $all_trainers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get all trainers error: ' . $e->getMessage());
    $all_trainers = [];
}

// Calculate overall stats
try {
    $rev_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN ps.status = 'completed' THEN ps.amount ELSE 0 END), 0) as total_revenue,
        COUNT(ps.session_id) as total_sessions,
        COUNT(CASE WHEN ps.status = 'completed' THEN 1 END) as completed_count
        FROM pt_sessions ps
        WHERE ps.session_date BETWEEN ? AND ?
    ");
    $rev_stmt->execute([$date_from, $date_to]);
    $overall = $rev_stmt->fetch();
} catch (PDOException $e) {
    error_log('Overall trainer stats error: ' . $e->getMessage());
    $overall = ['total_revenue' => 0, 'total_sessions' => 0, 'completed_count' => 0];
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
                    <h4 class="mb-0"><i class="bi bi-person-badge"></i> Trainer Report</h4>
                    <small class="text-muted">Period: <?php echo formatDate($date_from) . ' - ' . formatDate($date_to); ?></small>
                </div>
                
                <!-- Overall Statistics -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="summary-item">
                                    <small class="text-muted">Total Sessions</small>
                                    <h4 class="text-primary"><?php echo number_format($overall['total_sessions'] ?? 0); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-item">
                                    <small class="text-muted">Completed</small>
                                    <h4 class="text-success"><?php echo number_format($overall['completed_count'] ?? 0); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-item">
                                    <small class="text-muted">Total Revenue</small>
                                    <h4 class="text-success"><?php echo formatCurrency($overall['total_revenue'] ?? 0); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-item">
                                    <small class="text-muted">Completion Rate</small>
                                    <h4>
                                        <?php 
                                        $total = $overall['total_sessions'] ?? 0;
                                        $completed = $overall['completed_count'] ?? 0;
                                        echo $total > 0 ? number_format(($completed / $total) * 100, 1) . '%' : '0.0%';
                                        ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Trainer Filter -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Select Trainer</label>
                                <select class="form-control" name="trainer">
                                    <option value="">All Trainers</option>
                                    <?php foreach ($all_trainers as $t): ?>
                                    <option value="<?php echo $t['user_id']; ?>" 
                                        <?php echo ($trainer_id == $t['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Trainers Table -->
                <div class="card modern-card">
                    <div class="card-header">
                        <h5><i class="bi bi-person-badge"></i> Trainer Performance</h5>
                        <span class="badge bg-primary"><?php echo count($trainers); ?> trainers</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Trainer</th>
                                        <th>Sessions</th>
                                        <th>Completed</th>
                                        <th>Cancelled</th>
                                        <th>Scheduled</th>
                                        <th>Revenue</th>
                                        <th>Avg per Session</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($trainers)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-person-badge" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No trainer data found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($trainers as $trainer): 
                                        $completion_rate = $trainer['total_sessions'] > 0 
                                            ? number_format(($trainer['completed_sessions'] / $trainer['total_sessions']) * 100, 1) 
                                            : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($trainer['full_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo $trainer['employee_id'] ?? 'N/A'; ?></small>
                                        </td>
                                        <td><?php echo number_format($trainer['total_sessions'] ?? 0); ?></td>
                                        <td><?php echo number_format($trainer['completed_sessions'] ?? 0); ?></td>
                                        <td><?php echo number_format($trainer['cancelled_sessions'] ?? 0); ?></td>
                                        <td><?php echo number_format($trainer['scheduled_sessions'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($trainer['total_revenue'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($trainer['avg_session_price'] ?? 0); ?></td>
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
</body>
</html>