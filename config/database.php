<?php
/**
 * Database Configuration
 * Gym Management System
 * 
 * File: config/database.php
 * Purpose: Database connection using PDO
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'gym_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// PDO options
$db_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
];

/**
 * Get database connection
 * 
 * @return PDO Database connection
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $GLOBALS['db_options']);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database Connection Error: ' . $e->getMessage());
        die('Unable to connect to the database. Please contact your system administrator.');
    }
}

// Create global connection
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die('Database connection failed. Please check your configuration.');
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0);
    session_start();
}
?>