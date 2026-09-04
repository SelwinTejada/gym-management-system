<?php
/**
 * Membership Report
 * Gym Management System
 * 
 * File: reports/memberships.php
 * Purpose: View membership statistics and reports
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view membership report.');
    redirect('../dashboard.php');
}

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

// Initialize counters
$active_count = 0;
$expiring_count = 0;
$expired_count = 0;
$total_members = 0;

// Build status condition
$status_where = '';
$status_params = [];

if (!empty($status)) {
    $status_where = 'WHERE mm.status = ?';
    $status_params = [$status];
}

// Get membership data
try {
    $stmt = $pdo->prepare("
        SELECT m.member_id, m.first_name, m.last_name, m.member_number,
               mp.plan_name, mm.status as membership_status, mm.expiration_date,
               mm.join_date, mm.renewal_fee
        FROM members m
        INNER JOIN member_memberships mm ON m.member_id = mm.member_id
        INNER JOIN membership_plans mp ON mm.plan_id = mp.plan_id
        $status_where
        ORDER BY mm.status, m.last_name, m.first_name
    ");
    $stmt->execute(array_merge($status_params, [$date_from, $date_to]));
    $members = $stmt->fetchAll();
    $total_members = count($members);
} catch (PDOException $e) {
    error_log('Get memberships error: ' . $e->getMessage());
    $members = [];
}

// Count by status
try {
    $stmt = $pdo->prepare("
        SELECT mm.status, COUNT(*) as count
        FROM member_memberships mm
        INNER JOIN members m ON mm.member_id = m.member_id
        WHERE m.status = 'active'
        GROUP BY mm.status
    ");
    $stmt->execute();
    $status_counts = $stmt->fetchAll();
    
    foreach ($status_counts as $sc) {
        if ($sc['status'] === 'active') {
            $active_count = $sc['count'];
        } elseif ($sc['status'] === 'expired') {
            $expired_count = $sc['count'];
        }
    }
} catch (PDOException $e) {
    error_log('Count membership status error: ' . $e->getMessage());
    $status_counts = [];
}

// Count expiring members
try {
    $grace_period = defined('DEFAULT_MEMBERSHIP_GRACE_PERIOD') ? DEFAULT_MEMBERSHIP_GRACE_PERIOD : 7;
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT m.member_id) as count
        FROM members m
        INNER JOIN member_memberships mm ON m.member_id = mm.member_id
        WHERE mm.status = 'active'
        AND mm.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ");
    $stmt->execute([$grace_period]);
    $expiring_count = $stmt->fetch()['count'] ?? 0;
} catch (PDOException $e) {
    error_log('Count expiring error: ' . $e->getMessage());
    $expiring_count = 0;
}

// Get status options for filter
$status_options = ['active', 'expired', 'suspended', 'cancelled'];

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
                    <h4 class="mb-0"><i class="bi bi-card-list"></i> Membership Report</h5>
                    <small class="text-muted">Total Members: <?php echo number_format($total_members); ?></small>
                </div>
                
                <!-- Status Summary -->
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
                                <h3 class="text-warning"><?php echo number_format($expiring_count); ?></h3>
                                <h5 class="text-muted">Expiring Soon</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card modern-card text-center">
                            <div class="card-body">
                                <h3 class="text-danger"><?php echo number_format($expired_count); ?></h3>
                                <h5 class="text-muted">Expired</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card modern-card text-center">
                            <div class="card-body">
                                <h3 class="text-secondary"><?php echo number_format($total_members - $active_count - $expiring_count - $expired_count); ?></h3>
                                <h5 class="text-muted">Other</h5>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status Filter</label>
                                <select class="form-control" name="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($status_options as $s): ?>
                                    <option value="<?php echo $s; ?>" 
                                        <?php echo ($status === $s) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($s); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
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
                
                <!-- Members List -->
                <div class="card modern-card">
                    <div class="card-header">
                        <h5><i class="bi bi-person"></i> Members List</h5>
                        <span class="badge bg-info"><?php echo number_format($total_members); ?> total members</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Member Number</th>
                                        <th>Plan</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Expires</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($members)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-person" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No members found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($members as $member): 
                                        $is_expired = !empty($member['expiration_date']) && isDatePast($member['expiration_date']);
                                        $status_class = $is_expired ? 'bg-danger' : ($member['membership_status'] === 'active' ? 'bg-success' : 'bg-warning');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo $member['member_number']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['member_number']); ?></td>
                                        <td><?php echo htmlspecialchars($member['plan_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($member['membership_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($member['join_date']); ?></td>
                                        <td>
                                            <?php if (!empty($member['expiration_date'])): ?>
                                            <span class="text-warning">
                                                <?php echo formatDate($member['expiration_date']); ?>
                                                <br><small>(<?php echo daysDifference(date('Y-m-d'), $member['expiration_date']); ?> days)</small>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-secondary">No expiration</span>
                                            <?php endif; ?>
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
</body>
</html>