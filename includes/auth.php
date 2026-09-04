<?php
/**
 * Authentication Handler
 * Gym Management System
 * 
 * File: includes/auth.php
 * Purpose: User authentication and session management
 */

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        return false;
    }
    return verifyUserSession($_SESSION['user_id'], session_id());
}

/**
 * Verify user session
 */
function verifyUserSession($userId, $sessionId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT user_id, status FROM users WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Session verification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Authenticate user
 */
function authenticateUser($username, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT user_id, username, password_hash, full_name, role, status FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        if (password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            logAudit('LOGIN', 'auth', $user['user_id'], 'User logged in', $_SERVER['REMOTE_ADDR']);
            
            return [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
            ];
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Authentication error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Logout user
 */
function logoutUser($redirect = true) {
    if (isset($_SESSION['user_id'])) {
        logAudit('LOGOUT', 'auth', $_SESSION['user_id'], 'User logged out', $_SERVER['REMOTE_ADDR']);
    }
    
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    
    session_destroy();
    
    if ($redirect) {
        header('Location: /gym-management-system/login.php');
        exit;
    }
}

/**
 * Require authentication
 */
function requireAuth($redirect = 'login.php') {
    if (!isAuthenticated()) {
        if (!headers_sent()) {
            header('Location: ' . $redirect);
            exit;
        } else {
            echo '<script>window.location.href = "' . $redirect . '";</script>';
            exit;
        }
    }
}

/**
 * Require specific role
 */
function requireRole($roles, $redirect = 'dashboard.php') {
    requireAuth();
    
    $userRole = $_SESSION['role'];
    
    if (is_array($roles)) {
        if (!in_array($userRole, $roles)) {
            displayError('You do not have permission to access this page.');
            redirect($redirect);
        }
    } else {
        if ($userRole !== $roles) {
            displayError('You do not have permission to access this page.');
            redirect($redirect);
        }
    }
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (!isAuthenticated()) return false;
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT user_id, username, full_name, email, role, contact_number, profile_image, last_login FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get current user error: ' . $e->getMessage());
        return false;
    }
}
?>