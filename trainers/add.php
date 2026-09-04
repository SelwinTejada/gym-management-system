<?php
/**
 * Add Trainer
 * Gym Management System
 * 
 * File: trainers/add.php
 * Purpose: Add new personal trainer
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to add trainers.');
    redirect('../dashboard.php');
}

// Get available users for trainer account
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name FROM users WHERE role = 'trainer' AND status = 'active'");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get users error: ' . $e->getMessage());
    $users = [];
}

// Process form submission
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        // Sanitize input
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $rate = (float)($_POST['rate'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($contact_number) || $rate <= 0) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Check if user is already a trainer
                if ($user_id) {
                    $stmt = $pdo->prepare("SELECT trainer_id FROM personal_trainers WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    if ($stmt->fetch()) {
                        $error = 'This user is already a trainer.';
                        throw new Exception($error);
                    }
                }
                
                // Insert trainer
                $stmt = $pdo->prepare("
                    INSERT INTO personal_trainers (
                        user_id, first_name, last_name, contact_number, email,
                        specialization, rate, notes, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                $stmt->execute([
                    $user_id,
                    $first_name,
                    $last_name,
                    $contact_number,
                    $email ?: null,
                    $specialization ?: null,
                    $rate,
                    $notes ?: null
                ]);
                
                $trainer_id = $pdo->lastInsertId();
                
                // Log audit
                logAudit('TRAINER_ADD', 'trainers', $trainer_id, 'Added new trainer: ' . $first_name . ' ' . $last_name);
                
                $success = true;
                displaySuccess('Trainer successfully added!');
                header('Location: view.php?id=' . $trainer_id);
                exit;
                
            } catch (Exception $e) {
                if (empty($error)) {
                    $error = 'An error occurred while adding the trainer. Please try again.';
                }
                error_log('Add trainer error: ' . $e->getMessage());
            }
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
                    <h4 class="mb-0"><i class="bi bi-person-badge"></i> Add Personal Trainer</h4>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Trainers
                    </a>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card modern-card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php echo csrfTokenField(); ?>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required-field">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Contact Number *</label>
                                    <input type="text" class="form-control" name="contact_number" required placeholder="09XX-XXX-XXXX">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" placeholder="email@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Specialization</label>
                                    <input type="text" class="form-control" name="specialization" placeholder="e.g., Strength Training, Weight Loss">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Rate per Session (₱) *</label>
                                    <input type="number" class="form-control" name="rate" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Link to User Account</label>
                                    <select class="form-select" name="user_id">
                                        <option value="">None (Create standalone trainer)</option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Link this trainer to an existing system user account for system access.</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Any additional notes..."></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Add Trainer
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>