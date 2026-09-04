<?php
/**
 * Refund Process
 * Gym Management System
 * 
 * File: payments/refund.php
 * Purpose: Process refunds
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions - only owner can process refunds
if (!isOwner()) {
    displayError('You do not have permission to process refunds.');
    redirect('../dashboard.php');
}

// Get parameters
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

$is_editing_refund = $payment_id > 0;

$title = $is_editing_refund ? 'Process Refund' : 'Process Refund';

// Initialize variables
$error = '';
$success = false;
$form_data = [];
$payment = null;

// Load payment data if editing/refunding
if ($payment_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.member_number
            FROM payments p
            LEFT JOIN members m ON p.member_id = m.member_id
            WHERE p.payment_id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            displayError('Payment not found.');
            redirect('index.php');
        }
        
        $form_data = [
            'member_id' => $payment['member_id'],
            'member_name' => $payment['member_name'],
            'amount' => $payment['total'],
            'payment_method' => $payment['payment_method'],
            'transaction_number' => $payment['transaction_number'],
            'notes' => '',
        ];
    } catch (PDOException $e) {
        error_log('Get payment error: ' . $e->getMessage());
        displayError('Error loading payment data.');
        redirect('index.php');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $refund_amount = (float)($_POST['refund_amount'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
        
        if ($payment_id <= 0) {
            $error = 'Invalid payment ID';
        } elseif ($refund_amount <= 0) {
            $error = 'Refund amount must be greater than zero';
        } elseif ($refund_amount > ($payment ? $payment['total'] : 0)) {
            $error = 'Refund amount cannot exceed payment amount';
        } else {
            try {
                // Check if already refunded
                if ($payment && $payment['status'] === PAYMENT_REFUNDED) {
                    $error = 'This payment has already been refunded';
                } else {
                    // Process refund
                    $stmt = $pdo->prepare("
                        UPDATE payments SET status = ?, updated_at = NOW()
                        WHERE payment_id = ?
                    ");
                    $stmt->execute([PAYMENT_REFUNDED, $payment_id]);
                    
                    // Record the refund in audit or logs
                    // For now, just update the status
                    
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                        displaySuccess('Refund processed successfully. Amount: ' . formatCurrency($refund_amount));
                    } else {
                        $error = 'No changes made or payment not found';
                    }
                }
            } catch (PDOException $e) {
                error_log('Process refund error: ' . $e->getMessage());
                $error = 'Database error: ' . $e->getMessage();
            }
        }
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
            
            <?php if ($payment): ?>
            <div class="card modern-card mb-4">
                <div class="card-header">
                    <h5>Payment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Member</strong><br>
                            <?php echo htmlspecialchars($payment['member_name']); ?><br>
                            <small class="text-muted"><?php echo $payment['member_number']; ?></small>
                        </div>
                        <div class="col-md-6">
                            <strong>Amount</strong><br>
                            <?php echo formatCurrency($payment['total']); ?>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <strong>Payment Method</strong><br>
                            <span class="badge bg-light text-dark">
                                <?php echo ucfirst($payment['payment_method']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Transaction #</strong><br>
                            <code><?php echo htmlspecialchars($payment['transaction_number']); ?></code>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <strong>Original Status</strong><br>
                            <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($payment['status']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="card modern-card p-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateRandomString(32); ?>">
                <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
                
                <?php if ($payment): ?>
                <input type="hidden" name="original_amount" value="<?php echo $payment['total']; ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <?php if ($payment): ?>
                    <div class="col-md-6">
                        <label class="form-label">Payment Amount</label>
                        <p class="mb-0"><?php echo formatCurrency($payment['total']); ?></p>
                        <input type="hidden" name="original_payment_total" value="<?php echo $payment['total']; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Refund Amount</label>
                        <input type="number" step="0.01" class="form-control" name="refund_amount"
                               value="<?php echo isset($form_data['refund_amount']) ? $form_data['refund_amount'] : ($payment ? min($payment['total'], 100) : 0); ?>" 
                               min="0.01" max="<?php echo $payment ? $payment['total'] : 0; ?>" required>
                    </div>
                    <?php else: ?>
                    <div class="col-12">
                        <label class="form-label">Refund Amount</label>
                        <input type="number" step="0.01" class="form-control" name="refund_amount"
                               value="<?php echo $form_data['refund_amount'] ?? 0; ?>" min="0.01" required>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-12">
                        <label class="form-label">Reason for Refund</label>
                        <textarea class="form-control" name="reason" rows="3" required>
                            <?php echo $reason; ?></textarea>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-return-left me-2"></i> Process Refund
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>