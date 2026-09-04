<?php
/**
 * Walk-in Check-in AJAX
 * Gym Management System
 * 
 * File: ajax/walkin-checkin.php
 * Purpose: Process walk-in check-ins via AJAX
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
$walkin_name = isset($_POST['walkin_name']) ? sanitize($_POST['walkin_name']) : '';
$customer_type = isset($_POST['customer_type']) ? sanitize($_POST['customer_type']) : 'regular';
$notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';

if (empty($walkin_name)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Walk-in name required']);
    exit;
}

// Initialize walk-in data
$walkin_data = [
    'walkin_name' => $walkin_name,
    'customer_type' => $customer_type,
    'notes' => $notes,
    'member_id' => 0,
    'member_number' => '',
];

try {
    // Check if walk-in already checked in today
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE walkin_name = ? AND check_in_date = ?");
    $stmt->execute([$walkin_name, date('Y-m-d')]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Walk-in already checked in today']);
        exit;
    }
    
    // Create attendance record for walk-in
    $stmt = $pdo->prepare("
        INSERT INTO attendance (walkin_name, customer_type, check_in_date, check_in_time, notes, status)
        VALUES (?, ?, ?, NOW(), ?, 'checked_in')
    ");
    $stmt->execute([$walkin_name, $customer_type, date('Y-m-d'), $notes]);
    
    $attendance_id = $pdo->lastInsertId();
    
    $walkin_data['member_id'] = 0;
    $walkin_data['attendance_id'] = $attendance_id;
    
    // Get the check-in time
    $checkin_time = date('H:i:s');
    
    $result = [
        'success' => true,
        'attendance_id' => $attendance_id,
        'walkin_name' => $walkin_name,
        'customer_type' => $customer_type,
        'check_in_time' => $checkin_time,
        'message' => 'Walk-in check-in recorded successfully',
    ];
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (PDOException $e) {
    error_log('Walk-in check-in error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>