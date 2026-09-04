<?php
/**
 * Notifications
 * Gym Management System
 * 
 * File: includes/notifications.php
 * Purpose: Display and manage notifications
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view notifications.');
    redirect('../dashboard.php');
}

// Initialize notifications
$notifications = [];
$unread_count = 0;

// Get notifications
try {
    $stmt = $pdo->prepare("
        SELECT notification_id, title, message, type, created_at, 
               action_url, is_read
        FROM notifications
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll();
    
    // Count unread
    if (!empty($notifications)) {
        $unread_stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM notifications WHERE is_read = 0
        ");
        $unread_stmt->execute();
        $unread_count = $unread_stmt->fetch()['count'] ?? 0;
    }
    
} catch (PDOException $e) {
    error_log('Get notifications error: ' . $e->getMessage());
    $notifications = [];
    $unread_count = 0;
}
?>

<div class="notifications-sidebar">
    <?php if ($unread_count > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3">
        <i class="bi bi-bell me-2"></i>
        <strong>You have <?php echo $unread_count; ?> unread notification(s)</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="list-group">
        <?php if (empty($notifications)): ?>
        <div class="list-group-item text-center text-muted py-4">
            <i class="bi bi-bell" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
            No notifications.
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $notif): 
            $is_unread = !$notif['is_read'];
            $badge_class = $is_unread ? 'bg-warning text-dark' : 'bg-secondary';
            $badge_text = $is_unread ? 'Unread' : 'Read';
        ?>
        <a href="<?php echo htmlspecialchars($notif['action_url'] ?? '#'); ?>" 
           class="list-group-item list-group-item-action flex-column align-items-start 
                   <?php echo $is_unread ? 'fw-bold' : ''; ?>">
            <div class="w-100 text-start">
                <h6 class="mb-1"><?php echo htmlspecialchars($notif['title']); ?></h6>
                <p class="mb-1 small text-muted"><?php echo htmlspecialchars($notif['message']); ?></p>
                <small class="text-end">
                    <?php echo formatDate($notif['created_at']); ?>
                    <?php echo $is_unread ? ' <span class="badge <?php echo $badge_class;"><?php echo $badge_text; ?></span>' : ''; ?>
                </small>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($notifications)): ?>
    <div class="mt-3 text-center">
        <button class="btn btn-sm btn-outline-secondary" onclick="markAllRead()">
            <i class="bi bi-check-all"></i> Mark All as Read
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
function markAllRead() {
    if (confirm('Mark all notifications as read?')) {
        window.location.href = 'mark-all-read.php';
    }
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>