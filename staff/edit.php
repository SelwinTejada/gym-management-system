<?php
/**
 * Staff Edit
 * Gym Management System
 * 
 * File: staff/edit.php
 * Purpose: Edit staff user
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions - only owner can edit staff
if (!isOwner()) {
    displayError('You do not have permission to edit staff accounts.');
    redirect('../dashboard.php');
}

// Get parameters
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    displayError('Invalid user ID.');
    redirect('../dashboard.php');
}

// Check access - cannot edit own account via this method (or allow it with confirmation)
$can_edit_own = ($user_id == getCurrentUserId());

$title = 'Edit Staff Account';
$target_user = null;
$error = '';
$success = false;
$form_data = [];

// Load user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch();
    
    if (!$target_user) {
        displayError('User not found.');
        redirect('../dashboard.php');
    }
} catch (PDOException $e) {
    error_log('Get user error: ' . $e->getMessage());
    displayError('Error loading user data.');
    redirect('../dashboard.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $role = sanitize($_POST['role'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (empty($full_name)) {
            $error = 'Full name is required';
        } elseif (empty($username)) {
            $error = 'Username is required';
        } elseif (empty($role)) {
            $error = 'Role is required';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users SET full_name = ?, username = ?, contact_number = ?, 
                        email = ?, role = ?, status = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$full_name, $username, $contact_number, $email, $role, $status, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success = true;
                    displaySuccess('Staff account updated successfully.');
                } else {
                    $error = 'No changes made or user not found';
                }
            } catch (PDOException $e) {
                error_log('Update user error: ' . $e->getMessage());
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Set form values
if ($target_user) {
    $form_data = [
        'full_name' => $target_user['full_name'],
        'username' => $target_user['username'],
        'contact_number' => $target_user['contact_number'] ?? '',
        'email' => $target_user['email'] ?? '',
        'role' => $target_user['role'],
        'status' => $target_user['status'],
    ];
}
?>

<div class="app-container">
    <div class="main-content">
        <div class="content-wrapper">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0"><?php echo $title; ?></h4>
                <?php if ($can_edit_own || getCurrentUserRole() === ROLE_OWNER): ?>
                <small class="text-muted">
                    Editing account ID: <?php echo $user_id; ?>
                </small>
                <?php endif; ?>
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
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name"
                               value="<?php echo $form_data['full_name'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username"
                               value="<?php echo $form_data['username'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Contact Number</label>
                        <input type="text" class="form-control" name="contact_number"
                               value="<?php echo $form_data['contact_number'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email"
                               value="<?php echo $form_data['email'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="owner" <?php echo ($form_data['role'] ?? '') === 'owner' ? 'selected' : ''; ?>>Owner</option>
                            <option value="front_desk" <?php echo ($form_data['role'] ?? '') === 'front_desk' ? 'selected' : ''; ?>>Front Desk</option>
                            <option value="trainer" <?php echo ($form_data['role'] ?? '') === 'trainer' ? 'selected' : ''; ?>>Trainer</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status" id="status" 
                                   <?php echo ($form_data['status'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i> Save Changes
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>