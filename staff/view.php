<?php
/**
 * Staff View
 * Gym Management System
 * 
 * File: staff/view.php
 * Purpose: View staff user details
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view staff details.');
    redirect('../dashboard.php');
}

// Get parameters
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    displayError('Invalid user ID.');
    redirect('../dashboard.php');
}

// Load user data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM audit_logs WHERE user_id = u.user_id) as total_actions
        FROM users u
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        displayError('User not found.');
        redirect('../dashboard.php');
    }
} catch (PDOException $e) {
    error_log('Get user error: ' . $e->getMessage());
    displayError('Error loading user data.');
    redirect('../dashboard.php');
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
                    <h4 class="mb-0"><i class="bi bi-person-badge"></i> Staff Details</h4>
                    <a href="index.php" class="btn btn-link">← Back to Staff List</a>
                </div>
                
                <div class="card modern-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Full Name</strong>
                                <p class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Username</strong>
                                <p class="mt-2"><code><?php echo htmlspecialchars($user['username']); ?></code></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Role</strong>
                                <p class="mt-2">
                                    <span class="badge <?php 
                                        echo $user['role'] === 'owner' ? 'bg-danger' : 
                                             ($user['role'] === 'front_desk' ? 'bg-info' : 'bg-secondary'); 
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <strong>Contact Number</strong>
                                <p class="mt-2"><?php echo htmlspecialchars($user['contact_number'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="col-12">
                                <strong>Email</strong>
                                <p class="mt-2"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <strong>Status</strong>
                                <p class="mt-2">
                                    <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?></span>
                            </p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <strong>Total Actions (Audit)</strong>
                                <p class="mt-2"><?php echo number_format($user['total_actions'] ?? 0); ?></p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <strong>Last Login</strong>
                                <p class="mt-2">
                                    <?php echo $user['last_login'] ? formatDate($user['last_login']) . ' ' . formatTime($user['last_login']) : 'Never logged in'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>