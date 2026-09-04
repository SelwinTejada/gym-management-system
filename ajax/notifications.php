<?php
/**
 * Notifications AJAX
 * Gym Management System
 * 
 * File: ajax/notifications.php
 * Purpose: Return notifications via AJAX
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

try {
    $notifications = [];
    
    // Check for expiring members
    $grace_period = defined('DEFAULT_MEMBERSHIP_GRACE_PERIOD') ? DEFAULT_MEMBERSHIP_GRACE_PERIOD : 7;
    $stmt = $pdo->prepare("SELECT m.member_id, m.first_name, m.last_name, m.member_number, 
                           mm.expiration_date, mp.plan_name
                           FROM members m
                           INNER JOIN member_memberships mm ON m.member_id = mm.member_id
                           INNER JOIN membership_plans mp ON mm.plan_id = mp.plan_id
                           WHERE mm.status = 'active' 
                           AND mm.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                           AND m.status = 'active'
                           ORDER BY mm.expiration_date ASC
                           LIMIT 10");
    $stmt->execute([$grace_period]);
    
    if ($expiring = $stmt->fetchAll()) {
        foreach ($expiring as $member) {
            $days = daysDifference(date('Y-m-d'), $member['expiration_date']);
            $notifications[] = [
                'type' => 'warning',
                'title' => 'Membership Expiring',
                'message' => $member['first_name'] . ' ' . $member['last_name'] . ' - ' . $member['plan_name'] . ' expires in ' . $days . ' days',
                'action_url' => '../membership/renew.php?member_id=' . $member['member_id'],
                'member_id' => $member['member_id'],
            ];
        }
    }
    
    // Check for pending payments or other notifications could be added here
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'notifications' => $notifications]);
    
} catch (PDOException $e) {
    error_log('Notifications AJAX error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>