<?php
/**
 * Payment Receipt
 * Gym Management System
 * 
 * File: payments/receipt.php
 * Purpose: Generate printable receipt
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!canProcessPayments()) {
    die('Unauthorized access.');
}

// Get payment ID
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    die('Invalid payment ID.');
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
        die('Payment not found.');
    }
} catch (PDOException $e) {
    die('Error retrieving payment information.');
}

// Get payment items
try {
    $stmt = $pdo->prepare("SELECT * FROM payment_items WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    $items = [];
}

// Get gym settings
$gym_name = defined('GYM_NAME') ? GYM_NAME : 'Elite Fitness Gym';
$gym_address = defined('GYM_ADDRESS') ? GYM_ADDRESS : '123 Fitness Street, Manila, Philippines';
$gym_contact = defined('GYM_CONTACT') ? GYM_CONTACT : '+63 912 345 6789';
$gym_email = defined('GYM_EMAIL') ? GYM_EMAIL : 'info@elitefitness.com';
$currency = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₱';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo $payment['transaction_number']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #FFFFFF;
            padding: 20px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .receipt {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 4px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px dashed #ddd;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        
        .header h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }
        
        .header p {
            font-size: 12px;
            color: #666;
            margin: 2px 0;
        }
        
        .details {
            margin-bottom: 12px;
        }
        
        .details table {
            width: 100%;
            font-size: 13px;
        }
        
        .details td {
            padding: 2px 0;
        }
        
        .details td:last-child {
            text-align: right;
        }
        
        .items {
            margin: 12px 0;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            padding: 8px 0;
        }
        
        .items table {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
        }
        
        .items th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 1px solid #ddd;
            padding: 4px 0;
        }
        
        .items td {
            padding: 4px 0;
        }
        
        .items td:last-child {
            text-align: right;
        }
        
        .totals {
            margin: 8px 0;
        }
        
        .totals table {
            width: 100%;
            font-size: 13px;
        }
        
        .totals td {
            padding: 2px 0;
        }
        
        .totals td:last-child {
            text-align: right;
        }
        
        .totals .grand-total {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #ddd;
            padding-top: 4px;
            margin-top: 4px;
        }
        
        .footer {
            text-align: center;
            border-top: 2px dashed #ddd;
            padding-top: 12px;
            margin-top: 12px;
            font-size: 11px;
            color: #666;
        }
        
        .status {
            text-align: center;
            padding: 8px;
            margin: 8px 0;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-void {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-refunded {
            background: #fff3cd;
            color: #856404;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .receipt {
                border: none;
                padding: 10px;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <h1><?php echo htmlspecialchars($gym_name); ?></h1>
            <p><?php echo htmlspecialchars($gym_address); ?></p>
            <p>Tel: <?php echo htmlspecialchars($gym_contact); ?></p>
            <p>Email: <?php echo htmlspecialchars($gym_email); ?></p>
        </div>
        
        <!-- Transaction Details -->
        <div class="details">
            <table>
                <tr>
                    <td><strong>Receipt #:</strong></td>
                    <td><?php echo htmlspecialchars($payment['transaction_number']); ?></td>
                </tr>
                <tr>
                    <td><strong>Date:</strong></td>
                    <td><?php echo formatDate($payment['payment_date']) . ' ' . formatTime($payment['payment_time']); ?></td>
                </tr>
                <tr>
                    <td><strong>Customer:</strong></td>
                    <td>
                        <?php 
                        if (!empty($payment['member_name'])) {
                            echo htmlspecialchars($payment['member_name']);
                        } elseif (!empty($payment['walkin_name'])) {
                            echo htmlspecialchars($payment['walkin_name']) . ' (Walk-in)';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Type:</strong></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['transaction_type'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Method:</strong></td>
                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                </tr>
                <tr>
                    <td><strong>Processed By:</strong></td>
                    <td><?php echo htmlspecialchars($payment['processed_by_name'] ?? 'Unknown'); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Status -->
        <div class="status status-<?php echo $payment['status']; ?>">
            <?php echo strtoupper($payment['status']); ?>
        </div>
        
        <!-- Items -->
        <?php if (!empty($items)): ?>
        <div class="items">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo $currency . number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Totals -->
        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td><?php echo $currency . number_format($payment['subtotal'], 2); ?></td>
                </tr>
                <?php if ($payment['discount'] > 0): ?>
                <tr>
                    <td>Discount:</td>
                    <td><?php echo $currency . number_format($payment['discount'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td><strong>TOTAL:</strong></td>
                    <td><strong><?php echo $currency . number_format($payment['total'], 2); ?></strong></td>
                </tr>
                <tr>
                    <td>Amount Paid:</td>
                    <td><?php echo $currency . number_format($payment['amount_paid'], 2); ?></td>
                </tr>
                <?php if ($payment['change_amount'] > 0): ?>
                <tr>
                    <td>Change:</td>
                    <td><?php echo $currency . number_format($payment['change_amount'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['balance'] > 0): ?>
                <tr>
                    <td><strong>Balance:</strong></td>
                    <td><strong><?php echo $currency . number_format($payment['balance'], 2); ?></strong></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Notes -->
        <?php if (!empty($payment['notes'])): ?>
        <div style="margin: 8px 0; font-size: 12px; color: #666; border-top: 1px dashed #ddd; padding-top: 8px;">
            <strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your payment!</p>
            <p><?php echo date('Y-m-d H:i:s'); ?></p>
            <p style="font-size: 10px; margin-top: 4px;">This is a computer-generated receipt.</p>
        </div>
        
        <!-- Print Button -->
        <div style="text-align: center; margin-top: 12px;" class="no-print">
            <button onclick="window.print()" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                <i class="bi bi-printer"></i> Print Receipt
            </button>
            <button onclick="window.close()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">
                Close
            </button>
        </div>
    </div>
</body>
</html>