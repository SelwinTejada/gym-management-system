<?php
/**
 * Add Staff
 * Gym Management System
 * 
 * File: staff/add.php
 * Purpose: Create new staff account
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!canManageStaff()) {
    displayError('You do not have permission to add staff.');
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
        $username = sanitize($_POST['username'] ?? '');
        $full_name = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitize($_POST['role'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        
        // Validate
        if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($role)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            try {
                // Check if username exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already exists.';
                } else {
                    // Check if email exists
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Email already registered.';
                    } else {
                        // Hash password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert user
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, password_hash, email, full_name, role, contact_number, status)
                            VALUES (?, ?, ?, ?, ?, ?, 'active')
                        ");
                        
                        $stmt->execute([
                            $username,
                            $password_hash,
                            $email,
                            $full_name,
                            $role,
                            $contact_number
                        ]);
                        
                        $user_id = $pdo->lastInsertId();
                        
                        // Log audit
                        logAudit('STAFF_ADD', 'staff', $user_id, 'Added new staff: ' . $username);
                        
                        $success = true;
                        displaySuccess('Staff account created successfully!');
                        header('Location: index.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log('Add staff error: ' . $e->getMessage());
                $error = 'An error occurred while creating the account.';
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
                    <h4 class="mb-0"><i class="bi bi-person-plus"></i> Add Staff</h4>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back
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
                                    <label class="form-label required-field">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Username *</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Password *</label>
                                    <input type="password" class="form-control" name="password" required minlength="8">
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Role *</label>
                                    <select class="form-select" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="front_desk">Front Desk</option>
                                        <option value="trainer">Personal Trainer</option>
                                        <option value="owner">Owner</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact_number">
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Create Account
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
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