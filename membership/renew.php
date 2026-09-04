<?php
/**
 * Renew Membership
 * Gym Management System
 * 
 * File: membership/renew.php
 * Purpose: Renew member's membership
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to renew memberships.');
    redirect('../dashboard.php');
}

// Get parameters
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

if ($member_id <= 0) {
    displayError('Invalid member ID.');
    redirect('../members/index.php');
}

// Get member details
try {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ? AND status = 'active'");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        displayError('Member not found or inactive.');
        redirect('../members/index.php');
    }
} catch (PDOException $e) {
    error_log('Get member error: ' . $e->getMessage());
    displayError('An error occurred.');
    redirect('../members/index.php');
}

// Get current membership
try {
    $stmt = $pdo->prepare("
        SELECT mm.*, mp.plan_name, mp.duration_days
        FROM member_memberships mm
        INNER JOIN membership_plans mp ON mm.plan_id = mp.plan_id
        WHERE mm.member_id = ? AND mm.status = 'active'
        ORDER BY mm.start_date DESC
        LIMIT 1
    ");
    $stmt->execute([$member_id]);
    $current_membership = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Get membership error: ' . $e->getMessage());
    $current_membership = null;
}

// Get membership plans
try {
    $stmt = $pdo->query("SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY display_order");
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get plans error: ' . $e->getMessage());
    $plans = [];
}

// Process renewal
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed.';
    } else {
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        $customer_type = sanitize($_POST['customer_type'] ?? CUSTOMER_STUDENT);
        $amount_paid = (float)($_POST['amount_paid'] ?? 0);
        $payment_method = sanitize($_POST['payment_method'] ?? PAYMENT_CASH);
        $notes = sanitize($_POST['notes'] ?? '');
        
        if ($plan_id <= 0 || $amount_paid <= 0) {
            $error = 'Please select a plan and enter amount paid.';
        } else {
            try {
                // Get plan details
                $stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE plan_id = ?");
                $stmt->execute([$plan_id]);
                $plan = $stmt->fetch();
                
                if (!$plan) {
                    throw new Exception('Invalid plan selected.');
                }
                
                // Begin transaction
                $pdo->beginTransaction();
                
                $now = getCurrentDateTime();
                $price = ($customer_type === CUSTOMER_STUDENT) ? $plan['student_price'] : $plan['regular_price'];
                $start_date = $now['date'];
                $expiration_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_days'] . ' days'));
                
                // Deactivate current membership
                if ($current_membership) {
                    $stmt = $pdo->prepare("UPDATE member_memberships SET status = 'expired' WHERE membership_id = ?");
                    $stmt->execute([$current_membership['membership_id']]);
                }
                
                // Create new membership
                $stmt = $pdo->prepare("
                    INSERT INTO member_memberships (
                        member_id, plan_id, customer_type, start_date, expiration_date,
                        price, amount_paid, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $member_id,
                    $plan_id,
                    $customer_type,
                    $start_date,
                    $expiration_date,
                    $price,
                    $amount_paid,
                    MEMBERSHIP_ACTIVE,
                    getCurrentUserId()
                ]);
                
                $membership_id = $pdo->lastInsertId();
                
                // Create payment record
                $transaction_number = generateTransactionNumber('REN');
                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        transaction_number, member_id, transaction_type, payment_method,
                        subtotal, total, amount_paid, payment_date, payment_time,
                        status, notes, processed_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $transaction_number,
                    $member_id,
                    TRANSACTION_RENEWAL,
                    $payment_method,
                    $price,
                    $price,
                    $amount_paid,
                    $now['date'],
                    $now['time'],
                    PAYMENT_COMPLETED,
                    $notes,
                    getCurrentUserId()
                ]);
                
                $payment_id = $pdo->lastInsertId();
                
                // Insert payment item
                $stmt = $pdo->prepare("
                    INSERT INTO payment_items (payment_id, item_type, description, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $payment_id,
                    'renewal',
                    'Membership Renewal - ' . $plan['plan_name'],
                    1,
                    $price,
                    $price
                ]);
                
                // Log audit
                logAudit('MEMBERSHIP_RENEW', 'membership', $membership_id, 'Renewed membership for member: ' . $member['first_name'] . ' ' . $member['last_name']);
                
                // Commit transaction
                $pdo->commit();
                
                $success = true;
                displaySuccess('Membership renewed successfully!');
                header('Location: ../members/view.php?id=' . $member_id);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Renew membership error: ' . $e->getMessage());
                $error = 'An error occurred while renewing membership.';
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
                    <h4 class="mb-0"><i class="bi bi-arrow-repeat"></i> Renew Membership</h4>
                    <a href="../members/view.php?id=<?php echo $member_id; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Profile
                    </a>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card modern-card">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php echo csrfTokenField(); ?>
                                    
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <h6>Member: <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong></h6>
                                            <p class="text-muted">Member ID: <?php echo htmlspecialchars($member['member_number']); ?></p>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label required-field">Membership Plan *</label>
                                            <select class="form-select" name="plan_id" required>
                                                <option value="">Select Plan</option>
                                                <?php foreach ($plans as $plan): ?>
                                                <option value="<?php echo $plan['plan_id']; ?>">
                                                    <?php echo htmlspecialchars($plan['plan_name']); ?> - 
                                                    <?php echo formatCurrency($plan['student_price']); ?>/<?php echo formatCurrency($plan['regular_price']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label required-field">Customer Type *</label>
                                            <select class="form-select" name="customer_type" required>
                                                <option value="<?php echo CUSTOMER_STUDENT; ?>" <?php echo $member['customer_type'] === CUSTOMER_STUDENT ? 'selected' : ''; ?>>Student</option>
                                                <option value="<?php echo CUSTOMER_REGULAR; ?>" <?php echo $member['customer_type'] === CUSTOMER_REGULAR ? 'selected' : ''; ?>>Regular</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label required-field">Amount Paid (₱) *</label>
                                            <input type="number" class="form-control" name="amount_paid" step="0.01" min="0" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label required-field">Payment Method *</label>
                                            <select class="form-select" name="payment_method" required>
                                                <option value="<?php echo PAYMENT_CASH; ?>">Cash</option>
                                                <option value="<?php echo PAYMENT_GCASH; ?>">GCash</option>
                                                <option value="<?php echo PAYMENT_BANK_TRANSFER; ?>">Bank Transfer</option>
                                                <option value="<?php echo PAYMENT_CARD; ?>">Card</option>
                                                <option value="<?php echo PAYMENT_OTHER; ?>">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="notes" rows="2"></textarea>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg"></i> Renew Membership
                                        </button>
                                        <a href="../members/view.php?id=<?php echo $member_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Current Membership</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($current_membership): ?>
                                <p><strong>Plan:</strong> <?php echo htmlspecialchars($current_membership['plan_name']); ?></p>
                                <p><strong>Type:</strong> <?php echo ucfirst($current_membership['customer_type']); ?></p>
                                <p><strong>Start:</strong> <?php echo formatDate($current_membership['start_date']); ?></p>
                                <p><strong>Expires:</strong> <?php echo formatDate($current_membership['expiration_date']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge <?php echo $current_membership['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($current_membership['status']); ?>
                                    </span>
                                </p>
                                <?php else: ?>
                                <p class="text-muted">No active membership found.</p>
                                <?php endif; ?>
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