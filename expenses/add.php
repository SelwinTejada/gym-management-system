<?php
/**
 * Add Expense
 * Gym Management System
 * 
 * File: expenses/add.php
 * Purpose: Record a new expense
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to add expenses.');
    redirect('../dashboard.php');
}

// Get categories
try {
    $stmt = $pdo->query("SELECT category_id, category_name FROM expense_categories WHERE is_active = 1 ORDER BY display_order");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
    $categories = [];
}

// Process form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed.';
    } else {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $expense_date = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
        $payment_method = sanitize($_POST['payment_method'] ?? PAYMENT_CASH);
        $reference_number = sanitize($_POST['reference_number'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if ($category_id <= 0 || empty($description) || $amount <= 0) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO expenses (
                        category_id, description, amount, expense_date, payment_method,
                        reference_number, notes, recorded_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $category_id,
                    $description,
                    $amount,
                    $expense_date,
                    $payment_method,
                    $reference_number ?: null,
                    $notes,
                    getCurrentUserId()
                ]);
                
                $expense_id = $pdo->lastInsertId();
                
                logAudit('EXPENSE_ADD', 'expenses', $expense_id, 'Added expense: ' . $description);
                
                displaySuccess('Expense recorded successfully!');
                header('Location: index.php');
                exit;
                
            } catch (PDOException $e) {
                error_log('Add expense error: ' . $e->getMessage());
                $error = 'An error occurred while recording the expense.';
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
                    <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Add Expense</h4>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Expenses
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
                                    <label class="form-label required-field">Category *</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>">
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label required-field">Amount (₱) *</label>
                                    <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label required-field">Description *</label>
                                    <input type="text" class="form-control" name="description" required placeholder="Brief description of the expense">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label required-field">Date *</label>
                                    <input type="date" class="form-control" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label required-field">Payment Method *</label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="<?php echo PAYMENT_CASH; ?>">Cash</option>
                                        <option value="<?php echo PAYMENT_GCASH; ?>">GCash</option>
                                        <option value="<?php echo PAYMENT_BANK_TRANSFER; ?>">Bank Transfer</option>
                                        <option value="<?php echo PAYMENT_CARD; ?>">Card</option>
                                        <option value="<?php echo PAYMENT_OTHER; ?>">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" name="reference_number" placeholder="Receipt or reference number">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Record Expense
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