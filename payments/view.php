<?php
/**
 * View Payment Details
 * Gym Management System
 * 
 * File: payments/view.php
 * Purpose: Display payment details
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!canProcessPayments()) {
    displayError('You do not have permission to view payments.');
    redirect('../dashboard.php');
}

// Get payment ID
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    displayError('Invalid payment ID.');
    redirect('index.php');
}

// Get payment details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               u.full_name as processed_by_name
        FROM payments p
        LEFT JOIN members m ON p.member_id = m.member_id
        LEFT JOIN users u ON p.processed_by = u.user_id
        WHERE p.payment_id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        displayError('Payment not found.');
        redirect('index.php');
    }
} catch (PDOException $e) {
    error_log('Get payment error: ' . $e->getMessage());
    displayError('An error occurred while retrieving payment information.');
    redirect('index.php');
}

// Get payment items
try {
    $stmt = $pdo->prepare("SELECT * FROM payment_items WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get payment items error: ' . $e->getMessage());
    $items = [];
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
                    <h4 class="mb-0"><i class="bi bi-credit-card"></i> Payment Details</h4>
                    <div class="d-flex gap-2">
                        <a href="receipt.php?id=<?php echo $payment_id; ?>" class="btn btn-outline-secondary" target="_blank">
                            <i class="bi bi-printer"></i> Print Receipt
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <!-- Payment Status -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5>Transaction #: <code><?php echo htmlspecialchars($payment['transaction_number']); ?></code></h5>
                                <p class="text-muted mb-0">
                                    Processed on <?php echo formatDate($payment['payment_date']) . ' at ' . formatTime($payment['payment_time']); ?>
                                    by <?php echo htmlspecialchars($payment['processed_by_name'] ?? 'Unknown'); ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <?php if ($payment['status'] === PAYMENT_COMPLETED): ?>
                                    <span class="badge bg-success" style="font-size: 16px; padding: 8px 16px;">Completed</span>
                                <?php elseif ($payment['status'] === PAYMENT_VOID): ?>
                                    <span class="badge bg-danger" style="font-size: 16px; padding: 8px 16px;">Void</span>
                                <?php elseif ($payment['status'] === PAYMENT_REFUNDED): ?>
                                    <span class="badge bg-warning text-dark" style="font-size: 16px; padding: 8px 16px;">Refunded</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Customer Information -->
                    <div class="col-md-6">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Customer Information</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($payment['member_name'])): ?>
                                    <p class="mb-1"><strong>Member:</strong> <?php echo htmlspecialchars($payment['member_name']); ?></p>
                                    <p class="mb-0"><strong>Type:</strong> <?php echo ucfirst($payment['transaction_type']); ?></p>
                                <?php elseif (!empty($payment['walkin_name'])): ?>
                                    <p class="mb-1"><strong>Walk-in:</strong> <?php echo htmlspecialchars($payment['walkin_name']); ?></p>
                                    <p class="mb-0"><strong>Type:</strong> Walk-in</p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No customer information available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Summary -->
                    <div class="col-md-6">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Payment Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span><?php echo formatCurrency($payment['subtotal']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Discount:</span>
                                    <span><?php echo formatCurrency($payment['discount']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 fw-bold" style="font-size: 18px; border-top: 2px solid var(--border-color); padding-top: 8px;">
                                    <span>Total:</span>
                                    <span><?php echo formatCurrency($payment['total']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Amount Received:</span>
                                    <span><?php echo formatCurrency($payment['amount_paid']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-0">
                                    <span>Change:</span>
                                    <span><?php echo formatCurrency($payment['change_amount']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <span>Payment Method:</span>
                                    <span><?php echo ucfirst($payment['payment_method']); ?></span>
                                </div>
                                <?php if (!empty($payment['reference_number'])): ?>
                                <div class="d-flex justify-content-between mt-1">
                                    <span>Reference #:</span>
                                    <span><?php echo htmlspecialchars($payment['reference_number']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Items -->
                <?php if (!empty($items)): ?>
                <div class="card modern-card mt-4">
                    <div class="card-header">
                        <h5>Payment Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo formatCurrency($item['unit_price']); ?></td>
                                        <td><?php echo formatCurrency($item['total_price']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Total:</td>
                                        <td class="fw-bold"><?php echo formatCurrency($payment['total']); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Notes -->
                <?php if (!empty($payment['notes'])): ?>
                <div class="card modern-card mt-4">
                    <div class="card-header">
                        <h5>Notes</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <?php if (isOwner() && $payment['status'] === PAYMENT_COMPLETED): ?>
                <div class="mt-4">
                    <button class="btn btn-danger" onclick="voidPayment(<?php echo $payment_id; ?>)">
                        <i class="bi bi-x-circle"></i> Void Payment
                    </button>
                    <button class="btn btn-warning" onclick="refundPayment(<?php echo $payment_id; ?>)">
                        <i class="bi bi-arrow-counterclockwise"></i> Refund
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <script>
    function voidPayment(id) {
        const reason = prompt('Please provide a reason for voiding this payment:');
        if (reason !== null && reason.trim() !== '') {
            window.location.href = 'void.php?id=' + id + '&reason=' + encodeURIComponent(reason);
        } else if (reason !== null) {
            alert('Please provide a reason for voiding the payment.');
        }
    }
    
    function refundPayment(id) {
        const reason = prompt('Please provide a reason for refunding this payment:');
        if (reason !== null && reason.trim() !== '') {
            window.location.href = 'refund.php?id=' + id + '&reason=' + encodeURIComponent(reason);
        } else if (reason !== null) {
            alert('Please provide a reason for refunding the payment.');
        }
    }
    </script>
</body>
</html>