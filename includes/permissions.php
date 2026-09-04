<?php
/**
 * Permission Functions and Constants
 * Gym Management System
 * 
 * File: includes/permissions.php
 * Purpose: Central permission management for all user roles
 */

// ============================================
// PERMISSION CONSTANTS
// ============================================

// Module Access Permissions
define('PERM_DASHBOARD_VIEW', 'dashboard_view');
define('PERM_MEMBERS_VIEW', 'members_view');
define('PERM_MEMBERS_MANAGE', 'members_manage');
define('PERM_MEMBERS_DELETE', 'members_delete');
define('PERM_CHECKIN_PROCESS', 'checkin_process');
define('PERM_WALKIN_PROCESS', 'walkin_process');
define('PERM_PAYMENTS_VIEW', 'payments_view');
define('PERM_PAYMENTS_PROCESS', 'payments_process');
define('PERM_PAYMENTS_VOID', 'payments_void');
define('PERM_TRAINERS_VIEW', 'trainers_view');
define('PERM_TRAINERS_MANAGE', 'trainers_manage');
define('PERM_PT_VIEW', 'pt_view');
define('PERM_PT_MANAGE', 'pt_manage');
define('PERM_PT_COMPLETE', 'pt_complete');
define('PERM_EXPENSES_VIEW', 'expenses_view');
define('PERM_EXPENSES_MANAGE', 'expenses_manage');
define('PERM_REPORTS_VIEW', 'reports_view');
define('PERM_REPORTS_EXPORT', 'reports_export');
define('PERM_ANALYTICS_VIEW', 'analytics_view');
define('PERM_STAFF_VIEW', 'staff_view');
define('PERM_STAFF_MANAGE', 'staff_manage');
define('PERM_SETTINGS_VIEW', 'settings_view');
define('PERM_SETTINGS_MANAGE', 'settings_manage');
define('PERM_AUDIT_VIEW', 'audit_view');
define('PERM_CLOSING_PROCESS', 'closing_process');

// ============================================
// PERMISSION GROUPS BY ROLE
// ============================================

$permission_groups = [
    'owner' => [
        PERM_DASHBOARD_VIEW,
        PERM_MEMBERS_VIEW,
        PERM_MEMBERS_MANAGE,
        PERM_MEMBERS_DELETE,
        PERM_CHECKIN_PROCESS,
        PERM_WALKIN_PROCESS,
        PERM_PAYMENTS_VIEW,
        PERM_PAYMENTS_PROCESS,
        PERM_PAYMENTS_VOID,
        PERM_TRAINERS_VIEW,
        PERM_TRAINERS_MANAGE,
        PERM_PT_VIEW,
        PERM_PT_MANAGE,
        PERM_PT_COMPLETE,
        PERM_EXPENSES_VIEW,
        PERM_EXPENSES_MANAGE,
        PERM_REPORTS_VIEW,
        PERM_REPORTS_EXPORT,
        PERM_ANALYTICS_VIEW,
        PERM_STAFF_VIEW,
        PERM_STAFF_MANAGE,
        PERM_SETTINGS_VIEW,
        PERM_SETTINGS_MANAGE,
        PERM_AUDIT_VIEW,
        PERM_CLOSING_PROCESS,
    ],
    'front_desk' => [
        PERM_DASHBOARD_VIEW,
        PERM_MEMBERS_VIEW,
        PERM_MEMBERS_MANAGE,
        PERM_CHECKIN_PROCESS,
        PERM_WALKIN_PROCESS,
        PERM_PAYMENTS_VIEW,
        PERM_PAYMENTS_PROCESS,
        PERM_TRAINERS_VIEW,
        PERM_PT_VIEW,
        PERM_PT_MANAGE,
        PERM_PT_COMPLETE,
        PERM_REPORTS_VIEW,
        PERM_CLOSING_PROCESS,
    ],
    'trainer' => [
        PERM_DASHBOARD_VIEW,
        PERM_TRAINERS_VIEW,
        PERM_PT_VIEW,
        PERM_PT_COMPLETE,
    ],
];

// ============================================
// PERMISSION CHECK FUNCTIONS
// ============================================

/**
 * Check if user has a specific permission
 * 
 * @param string $permission The permission to check
 * @return bool True if user has permission
 */
function hasPermission($permission) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    global $permission_groups;
    $role = $_SESSION['role'];
    
    if (!isset($permission_groups[$role])) {
        return false;
    }
    
    return in_array($permission, $permission_groups[$role]);
}

/**
 * Require a specific permission
 * 
 * @param string $permission Required permission
 * @param string $redirect URL to redirect if unauthorized
 */
function requirePermission($permission, $redirect = 'dashboard.php') {
    if (!hasPermission($permission)) {
        displayError('You do not have permission to access this feature.');
        redirect($redirect);
    }
}

/**
 * Check if user is owner
 * 
 * @return bool True if user is owner
 */
function isOwner() {
    return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_OWNER;
}

/**
 * Check if user is front desk
 * 
 * @return bool True if user is front desk
 */
function isFrontDesk() {
    return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_FRONT_DESK;
}

/**
 * Check if user is trainer
 * 
 * @return bool True if user is trainer
 */
function isTrainer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_TRAINER;
}

/**
 * Check if user is owner or front desk (staff)
 * 
 * @return bool True if user is staff
 */
function isStaff() {
    return isOwner() || isFrontDesk();
}

/**
 * Get current trainer ID from user ID
 * 
 * @param int|null $userId User ID (null for current user)
 * @return int|null Trainer ID or null
 */
function getCurrentTrainerId($userId = null) {
    if ($userId === null) {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $userId = $_SESSION['user_id'];
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT trainer_id FROM personal_trainers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? $result['trainer_id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Check if trainer is assigned to a member
 * 
 * @param int $memberId Member ID
 * @param int|null $trainerId Trainer ID (null for current trainer)
 * @return bool True if trainer is assigned
 */
function isTrainerAssignedToMember($memberId, $trainerId = null) {
    if ($trainerId === null) {
        $trainerId = getCurrentTrainerId();
    }
    
    if (!$trainerId) {
        return false;
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pt_packages WHERE member_id = ? AND trainer_id = ? AND status = 'active'");
        $stmt->execute([$memberId, $trainerId]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if user can access a member (for trainers)
 * 
 * @param int $memberId Member ID to check
 * @return bool True if user can access this member
 */
function canAccessMember($memberId) {
    if (isOwner() || isFrontDesk()) {
        return true;
    }
    
    if (isTrainer()) {
        return isTrainerAssignedToMember($memberId);
    }
    
    return false;
}

/**
 * Check if user can access a PT session (for trainers)
 * 
 * @param int $sessionId Session ID to check
 * @return bool True if user can access this session
 */
function canAccessPTSession($sessionId) {
    if (isOwner() || isFrontDesk()) {
        return true;
    }
    
    if (isTrainer()) {
        $trainerId = getCurrentTrainerId();
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT trainer_id FROM pt_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
            return $session && $session['trainer_id'] === $trainerId;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    return false;
}

/**
 * Check if user can access a PT package (for trainers)
 * 
 * @param int $packageId Package ID to check
 * @return bool True if user can access this package
 */
function canAccessPTPackage($packageId) {
    if (isOwner() || isFrontDesk()) {
        return true;
    }
    
    if (isTrainer()) {
        $trainerId = getCurrentTrainerId();
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT trainer_id FROM pt_packages WHERE package_id = ?");
            $stmt->execute([$packageId]);
            $package = $stmt->fetch();
            return $package && $package['trainer_id'] === $trainerId;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    return false;
}

/**
 * Check if user can view financial information
 * 
 * @return bool True if user can view financials
 */
function canViewFinancials() {
    return isOwner();
}

/**
 * Check if user can process payments
 * 
 * @return bool True if user can process payments
 */
function canProcessPayments() {
    return isOwner() || isFrontDesk();
}

/**
 * Check if user can manage staff
 * 
 * @return bool True if user can manage staff
 */
function canManageStaff() {
    return isOwner();
}

/**
 * Check if user can manage settings
 * 
 * @return bool True if user can manage settings
 */
function canManageSettings() {
    return isOwner();
}

/**
 * Check if user can view audit logs
 * 
 * @return bool True if user can view audit logs
 */
function canViewAudit() {
    return isOwner();
}

/**
 * Check if user can void payments
 * 
 * @return bool True if user can void payments
 */
function canVoidPayments() {
    return isOwner();
}

/**
 * Get all members that a trainer can access
 * 
 * @param int|null $trainerId Trainer ID (null for current trainer)
 * @return array Array of member IDs
 */
function getTrainerMemberIds($trainerId = null) {
    if ($trainerId === null) {
        $trainerId = getCurrentTrainerId();
    }
    
    if (!$trainerId) {
        return [];
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT member_id FROM pt_packages WHERE trainer_id = ? AND status = 'active'");
        $stmt->execute([$trainerId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}
?>