<?php
/**
 * View Member Profile
 * Gym Management System
 * 
 * File: members/view.php
 * Purpose: Display complete member profile
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Get member ID
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($member_id <= 0) {
    displayError('Invalid member ID.');
    redirect('index.php');
}

// Check access
if (!canAccessMember($member_id)) {
    displayError('You do not have permission to view this member.');
    redirect('index.php');
}

// Get member details
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.full_name as created_by_name,
               (SELECT COUNT(*) FROM attendance a WHERE a.member_id = m.member_id) as total_visits,
               (SELECT COUNT(*) FROM attendance a WHERE a.member_id = m.member_id AND a.check_in_date = CURDATE()) as visits_today
        FROM members m
        LEFT JOIN users u ON m.created_by = u.user_id
        WHERE m.member_id = ?
    ");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        displayError('Member not found.');
        redirect('index.php');
    }
} catch (PDOException $e) {
    error_log('Get member error: ' . $e->getMessage());
    displayError('An error occurred while retrieving member information.');
    redirect('index.php');
}

// Get active membership
try {
    $stmt = $pdo->prepare("
        SELECT mm.*, mp.plan_name, mp.duration_days
        FROM member_memberships mm
        INNER JOIN membership_plans mp ON mm.plan_id = mp.plan_id
        WHERE mm.member_id = ? AND mm.status = 'active'
        ORDER BY mm.start_date DESC
        LIMIT 1
    ");
    $stmt->execute([$member_id]);
    $membership = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Get membership error: ' . $e->getMessage());
    $membership = null;
}

// Get membership history
try {
    $stmt = $pdo->prepare("
        SELECT mm.*, mp.plan_name
        FROM member_memberships mm
        INNER JOIN membership_plans mp ON mm.plan_id = mp.plan_id
        WHERE mm.member_id = ?
        ORDER BY mm.start_date DESC
        LIMIT 5
    ");
    $stmt->execute([$member_id]);
    $membership_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get membership history error: ' . $e->getMessage());
    $membership_history = [];
}

// Get recent attendance
try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name as processed_by_name
        FROM attendance a
        LEFT JOIN users u ON a.processed_by = u.user_id
        WHERE a.member_id = ?
        ORDER BY a.check_in_date DESC, a.check_in_time DESC
        LIMIT 10
    ");
    $stmt->execute([$member_id]);
    $attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get attendance error: ' . $e->getMessage());
    $attendance = [];
}

// Get payments
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as processed_by_name
        FROM payments p
        LEFT JOIN users u ON p.processed_by = u.user_id
        WHERE p.member_id = ? AND p.status = 'completed'
        ORDER BY p.payment_date DESC, p.payment_time DESC
        LIMIT 10
    ");
    $stmt->execute([$member_id]);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get payments error: ' . $e->getMessage());
    $payments = [];
}

// Get PT packages
try {
    $stmt = $pdo->prepare("
        SELECT pp.*, 
               CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name
        FROM pt_packages pp
        INNER JOIN personal_trainers pt ON pp.trainer_id = pt.trainer_id
        WHERE pp.member_id = ?
        ORDER BY pp.start_date DESC
    ");
    $stmt->execute([$member_id]);
    $pt_packages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get PT packages error: ' . $e->getMessage());
    $pt_packages = [];
}

// Get active PT package
$active_pt_package = null;
foreach ($pt_packages as $pkg) {
    if ($pkg['status'] === PT_ACTIVE) {
        $active_pt_package = $pkg;
        break;
    }
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
                    <h4 class="mb-0"><i class="bi bi-person"></i> Member Profile</h4>
                    <div class="d-flex gap-2">
                        <?php if (isStaff()): ?>
                        <a href="edit.php?id=<?php echo $member_id; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <!-- Profile Header -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="profile-avatar">
                                        <i class="bi bi-person-circle" style="font-size: 64px; color: #ccc;"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-1">
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                            <?php if (!empty($member['suffix'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['suffix']); ?></small>
                                            <?php endif; ?>
                                        </h3>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="badge bg-secondary">ID: <?php echo htmlspecialchars($member['member_number']); ?></span>
                                            <span class="badge <?php echo $member['customer_type'] === 'student' ? 'bg-info' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($member['customer_type']); ?>
                                            </span>
                                            <?php if ($membership): ?>
                                                <?php 
                                                $is_expired = isDatePast($membership['expiration_date']);
                                                $expiring_soon = !$is_expired && daysDifference(date('Y-m-d'), $membership['expiration_date']) <= 7;
                                                ?>
                                                <?php if ($is_expired): ?>
                                                    <span class="badge bg-danger">Membership Expired</span>
                                                <?php elseif ($expiring_soon): ?>
                                                    <span class="badge bg-warning text-dark">Expiring Soon</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Membership</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small">
                                            <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($member['contact_number']); ?></div>
                                            <?php if (!empty($member['email'])): ?>
                                            <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($member['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($member['address'])): ?>
                                            <div><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($member['address']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="stats-mini">
                                    <div class="stat-mini-item">
                                        <div class="stat-mini-label">Visits</div>
                                        <div class="stat-mini-value"><?php echo number_format($member['total_visits'] ?? 0); ?></div>
                                    </div>
                                    <div class="stat-mini-item">
                                        <div class="stat-mini-label">Today</div>
                                        <div class="stat-mini-value"><?php echo number_format($member['visits_today'] ?? 0); ?></div>
                                    </div>
                                    <?php if ($membership): ?>
                                    <div class="stat-mini-item">
                                        <div class="stat-mini-label">Days Left</div>
                                        <div class="stat-mini-value">
                                            <?php 
                                            $days_left = daysDifference(date('Y-m-d'), $membership['expiration_date']);
                                            echo $days_left > 0 ? $days_left : 'Expired';
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            <i class="bi bi-grid"></i> Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="membership-tab" data-bs-toggle="tab" data-bs-target="#membership" type="button" role="tab">
                            <i class="bi bi-card-list"></i> Membership
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                            <i class="bi bi-clock-history"></i> Attendance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                            <i class="bi bi-credit-card"></i> Payments
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pt-tab" data-bs-toggle="tab" data-bs-target="#pt" type="button" role="tab">
                            <i class="bi bi-person-badge"></i> Personal Training
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card modern-card">
                                    <div class="card-header">
                                        <h5>Personal Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4">Full Name</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></dd>
                                            
                                            <?php if (!empty($member['middle_name'])): ?>
                                            <dt class="col-sm-4">Middle Name</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($member['middle_name']); ?></dd>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($member['date_of_birth'])): ?>
                                            <dt class="col-sm-4">Date of Birth</dt>
                                            <dd class="col-sm-8"><?php echo formatDate($member['date_of_birth']); ?></dd>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($member['gender'])): ?>
                                            <dt class="col-sm-4">Gender</dt>
                                            <dd class="col-sm-8"><?php echo ucfirst($member['gender']); ?></dd>
                                            <?php endif; ?>
                                            
                                            <dt class="col-sm-4">Contact</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($member['contact_number']); ?></dd>
                                            
                                            <?php if (!empty($member['email'])): ?>
                                            <dt class="col-sm-4">Email</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($member['email']); ?></dd>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($member['address'])): ?>
                                            <dt class="col-sm-4">Address</dt>
                                            <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($member['address'])); ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card modern-card">
                                    <div class="card-header">
                                        <h5>Emergency Contact</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($member['emergency_contact_name'])): ?>
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4">Name</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($member['emergency_contact_name']); ?></dd>
                                            
                                            <dt class="col-sm-4">Number</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($member['emergency_contact_number']); ?></dd>
                                        </dl>
                                        <?php else: ?>
                                        <p class="text-muted mb-0">No emergency contact information provided.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($member['notes'])): ?>
                                <div class="card modern-card mt-3">
                                    <div class="card-header">
                                        <h5>Notes</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['notes'])); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Membership Tab -->
                    <div class="tab-pane fade" id="membership" role="tabpanel">
                        <?php if ($membership): ?>
                        <div class="card modern-card mb-4">
                            <div class="card-header">
                                <h5>Current Membership</h5>
                                <?php if (isStaff()): ?>
                                <a href="../membership/renew.php?member_id=<?php echo $member_id; ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-arrow-repeat"></i> Renew
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="text-muted">Plan</label>
                                        <div class="fw-bold"><?php echo htmlspecialchars($membership['plan_name']); ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="text-muted">Type</label>
                                        <div class="fw-bold"><?php echo ucfirst($membership['customer_type']); ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="text-muted">Price</label>
                                        <div class="fw-bold"><?php echo formatCurrency($membership['price']); ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="text-muted">Start</label>
                                        <div><?php echo formatDate($membership['start_date']); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted">Expires</label>
                                        <div class="fw-bold <?php echo isDatePast($membership['expiration_date']) ? 'text-danger' : ''; ?>">
                                            <?php echo formatDate($membership['expiration_date']); ?>
                                            <?php if (!isDatePast($membership['expiration_date'])): ?>
                                            <br><small class="text-muted">(<?php echo daysDifference(date('Y-m-d'), $membership['expiration_date']); ?> days left)</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> This member does not have an active membership.
                            <?php if (isStaff()): ?>
                            <a href="../membership/renew.php?member_id=<?php echo $member_id; ?>" class="btn btn-primary btn-sm ms-2">
                                <i class="bi bi-plus-circle"></i> Add Membership
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Membership History -->
                        <?php if (!empty($membership_history)): ?>
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Membership History</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Plan</th>
                                                <th>Type</th>
                                                <th>Start</th>
                                                <th>End</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($membership_history as $hist): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($hist['plan_name']); ?></td>
                                                <td><?php echo ucfirst($hist['customer_type']); ?></td>
                                                <td><?php echo formatDate($hist['start_date']); ?></td>
                                                <td><?php echo formatDate($hist['expiration_date']); ?></td>
                                                <td><?php echo formatCurrency($hist['price']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $hist['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo ucfirst($hist['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Attendance Tab -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Attendance History</h5>
                                <span class="text-muted">Total Visits: <?php echo number_format($member['total_visits'] ?? 0); ?></span>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($attendance)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-clock" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                    No attendance records found.
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Processed By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance as $record): ?>
                                            <tr>
                                                <td><?php echo formatDate($record['check_in_date']); ?></td>
                                                <td><?php echo formatTime($record['check_in_time']); ?></td>
                                                <td><?php echo ucfirst($record['customer_type']); ?></td>
                                                <td>
                                                    <span class="badge bg-success">Checked In</span>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['processed_by_name'] ?? 'Unknown'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payments Tab -->
                    <div class="tab-pane fade" id="payments" role="tabpanel">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Payment History</h5>
                                <?php if (canProcessPayments()): ?>
                                <a href="../payments/create.php?member_id=<?php echo $member_id; ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-circle"></i> New Payment
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($payments)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-credit-card" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                    No payment records found.
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Transaction #</th>
                                                <th>Type</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($payment['transaction_number']); ?></code></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['transaction_type'])); ?></td>
                                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                                <td><?php echo formatCurrency($payment['total']); ?></td>
                                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                <td>
                                                    <span class="badge bg-success">Completed</span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PT Tab -->
                    <div class="tab-pane fade" id="pt" role="tabpanel">
                        <?php if ($active_pt_package): ?>
                        <div class="card modern-card mb-4">
                            <div class="card-header">
                                <h5>Active PT Package</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="text-muted">Trainer</label>
                                        <div class="fw-bold"><?php echo htmlspecialchars($active_pt_package['trainer_name']); ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="text-muted">Total Sessions</label>
                                        <div class="fw-bold"><?php echo $active_pt_package['total_sessions']; ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="text-muted">Used</label>
                                        <div class="fw-bold"><?php echo $active_pt_package['used_sessions']; ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="text-muted">Remaining</label>
                                        <div class="fw-bold <?php echo $active_pt_package['remaining_sessions'] <= 3 ? 'text-warning' : ''; ?>">
                                            <?php echo $active_pt_package['remaining_sessions']; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted">Progress</label>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo ($active_pt_package['used_sessions'] / $active_pt_package['total_sessions']) * 100; ?>%;">
                                                <?php echo round(($active_pt_package['used_sessions'] / $active_pt_package['total_sessions']) * 100); ?>%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PT Sessions -->
                        <?php
                        try {
                            $stmt = $pdo->prepare("
                                SELECT ps.*, CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name
                                FROM pt_sessions ps
                                INNER JOIN personal_trainers pt ON ps.trainer_id = pt.trainer_id
                                WHERE ps.member_id = ?
                                ORDER BY ps.session_date DESC, ps.session_time DESC
                                LIMIT 10
                            ");
                            $stmt->execute([$member_id]);
                            $pt_sessions = $stmt->fetchAll();
                        } catch (PDOException $e) {
                            $pt_sessions = [];
                        }
                        ?>
                        
                        <?php if (!empty($pt_sessions)): ?>
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Recent PT Sessions</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Trainer</th>
                                                <th>Session #</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pt_sessions as $session): ?>
                                            <tr>
                                                <td><?php echo formatDate($session['session_date']); ?></td>
                                                <td><?php echo formatTime($session['session_time']); ?></td>
                                                <td><?php echo htmlspecialchars($session['trainer_name']); ?></td>
                                                <td><?php echo $session['session_number']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $session['status'] === 'completed' ? 'bg-success' : ($session['status'] === 'scheduled' ? 'bg-info' : ($session['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning')); ?>">
                                                        <?php echo ucfirst($session['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> This member does not have an active PT package.
                            <?php if (isStaff()): ?>
                            <a href="../pt/create_package.php?member_id=<?php echo $member_id; ?>" class="btn btn-primary btn-sm ms-2">
                                <i class="bi bi-plus-circle"></i> Create PT Package
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- All PT Packages -->
                        <?php if (!empty($pt_packages)): ?>
                        <div class="card modern-card mt-3">
                            <div class="card-header">
                                <h5>All PT Packages</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Trainer</th>
                                                <th>Sessions</th>
                                                <th>Used</th>
                                                <th>Remaining</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pt_packages as $pkg): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pkg['trainer_name']); ?></td>
                                                <td><?php echo $pkg['total_sessions']; ?></td>
                                                <td><?php echo $pkg['used_sessions']; ?></td>
                                                <td><?php echo $pkg['remaining_sessions']; ?></td>
                                                <td><?php echo formatCurrency($pkg['price']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $pkg['status'] === 'active' ? 'bg-success' : ($pkg['status'] === 'completed' ? 'bg-info' : ($pkg['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning')); ?>">
                                                        <?php echo ucfirst($pkg['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <style>
    .stats-mini {
        display: flex;
        gap: 24px;
        justify-content: flex-end;
    }
    
    .stat-mini-item {
        text-align: center;
    }
    
    .stat-mini-label {
        font-size: 12px;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-mini-value {
        font-size: 24px;
        font-weight: 700;
    }
    
    .profile-avatar i {
        font-size: 64px;
        color: #ccc;
    }
    </style>
</body>
</html>