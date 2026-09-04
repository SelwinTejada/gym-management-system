<?php
/**
 * Dashboard
 * Gym Management System
 * 
 * File: dashboard.php
 * Purpose: Main dashboard with statistics and quick actions
 */

// Load configuration
require_once 'config/config.php';

// Require authentication
requireAuth();

// Get current user
$user = getCurrentUser();

// Get today's date
$today = date('Y-m-d');
$current_month = date('Y-m');

// Initialize statistics
$stats = [
    'today_revenue' => 0,
    'today_expenses' => 0,
    'today_profit' => 0,
    'today_checkins' => 0,
    'active_members' => 0,
    'expiring_members' => 0,
    'expired_members' => 0,
    'monthly_revenue' => 0,
    'monthly_expenses' => 0,
    'monthly_profit' => 0,
    'recent_checkins' => [],
    'expiring_members_list' => [],
];

// Get statistics from database
try {
    // Today's revenue
    $stmt = $pdo->prepare("SELECT SUM(total) as total FROM payments WHERE payment_date = ? AND status = 'completed'");
    $stmt->execute([$today]);
    $stats['today_revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Today's expenses
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date = ?");
    $stmt->execute([$today]);
    $stats['today_expenses'] = $stmt->fetch()['total'] ?? 0;
    
    // Today's profit
    $stats['today_profit'] = $stats['today_revenue'] - $stats['today_expenses'];
    
    // Today's check-ins
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE check_in_date = ?");
    $stmt->execute([$today]);
    $stats['today_checkins'] = $stmt->fetch()['count'] ?? 0;
    
    // Active members
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT m.member_id) as count 
                           FROM members m 
                           INNER JOIN member_memberships mm ON m.member_id = mm.member_id 
                           WHERE mm.status = 'active' AND mm.expiration_date >= CURDATE()
                           AND m.status = 'active'");
    $stmt->execute();
    $stats['active_members'] = $stmt->fetch()['count'] ?? 0;
    
    // Expiring members (within 7 days)
    $grace_period = defined('DEFAULT_MEMBERSHIP_GRACE_PERIOD') ? DEFAULT_MEMBERSHIP_GRACE_PERIOD : 7;
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT m.member_id) as count 
                           FROM members m 
                           INNER JOIN member_memberships mm ON m.member_id = mm.member_id 
                           WHERE mm.status = 'active' 
                           AND mm.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                           AND m.status = 'active'");
    $stmt->execute([$grace_period]);
    $stats['expiring_members'] = $stmt->fetch()['count'] ?? 0;
    
    // Expired members
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT m.member_id) as count 
                           FROM members m 
                           INNER JOIN member_memberships mm ON m.member_id = mm.member_id 
                           WHERE mm.status = 'active' AND mm.expiration_date < CURDATE()
                           AND m.status = 'active'");
    $stmt->execute();
    $stats['expired_members'] = $stmt->fetch()['count'] ?? 0;
    
    // Monthly revenue
    $stmt = $pdo->prepare("SELECT SUM(total) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ? AND status = 'completed'");
    $stmt->execute([$current_month]);
    $stats['monthly_revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Monthly expenses
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?");
    $stmt->execute([$current_month]);
    $stats['monthly_expenses'] = $stmt->fetch()['total'] ?? 0;
    
    // Monthly profit
    $stats['monthly_profit'] = $stats['monthly_revenue'] - $stats['monthly_expenses'];
    
    // Recent check-ins
    $stmt = $pdo->prepare("SELECT a.*, 
                           CASE 
                               WHEN a.member_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name)
                               ELSE a.walkin_name
                           END as customer_name,
                           m.member_number
                           FROM attendance a
                           LEFT JOIN members m ON a.member_id = m.member_id
                           WHERE a.check_in_date = ?
                           ORDER BY a.check_in_time DESC
                           LIMIT 10");
    $stmt->execute([$today]);
    $stats['recent_checkins'] = $stmt->fetchAll();
    
    // Expiring members list
    $stmt = $pdo->prepare("SELECT m.member_id, m.first_name, m.last_name, m.member_number, 
                           mm.expiration_date, mp.plan_name
                           FROM members m
                           INNER JOIN member_memberships mm ON m.member_id = mm.member_id
                           INNER JOIN membership_plans mp ON mm.plan_id = mp.plan_id
                           WHERE mm.status = 'active' 
                           AND mm.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                           AND m.status = 'active'
                           ORDER BY mm.expiration_date ASC
                           LIMIT 5");
    $stmt->execute([$grace_period]);
    $stats['expiring_members_list'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Dashboard statistics error: ' . $e->getMessage());
}

// Include header
include 'includes/header.php';
?>

<body>
    <!-- Main Layout -->
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>
            
            <div class="content-wrapper">
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <div class="quick-actions-grid">
                        <?php if (isStaff()): ?>
                        <a href="<?php echo BASE_URL; ?>attendance/checkin.php" class="quick-action-btn primary">
                            <i class="bi bi-check2-circle"></i>
                            <span>Check In Member</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>members/add.php" class="quick-action-btn success">
                            <i class="bi bi-person-plus"></i>
                            <span>Register Member</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>walkins/add.php" class="quick-action-btn info">
                            <i class="bi bi-person-walking"></i>
                            <span>Walk-In</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>payments/create.php" class="quick-action-btn warning">
                            <i class="bi bi-credit-card"></i>
                            <span>Accept Payment</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>membership/renew.php" class="quick-action-btn secondary">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Renew Membership</span>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>pt/schedule.php" class="quick-action-btn dark">
                            <i class="bi bi-clock"></i>
                            <span>PT Session</span>
                        </a>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <?php if (isOwner()): ?>
                    <!-- Financial Stats - Owner Only -->
                    <div class="stat-card">
                        <div class="stat-icon revenue">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div class="stat-content">
                            <h5>Today's Revenue</h5>
                            <h2><?php echo formatCurrency($stats['today_revenue']); ?></h2>
                            <small class="text-muted"><?php echo date('F j, Y'); ?></small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon expenses">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div class="stat-content">
                            <h5>Today's Expenses</h5>
                            <h2><?php echo formatCurrency($stats['today_expenses']); ?></h2>
                            <small class="text-muted"><?php echo date('F j, Y'); ?></small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon profit">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div class="stat-content">
                            <h5>Today's Profit</h5>
                            <h2><?php echo formatCurrency($stats['today_profit']); ?></h2>
                            <small class="<?php echo $stats['today_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $stats['today_profit'] >= 0 ? '▲' : '▼'; ?> 
                                <?php echo formatCurrency(abs($stats['today_profit'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Attendance Stats - All Staff -->
                    <?php if (isStaff()): ?>
                    <div class="stat-card">
                        <div class="stat-icon checkins">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h5>Today's Check-ins</h5>
                            <h2><?php echo number_format($stats['today_checkins']); ?></h2>
                            <small class="text-muted"><?php echo date('F j, Y'); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Membership Stats - Owner Only -->
                    <?php if (isOwner()): ?>
                    <div class="stat-card">
                        <div class="stat-icon members">
                            <i class="bi bi-person-arms-up"></i>
                        </div>
                        <div class="stat-content">
                            <h5>Active Members</h5>
                            <h2><?php echo number_format($stats['active_members']); ?></h2>
                            <small class="text-muted">Total active memberships</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Expiring Members - Owner Only -->
                    <?php if (isOwner()): ?>
                    <div class="stat-card warning">
                        <div class="stat-icon expiring">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-content">
                            <h5>Expiring Soon</h5>
                            <h2><?php echo number_format($stats['expiring_members']); ?></h2>
                            <small class="text-warning">Within <?php echo $grace_period; ?> days</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Trainer Stats - Trainer Only -->
                    <?php if (isTrainer()): ?>
                    <div class="stat-card">
                        <div class="stat-icon pt">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="stat-content">
                            <h5>Today's PT Sessions</h5>
                            <h2><?php 
                                $trainer_id = getCurrentTrainerId();
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pt_sessions WHERE trainer_id = ? AND session_date = ? AND status = 'completed'");
                                $stmt->execute([$trainer_id, $today]);
                                echo number_format($stmt->fetchColumn());
                            ?></h2>
                            <small class="text-muted"><?php echo date('F j, Y'); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Activity and Expiring Members -->
                <div class="row mt-4">
                    <!-- Recent Check-ins -->
                    <div class="col-lg-7">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5><i class="bi bi-clock-history"></i> Recent Check-ins</h5>
                                <a href="<?php echo BASE_URL; ?>attendance/index.php" class="btn btn-sm btn-link">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Type</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($stats['recent_checkins'])): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">
                                                    No check-ins recorded today.
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($stats['recent_checkins'] as $checkin): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($checkin['customer_name']); ?></strong>
                                                        <?php if (!empty($checkin['member_number'])): ?>
                                                        <br><small class="text-muted"><?php echo $checkin['member_number']; ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $checkin['customer_type'] == 'student' ? 'bg-info' : 'bg-secondary'; ?>">
                                                            <?php echo ucfirst($checkin['customer_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatTime($checkin['check_in_time']); ?></td>
                                                    <td>
                                                        <span class="badge bg-success">Checked In</span>
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
                    
                    <!-- Expiring Members -->
                    <?php if (isOwner()): ?>
                    <div class="col-lg-5">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5><i class="bi bi-exclamation-triangle"></i> Expiring Memberships</h5>
                                <a href="<?php echo BASE_URL; ?>membership/history.php" class="btn btn-sm btn-link">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Plan</th>
                                                <th>Expires</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($stats['expiring_members_list'])): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-4">
                                                    No memberships expiring soon.
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($stats['expiring_members_list'] as $member): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo $member['member_number']; ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($member['plan_name']); ?></td>
                                                    <td>
                                                        <span class="text-warning">
                                                            <?php echo formatDate($member['expiration_date']); ?>
                                                            <br><small>(<?php echo daysDifference(date('Y-m-d'), $member['expiration_date']); ?> days)</small>
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
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Monthly Summary - Owner Only -->
                <?php if (isOwner()): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5><i class="bi bi-calendar-month"></i> Monthly Summary - <?php echo date('F Y'); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="summary-item">
                                            <small class="text-muted">Revenue</small>
                                            <h4 class="text-success"><?php echo formatCurrency($stats['monthly_revenue']); ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="summary-item">
                                            <small class="text-muted">Expenses</small>
                                            <h4 class="text-danger"><?php echo formatCurrency($stats['monthly_expenses']); ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="summary-item">
                                            <small class="text-muted">Net Profit</small>
                                            <h4 class="<?php echo $stats['monthly_profit'] >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                                <?php echo formatCurrency($stats['monthly_profit']); ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($stats['monthly_revenue'] > 0): ?>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?php echo min(100, ($stats['monthly_profit'] / $stats['monthly_revenue']) * 100); ?>%;">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    Profit Margin: <?php echo number_format(($stats['monthly_profit'] / $stats['monthly_revenue']) * 100, 1); ?>%
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
            
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>