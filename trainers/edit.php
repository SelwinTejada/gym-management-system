<?php
/**
 * Edit Trainer
 * Gym Management System
 * 
 * File: trainers/edit.php
 * Purpose: Edit trainer profile
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to edit trainers.');
    redirect('../dashboard.php');
}

// Get trainer ID
$trainer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($trainer_id <= 0) {
    displayError('Invalid trainer ID.');
    redirect('index.php');
}

// Get trainer details
try {
    $stmt = $pdo->prepare("SELECT * FROM personal_trainers WHERE trainer_id = ?");
    $stmt->execute([$trainer_id]);
    $trainer = $stmt->fetch();
    
    if (!$trainer) {
        displayError('Trainer not found.');
        redirect('index.php');
    }
} catch (PDOException $e) {
    error_log('Get trainer error: ' . $e->getMessage());
    displayError('An error occurred.');
    redirect('index.php');
}

// Get available users
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name FROM users WHERE role = 'trainer' AND status = 'active'");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get users error: ' . $e->getMessage());
    $users = [];
}

// Handle status toggle from GET
if (isset($_GET['status'])) {
    $new_status = sanitize($_GET['status']);
    if (in_array($new_status, ['active', 'inactive'])) {
        try {
            $stmt = $pdo->prepare("UPDATE personal_trainers SET status = ? WHERE trainer_id = ?");
            $stmt->execute([$new_status, $trainer_id]);
            
            logAudit('TRAINER_STATUS', 'trainers', $trainer_id, 'Trainer status changed to: ' . $new_status);
            displaySuccess('Trainer status updated successfully.');
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Update trainer status error: ' . $e->getMessage());
            displayError('An error occurred while updating status.');
        }
    }
}

// Process form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $rate = (float)($_POST['rate'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (empty($first_name) || empty($last_name) || empty($contact_number) || $rate <= 0) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Check if user is already a trainer (excluding current)
                if ($user_id) {
                    $stmt = $pdo->prepare("SELECT trainer_id FROM personal_trainers WHERE user_id = ? AND trainer_id != ?");
                    $stmt->execute([$user_id, $trainer_id]);
                    if ($stmt->fetch()) {
                        $error = 'This user is already assigned to another trainer.';
                        throw new Exception($error);
                    }
                }
                
                $stmt = $pdo->prepare("
                    UPDATE personal_trainers SET
                        user_id = ?,
                        first_name = ?,
                        last_name = ?,
                        contact_number = ?,
                        email = ?,
                        specialization = ?,
                        rate = ?,
                        notes = ?
                    WHERE trainer_id = ?
                ");
                
                $stmt->execute([
                    $user_id,
                    $first_name,
                    $last_name,
                    $contact_number,
                    $email ?: null,
                    $specialization ?: null,
                    $rate,
                    $notes ?: null,
                    $trainer_id
                ]);
                
                logAudit('TRAINER_EDIT', 'trainers', $trainer_id, 'Updated trainer: ' . $first_name . ' ' . $last_name);
                
                displaySuccess('Trainer updated successfully!');
                header('Location: view.php?id=' . $trainer_id);
                exit;
                
            } catch (Exception $e) {
                if (empty($error)) {
                    $error = 'An error occurred while updating the trainer.';
                }
                error_log('Update trainer error: ' . $e->getMessage());
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
                    <h4 class="mb-0"><i class="bi bi-pencil"></i> Edit Trainer</h4>
                    <a href="view.php?id=<?php echo $trainer_id; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Profile
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
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo htmlspecialchars($trainer['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo htmlspecialchars($trainer['last_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Contact Number *</label>
                                    <input type="text" class="form-control" name="contact_number" 
                                           value="<?php echo htmlspecialchars($trainer['contact_number']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($trainer['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Specialization</label>
                                    <input type="text" class="form-control" name="specialization" 
                                           value="<?php echo htmlspecialchars($trainer['specialization'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Rate per Session (₱) *</label>
                                    <input type="number" class="form-control" name="rate" step="0.01" min="0" 
                                           value="<?php echo $trainer['rate']; ?>" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Link to User Account</label>
                                    <select class="form-select" name="user_id">
                                        <option value="">None</option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>" 
                                                <?php echo $user['user_id'] == $trainer['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($trainer['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Trainer
                                </button>
                                <a href="view.php?id=<?php echo $trainer_id; ?>" class="btn btn-outline-secondary">Cancel</a>
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