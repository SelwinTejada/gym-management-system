<?php
/**
 * Member Lookup AJAX
 * Gym Management System
 * 
 * File: ajax/member-lookup.php
 * Purpose: Lookup member details via AJAX
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

// Get member ID
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

if ($member_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Member ID required']);
    exit;
}

try {
    // Get member basic info
    $stmt = $pdo->prepare("SELECT m.*, mm.status as membership_status, mm.expiration_date, mp.plan_name, mp.duration_days, mp.student_price, mp.regular_price 
                           FROM members m
                           LEFT JOIN member_memberships mm ON m.member_id = mm.member_id
                           LEFT JOIN membership_plans mp ON mm.plan_id = mp.plan_id
                           WHERE m.member_id = ? AND m.status = 'active'");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }
    
    // Get membership details
    $membership_status = $member['membership_status'] ?? 'none';
    $expiration_date = $member['expiration_date'] ?? null;
    
    // Calculate status
    $is_expired = !empty($expiration_date) && isDatePast($expiration_date);
    $status = $is_expired ? MEMBERSHIP_EXPIRED : ($member['membership_status'] ?? 'none');
    
    $result = [
        'member_id' => $member['member_id'],
        'member_number' => $member['member_number'],
        'first_name' => $member['first_name'],
        'last_name' => $member['last_name'],
        'contact_number' => $member['contact_number'] ?? '',
        'email' => $member['email'] ?? '',
        'address' => $member['address'] ?? '',
        'customer_type' => $member['customer_type'] ?? 'regular',
        'membership_status' => $status,
        'plan_name' => $member['plan_name'] ?? 'No Plan',
        'plan_duration' => $member['duration_days'] ?? 0,
        'expiration_date' => $expiration_date,
        'enrollment_date' => $member['created_at'] ?? date('Y-m-d'),
    ];
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'member' => $result]);
    
} catch (PDOException $e) {
    error_log('Member lookup error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>