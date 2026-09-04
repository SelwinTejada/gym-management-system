<?php
/**
 * CSRF Protection
 * Gym Management System
 * 
 * File: includes/csrf.php
 * Purpose: CSRF token generation and validation
 */

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Get CSRF token
 */
function getCSRFToken() {
    return generateCSRFToken();
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || $token !== $_SESSION[CSRF_TOKEN_NAME]) {
        return false;
    }
    
    // Check token expiration
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME)) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return true;
}

/**
 * CSRF token field
 */
function csrfTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify CSRF token from POST
 */
function verifyCSRFToken() {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        displayError('Security validation failed. Please try again.');
        return false;
    }
    return true;
}
?>