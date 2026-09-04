<?php
/**
 * Payment Calculation AJAX
 * Gym Management System
 * 
 * File: ajax/payment-calc.php
 * Purpose: Calculate payment amounts via AJAX
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get parameters
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$customer_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'regular';

try {
    // Get plan details
    $stmt = $pdo->prepare("SELECT plan_name, duration_days, student_price, regular_price, is_active FROM membership_plans WHERE plan_id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    
    if (!$plan || !$plan['is_active']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Plan not found or inactive']);
        exit;
    }
    
    // Calculate amount based on customer type
    $price_key = strtolower($customer_type) . '_price';
    $amount = $plan[$price_key] ?? 0;
    
    // Calculate due date
    $due_date = date('Y-m-d', strtotime('+' . $plan['duration_days'] . ' days'));
    
    $result = [
        'plan_name' => $plan['plan_name'],
        'duration_days' => $plan['duration_days'],
        'customer_type' => $customer_type,
        'amount' => formatCurrency($amount),
        'due_date' => $due_date,
        'student_price' => formatCurrency($plan['student_price'] ?? 0),
        'regular_price' => formatCurrency($plan['regular_price'] ?? 0),
    ];
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'calculation' => $result]);
    
} catch (PDOException $e) {
    error_log('Payment calculation error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>