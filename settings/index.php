<?php
/**
 * System Settings
 * Gym Management System
 * 
 * File: settings/index.php
 * Purpose: Configure system settings
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!canManageSettings()) {
    displayError('You do not have permission to modify settings.');
    redirect('../dashboard.php');
}

// Get current settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value, setting_group FROM settings ORDER BY setting_group, setting_key");
    $settings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get settings error: ' . $e->getMessage());
    $settings = [];
}

// Group settings
$grouped_settings = [];
foreach ($settings as $setting) {
    $group = $setting['setting_group'] ?? 'general';
    if (!isset($grouped_settings[$group])) {
        $grouped_settings[$group] = [];
    }
    $grouped_settings[$group][$setting['setting_key']] = $setting['setting_value'];
}

// Process form submission
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        try {
            $pdo->beginTransaction();
            
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'setting_') === 0) {
                    $setting_key = substr($key, 8);
                    $setting_value = sanitize($value);
                    
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$setting_value, $setting_key]);
                }
            }
            
            $pdo->commit();
            
            // Log audit
            logAudit('SETTINGS_UPDATE', 'settings', 0, 'System settings updated');
            
            $success = true;
            displaySuccess('Settings updated successfully!');
            
            // Reload settings
            loadSystemSettings();
            
            // Refresh page to show updated settings
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Update settings error: ' . $e->getMessage());
            $error = 'An error occurred while updating settings.';
        }
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
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h4 class="mb-0"><i class="bi bi-gear"></i> System Settings</h4>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">Settings updated successfully!</div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php echo csrfTokenField(); ?>
                    
                    <!-- General Settings -->
                    <div class="card modern-card mb-4">
                        <div class="card-header">
                            <h5>General Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Gym Name</label>
                                    <input type="text" class="form-control" name="setting_gym_name" 
                                           value="<?php echo htmlspecialchars($grouped_settings['general']['gym_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" name="setting_currency_symbol" 
                                           value="<?php echo htmlspecialchars($grouped_settings['general']['currency_symbol'] ?? '₱'); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Gym Address</label>
                                    <input type="text" class="form-control" name="setting_gym_address" 
                                           value="<?php echo htmlspecialchars($grouped_settings['general']['gym_address'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="setting_gym_contact" 
                                           value="<?php echo htmlspecialchars($grouped_settings['general']['gym_contact'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="setting_gym_email" 
                                           value="<?php echo htmlspecialchars($grouped_settings['general']['gym_email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Settings -->
                    <div class="card modern-card mb-4">
                        <div class="card-header">
                            <h5>Attendance Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Duplicate Check-in Threshold (minutes)</label>
                                    <input type="number" class="form-control" name="setting_duplicate_checkin_threshold_minutes" 
                                           value="<?php echo htmlspecialchars($grouped_settings['attendance']['duplicate_checkin_threshold_minutes'] ?? 60); ?>">
                                    <small class="text-muted">Minimum time between duplicate check-ins</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Membership Grace Period (days)</label>
                                    <input type="number" class="form-control" name="setting_membership_grace_period_days" 
                                           value="<?php echo htmlspecialchars($grouped_settings['membership']['membership_grace_period_days'] ?? 7); ?>">
                                    <small class="text-muted">Days after expiration before marking as expired</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Settings
                    </button>
                </form>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>