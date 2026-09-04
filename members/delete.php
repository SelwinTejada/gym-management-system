<?php
/**
 * Delete Member
 * Gym Management System
 * 
 * File: members/delete.php
 * Purpose: Delete a member
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to delete members.');
    redirect('../dashboard.php');
}

// Get member ID
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$confirm = isset($_GET['confirm']) ? $_GET['confirm'] : '';

if ($member_id <= 0) {
    displayError('Invalid member ID.');
    redirect('index.php');
}

// Get member details
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, member_number FROM members WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        displayError('Member not found.');
        redirect('index.php');
    }
} catch (PDOException $e) {
    error_log('Get member error: ' . $e->getMessage());
    displayError('An error occurred.');
    redirect('index.php');
}

// Process deletion
if ($confirm === '1') {
    try {
        // Check if member has active membership
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM member_memberships WHERE member_id = ? AND status = 'active'");
        $stmt->execute([$member_id]);
        $active_membership = $stmt->fetchColumn();
        
        if ($active_membership > 0) {
            displayError('Cannot delete member with active membership. Please cancel the membership first.');
            redirect('view.php?id=' . $member_id);
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete attendance records
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE member_id = ?");
        $stmt->execute([$member_id]);
        
        // Delete PT sessions
        $stmt = $pdo->prepare("DELETE FROM pt_sessions WHERE member_id = ?");
        $stmt->execute([$member_id]);
        
        // Delete PT packages
        $stmt = $pdo->prepare("DELETE FROM pt_packages WHERE member_id = ?");
        $stmt->execute([$member_id]);
        
        // Delete payments (set member_id to NULL)
        $stmt = $pdo->prepare("UPDATE payments SET member_id = NULL WHERE member_id = ?");
        $stmt->execute([$member_id]);
        
        // Delete memberships
        $stmt = $pdo->prepare("DELETE FROM member_memberships WHERE member_id = ?");
        $stmt->execute([$member_id]);
        
        // Delete member
        $stmt = $pdo->prepare("DELETE FROM members WHERE member_id = ?");
        $stmt->execute([$member_id]);
        
        // Log audit
        logAudit('MEMBER_DELETE', 'members', $member_id, 'Deleted member: ' . $member['first_name'] . ' ' . $member['last_name']);
        
        // Commit transaction
        $pdo->commit();
        
        displaySuccess('Member successfully deleted.');
        header('Location: index.php');
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Delete member error: ' . $e->getMessage());
        displayError('An error occurred while deleting the member.');
        redirect('view.php?id=' . $member_id);
    }
}

// Include header
include '../includes/header.php';
?>

<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/navbar.php'; ?>
            
            <div class="content-wrapper">
                <div class="card modern-card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #dc3545; display: block; margin-bottom: 16px;"></i>
                        <h4>Delete Member</h4>
                        <p class="text-muted">
                            Are you sure you want to delete <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>?
                            <br><small>Member ID: <?php echo htmlspecialchars($member['member_number']); ?></small>
                        </p>
                        <div class="alert alert-danger">
                            <i class="bi bi-info-circle"></i> This action cannot be undone. All member data including attendance, payments, and PT records will be removed.
                        </div>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="view.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <a href="?id=<?php echo $member_id; ?>&confirm=1" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This action cannot be undone!');">
                                <i class="bi bi-trash"></i> Delete Permanently
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>