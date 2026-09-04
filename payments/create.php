<?php
/**
 * Create Payment
 * Gym Management System
 * 
 * File: payments/create.php
 * Purpose: Process new payments
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!canProcessPayments()) {
    displayError('You do not have permission to process payments.');
    redirect('../dashboard.php');
}

// Get parameters
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$transaction_type = isset($_GET['type']) ? sanitize($_GET['type']) : '';

// Get member if specified
$member = null;
if ($member_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ? AND status = 'active'");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get member error: ' . $e->getMessage());
    }
}

// Get membership plans
try {
    $stmt = $pdo->query("SELECT plan_id, plan_name, duration_days, student_price, regular_price FROM membership_plans WHERE is_active = 1 ORDER BY display_order");
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get plans error: ' . $e->getMessage());
    $plans = [];
}

// Get PT packages for member
$pt_packages = [];
if ($member_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT pp.*, CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name
            FROM pt_packages pp
            INNER JOIN personal_trainers pt ON pp.trainer_id = pt.trainer_id
            WHERE pp.member_id = ? AND pp.status = 'active'
        ");
        $stmt->execute([$member_id]);
        $pt_packages = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get PT packages error: ' . $e->getMessage());
    }
}

// Process form submission
$error = '';
$success = false;
$payment_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        // Sanitize input
        $member_id = (int)($_POST['member_id'] ?? 0);
        $walkin_name = sanitize($_POST['walkin_name'] ?? '');
        $transaction_type = sanitize($_POST['transaction_type'] ?? '');
        $payment_method = sanitize($_POST['payment_method'] ?? PAYMENT_CASH);
        $amount_received = (float)($_POST['amount_received'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Calculate based on transaction type
        $subtotal = 0;
        $discount = 0;
        $total = 0;
        $description = '';
        $reference_id = null;
        
        switch ($transaction_type) {
            case 'membership':
                $plan_id = (int)($_POST['plan_id'] ?? 0);
                $customer_type = sanitize($_POST['customer_type'] ?? CUSTOMER_STUDENT);
                $start_date = sanitize($_POST['start_date'] ?? date('Y-m-d'));
                
                // Get plan details
                $stmt = $pdo->prepare("SELECT plan_name, duration_days, student_price, regular_price FROM membership_plans WHERE plan_id = ?");
                $stmt->execute([$plan_id]);
                $plan = $stmt->fetch();
                
                if (!$plan) {
                    $error = 'Invalid membership plan selected.';
                    break;
                }
                
                $price = ($customer_type === CUSTOMER_STUDENT) ? $plan['student_price'] : $plan['regular_price'];
                $subtotal = $price;
                $total = $price;
                $description = $plan['plan_name'] . ' - ' . ucfirst($customer_type);
                break;
                
            case 'renewal':
                $plan_id = (int)($_POST['plan_id'] ?? 0);
                $customer_type = sanitize($_POST['customer_type'] ?? CUSTOMER_STUDENT);
                
                // Get plan details
                $stmt = $pdo->prepare("SELECT plan_name, duration_days, student_price, regular_price FROM membership_plans WHERE plan_id = ?");
                $stmt->execute([$plan_id]);
                $plan = $stmt->fetch();
                
                if (!$plan) {
                    $error = 'Invalid membership plan selected.';
                    break;
                }
                
                $price = ($customer_type === CUSTOMER_STUDENT) ? $plan['student_price'] : $plan['regular_price'];
                $subtotal = $price;
                $total = $price;
                $description = 'Renewal - ' . $plan['plan_name'] . ' - ' . ucfirst($customer_type);
                break;
                
            case 'pt_package':
                $package_id = (int)($_POST['package_id'] ?? 0);
                
                // Get package details
                $stmt = $pdo->prepare("SELECT total_sessions, price, member_id FROM pt_packages WHERE package_id = ?");
                $stmt->execute([$package_id]);
                $package = $stmt->fetch();
                
                if (!$package) {
                    $error = 'Invalid PT package selected.';
                    break;
                }
                
                $subtotal = $package['price'];
                $total = $package['price'];
                $description = 'PT Package - ' . $package['total_sessions'] . ' sessions';
                break;
                
            case 'pt_session':
                $session_id = (int)($_POST['session_id'] ?? 0);
                
                // Get session details
                $stmt = $pdo->prepare("SELECT * FROM pt_sessions WHERE session_id = ? AND status = 'scheduled'");
                $stmt->execute([$session_id]);
                $session = $stmt->fetch();
                
                if (!$session) {
                    $error = 'Invalid PT session selected.';
                    break;
                }
                
                // Get trainer rate
                $stmt = $pdo->prepare("SELECT rate FROM personal_trainers WHERE trainer_id = ?");
                $stmt->execute([$session['trainer_id']]);
                $trainer = $stmt->fetch();
                
                $price = $trainer['rate'] ?? 500;
                $subtotal = $price;
                $total = $price;
                $description = 'PT Session - Session #' . $session['session_number'];
                break;
                
            case 'walkin':
                $walkin_name = sanitize($_POST['walkin_name'] ?? '');
                $customer_type = sanitize($_POST['customer_type'] ?? CUSTOMER_STUDENT);
                
                if (empty($walkin_name)) {
                    $error = 'Please enter the walk-in customer name.';
                    break;
                }
                
                $price = ($customer_type === CUSTOMER_STUDENT) ? 70 : 100;
                $subtotal = $price;
                $total = $price;
                $description = 'Walk-in - ' . ucfirst($customer_type);
                break;
                
            default:
                $error = 'Invalid transaction type.';
                break;
        }
        
        if (empty($error)) {
            if ($amount_received < $total) {
                $error = 'Amount received is less than the total amount due (₱' . number_format($total, 2) . ').';
            } else {
                try {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    $now = getCurrentDateTime();
                    $change = $amount_received - $total;
                    $transaction_number = generateTransactionNumber();
                    
                    // Insert payment
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (
                            transaction_number, member_id, walkin_name, transaction_type, payment_method,
                            subtotal, discount, total, amount_paid, change_amount,
                            payment_date, payment_time, status, notes, processed_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $transaction_number,
                        $member_id > 0 ? $member_id : null,
                        $walkin_name ?: null,
                        $transaction_type,
                        $payment_method,
                        $subtotal,
                        $discount,
                        $total,
                        $amount_received,
                        $change,
                        $now['date'],
                        $now['time'],
                        PAYMENT_COMPLETED,
                        $notes,
                        getCurrentUserId()
                    ]);
                    
                    $payment_id = $pdo->lastInsertId();
                    
                    // Insert payment item
                    $stmt = $pdo->prepare("
                        INSERT INTO payment_items (
                            payment_id, item_type, description, quantity, unit_price, total_price
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $payment_id,
                        $transaction_type,
                        $description,
                        1,
                        $total,
                        $total
                    ]);
                    
                    // Process specific transaction types
                    switch ($transaction_type) {
                        case 'membership':
                            // Create membership record
                            $expiration_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_days'] . ' days'));
                            
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
                                $amount_received,
                                MEMBERSHIP_ACTIVE,
                                getCurrentUserId()
                            ]);
                            
                            // Update member customer type
                            $stmt = $pdo->prepare("UPDATE members SET customer_type = ? WHERE member_id = ?");
                            $stmt->execute([$customer_type, $member_id]);
                            
                            break;
                            
                        case 'renewal':
                            // Update existing membership
                            $stmt = $pdo->prepare("
                                UPDATE member_memberships 
                                SET start_date = ?, expiration_date = DATE_ADD(?, INTERVAL ? DAY),
                                    price = ?, amount_paid = ?, status = 'active'
                                WHERE member_id = ? AND status = 'active'
                            ");
                            
                            $stmt->execute([
                                $now['date'],
                                $now['date'],
                                $plan['duration_days'],
                                $price,
                                $amount_received,
                                $member_id
                            ]);
                            
                            break;
                            
                        case 'pt_package':
                            // Mark package as paid
                            $stmt = $pdo->prepare("UPDATE pt_packages SET status = 'active' WHERE package_id = ?");
                            $stmt->execute([$package_id]);
                            break;
                            
                        case 'pt_session':
                            // Mark session as paid
                            $stmt = $pdo->prepare("UPDATE pt_sessions SET status = 'scheduled' WHERE session_id = ?");
                            $stmt->execute([$session_id]);
                            break;
                    }
                    
                    // Log audit
                    logAudit('PAYMENT_CREATE', 'payments', $payment_id, 'Payment created: ' . $transaction_number);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $success = true;
                    displaySuccess('Payment successfully recorded!');
                    
                    $payment_data = [
                        'transaction_number' => $transaction_number,
                        'customer' => $member ? $member['first_name'] . ' ' . $member['last_name'] : ($walkin_name ?: 'N/A'),
                        'type' => $transaction_type,
                        'total' => $total,
                        'amount_received' => $amount_received,
                        'change' => $change,
                        'payment_method' => $payment_method,
                        'date' => $now['display_date'],
                        'time' => $now['display_time'],
                        'payment_id' => $payment_id,
                    ];
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log('Payment creation error: ' . $e->getMessage());
                    $error = 'An error occurred while processing the payment. Please try again.';
                }
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
                    <h4 class="mb-0"><i class="bi bi-credit-card"></i> New Payment</h4>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Payments
                    </a>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success && $payment_data): ?>
                <!-- Success Display -->
                <div class="card modern-card mb-4 border-success">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 48px;"></i>
                        <h4 class="mt-3">✓ Payment Successful</h4>
                        <p class="text-muted">Payment has been recorded successfully.</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6 offset-md-3">
                                <table class="table table-bordered">
                                    <tr>
                                        <td><strong>Transaction #</strong></td>
                                        <td><code><?php echo $payment_data['transaction_number']; ?></code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Customer</strong></td>
                                        <td><?php echo htmlspecialchars($payment_data['customer']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Type</strong></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment_data['type'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td><?php echo formatCurrency($payment_data['total']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Amount Received</strong></td>
                                        <td><?php echo formatCurrency($payment_data['amount_received']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Change</strong></td>
                                        <td><?php echo formatCurrency($payment_data['change']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Payment Method</strong></td>
                                        <td><?php echo ucfirst($payment_data['payment_method']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date</strong></td>
                                        <td><?php echo $payment_data['date'] . ' ' . $payment_data['time']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-4 d-flex gap-2 justify-content-center">
                            <a href="create.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> New Payment
                            </a>
                            <a href="receipt.php?id=<?php echo $payment_data['payment_id']; ?>" class="btn btn-outline-secondary" target="_blank">
                                <i class="bi bi-printer"></i> Print Receipt
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-list"></i> View All
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Payment Form -->
                <div class="card modern-card">
                    <div class="card-body">
                        <form method="POST" action="" id="paymentForm">
                            <?php echo csrfTokenField(); ?>
                            
                            <div class="row g-3">
                                <!-- Customer Selection -->
                                <div class="col-md-6">
                                    <label class="form-label">Customer</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="customerSearch" 
                                               placeholder="Search member..." value="<?php echo $member ? $member['first_name'] . ' ' . $member['last_name'] : ''; ?>">
                                        <input type="hidden" name="member_id" id="memberId" value="<?php echo $member_id; ?>">
                                        <button class="btn btn-outline-secondary" type="button" id="clearMember">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                    <div id="memberSearchResults" class="mt-2" style="display: none;"></div>
                                </div>
                                
                                <!-- Transaction Type -->
                                <div class="col-md-6">
                                    <label class="form-label required-field">Transaction Type *</label>
                                    <select class="form-select" name="transaction_type" id="transactionType" required>
                                        <option value="">Select Type</option>
                                        <option value="membership" <?php echo $transaction_type === 'membership' ? 'selected' : ''; ?>>Membership</option>
                                        <option value="renewal" <?php echo $transaction_type === 'renewal' ? 'selected' : ''; ?>>Renewal</option>
                                        <option value="walkin" <?php echo $transaction_type === 'walkin' ? 'selected' : ''; ?>>Walk-in</option>
                                        <option value="pt_package" <?php echo $transaction_type === 'pt_package' ? 'selected' : ''; ?>>PT Package</option>
                                        <option value="pt_session" <?php echo $transaction_type === 'pt_session' ? 'selected' : ''; ?>>PT Session</option>
                                        <option value="other" <?php echo $transaction_type === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <!-- Dynamic Fields -->
                                <div id="transactionFields" class="col-12">
                                    <!-- Membership Fields -->
                                    <div class="transaction-fields" data-type="membership" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label required-field">Customer Type *</label>
                                                <select class="form-select" name="customer_type">
                                                    <option value="<?php echo CUSTOMER_STUDENT; ?>">Student</option>
                                                    <option value="<?php echo CUSTOMER_REGULAR; ?>">Regular</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label required-field">Plan *</label>
                                                <select class="form-select" name="plan_id">
                                                    <option value="">Select Plan</option>
                                                    <?php foreach ($plans as $plan): ?>
                                                    <option value="<?php echo $plan['plan_id']; ?>">
                                                        <?php echo htmlspecialchars($plan['plan_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Start Date</label>
                                                <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Walk-in Fields -->
                                    <div class="transaction-fields" data-type="walkin" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label required-field">Customer Name *</label>
                                                <input type="text" class="form-control" name="walkin_name" placeholder="Enter full name">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label required-field">Customer Type *</label>
                                                <select class="form-select" name="customer_type">
                                                    <option value="<?php echo CUSTOMER_STUDENT; ?>">Student (₱70)</option>
                                                    <option value="<?php echo CUSTOMER_REGULAR; ?>">Regular (₱100)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- PT Package Fields -->
                                    <div class="transaction-fields" data-type="pt_package" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label required-field">PT Package *</label>
                                                <select class="form-select" name="package_id">
                                                    <option value="">Select Package</option>
                                                    <?php foreach ($pt_packages as $pkg): ?>
                                                    <option value="<?php echo $pkg['package_id']; ?>">
                                                        <?php echo htmlspecialchars($pkg['trainer_name']); ?> - 
                                                        <?php echo $pkg['total_sessions']; ?> sessions - 
                                                        <?php echo formatCurrency($pkg['price']); ?>
                                                        (<?php echo $pkg['remaining_sessions']; ?> remaining)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- PT Session Fields -->
                                    <div class="transaction-fields" data-type="pt_session" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label required-field">PT Session *</label>
                                                <select class="form-select" name="session_id">
                                                    <option value="">Select Session</option>
                                                    <?php
                                                    if ($member_id > 0) {
                                                        try {
                                                            $stmt = $pdo->prepare("
                                                                SELECT ps.*, CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name
                                                                FROM pt_sessions ps
                                                                INNER JOIN personal_trainers pt ON ps.trainer_id = pt.trainer_id
                                                                WHERE ps.member_id = ? AND ps.status = 'scheduled'
                                                            ");
                                                            $stmt->execute([$member_id]);
                                                            $sessions = $stmt->fetchAll();
                                                            foreach ($sessions as $session) {
                                                                echo '<option value="' . $session['session_id'] . '">';
                                                                echo htmlspecialchars($session['trainer_name']) . ' - ';
                                                                echo 'Session #' . $session['session_number'] . ' - ';
                                                                echo formatDate($session['session_date']) . ' ' . formatTime($session['session_time']);
                                                                echo '</option>';
                                                            }
                                                        } catch (PDOException $e) {}
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Other Fields -->
                                    <div class="transaction-fields" data-type="other" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label required-field">Description *</label>
                                                <input type="text" class="form-control" name="description" placeholder="Enter description">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label required-field">Amount *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₱</span>
                                                    <input type="number" class="form-control" name="other_amount" step="0.01" min="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Details -->
                                <div class="col-md-4">
                                    <label class="form-label required-field">Payment Method *</label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="<?php echo PAYMENT_CASH; ?>">Cash</option>
                                        <option value="<?php echo PAYMENT_GCASH; ?>">GCash</option>
                                        <option value="<?php echo PAYMENT_BANK_TRANSFER; ?>">Bank Transfer</option>
                                        <option value="<?php echo PAYMENT_CARD; ?>">Card</option>
                                        <option value="<?php echo PAYMENT_OTHER; ?>">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label required-field">Amount Received *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" name="amount_received" id="amountReceived" step="0.01" min="0" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Change</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="text" class="form-control" id="changeDisplay" value="0.00" readonly>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Total Display -->
                            <div class="row mb-3">
                                <div class="col-md-6 offset-md-6">
                                    <div class="payment-total">
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span id="subtotalDisplay">₱0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Discount:</span>
                                            <span id="discountDisplay">₱0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-bold" style="font-size: 18px; border-top: 2px solid var(--border-color); padding-top: 8px; margin-top: 8px;">
                                            <span>Total:</span>
                                            <span id="totalDisplay">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="bi bi-check-lg"></i> Process Payment
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
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
        // Toggle transaction fields
        $('#transactionType').on('change', function() {
            const type = $(this).val();
            $('.transaction-fields').hide();
            if (type && type !== 'renewal') {
                $('.transaction-fields[data-type="' + type + '"]').show();
            }
            calculateTotal();
        });
        
        // Trigger on load
        $('#transactionType').trigger('change');
        
        // Calculate total
        function calculateTotal() {
            const type = $('#transactionType').val();
            let total = 0;
            
            switch(type) {
                case 'membership':
                case 'renewal':
                    const planId = $('select[name="plan_id"]').val();
                    const customerType = $('select[name="customer_type"]').val();
                    if (planId) {
                        const option = $('select[name="plan_id"] option:selected');
                        total = customerType === 'student' 
                            ? parseFloat(option.data('student')) || 0
                            : parseFloat(option.data('regular')) || 0;
                    }
                    break;
                    
                case 'walkin':
                    const walkinType = $('select[name="customer_type"]').val();
                    total = walkinType === 'student' ? 70 : 100;
                    break;
                    
                case 'pt_package':
                    const packageId = $('select[name="package_id"]').val();
                    if (packageId) {
                        const option = $('select[name="package_id"] option:selected');
                        // Extract price from option text
                        const text = option.text();
                        const match = text.match(/₱([\d,]+\.\d{2})/);
                        if (match) {
                            total = parseFloat(match[1].replace(/,/g, ''));
                        }
                    }
                    break;
                    
                case 'pt_session':
                    const sessionId = $('select[name="session_id"]').val();
                    if (sessionId) {
                        // Default PT session rate
                        total = 500;
                    }
                    break;
                    
                case 'other':
                    total = parseFloat($('input[name="other_amount"]').val()) || 0;
                    break;
            }
            
            $('#subtotalDisplay').text('₱' + total.toFixed(2));
            $('#totalDisplay').text('₱' + total.toFixed(2));
            
            // Calculate change
            const received = parseFloat($('#amountReceived').val()) || 0;
            const change = Math.max(0, received - total);
            $('#changeDisplay').val(change.toFixed(2));
        }
        
        // Calculate on input changes
        $(document).on('change', 'select, input', function() {
            calculateTotal();
        });
        
        $('#amountReceived').on('input', function() {
            calculateTotal();
        });
        
        // Member search
        let searchTimeout;
        $('#customerSearch').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            
            if (query.length < 2) {
                $('#memberSearchResults').hide().empty();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                searchMembers(query);
            }, 300);
        });
        
        function searchMembers(query) {
            $.ajax({
                url: '../ajax/member-search.php',
                type: 'GET',
                data: { query: query },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.members.length > 0) {
                        displaySearchResults(response.members);
                    } else {
                        $('#memberSearchResults').html('<div class="text-muted small">No members found.</div>').show();
                    }
                }
            });
        }
        
        function displaySearchResults(members) {
            let html = '<div class="list-group">';
            members.forEach(function(member) {
                html += `
                    <a href="#" class="list-group-item list-group-item-action" data-id="${member.member_id}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${member.first_name} ${member.last_name}</strong>
                                <br><small class="text-muted">${member.member_number}</small>
                            </div>
                            <span class="badge ${member.customer_type === 'student' ? 'bg-info' : 'bg-secondary'}">${member.customer_type}</span>
                        </div>
                    </a>
                `;
            });
            html += '</div>';
            
            $('#memberSearchResults').html(html).show();
            
            // Handle selection
            $('.list-group-item').on('click', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const name = $(this).find('strong').text();
                $('#memberId').val(id);
                $('#customerSearch').val(name);
                $('#memberSearchResults').hide();
                $('#transactionType').trigger('change');
            });
        }
        
        // Clear member selection
        $('#clearMember').on('click', function() {
            $('#memberId').val('');
            $('#customerSearch').val('');
            $('#memberSearchResults').hide();
        });
    });
    </script>
    
    <style>
    .transaction-fields {
        background: var(--light-bg);
        padding: 16px;
        border-radius: var(--radius-sm);
        margin: 8px 0;
    }
    
    .payment-total {
        background: var(--light-bg);
        padding: 16px;
        border-radius: var(--radius-sm);
    }
    </style>
</body>
</html>