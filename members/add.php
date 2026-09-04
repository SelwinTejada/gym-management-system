<?php
/**
 * Add Member
 * Gym Management System
 * 
 * File: members/add.php
 * Purpose: Register a new member
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to add members.');
    redirect('../dashboard.php');
}

// Get membership plans for dropdown
try {
    $stmt = $pdo->query("SELECT plan_id, plan_name, duration_days, student_price, regular_price FROM membership_plans WHERE is_active = 1 ORDER BY display_order");
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get plans error: ' . $e->getMessage());
    $plans = [];
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
        $plan_id = sanitize($_POST['plan_id'] ?? '');
        $start_date = sanitize($_POST['start_date'] ?? date('Y-m-d'));
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($contact_number) || empty($plan_id)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Generate member number
                $member_number = generateMemberNumber();
                
                // Insert member
                $stmt = $pdo->prepare("
                    INSERT INTO members (
                        member_number, first_name, middle_name, last_name, suffix,
                        date_of_birth, gender, contact_number, email, address,
                        emergency_contact_name, emergency_contact_number,
                        customer_type, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $member_number,
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
                    $notes,
                    getCurrentUserId()
                ]);
                
                $member_id = $pdo->lastInsertId();
                
                // Get plan details
                $stmt = $pdo->prepare("SELECT plan_name, duration_days, student_price, regular_price FROM membership_plans WHERE plan_id = ?");
                $stmt->execute([$plan_id]);
                $plan = $stmt->fetch();
                
                if (!$plan) {
                    throw new Exception('Invalid membership plan selected.');
                }
                
                // Calculate price based on customer type
                $price = ($customer_type === CUSTOMER_STUDENT) ? $plan['student_price'] : $plan['regular_price'];
                
                // Calculate expiration date
                $expiration_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_days'] . ' days'));
                
                // Create membership
                $stmt = $pdo->prepare("
                    INSERT INTO member_memberships (
                        member_id, plan_id, customer_type, start_date, expiration_date,
                        price, amount_paid, balance, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $member_id,
                    $plan_id,
                    $customer_type,
                    $start_date,
                    $expiration_date,
                    $price,
                    $price, // Full payment assumed
                    0,
                    MEMBERSHIP_ACTIVE,
                    getCurrentUserId()
                ]);
                
                $membership_id = $pdo->lastInsertId();
                
                // Log audit
                logAudit('MEMBER_ADD', 'members', $member_id, 'Added new member: ' . $first_name . ' ' . $last_name);
                logAudit('MEMBERSHIP_CREATE', 'membership', $membership_id, 'Created membership for member: ' . $member_number);
                
                // Commit transaction
                $pdo->commit();
                
                $success = true;
                displaySuccess('Member successfully registered!');
                header('Location: view.php?id=' . $member_id);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Add member error: ' . $e->getMessage());
                $error = 'An error occurred while adding the member. Please try again.';
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
                    <h4 class="mb-0"><i class="bi bi-person-plus"></i> Register New Member</h4>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Members
                    </a>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card modern-card">
                    <div class="card-body">
                        <form method="POST" action="" id="memberForm">
                            <?php echo csrfTokenField(); ?>
                            
                            <!-- Personal Information -->
                            <h5 class="mb-3">Personal Information</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label required-field">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" name="middle_name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-field">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Suffix</label>
                                    <input type="text" class="form-control" name="suffix" placeholder="Jr., Sr., etc.">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-field">Contact Number *</label>
                                    <input type="text" class="form-control" name="contact_number" required placeholder="09XX-XXX-XXXX">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" placeholder="email@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" placeholder="Street, City, Province">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" name="emergency_contact_name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Emergency Contact Number</label>
                                    <input type="text" class="form-control" name="emergency_contact_number">
                                </div>
                            </div>
                            
                            <!-- Membership Details -->
                            <h5 class="mb-3">Membership Details</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label required-field">Customer Type *</label>
                                    <select class="form-select" name="customer_type" id="customerType" required>
                                        <option value="<?php echo CUSTOMER_STUDENT; ?>">Student</option>
                                        <option value="<?php echo CUSTOMER_REGULAR; ?>">Regular</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-field">Membership Plan *</label>
                                    <select class="form-select" name="plan_id" id="planId" required>
                                        <option value="">Select Plan</option>
                                        <?php foreach ($plans as $plan): ?>
                                        <option value="<?php echo $plan['plan_id']; ?>" 
                                                data-student="<?php echo $plan['student_price']; ?>"
                                                data-regular="<?php echo $plan['regular_price']; ?>">
                                            <?php echo htmlspecialchars($plan['plan_name']); ?>
                                            (Student: <?php echo formatCurrency($plan['student_price']); ?> / 
                                            Regular: <?php echo formatCurrency($plan['regular_price']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label required-field">Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Price</label>
                                    <input type="text" class="form-control" id="priceDisplay" readonly>
                                </div>
                            </div>
                            
                            <!-- Additional Information -->
                            <h5 class="mb-3">Additional Information</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Any additional notes about this member..."></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Register Member
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
    
    <script>
    $(document).ready(function() {
        // Auto-calculate price based on customer type and plan
        $('#customerType, #planId').on('change', function() {
            calculatePrice();
        });
        
        function calculatePrice() {
            const customerType = $('#customerType').val();
            const planId = $('#planId').val();
            
            if (!planId) {
                $('#priceDisplay').val('');
                return;
            }
            
            const selectedOption = $('#planId option:selected');
            const price = customerType === 'student' 
                ? selectedOption.data('student') 
                : selectedOption.data('regular');
            
            if (price) {
                $('#priceDisplay').val('₱' + parseFloat(price).toFixed(2));
            }
        }
        
        // Calculate on load
        calculatePrice();
    });
    </script>
</body>
</html>