<?php
/**
 * Member Check-in AJAX
 * Gym Management System
 * 
 * File: ajax/checkin-member.php
 * Purpose: Process member check-in
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

// Get parameters
$member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

if ($member_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
    exit;
}

try {
    // Get member details
    $stmt = $pdo->prepare("
        SELECT m.*, 
               mm.plan_id, mm.expiration_date, mm.status as membership_status,
               mp.plan_name, mp.duration_days
        FROM members m
        LEFT JOIN member_memberships mm ON m.member_id = mm.member_id AND mm.status = 'active'
        LEFT JOIN membership_plans mp ON mm.plan_id = mp.plan_id
        WHERE m.member_id = ? AND m.status = 'active'
    ");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }
    
    // Check if membership is expired
    $is_expired = !empty($member['expiration_date']) && isDatePast($member['expiration_date']);
    
    if ($is_expired) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'type' => 'warning',
            'title' => '⚠️ Membership Expired',
            'message' => $member['first_name'] . ' ' . $member['last_name'] . "'s membership expired on " . formatDate($member['expiration_date']) . ". Please renew before allowing gym access.",
            'action' => 'renew',
            'member_id' => $member_id,
            'member_name' => $member['first_name'] . ' ' . $member['last_name']
        ]);
        exit;
    }
    
    // Check for duplicate check-in
    $threshold = defined('DUPLICATE_CHECKIN_THRESHOLD_MINUTES') ? DUPLICATE_CHECKIN_THRESHOLD_MINUTES : 60;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, check_in_time 
        FROM attendance 
        WHERE member_id = ? AND check_in_date = CURDATE() 
        AND check_in_time >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$member_id, $threshold]);
    $duplicate = $stmt->fetch();
    
    if ($duplicate['count'] > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'type' => 'info',
            'title' => 'ℹ Already Checked In',
            'message' => $member['first_name'] . ' ' . $member['last_name'] . ' has already checked in today at ' . formatTime($duplicate['check_in_time']) . '.',
            'member_name' => $member['first_name'] . ' ' . $member['last_name']
        ]);
        exit;
    }
    
    // Process check-in
    $now = getCurrentDateTime();
    $membership_status = $member['membership_status'] ?? 'none';
    
    $stmt = $pdo->prepare("
        INSERT INTO attendance (
            member_id, customer_type, check_in_date, check_in_time,
            membership_status, processed_by
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $member_id,
        $member['customer_type'],
        $now['date'],
        $now['time'],
        $membership_status,
        getCurrentUserId()
    ]);
    
    $attendance_id = $pdo->lastInsertId();
    
    // Log audit
    logAudit('CHECKIN', 'attendance', $attendance_id, 'Member checked in: ' . $member['first_name'] . ' ' . $member['last_name']);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'member_name' => $member['first_name'] . ' ' . $member['last_name'],
        'customer_type' => $member['customer_type'],
        'checkin_time' => $now['display_time'],
        'plan_name' => $member['plan_name'] ?? 'No Plan',
        'expiration_date' => $member['expiration_date'] ? formatDate($member['expiration_date']) : 'N/A'
    ]);
    
} catch (PDOException $e) {
    error_log('Check-in error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'type' => 'error',
        'title' => 'Error',
        'message' => 'An error occurred while processing check-in. Please try again.'
    ]);
}
?>