<?php
/**
 * Daily Close Process
 * Gym Management System
 * 
 * File: daily-closing/close.php
 * Purpose: Process daily closing and generate report
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions - only owner can close daily
if (!isOwner()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');
$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // Get today's statistics
    $stats = [];
    
    // Today's revenue
    $stmt = $pdo->prepare("SELECT SUM(total) as total FROM payments WHERE payment_date = ? AND status = 'completed'");
    $stmt->execute([$today]);
    $revenue = $stmt->fetch()['total'] ?? 0;
    $stats['today_revenue'] = $revenue;
    
    // Today's expenses
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date = ?");
    $stmt->execute([$today]);
    $expenses = $stmt->fetch()['total'] ?? 0;
    $stats['today_expenses'] = $expenses;
    
    // Today's profit
    $stats['today_profit'] = $revenue - $expenses;
    
    // Today's check-ins
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE check_in_date = ?");
    $stmt->execute([$today]);
    $stats['today_checkins'] = $stmt->fetch()['count'] ?? 0;
    
    // Member check-in summary
    $stmt = $pdo->prepare("
        SELECT customer_type, COUNT(*) as count 
        FROM attendance 
        WHERE check_in_date = ? 
        GROUP BY customer_type
    ");
    $stmt->execute([$today]);
    $checkin_summary = $stmt->fetchAll();
    $stats['checkin_by_type'] = $checkin_summary;
    
    // Payments today
    $stmt = $pdo->prepare("
        SELECT p.*, m.member_number, CONCAT(m.first_name, ' ', m.last_name) as member_name
        FROM payments p
        LEFT JOIN members m ON p.member_id = m.member_id
        WHERE p.payment_date = ? AND p.status = 'completed'
        ORDER BY p.payment_time DESC
    ");
    $stmt->execute([$today]);
    $payments_today = $stmt->fetchAll();
    $stats['payments_today'] = $payments_today;
    
    // Expenses today
    $stmt = $pdo->prepare("
        SELECT * FROM expenses 
        WHERE expense_date = ?
        ORDER BY expense_time DESC
    ");
    $stmt->execute([$today]);
    $expenses_today = $stmt->fetchAll();
    $stats['expenses_today'] = $expenses_today;
    
    // Generate closing report data
    $report_data = [
        'closing_date' => $today,
        'closing_time' => date('H:i:s'),
        'revenue' => $stats['today_revenue'],
        'expenses' => $stats['today_expenses'],
        'profit' => $stats['today_profit'],
        'checkins' => $stats['today_checkins'],
        'payments' => $payments_today,
        'expenses_list' => $expenses_today,
    ];
    
    // In a real system, this would save to a closing_logs table
    // For now, we'll just return the data
    
    $response['success'] = true;
    $response['message'] = 'Daily close processed successfully for ' . $today;
    $response['data'] = $report_data;
    
    error_log('Daily close completed: ' . print_r($report_data, true));
    
} catch (PDOException $e) {
    error_log('Daily close error: ' . $e->getMessage());
    $response['message'] = 'Error processing daily close: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>