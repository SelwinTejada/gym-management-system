<?php
/**
 * Create PT Package
 * Gym Management System
 * 
 * File: pt/create_package.php
 * Purpose: Create new PT package for a member
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to create PT packages.');
    redirect('../dashboard.php');
}

// Get parameters
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$trainer_id = isset($_GET['trainer_id']) ? (int)$_GET['trainer_id'] : 0;

// Get member if specified
$member = null;
if ($member_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ? AND status = 'active'");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get member error: ' . $e->getMessage());
    }
}

// Get active trainers
try {
    $stmt = $pdo->query("SELECT trainer_id, first_name, last_name, rate FROM personal_trainers WHERE status = 'active' ORDER BY last_name");
    $trainers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get trainers error: ' . $e->getMessage());
    $trainers = [];
}

// Process form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed.';
    } else {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $trainer_id = (int)($_POST['trainer_id'] ?? 0);
        $total_sessions = (int)($_POST['total_sessions'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $start_date = sanitize($_POST['start_date'] ?? date('Y-m-d'));
        $expiration_date = sanitize($_POST['expiration_date'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if ($member_id <= 0 || $trainer_id <= 0 || $total_sessions <= 0 || $price <= 0) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Check for existing active package
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pt_packages WHERE member_id = ? AND status = 'active'");
                $stmt->execute([$member_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'This member already has an active PT package.';
                    throw new Exception($error);
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO pt_packages (
                        member_id, trainer_id, total_sessions, price, start_date, expiration_date, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $member_id,
                    $trainer_id,
                    $total_sessions,
                    $price,
                    $start_date,
                    $expiration_date ?: null,
                    $notes,
                    getCurrentUserId()
                ]);
                
                $package_id = $pdo->lastInsertId();
                
                logAudit('PT_PACKAGE_CREATE', 'pt', $package_id, 'Created PT package for member ID: ' . $member_id);
                
                displaySuccess('PT package created successfully!');
                header('Location: view_package.php?id=' . $package_id);
                exit;
                
            } catch (Exception $e) {
                if (empty($error)) {
                    $error = 'An error occurred while creating the package.';
                }
                error_log('Create package error: ' . $e->getMessage());
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
                    <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Create PT Package</h4>
                    <a href="packages.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Packages
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
                                    <label class="form-label required-field">Member *</label>
                                    <select class="form-select" name="member_id" required>
                                        <option value="">Select Member</option>
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT member_id, first_name, last_name, member_number FROM members WHERE status = 'active' ORDER BY last_name");
                                            $members = $stmt->fetchAll();
                                            foreach ($members as $m) {
                                                $selected = $member_id == $m['member_id'] ? 'selected' : '';
                                                echo '<option value="' . $m['member_id'] . '" ' . $selected . '>';
                                                echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name'] . ' (' . $m['member_number'] . ')');
                                                echo '</option>';
                                            }
                                        } catch (PDOException $e) {
                                            error_log('Get members error: ' . $e->getMessage());
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label required-field">Trainer *</label>
                                    <select class="form-select" name="trainer_id" required>
                                        <option value="">Select Trainer</option>
                                        <?php foreach ($trainers as $t): ?>
                                        <option value="<?php echo $t['trainer_id']; ?>" <?php echo $trainer_id == $t['trainer_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                                            (Rate: <?php echo formatCurrency($t['rate']); ?>/session)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label required-field">Total Sessions *</label>
                                    <input type="number" class="form-control" name="total_sessions" min="1" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label required-field">Price (₱) *</label>
                                    <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Price per Session</label>
                                    <input type="text" class="form-control" id="pricePerSession" readonly>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Expiration Date</label>
                                    <input type="date" class="form-control" name="expiration_date">
                                    <small class="text-muted">Leave blank for no expiration</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Create Package
                                </button>
                                <a href="packages.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        $('input[name="total_sessions"], input[name="price"]').on('input', function() {
            calculatePricePerSession();
        });
        
        function calculatePricePerSession() {
            const sessions = parseInt($('input[name="total_sessions"]').val()) || 0;
            const price = parseFloat($('input[name="price"]').val()) || 0;
            
            if (sessions > 0 && price > 0) {
                const perSession = price / sessions;
                $('#pricePerSession').val('₱' + perSession.toFixed(2));
            } else {
                $('#pricePerSession').val('');
            }
        }
    });
    </script>
</body>
</html>