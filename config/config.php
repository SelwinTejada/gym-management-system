<?php
/**
 * Application Configuration
 * Gym Management System
 * 
 * File: config/config.php
 * Purpose: Central application configuration
 */

// Application paths
define('BASE_URL', '/gym-management-system/');
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/gym-management-system/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOAD_PATH', BASE_PATH . 'assets/uploads/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');

// Application settings
define('APP_NAME', 'Elite Fitness Gym Management');
define('APP_VERSION', '1.0.0');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LIFETIME', 3600);

// Pagination
define('ITEMS_PER_PAGE', 25);

// File upload limits
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Date formats
define('DISPLAY_DATE_FORMAT', 'F j, Y');
define('DISPLAY_TIME_FORMAT', 'g:i A');
define('DISPLAY_DATETIME_FORMAT', 'F j, Y g:i A');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . 'logs/error_log.txt');

// Load dependencies
require_once BASE_PATH . 'config/database.php';
require_once BASE_PATH . 'includes/functions.php';
require_once BASE_PATH . 'includes/auth.php';
require_once BASE_PATH . 'includes/permissions.php';
require_once BASE_PATH . 'includes/csrf.php';
require_once BASE_PATH . 'config/constants.php';
require_once BASE_PATH . 'includes/audit.php';

// Load system settings from database
function loadSystemSettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($settings as $key => $value) {
            $constName = strtoupper($key);
            if (!defined($constName)) {
                define($constName, $value);
            }
        }
        
        if (!defined('GYM_NAME')) define('GYM_NAME', 'Elite Fitness Gym');
        if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₱');
        if (!defined('DUPLICATE_CHECKIN_THRESHOLD_MINUTES')) define('DUPLICATE_CHECKIN_THRESHOLD_MINUTES', 60);
        
        return true;
    } catch (PDOException $e) {
        error_log('Failed to load system settings: ' . $e->getMessage());
        return false;
    }
}

loadSystemSettings();
?>