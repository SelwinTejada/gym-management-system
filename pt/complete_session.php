<?php
/**
 * Complete PT Session
 * Gym Management System
 * 
 * File: pt/complete_session.php
 * Purpose: Mark a PT session as completed
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff() && !isTrainer()) {
    displayError('You do not have permission to complete PT sessions.');
    redirect('../dashboard.php');
}

// Get session ID
$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($session_id <= 0) {
    displayError('Invalid session ID.');
    redirect('sessions.php');
}

// Check access
if (!canAccessPTSession($session_id)) {
    displayError('You do not have permission to access this session.');
    redirect('sessions.php');
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get session details
    $stmt = $pdo->prepare("SELECT package_id, member_id, trainer_id, session_number FROM pt_sessions WHERE session_id = ? AND status = 'scheduled'");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Session not found or already completed.');
    }
    
    // Update session status
    $stmt = $pdo->prepare("UPDATE pt_sessions SET status = 'completed', completed_at = NOW() WHERE session_id = ?");
    $stmt->execute([$session_id]);
    
    // Update package usage
    $stmt = $pdo->prepare("UPDATE pt_packages SET used_sessions = used_sessions + 1 WHERE package_id = ?");
    $stmt->execute([$session['package_id']]);
    
    // Check if package is complete
    $stmt = $pdo->prepare("SELECT total_sessions, used_sessions FROM pt_packages WHERE package_id = ?");
    $stmt->execute([$session['package_id']]);
    $package = $stmt->fetch();
    
    if ($package['used_sessions'] >= $package['total_sessions']) {
        $stmt = $pdo->prepare("UPDATE pt_packages SET status = 'completed' WHERE package_id = ?");
        $stmt->execute([$session['package_id']]);
    }
    
    // Log audit
    logAudit('PT_SESSION_COMPLETE', 'pt', $session_id, 'PT Session #' . $session['session_number'] . ' completed for member ID: ' . $session['member_id']);
    
    // Commit transaction
    $pdo->commit();
    
    displaySuccess('PT Session successfully completed!');
    header('Location: sessions.php');
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Complete session error: ' . $e->getMessage());
    displayError('An error occurred while completing the session. Please try again.');
    header('Location: sessions.php');
    exit;
}
?>