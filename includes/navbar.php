<?php
/**
 * Top Navigation Bar
 * Gym Management System
 * 
 * File: includes/navbar.php
 * Purpose: Top navigation with page title, date, and user info
 */

// Get current page title
$page_title = 'Dashboard';
$current_page = basename($_SERVER['PHP_SELF']);

// Map page to title
$page_titles = [
    'dashboard.php' => 'Dashboard',
    'login.php' => 'Login',
    'members/index.php' => 'Members',
    'members/add.php' => 'Add Member',
    'members/edit.php' => 'Edit Member',
    'members/view.php' => 'Member Profile',
    'attendance/checkin.php' => 'Check In',
    'attendance/index.php' => 'Attendance',
    'payments/index.php' => 'Payments',
    'payments/create.php' => 'New Payment',
    'trainers/index.php' => 'Personal Trainers',
    'trainers/add.php' => 'Add Trainer',
    'pt/sessions.php' => 'PT Sessions',
    'pt/packages.php' => 'PT Packages',
    'expenses/index.php' => 'Expenses',
    'expenses/add.php' => 'Add Expense',
    'reports/index.php' => 'Reports',
    'analytics/index.php' => 'Analytics',
    'staff/index.php' => 'Staff Management',
    'settings/index.php' => 'Settings',
    'audit/index.php' => 'Audit Logs',
    'daily-closing/index.php' => 'Daily Closing',
];

if (isset($page_titles[$current_page])) {
    $page_title = $page_titles[$current_page];
} else {
    // Check if in a subdirectory
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    $dir_titles = [
        'members' => 'Members',
        'attendance' => 'Attendance',
        'payments' => 'Payments',
        'trainers' => 'Personal Trainers',
        'pt' => 'PT Sessions',
        'expenses' => 'Expenses',
        'reports' => 'Reports',
        'analytics' => 'Analytics',
        'staff' => 'Staff Management',
        'settings' => 'Settings',
        'audit' => 'Audit Logs',
        'daily-closing' => 'Daily Closing',
    ];
    if (isset($dir_titles[$current_dir])) {
        $page_title = $dir_titles[$current_dir];
    }
}

// Get current date
$current_date = date('F j, Y');
$current_time = date('g:i A');
?>

<nav class="navbar">
    <div class="navbar-left">
        <button class="navbar-toggle" id="navbarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="navbar-title"><?php echo $page_title; ?></h1>
    </div>
    
    <div class="navbar-right">
        <div class="navbar-date">
            <i class="bi bi-calendar3"></i>
            <span><?php echo $current_date; ?></span>
            <span class="navbar-time"><?php echo $current_time; ?></span>
        </div>
        
        <!-- Notifications -->
        <div class="navbar-notifications dropdown">
            <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell-fill"></i>
                <span class="notification-badge" id="notificationBadge">0</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" id="notificationDropdown">
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <li><hr class="dropdown-divider"></li>
                <li class="text-center text-muted" id="noNotifications">No notifications</li>
                <div id="notificationList"></div>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-center" href="#">View All</a></li>
            </ul>
        </div>
        
        <!-- User Profile -->
        <div class="navbar-user dropdown">
            <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar-small">
                    <i class="bi bi-person-circle"></i>
                </div>
                <span class="user-name-small"><?php echo $_SESSION['full_name'] ?? 'User'; ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">
                    <i class="bi bi-person"></i> Profile
                </a></li>
                <li><a class="dropdown-item" href="#">
                    <i class="bi bi-gear"></i> Settings
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a></li>
            </ul>
        </div>
    </div>
</nav>