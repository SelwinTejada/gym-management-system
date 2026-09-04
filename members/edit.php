<?php
/**
 * Edit Member
 * Gym Management System
 * 
 * File: members/edit.php
 * Purpose: Edit existing member profile
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to edit members.');
    redirect('../dashboard.php');
}

// Get member ID
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($member_id <= 0) {
    displayError('Invalid member ID.');
    redirect('index.php');
}

// Get member details
try {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
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

// Process form submission
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        // Sanitize input
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $suffix = sanitize($_POST['suffix'] ?? '');
        $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $emergency_contact_name = sanitize($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_number = sanitize($_POST['emergency_contact_number'] ?? '');
        $customer_type = sanitize($_POST['customer_type'] ?? CUSTOMER_STUDENT);
        $status = sanitize($_POST['status'] ?? 'active');
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($contact_number)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Update member
                $stmt = $pdo->prepare("
                    UPDATE members SET
                        first_name = ?,
                        middle_name = ?,
                        last_name = ?,
                        suffix = ?,
                        date_of_birth = ?,
                        gender = ?,
                        contact_number = ?,
                        email = ?,
                        address = ?,
                        emergency_contact_name = ?,
                        emergency_contact_number = ?,
                        customer_type = ?,
                        status = ?,
                        notes = ?
                    WHERE member_id = ?
                ");
                
                $stmt->execute([
                    $first_name,
                    $middle_name,
                    $last_name,
                    $suffix,
                    $date_of_birth ?: null,
                    $gender ?: null,
                    $contact_number,
                    $email ?: null,
                    $address ?: null,
                    $emergency_contact_name ?: null,
                    $emergency_contact_number ?: null,
                    $customer_type,
                    $status,
                    $notes,
                    $member_id
                ]);
                
                // Log audit
                logAudit('MEMBER_EDIT', 'members', $member_id, 'Updated member: ' . $first_name . ' ' . $last_name);
                
                $success = true;
                displaySuccess('Member profile updated successfully!');
                header('Location: view.php?id=' . $member_id);
                exit;
                
            } catch (PDOException $e) {
                error_log('Update member error: ' . $e->getMessage());
                $error = 'An error occurred while updating the member. Please try again.';
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
                    <h4 class="mb-0"><i class="bi bi-pencil"></i> Edit Member</h4>
                    <a href="view.php?id=<?php echo $member_id; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Profile
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
                                <div class="col-md-4">
                                    <label class="form-label required-field">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" name="middle_name" 
                                           value="<?php echo htmlspecialchars($member['middle_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-field">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Suffix</label>
                                    <input type="text" class="form-control" name="suffix" 
                                           value="<?php echo htmlspecialchars($member['suffix'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($member['date_of_birth'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo $member['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo $member['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo $member['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-field">Contact Number *</label>
                                    <input type="text" class="form-control" name="contact_number" 
                                           value="<?php echo htmlspecialchars($member['contact_number']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" 
                                           value="<?php echo htmlspecialchars($member['address'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" name="emergency_contact_name" 
                                           value="<?php echo htmlspecialchars($member['emergency_contact_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Emergency Contact Number</label>
                                    <input type="text" class="form-control" name="emergency_contact_number" 
                                           value="<?php echo htmlspecialchars($member['emergency_contact_number'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-field">Customer Type *</label>
                                    <select class="form-select" name="customer_type" required>
                                        <option value="<?php echo CUSTOMER_STUDENT; ?>" <?php echo $member['customer_type'] === CUSTOMER_STUDENT ? 'selected' : ''; ?>>Student</option>
                                        <option value="<?php echo CUSTOMER_REGULAR; ?>" <?php echo $member['customer_type'] === CUSTOMER_REGULAR ? 'selected' : ''; ?>>Regular</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?php echo $member['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="suspended" <?php echo $member['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <option value="cancelled" <?php echo $member['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($member['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Member
                                </button>
                                <a href="view.php?id=<?php echo $member_id; ?>" class="btn btn-outline-secondary">Cancel</a>
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