<?php
/**
 * Void Payment
 * Gym Management System
 * 
 * File: payments/void.php
 * Purpose: Void a payment transaction
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to void payments.');
    redirect('../dashboard.php');
}

// Get payment ID
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$reason = isset($_GET['reason']) ? sanitize($_GET['reason']) : '';

if ($payment_id <= 0) {
    displayError('Invalid payment ID.');
    redirect('index.php');
}

// Get payment details
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND status = 'completed'");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        displayError('Payment not found or already voided.');
        redirect('index.php');
    }
} catch (PDOException $e) {
    error_log('Get payment error: ' . $e->getMessage());
    displayError('An error occurred.');
    redirect('index.php');
}

// Process void
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        displayError('Security validation failed.');
        redirect('index.php');
    }
    
    $void_reason = sanitize($_POST['reason'] ?? '');
    
    if (empty($void_reason)) {
        displayError('Please provide a reason for voiding this payment.');
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update payment status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'void', notes = CONCAT(notes, '\nVoided: ', ?) WHERE payment_id = ?");
            $stmt->execute([$void_reason, $payment_id]);
            
            // Log audit
            logAudit('PAYMENT_VOID', 'payments', $payment_id, 'Voided payment: ' . $payment['transaction_number'] . ' - Reason: ' . $void_reason);
            
            $pdo->commit();
            
            displaySuccess('Payment voided successfully.');
            header('Location: view.php?id=' . $payment_id);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Void payment error: ' . $e->getMessage());
            displayError('An error occurred while voiding the payment.');
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
                <div class="card modern-card">
                    <div class="card-body">
                        <h4 class="mb-3"><i class="bi bi-x-circle"></i> Void Payment</h4>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>Warning:</strong> This action will void payment <code><?php echo htmlspecialchars($payment['transaction_number']); ?></code>
                            and remove it from revenue reports. This action cannot be undone.
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Transaction #:</strong> <?php echo htmlspecialchars($payment['transaction_number']); ?></p>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($payment['member_id'] ? 'Member' : ($payment['walkin_name'] ?? 'N/A')); ?></p>
                                <p><strong>Amount:</strong> <?php echo formatCurrency($payment['total']); ?></p>
                                <p><strong>Date:</strong> <?php echo formatDate($payment['payment_date']); ?></p>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <?php echo csrfTokenField(); ?>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Reason for Voiding *</label>
                                <textarea class="form-control" name="reason" rows="3" required placeholder="Please explain why this payment is being voided..."></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-x-circle"></i> Void Payment
                                </button>
                                <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-outline-secondary">Cancel</a>
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