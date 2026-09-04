<?php
/**
 * Reports Dashboard
 * Gym Management System
 * 
 * File: reports/index.php
 * Purpose: Central reports hub
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view reports.');
    redirect('../dashboard.php');
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
                <h4 class="mb-4"><i class="bi bi-file-earmark-text"></i> Reports</h4>
                
                <div class="row">
                    <!-- Attendance Report -->
                    <div class="col-md-4 mb-4">
                        <div class="card modern-card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <h5 class="mt-3">Attendance Report</h5>
                                <p class="text-muted small">View member attendance history with filters for date range and member type.</p>
                                <a href="attendance.php" class="btn btn-primary">
                                    <i class="bi bi-arrow-right"></i> View Report
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Revenue Report -->
                    <?php if (isOwner()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card modern-card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <h5 class="mt-3">Revenue Report</h5>
                                <p class="text-muted small">View revenue breakdown by type, payment method, and date range.</p>
                                <a href="revenue.php" class="btn btn-success">
                                    <i class="bi bi-arrow-right"></i> View Report
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Expense Report -->
                    <div class="col-md-4 mb-4">
                        <div class="card modern-card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <h5 class="mt-3">Expense Report</h5>
                                <p class="text-muted small">View expenses by category, payment method, and date range.</p>
                                <a href="expenses.php" class="btn btn-danger">
                                    <i class="bi bi-arrow-right"></i> View Report
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profit/Loss Report -->
                    <div class="col-md-4 mb-4">
                        <div class="card modern-card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon" style="background: rgba(215, 38, 56, 0.1); color: #D72638;">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <h5 class="mt-3">Profit/Loss Report</h5>
                                <p class="text-muted small">View revenue vs expenses with profit margin calculations.</p>
                                <a href="profit-loss.php" class="btn" style="background: #D72638; color: white;">
                                    <i class="bi bi-arrow-right"></i> View Report
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Membership Report -->
                    <div class="col-md-4 mb-4">
                        <div class="card modern-card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                                    <i class="bi bi-card-list"></i>
                                </div>
                                <h5 class="mt-3">Membership Report</h5>
                                <p class="text-muted small">View membership statistics including active, expired, and renewals.</p>
                                <a href="memberships.php" class="btn btn-info">
                                    <i class="bi bi-arrow-right"></i> View Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Trainer Report -->
                    <div class="col-md-4 mb-4">
                        <div class="card modern-card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon" style="background: rgba(108, 117, 125, 0.1); color: #6c757d;">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <h5 class="mt-3">Trainer Report</h5>
                                <p class="text-muted small">View trainer performance, session counts, and revenue generated.</p>
                                <a href="trainers.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-right"></i> View Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <style>
    .report-card {
        transition: var(--transition);
        cursor: pointer;
    }
    
    .report-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }
    
    .report-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: rgba(215, 38, 56, 0.1);
        color: #D72638;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin: 0 auto;
    }
    </style>
</body>
</html>