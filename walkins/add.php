<?php
/**
 * Add Walk-in
 * Gym Management System
 * 
 * File: walkins/add.php
 * Purpose: Process walk-in customers
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to process walk-ins.');
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
        $name = sanitize($_POST['name'] ?? '');
        $customer_type = sanitize($_POST['customer_type'] ?? CUSTOMER_STUDENT);
        $payment_method = sanitize($_POST['payment_method'] ?? PAYMENT_CASH);
        $amount_received = (float)($_POST['amount_received'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Set entrance fee based on customer type
        $entrance_fee = ($customer_type === CUSTOMER_STUDENT) ? 70.00 : 100.00;
        
        // Validate required fields
        if (empty($name)) {
            $error = 'Please enter the customer name.';
        } elseif ($amount_received < $entrance_fee) {
            $error = 'Amount received is less than the entrance fee (₱' . number_format($entrance_fee, 2) . ').';
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                $now = getCurrentDateTime();
                $change = $amount_received - $entrance_fee;
                $transaction_number = generateTransactionNumber('WALK');
                
                // Insert attendance record
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (
                        walkin_name, customer_type, check_in_date, check_in_time, 
                        membership_status, processed_by, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $name,
                    $customer_type,
                    $now['date'],
                    $now['time'],
                    'walkin',
                    getCurrentUserId(),
                    $notes
                ]);
                
                $attendance_id = $pdo->lastInsertId();
                
                // Insert payment record
                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        transaction_number, walkin_name, transaction_type, payment_method,
                        subtotal, discount, total, amount_paid, change_amount,
                        payment_date, payment_time, status, notes, processed_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $transaction_number,
                    $name,
                    TRANSACTION_WALKIN,
                    $payment_method,
                    $entrance_fee,
                    0,
                    $entrance_fee,
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
                    'walkin',
                    'Walk-in Entrance Fee - ' . ucfirst($customer_type),
                    1,
                    $entrance_fee,
                    $entrance_fee
                ]);
                
                // Log audit
                logAudit('WALKIN_ADD', 'walkins', $attendance_id, 'Walk-in checked in: ' . $name);
                logAudit('PAYMENT_CREATE', 'payments', $payment_id, 'Walk-in payment: ' . $transaction_number);
                
                // Commit transaction
                $pdo->commit();
                
                $success = true;
                displaySuccess('Walk-in successfully checked in!');
                
                // Store for display
                $walkin_data = [
                    'name' => $name,
                    'customer_type' => $customer_type,
                    'entrance_fee' => $entrance_fee,
                    'payment_method' => $payment_method,
                    'amount_received' => $amount_received,
                    'change' => $change,
                    'transaction_number' => $transaction_number,
                    'checkin_time' => $now['display_time'],
                    'checkin_date' => $now['display_date'],
                ];
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Walk-in error: ' . $e->getMessage());
                $error = 'An error occurred while processing the walk-in. Please try again.';
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
                    <h4 class="mb-0"><i class="bi bi-person-walking"></i> Walk-in Customer</h4>
                    <a href="../attendance/checkin.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Check-in
                    </a>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success && isset($walkin_data)): ?>
                <!-- Success Display -->
                <div class="card modern-card mb-4 border-success">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 48px;"></i>
                        <h4 class="mt-3">✓ Walk-in Check-in Successful</h4>
                        <p class="text-muted">Customer has been checked in successfully.</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6 offset-md-3">
                                <table class="table table-bordered">
                                    <tr>
                                        <td><strong>Customer</strong></td>
                                        <td><?php echo htmlspecialchars($walkin_data['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Type</strong></td>
                                        <td><?php echo ucfirst($walkin_data['customer_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Entrance Fee</strong></td>
                                        <td><?php echo formatCurrency($walkin_data['entrance_fee']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Payment</strong></td>
                                        <td><?php echo ucfirst($walkin_data['payment_method']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Amount Received</strong></td>
                                        <td><?php echo formatCurrency($walkin_data['amount_received']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Change</strong></td>
                                        <td><?php echo formatCurrency($walkin_data['change']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Transaction #</strong></td>
                                        <td><code><?php echo $walkin_data['transaction_number']; ?></code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Check-in Time</strong></td>
                                        <td><?php echo $walkin_data['checkin_time']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-4 d-flex gap-2 justify-content-center">
                            <a href="add.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> New Walk-in
                            </a>
                            <a href="../attendance/checkin.php" class="btn btn-outline-secondary">
                                <i class="bi bi-check2-circle"></i> Member Check-in
                            </a>
                            <button class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print Receipt
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Walk-in Form -->
                <div class="card modern-card">
                    <div class="card-body">
                        <form method="POST" action="" id="walkinForm">
                            <?php echo csrfTokenField(); ?>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required-field">Customer Name *</label>
                                    <input type="text" class="form-control form-control-lg" name="name" 
                                           placeholder="Enter full name" required>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label required-field">Customer Type *</label>
                                    <select class="form-select form-select-lg" name="customer_type" id="customerType" required>
                                        <option value="<?php echo CUSTOMER_STUDENT; ?>">Student (₱70)</option>
                                        <option value="<?php echo CUSTOMER_REGULAR; ?>">Regular (₱100)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Entrance Fee</label>
                                    <input type="text" class="form-control form-control-lg" id="entranceFee" 
                                           value="₱70.00" readonly>
                                </div>
                                
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
                                        <input type="number" class="form-control form-control-lg" name="amount_received" 
                                               id="amountReceived" step="0.01" min="0" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Change</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="text" class="form-control form-control-lg" id="changeDisplay" 
                                               value="0.00" readonly>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="2" 
                                              placeholder="Any additional notes..."></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="bi bi-check2-circle"></i> Check In
                                </button>
                                <a href="../attendance/checkin.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
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
        // Auto-calculate entrance fee based on customer type
        $('#customerType').on('change', function() {
            const type = $(this).val();
            const fee = type === 'student' ? 70 : 100;
            $('#entranceFee').val('₱' + fee.toFixed(2));
            calculateChange();
        });
        
        // Calculate change on amount received change
        $('#amountReceived').on('input', function() {
            calculateChange();
        });
        
        function calculateChange() {
            const type = $('#customerType').val();
            const fee = type === 'student' ? 70 : 100;
            const received = parseFloat($('#amountReceived').val()) || 0;
            const change = Math.max(0, received - fee);
            $('#changeDisplay').val(change.toFixed(2));
        }
        
        // Validate form on submit
        $('#walkinForm').on('submit', function(e) {
            const received = parseFloat($('#amountReceived').val()) || 0;
            const type = $('#customerType').val();
            const fee = type === 'student' ? 70 : 100;
            
            if (received < fee) {
                e.preventDefault();
                showToast('warning', 'Amount received must be at least ₱' + fee.toFixed(2));
                return false;
            }
            
            return true;
        });
        
        // Calculate on load
        calculateChange();
    });
    </script>
</body>
</html>