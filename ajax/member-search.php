<?php
/**
 * Member Search AJAX
 * Gym Management System
 * 
 * File: ajax/member-search.php
 * Purpose: Search members for check-in
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

// Get search query
$query = isset($_GET['query']) ? sanitize($_GET['query']) : '';

if (strlen($query) < 2) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'members' => []]);
    exit;
}

try {
    $search_param = '%' . $query . '%';
    
    $sql = "SELECT m.*, 
            mm.plan_id, mm.expiration_date, mm.status as membership_status,
            mp.plan_name
            FROM members m
            LEFT JOIN member_memberships mm ON m.member_id = mm.member_id AND mm.status = 'active'
            LEFT JOIN membership_plans mp ON mm.plan_id = mp.plan_id
            WHERE m.status = 'active' AND (
                m.first_name LIKE ? OR 
                m.last_name LIKE ? OR 
                m.member_number LIKE ? OR 
                m.contact_number LIKE ? OR
                CONCAT(m.first_name, ' ', m.last_name) LIKE ?
            )
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_param, $search_param, $search_param, $search_param, $search_param]);
    $members = $stmt->fetchAll();
    
    // Format response
    $results = [];
    foreach ($members as $member) {
        $is_expired = !empty($member['expiration_date']) && isDatePast($member['expiration_date']);
        $results[] = [
            'member_id' => $member['member_id'],
            'member_number' => $member['member_number'],
            'first_name' => $member['first_name'],
            'last_name' => $member['last_name'],
            'customer_type' => $member['customer_type'],
            'plan_name' => $member['plan_name'] ?? 'No Plan',
            'expiration_date' => $member['expiration_date'],
            'membership_status' => $is_expired ? 'expired' : ($member['membership_status'] ?? 'none'),
            'display_name' => $member['first_name'] . ' ' . $member['last_name']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'members' => $results]);
    
} catch (PDOException $e) {
    error_log('Member search error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>