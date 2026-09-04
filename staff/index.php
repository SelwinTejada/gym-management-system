<?php
/**
 * Staff Management
 * Gym Management System
 * 
 * File: staff/index.php
 * Purpose: Manage system users
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!canManageStaff()) {
    displayError('You do not have permission to manage staff.');
    redirect('../dashboard.php');
}

// Get staff users
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM audit_logs WHERE user_id = u.user_id) as total_actions
        FROM users u
        ORDER BY u.role, u.full_name
    ");
    $stmt->execute();
    $staff = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get staff error: ' . $e->getMessage());
    $staff = [];
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
                    <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Staff Management</h4>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Staff
                    </a>
                </div>
                
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($staff)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-shield-lock" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No staff accounts found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($staff as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $user['role'] === 'owner' ? 'bg-danger' : 
                                                         ($user['role'] === 'front_desk' ? 'bg-info' : 'bg-secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['contact_number'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $user['last_login'] ? formatDate($user['last_login']) . ' ' . formatTime($user['last_login']) : 'Never'; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline-secondary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($user['user_id'] != getCurrentUserId()): ?>
                                                    <button class="btn btn-outline-danger" onclick="deleteStaff(<?php echo $user['user_id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
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
    function deleteStaff(id) {
        if (confirm('Are you sure you want to delete this staff account? This action cannot be undone.')) {
            window.location.href = 'delete.php?id=' + id;
        }
    }
    </script>
</body>
</html>