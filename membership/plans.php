<?php
/**
 * Membership Plans
 * Gym Management System
 * 
 * File: membership/plans.php
 * Purpose: Manage membership plans
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to manage membership plans.');
    redirect('../dashboard.php');
}

// Get all plans
try {
    $stmt = $pdo->query("SELECT * FROM membership_plans ORDER BY display_order, plan_id");
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get plans error: ' . $e->getMessage());
    $plans = [];
}

// Process plan deletion
if (isset($_GET['delete']) && isOwner()) {
    $plan_id = (int)$_GET['delete'];
    
    try {
        // Check if plan is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM member_memberships WHERE plan_id = ?");
        $stmt->execute([$plan_id]);
        $in_use = $stmt->fetchColumn();
        
        if ($in_use > 0) {
            displayWarning('Cannot delete plan that is in use by members.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM membership_plans WHERE plan_id = ?");
            $stmt->execute([$plan_id]);
            displaySuccess('Plan deleted successfully.');
        }
    } catch (PDOException $e) {
        error_log('Delete plan error: ' . $e->getMessage());
        displayError('An error occurred while deleting the plan.');
    }
    
    header('Location: plans.php');
    exit;
}

// Toggle plan active status
if (isset($_GET['toggle']) && isOwner()) {
    $plan_id = (int)$_GET['toggle'];
    
    try {
        $stmt = $pdo->prepare("UPDATE membership_plans SET is_active = NOT is_active WHERE plan_id = ?");
        $stmt->execute([$plan_id]);
        displaySuccess('Plan status updated successfully.');
    } catch (PDOException $e) {
        error_log('Toggle plan error: ' . $e->getMessage());
        displayError('An error occurred while updating the plan.');
    }
    
    header('Location: plans.php');
    exit;
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
                    <h4 class="mb-0"><i class="bi bi-card-list"></i> Membership Plans</h4>
                    <a href="add_plan.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Plan
                    </a>
                </div>
                
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Plan Name</th>
                                        <th>Duration</th>
                                        <th>Student Price</th>
                                        <th>Regular Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($plans)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-card-list" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No membership plans found.
                                            <br><a href="add_plan.php" class="btn btn-primary btn-sm mt-2">Add Your First Plan</a>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($plans as $plan): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($plan['plan_name']); ?></strong></td>
                                            <td><?php echo $plan['duration_days']; ?> days</td>
                                            <td><?php echo formatCurrency($plan['student_price']); ?></td>
                                            <td><?php echo formatCurrency($plan['regular_price']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $plan['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit_plan.php?id=<?php echo $plan['plan_id']; ?>" class="btn btn-outline-secondary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="?toggle=<?php echo $plan['plan_id']; ?>" class="btn btn-outline-<?php echo $plan['is_active'] ? 'warning' : 'success'; ?>">
                                                        <i class="bi bi-<?php echo $plan['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $plan['plan_id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this plan?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
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
</body>
</html>