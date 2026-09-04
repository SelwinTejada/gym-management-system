<?php
/**
 * Dashboard Stats AJAX
 * Gym Management System
 * 
 * File: ajax/dashboard-stats.php
 * Purpose: Return dashboard statistics via AJAX
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get today's date
$today = date('Y-m-d');
$current_month = date('Y-m');
$grace_period = defined('DEFAULT_MEMBERSHIP_GRACE_PERIOD') ? DEFAULT_MEMBERSHIP_GRACE_PERIOD : 7;

// Initialize stats
$stats = [
    'today_revenue' => 0,
    'today_expenses' => 0,
    'today_profit' => 0,
    'today_checkins' => 0,
    'active_members' => 0,
    'expiring_members' => 0,
    'expired_members' => 0,
    'monthly_revenue' => 0,
    'monthly_expenses' => 0,
    'monthly_profit' => 0,
];

try {
    // Today's revenue
    $stmt = $pdo->prepare("SELECT SUM(total) as total FROM payments WHERE payment_date = ? AND status = 'completed'");
    $stmt->execute([$today]);
    $stats['today_revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Today's expenses
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date = ?");
    $stmt->execute([$today]);
    $stats['today_expenses'] = $stmt->fetch()['total'] ?? 0;
    
    // Today's profit
    $stats['today_profit'] = $stats['today_revenue'] - $stats['today_expenses'];
    
    // Today's check-ins
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE check_in_date = ?");
    $stmt->execute([$today]);
    $stats['today_checkins'] = $stmt->fetch()['count'] ?? 0;
    
    // Active members
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT m.member_id) as count 
                           FROM members m 
                           INNER JOIN member_memberships mm ON m.member_id = mm.member_id 
                           WHERE mm.status = 'active' AND mm.expiration_date >= CURDATE()
                           AND m.status = 'active'");
    $stmt->execute();
    $stats['active_members'] = $stmt->fetch()['count'] ?? 0;
    
    // Expiring members (within 7 days)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT m.member_id) as count 
                           FROM members m 
                           INNER JOIN member_memberships mm ON m.member_id = mm.member_id 
                           WHERE mm.status = 'active' 
                           AND mm.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                           AND m.status = 'active'");
    $stmt->execute([$grace_period]);
    $stats['expiring_members'] = $stmt->fetch()['count'] ?? 0;
    
    // Expired members
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT m.member_id) as count 
                           FROM members m 
                           INNER JOIN member_memberships mm ON m.member_id = mm.member_id 
                           WHERE mm.status = 'active' AND mm.expiration_date < CURDATE()
                           AND m.status = 'active'");
    $stmt->execute();
    $stats['expired_members'] = $stmt->fetch()['count'] ?? 0;
    
    // Monthly revenue
    $stmt = $pdo->prepare("SELECT SUM(total) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ? AND status = 'completed'");
    $stmt->execute([$current_month]);
    $stats['monthly_revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Monthly expenses
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?");
    $stmt->execute([$current_month]);
    $stats['monthly_expenses'] = $stmt->fetch()['total'] ?? 0;
    
    // Monthly profit
    $stats['monthly_profit'] = $stats['monthly_revenue'] - $stats['monthly_expenses'];
    
} catch (PDOException $e) {
    error_log('Dashboard stats AJAX error: ' . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'stats' => $stats]);
?>