<?php
/**
 * View PT Package
 * Gym Management System
 * 
 * File: pt/view_package.php
 * Purpose: Display PT package details
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Get package ID
$package_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($package_id <= 0) {
    displayError('Invalid package ID.');
    redirect('packages.php');
}

// Check access
if (!canAccessPTPackage($package_id)) {
    displayError('You do not have permission to view this package.');
    redirect('packages.php');
}

// Get package details
try {
    $stmt = $pdo->prepare("
        SELECT pp.*, 
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.member_number,
               CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name,
               pt.rate as trainer_rate
        FROM pt_packages pp
        INNER JOIN members m ON pp.member_id = m.member_id
        INNER JOIN personal_trainers pt ON pp.trainer_id = pt.trainer_id
        WHERE pp.package_id = ?
    ");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch();
    
    if (!$package) {
        displayError('Package not found.');
        redirect('packages.php');
    }
} catch (PDOException $e) {
    error_log('Get package error: ' . $e->getMessage());
    displayError('An error occurred.');
    redirect('packages.php');
}

// Get sessions for this package
try {
    $stmt = $pdo->prepare("
        SELECT * FROM pt_sessions 
        WHERE package_id = ? 
        ORDER BY session_date DESC, session_time DESC
    ");
    $stmt->execute([$package_id]);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get sessions error: ' . $e->getMessage());
    $sessions = [];
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
                    <h4 class="mb-0"><i class="bi bi-box"></i> PT Package Details</h4>
                    <a href="packages.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Packages
                    </a>
                </div>
                
                <!-- Package Summary -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Package Information</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Member:</strong></td>
                                        <td><?php echo htmlspecialchars($package['member_name']); ?> (<?php echo $package['member_number']; ?>)</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Trainer:</strong></td>
                                        <td><?php echo htmlspecialchars($package['trainer_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $package['status'] === 'active' ? 'bg-success' : 
                                                     ($package['status'] === 'completed' ? 'bg-info' : 
                                                     ($package['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning')); 
                                            ?>">
                                                <?php echo ucfirst($package['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Price:</strong></td>
                                        <td><?php echo formatCurrency($package['price']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Start Date:</strong></td>
                                        <td><?php echo formatDate($package['start_date']); ?></td>
                                    </tr>
                                    <?php if ($package['expiration_date']): ?>
                                    <tr>
                                        <td><strong>Expiration:</strong></td>
                                        <td><?php echo formatDate($package['expiration_date']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Session Progress</h5>
                                <div class="text-center">
                                    <div style="font-size: 48px; font-weight: 700; color: #D72638;">
                                        <?php echo $package['used_sessions']; ?>/<?php echo $package['total_sessions']; ?>
                                    </div>
                                    <div class="progress" style="height: 30px;">
                                        <div class="progress-bar bg-success" 
                                             style="width: <?php echo ($package['used_sessions'] / $package['total_sessions']) * 100; ?>%;">
                                            <?php echo round(($package['used_sessions'] / $package['total_sessions']) * 100); ?>% Complete
                                        </div>
                                    </div>
                                    <div class="mt-2 text-muted">
                                        <strong><?php echo $package['remaining_sessions']; ?></strong> sessions remaining
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($package['notes'])): ?>
                        <div class="mt-3">
                            <h6>Notes:</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($package['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isStaff() && $package['status'] === 'active'): ?>
                        <div class="mt-3">
                            <a href="schedule.php?package_id=<?php echo $package_id; ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Schedule Session
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sessions List -->
                <div class="card modern-card">
                    <div class="card-header">
                        <h5>Session History</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($sessions)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-clock" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            No sessions scheduled yet.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?php echo $session['session_number']; ?></td>
                                        <td><?php echo formatDate($session['session_date']); ?></td>
                                        <td><?php echo formatTime($session['session_time']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $session['status'] === 'completed' ? 'bg-success' : 
                                                     ($session['status'] === 'scheduled' ? 'bg-info' : 
                                                     ($session['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning')); 
                                            ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($session['notes'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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