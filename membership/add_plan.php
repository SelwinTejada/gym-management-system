<?php
/**
 * Add Membership Plan
 * Gym Management System
 * 
 * File: membership/add_plan.php
 * Purpose: Create new membership plan
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to add membership plans.');
    redirect('../dashboard.php');
}

// Process form submission
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        // Sanitize input
        $plan_name = sanitize($_POST['plan_name'] ?? '');
        $duration_days = (int)($_POST['duration_days'] ?? 0);
        $student_price = (float)($_POST['student_price'] ?? 0);
        $regular_price = (float)($_POST['regular_price'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $display_order = (int)($_POST['display_order'] ?? 0);
        
        // Validate
        if (empty($plan_name) || $duration_days <= 0 || $student_price < 0 || $regular_price < 0) {
            $error = 'Please fill in all required fields with valid values.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO membership_plans (plan_name, duration_days, student_price, regular_price, description, display_order)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $plan_name,
                    $duration_days,
                    $student_price,
                    $regular_price,
                    $description,
                    $display_order
                ]);
                
                $plan_id = $pdo->lastInsertId();
                
                // Log audit
                logAudit('PLAN_ADD', 'membership', $plan_id, 'Added membership plan: ' . $plan_name);
                
                $success = true;
                displaySuccess('Membership plan created successfully!');
                header('Location: plans.php');
                exit;
                
            } catch (PDOException $e) {
                error_log('Add plan error: ' . $e->getMessage());
                $error = 'An error occurred while creating the plan.';
            }
        }
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
                    <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Add Membership Plan</h4>
                    <a href="plans.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Plans
                    </a>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card modern-card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php echo csrfTokenField(); ?>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required-field">Plan Name *</label>
                                    <input type="text" class="form-control" name="plan_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Duration (days) *</label>
                                    <input type="number" class="form-control" name="duration_days" min="1" required>
                                    <small class="text-muted">Number of days the membership is valid</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Student Price (₱) *</label>
                                    <input type="number" class="form-control" name="student_price" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Regular Price (₱) *</label>
                                    <input type="number" class="form-control" name="regular_price" step="0.01" min="0" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Describe the membership plan..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" class="form-control" name="display_order" value="0">
                                    <small class="text-muted">Lower numbers appear first</small>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Create Plan
                                </button>
                                <a href="plans.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>