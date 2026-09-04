<?php
/**
 * PT Remaining Sessions AJAX
 * Gym Management System
 * 
 * File: ajax/pt-remaining.php
 * Purpose: Return remaining PT sessions via AJAX
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

$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

try {
    $remaining = 0;
    $total_packages = 0;
    $session_history = [];
    
    if ($member_id > 0) {
        // Get active PT packages for member
        $stmt = $pdo->prepare("
            SELECT pp.*, pt.first_name as trainer_name, pt.last_name as trainer_last_name,
                   COUNT(ps.session_id) as completed_sessions,
                   pp.package_price as total_price
            FROM pt_packages pp
            INNER JOIN personal_trainers pt ON pp.trainer_id = pt.trainer_id
            LEFT JOIN pt_sessions ps ON pp.package_id = ps.package_id AND ps.status = 'completed'
            WHERE pp.member_id = ? AND pp.status = 'active'
            GROUP BY pp.package_id
        ");
        $stmt->execute([$member_id]);
        $packages = $stmt->fetchAll();
        
        foreach ($packages as $pkg) {
            $total_packages++;
            // Count total sessions in the package
            $stmt2 = $pdo->prepare("
                SELECT COUNT(*) as total FROM pt_sessions WHERE package_id = ?
            ");
            $stmt2->execute([$pkg['package_id']]);
            $total_count = $stmt2->fetch()['total'] ?? 0;
            
            $remaining += ($total_count - ($pkg['completed_sessions'] ?? 0));
            
            $session_history[] = [
                'package_name' => $pkg['plan_name'] ?? 'PT Package',
                'total_sessions' => $total_count,
                'completed' => $pkg['completed_sessions'] ?? 0,
                'remaining' => $total_count - ($pkg['completed_sessions'] ?? 0),
                'trainer' => $pkg['trainer_name'] . ' ' . $pkg['trainer_last_name'],
                'package_price' => formatCurrency($pkg['total_price'] ?? 0),
            ];
        }
    } else {
        // Get all remaining PT sessions for staff
        $stmt = $pdo->prepare("
            SELECT pp.*, CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name,
                   COUNT(ps.session_id) as completed_sessions,
                   pp.package_price as total_price
            FROM pt_packages pp
            INNER JOIN personal_trainers pt ON pp.trainer_id = pt.trainer_id
            LEFT JOIN pt_sessions ps ON pp.package_id = ps.package_id AND ps.status = 'completed'
            WHERE pp.status = 'active'
            GROUP BY pp.package_id
        ");
        $stmt->execute();
        $packages = $stmt->fetchAll();
        
        foreach ($packages as $pkg) {
            $total_packages++;
            $stmt2 = $pdo->prepare("
                SELECT COUNT(*) as total FROM pt_sessions WHERE package_id = ?
            ");
            $stmt2->execute([$pkg['package_id']]);
            $total_count = $stmt2->fetch()['total'] ?? 0;
            
            $remaining += ($total_count - ($pkg['completed_sessions'] ?? 0));
            
            $session_history[] = [
                'package_name' => $pkg['plan_name'] ?? 'PT Package',
                'total_sessions' => $total_count,
                'completed' => $pkg['completed_sessions'] ?? 0,
                'remaining' => $total_count - ($pkg['completed_sessions'] ?? 0),
                'trainer' => $pkg['trainer_name'],
                'member_number' => $pkg['member_number'] ?? 'N/A',
                'package_price' => formatCurrency($pkg['total_price'] ?? 0),
            ];
        }
    }
    
    $result = [
        'remaining_sessions' => $remaining,
        'total_packages' => $total_packages,
        'session_history' => $session_history,
    ];
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $result]);
    
} catch (PDOException $e) {
    error_log('PT remaining error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>