<?php
/**
 * Expense Edit
 * Gym Management System
 * 
 * File: expenses/edit.php
 * Purpose: Edit expense category or expense record
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to edit expenses.');
    redirect('../dashboard.php');
}

// Get parameters
$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$is_editing_expense = $expense_id > 0;
$is_editing_category = $category_id > 0;

$title = $is_editing_expense ? 'Edit Expense' : 'Edit Category';

// Initialize variables
$error = '';
$success = '';
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } else {
        if ($is_editing_expense) {
            // Edit expense
            $description = sanitize($_POST['description'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $cat_id = (int)($_POST['category_id'] ?? 0);
            $payment_method = sanitize($_POST['payment_method'] ?? 'cash');
            $reference_number = sanitize($_POST['reference_number'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (empty($description)) {
                $error = 'Description is required';
            } elseif ($amount <= 0) {
                $error = 'Amount must be greater than zero';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE expenses SET description = ?, amount = ?, category_id = ?, 
                            payment_method = ?, reference_number = ?, notes = ?, updated_at = NOW()
                        WHERE expense_id = ?
                    ");
                    $stmt->execute([$description, $amount, $cat_id, $payment_method, $reference_number, $notes, $expense_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = 'Expense updated successfully.';
                    } else {
                        $error = 'No changes made or expense not found';
                    }
                } catch (PDOException $e) {
                    error_log('Update expense error: ' . $e->getMessage());
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($is_editing_category) {
            // Edit category
            $category_name = sanitize($_POST['category_name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($category_name)) {
                $error = 'Category name is required';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE expense_categories SET category_name = ?, description = ?, is_active = ?, updated_at = NOW()
                        WHERE category_id = ?
                    ");
                    $stmt->execute([$category_name, $description, $is_active, $category_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = 'Category updated successfully.';
                    } else {
                        $error = 'No changes made or category not found';
                    }
                } catch (PDOException $e) {
                    error_log('Update category error: ' . $e->getMessage());
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Load data based on what we're editing
if ($is_editing_expense) {
    try {
        $stmt = $pdo->prepare("SELECT e.*, ec.category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.category_id WHERE e.expense_id = ?");
        $stmt->execute([$expense_id]);
        $expense = $stmt->fetch();
        
        if (!$expense) {
            displayError('Expense not found.');
            redirect('index.php');
        }
        
        $form_data = [
            'description' => $expense['description'],
            'amount' => $expense['amount'],
            'category_id' => $expense['category_id'],
            'payment_method' => $expense['payment_method'],
            'reference_number' => $expense['reference_number'] ?? '',
            'notes' => $expense['notes'] ?? '',
        ];
    } catch (PDOException $e) {
        error_log('Get expense error: ' . $e->getMessage());
        displayError('Error loading expense data.');
        redirect('index.php');
    }
} elseif ($is_editing_category) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            displayError('Category not found.');
            redirect('categories.php');
        }
        
        $form_data = [
            'category_name' => $category['category_name'],
            'description' => $category['description'] ?? '',
            'is_active' => $category['is_active'],
        ];
    } catch (PDOException $e) {
        error_log('Get category error: ' . $e->getMessage());
        displayError('Error loading category data.');
        redirect('categories.php');
    }
}

// Include header
include '../includes/header.php';
?>

<div class="app-container">
    <div class="main-content">
        <div class="content-wrapper">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0"><?php echo $title; ?></h4>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="card modern-card p-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateRandomString(32); ?>">
                <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">
                <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                
                <div class="row mb-3">
                    <?php if ($is_editing_expense): ?>
                    <div class="col-md-6">
                        <label class="form-label">Description *</label>
                        <input type="text" class="form-control" name="description" 
                               value="<?php echo htmlspecialchars($form_data['description'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount"
                               value="<?php echo htmlspecialchars($form_data['amount'] ?? 0); ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category_id">
                            <option value="0">Select Category</option>
                            <?php 
                            try {
                                $stmt = $pdo->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY category_name");
                                $cats = $stmt->fetchAll();
                                foreach ($cats as $cat): 
                            ?>
                            <option value="<?php echo $cat['category_id']; ?>" 
                                <?php echo ($form_data['category_id'] ?? 0) == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                            <?php } catch (PDOException $e) {
                                error_log('Get categories error: ' . $e->getMessage());
                            } ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method">
                            <option value="cash" <?php echo ($form_data['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="gcash" <?php echo ($form_data['payment_method'] ?? '') === 'gcash' ? 'selected' : ''; ?>>GCash</option>
                            <option value="bank_transfer" <?php echo ($form_data['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="card" <?php echo ($form_data['payment_method'] ?? '') === 'card' ? 'selected' : ''; ?>>Card</option>
                            <option value="other" <?php echo ($form_data['payment_method'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Reference #</label>
                        <input type="text" class="form-control" name="reference_number"
                               value="<?php echo htmlspecialchars($form_data['reference_number'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($form_data['notes'] ?? ''); ?></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_editing_category): ?>
                    <div class="col-12">
                        <label class="form-label">Category Name *</label>
                        <input type="text" class="form-control" name="category_name"
                               value="<?php echo htmlspecialchars($form_data['category_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="col-12">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                   value="1" <?php echo (isset($form_data['is_active']) && $form_data['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i> Save Changes
                    </button>
                    <a href="<?php echo $is_editing_expense ? 'index.php' : 'categories.php'; ?>" 
                       class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>