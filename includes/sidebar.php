<?php
/**
 * Sidebar Navigation
 * Gym Management System
 * 
 * File: includes/sidebar.php
 * Purpose: Main navigation sidebar with permission-based visibility
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="bi bi-dumbbell"></i>
            <span>Elite Fitness</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <div class="sidebar-menu">
        <!-- Dashboard -->
        <a href="<?php echo BASE_URL; ?>dashboard.php" 
           class="sidebar-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        
        <!-- Members - Visible to Owner and Front Desk -->
        <?php if (isStaff()): ?>
        <a href="<?php echo BASE_URL; ?>members/index.php" 
           class="sidebar-item <?php echo $current_dir == 'members' ? 'active' : ''; ?>">
            <i class="bi bi-people-fill"></i>
            <span>Members</span>
        </a>
        <?php endif; ?>
        
        <!-- Check In - Visible to Owner and Front Desk -->
        <?php if (isStaff()): ?>
        <a href="<?php echo BASE_URL; ?>attendance/checkin.php" 
           class="sidebar-item <?php echo $current_page == 'checkin.php' ? 'active' : ''; ?>">
            <i class="bi bi-check2-circle"></i>
            <span>Check In</span>
        </a>
        <?php endif; ?>
        
        <!-- Payments - Visible to Owner and Front Desk -->
        <?php if (canProcessPayments()): ?>
        <a href="<?php echo BASE_URL; ?>payments/index.php" 
           class="sidebar-item <?php echo $current_dir == 'payments' ? 'active' : ''; ?>">
            <i class="bi bi-credit-card-fill"></i>
            <span>Payments</span>
        </a>
        <?php endif; ?>
        
        <!-- Personal Trainers - Visible to all logged-in users -->
        <a href="<?php echo BASE_URL; ?>trainers/index.php" 
           class="sidebar-item <?php echo $current_dir == 'trainers' ? 'active' : ''; ?>">
            <i class="bi bi-person-badge-fill"></i>
            <span>Personal Trainers</span>
        </a>
        
        <!-- PT Sessions - Visible to all logged-in users -->
        <a href="<?php echo BASE_URL; ?>pt/sessions.php" 
           class="sidebar-item <?php echo $current_dir == 'pt' ? 'active' : ''; ?>">
            <i class="bi bi-clock-history"></i>
            <span>PT Sessions</span>
        </a>
        
        <!-- Expenses - Owner Only -->
        <?php if (isOwner()): ?>
        <a href="<?php echo BASE_URL; ?>expenses/index.php" 
           class="sidebar-item <?php echo $current_dir == 'expenses' ? 'active' : ''; ?>">
            <i class="bi bi-wallet2"></i>
            <span>Expenses</span>
        </a>
        <?php endif; ?>
        
        <!-- Reports - Visible to Owner and Front Desk -->
        <?php if (isStaff()): ?>
        <a href="<?php echo BASE_URL; ?>reports/index.php" 
           class="sidebar-item <?php echo $current_dir == 'reports' ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-text-fill"></i>
            <span>Reports</span>
        </a>
        <?php endif; ?>
        
        <!-- Analytics - Owner Only -->
        <?php if (isOwner()): ?>
        <a href="<?php echo BASE_URL; ?>analytics/index.php" 
           class="sidebar-item <?php echo $current_dir == 'analytics' ? 'active' : ''; ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Analytics</span>
        </a>
        <?php endif; ?>
        
        <!-- Staff Management - Owner Only -->
        <?php if (canManageStaff()): ?>
        <a href="<?php echo BASE_URL; ?>staff/index.php" 
           class="sidebar-item <?php echo $current_dir == 'staff' ? 'active' : ''; ?>">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Staff</span>
        </a>
        <?php endif; ?>
        
        <!-- Settings - Owner Only -->
        <?php if (canManageSettings()): ?>
        <a href="<?php echo BASE_URL; ?>settings/index.php" 
           class="sidebar-item <?php echo $current_dir == 'settings' ? 'active' : ''; ?>">
            <i class="bi bi-gear-fill"></i>
            <span>Settings</span>
        </a>
        <?php endif; ?>
        
        <!-- Audit Logs - Owner Only -->
        <?php if (canViewAudit()): ?>
        <a href="<?php echo BASE_URL; ?>audit/index.php" 
           class="sidebar-item <?php echo $current_dir == 'audit' ? 'active' : ''; ?>">
            <i class="bi bi-clipboard-data-fill"></i>
            <span>Audit Logs</span>
        </a>
        <?php endif; ?>
    </div>
    
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="bi bi-person-circle"></i>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name'] ?? 'User'; ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['role'] ?? 'Guest'); ?></div>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>