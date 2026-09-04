<?php
/**
 * Daily Closing Index
 * Gym Management System
 * 
 * File: daily-closing/index.php
 * Purpose: Daily closing procedure start
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions - only owner can close daily
if (!isOwner()) {
    displayError('You do not have permission to perform daily closing.');
    redirect('../dashboard.php');
}

// Set page title
$page_title = 'Daily Closing';
$today = date('Y-m-d');
?>

<div class="card modern-card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history"></i> Daily Closing - <?php echo date('F j, Y'); ?></h5>
    </div>
    <div class="card-body">
        <p class="text-muted">Performing daily closing procedures for <?php echo date('F j, Y'); ?></p>
        
        <div class="row mt-4">
            <!-- Revenue Summary -->
            <div class="col-md-6">
                <div class="card modern-card">
                    <div class="card-header">
                        <h6>Today's Revenue</h6>
                    </div>
                    <div class="card-body">
                        <h3 class="text-success"><?php echo formatCurrency($stats['today_revenue'] ?? 0); ?></h3>
                        <small>Total completed payments</small>
                    </div>
                </div>
            </div>
            
            <!-- Expenses Summary -->
            <div class="col-md-6">
                <div class="card modern-card">
                    <div class="card-header">
                        <h6>Today's Expenses</h6>
                    </div>
                    <div class="card-body">
                        <h3 class="text-danger"><?php echo formatCurrency($stats['today_expenses'] ?? 0); ?></h3>
                        <small>Total expenses recorded</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Check-in Summary -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card modern-card">
                    <div class="card-header">
                        <h6>Today's Check-ins</h6>
                    </div>
                    <div class="card-body">
                        <h3><?php echo number_format($stats['today_checkins'] ?? 0); ?></h3>
                        <small>Total members checked in</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="processDailyClose()">
                        <i class="bi bi-check-all"></i> Process Daily Close
                    </button>
                    <a href="close.php" class="btn btn-secondary">
                        <i class="bi bi-download"></i> Generate Close Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function processDailyClose() {
    if (confirm('Are you sure you want to process the daily close? This will finalize today\'s records.')) {
        $.ajax({
            url: 'close.php',
            type: 'POST',
            data: { process_close: true },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('success', response.message);
                    setTimeout(() => window.location.href = 'index.php', 2000);
                } else {
                    showToast('error', response.message);
                }
            },
            error: function() {
                showToast('error', 'An error occurred during daily close.');
            }
        });
    }
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>