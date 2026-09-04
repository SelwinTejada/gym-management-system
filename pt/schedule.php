<?php
/**
 * Schedule PT Session
 * Gym Management System
 * 
 * File: pt/schedule.php
 * Purpose: Schedule a new PT session
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff() && !isTrainer()) {
    displayError('You do not have permission to schedule PT sessions.');
    redirect('../dashboard.php');
}

// Get parameters
$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

// Get active packages
try {
    $sql = "SELECT pp.*, 
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name
            FROM pt_packages pp
            INNER JOIN members m ON pp.member_id = m.member_id
            INNER JOIN personal_trainers pt ON pp.trainer_id = pt.trainer_id
            WHERE pp.status = 'active'";
    
    // If trainer, only show their packages
    if (isTrainer()) {
        $trainer_id = getCurrentTrainerId();
        if ($trainer_id) {
            $sql .= " AND pp.trainer_id = " . $trainer_id;
        }
    }
    
    $sql .= " ORDER BY pp.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get packages error: ' . $e->getMessage());
    $packages = [];
}

// Process form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed.';
    } else {
        $package_id = (int)($_POST['package_id'] ?? 0);
        $session_date = sanitize($_POST['session_date'] ?? '');
        $session_time = sanitize($_POST['session_time'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if ($package_id <= 0 || empty($session_date) || empty($session_time)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Get package details
                $stmt = $pdo->prepare("SELECT member_id, trainer_id, used_sessions FROM pt_packages WHERE package_id = ?");
                $stmt->execute([$package_id]);
                $package = $stmt->fetch();
                
                if (!$package) {
                    $error = 'Package not found.';
                    throw new Exception($error);
                }
                
                // Get next session number
                $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as next_num FROM pt_sessions WHERE package_id = ?");
                $stmt->execute([$package_id]);
                $next_session = $stmt->fetch()['next_num'] ?? 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO pt_sessions (
                        package_id, member_id, trainer_id, session_date, session_time,
                        session_number, notes, recorded_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $package_id,
                    $package['member_id'],
                    $package['trainer_id'],
                    $session_date,
                    $session_time,
                    $next_session,
                    $notes,
                    getCurrentUserId()
                ]);
                
                $session_id = $pdo->lastInsertId();
                
                logAudit('PT_SESSION_SCHEDULE', 'pt', $session_id, 'Scheduled PT session #' . $next_session);
                
                displaySuccess('PT session scheduled successfully!');
                header('Location: sessions.php');
                exit;
                
            } catch (Exception $e) {
                if (empty($error)) {
                    $error = 'An error occurred while scheduling the session.';
                }
                error_log('Schedule session error: ' . $e->getMessage());
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
                    <h4 class="mb-0"><i class="bi bi-clock"></i> Schedule PT Session</h4>
                    <a href="sessions.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Sessions
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
                                    <label class="form-label required-field">PT Package *</label>
                                    <select class="form-select" name="package_id" required>
                                        <option value="">Select Package</option>
                                        <?php foreach ($packages as $pkg): ?>
                                        <option value="<?php echo $pkg['package_id']; ?>" <?php echo $package_id == $pkg['package_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pkg['member_name']); ?> - 
                                            <?php echo htmlspecialchars($pkg['trainer_name']); ?> - 
                                            <?php echo $pkg['remaining_sessions']; ?> sessions remaining
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label required-field">Date *</label>
                                    <input type="date" class="form-control" name="session_date" 
                                           value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label required-field">Time *</label>
                                    <input type="time" class="form-control" name="session_time" 
                                           value="09:00" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Any special notes for this session..."></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Schedule Session
                                </button>
                                <a href="sessions.php" class="btn btn-outline-secondary">Cancel</a>
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