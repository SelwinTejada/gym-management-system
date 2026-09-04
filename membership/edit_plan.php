<?php
/**
 * Membership Plan Edit
 * Gym Management System
 * 
 * File: membership/edit_plan.php
 * Purpose: Edit membership plan
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions - only owner can edit plans
if (!isOwner()) {
    displayError('You do not have permission to edit membership plans.');
    redirect('../dashboard.php');
}

// Get parameters
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

$is_editing_plan = $plan_id > 0;

$title = $is_editing_plan ? 'Edit Membership Plan' : 'Add New Membership Plan';

// Initialize variables
$error = '';
$success = false;
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $plan_name = sanitize($_POST['plan_name'] ?? '');
        $duration_days = (int)($_POST['duration_days'] ?? 30);
        $student_price = (float)($_POST['student_price'] ?? 0);
        $regular_price = (float)($_POST['regular_price'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = (int)($_POST['display_order'] ?? 0);
        
        if (empty($plan_name)) {
            $error = 'Plan name is required';
        } elseif ($duration_days <= 0) {
            $error = 'Duration must be greater than zero';
        } elseif ($student_price < 0 || $regular_price < 0) {
            $error = 'Prices cannot be negative';
        } else {
            try {
                if ($is_editing_plan) {
                    // Edit plan
                    $stmt = $pdo->prepare("
                        UPDATE membership_plans SET plan_name = ?, duration_days = ?, 
                            student_price = ?, regular_price = ?, is_active = ?, display_order = ?, updated_at = NOW()
                        WHERE plan_id = ?
                    ");
                    $stmt->execute([$plan_name, $duration_days, $student_price, $regular_price, 
                                    $is_active, $display_order, $plan_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                        displaySuccess('Membership plan updated successfully.');
                    } else {
                        $error = 'No changes made or plan not found';
                    }
                } else {
                    // Add new plan
                    // Check if plan name already exists
                    $check_stmt = $pdo->prepare("SELECT plan_id FROM membership_plans WHERE plan_name = ?");
                    $check_stmt->execute([$plan_name]);
                    
                    if ($check_stmt->fetch()) {
                        $error = 'A plan with this name already exists';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO membership_plans (plan_name, duration_days, student_price, regular_price, is_active, display_order)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$plan_name, $duration_days, $student_price, $regular_price, $is_active, $display_order]);
                        
                        if ($stmt->rowCount() > 0) {
                            $success = true;
                            displaySuccess('Membership plan added successfully.');
                        } else {
                            $error = 'Failed to add plan';
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log('Update membership plan error: ' . $e->getMessage());
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Load data if editing
if ($is_editing_plan) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE plan_id = ?");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            displayError('Plan not found.');
            redirect('plans.php');
        }
        
        $form_data = [
            'plan_name' => $plan['plan_name'],
            'duration_days' => $plan['duration_days'],
            'student_price' => $plan['student_price'],
            'regular_price' => $plan['regular_price'],
            'is_active' => $plan['is_active'],
            'display_order' => $plan['display_order'],
        ];
    } catch (PDOException $e) {
        error_log('Get plan error: ' . $e->getMessage());
        displayError('Error loading plan data.');
        redirect('plans.php');
    }
}
?>

<div class="app-container">
    <div class="main-content">
        <div class="content-wrapper">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0"><?php echo $title; ?></h4>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="card modern-card p-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateRandomString(32); ?>">
                <input type="hidden" name="is_edit" value="<?php echo $is_editing_plan ? '1' : '0'; ?>">
                <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
                
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="form-label">Plan Name</label>
                        <input type="text" class="form-control" name="plan_name"
                               value="<?php echo $form_data['plan_name'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Duration (Days)</label>
                        <input type="number" class="form-control" name="duration_days"
                               value="<?php echo $form_data['duration_days'] ?? 30; ?>" min="1" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Student Price</label>
                        <input type="number" step="0.01" class="form-control" name="student_price"
                               value="<?php echo formatCurrency($form_data['student_price'] ?? 0); ?>" min="0" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Regular Price</label>
                        <input type="number" step="0.01" class="form-control" name="regular_price"
                               value="<?php echo formatCurrency($form_data['regular_price'] ?? 0); ?>" min="0" required>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Display Order</label>
                        <input type="number" class="form-control" name="display_order"
                               value="<?php echo $form_data['display_order'] ?? 0; ?>" min="0" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                   <?php echo ($form_data['is_active'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Is Visible in Dashboard</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_visible" id="is_visible" 
                                   <?php echo ($form_data['is_visible'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_visible">
                                Show in Dashboard
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i> Save Changes
                    </button>
                    <a href="plans.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>