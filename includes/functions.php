<?php
/**
 * Helper Functions
 * Gym Management System
 * 
 * File: includes/functions.php
 * Purpose: Common utility functions
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate member number
 */
function generateMemberNumber($prefix = 'GM') {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM members");
        $count = $stmt->fetch()['count'] + 1;
        return $prefix . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return $prefix . '-' . date('Ymd') . '-' . rand(1000, 9999);
    }
}

/**
 * Generate transaction number
 */
function generateTransactionNumber($prefix = 'TRX') {
    return $prefix . '-' . date('Ymd') . '-' . rand(10000, 99999);
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = null) {
    if ($currency === null) {
        $currency = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₱';
    }
    return $currency . number_format((float)$amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = null) {
    if (empty($date)) return '';
    if ($format === null) $format = defined('DISPLAY_DATE_FORMAT') ? DISPLAY_DATE_FORMAT : 'F j, Y';
    
    try {
        $datetime = new DateTime($date);
        return $datetime->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format time
 */
function formatTime($time) {
    if (empty($time)) return '';
    $format = defined('DISPLAY_TIME_FORMAT') ? DISPLAY_TIME_FORMAT : 'g:i A';
    
    try {
        if (strlen($time) <= 5) {
            return date($format, strtotime($time));
        }
        $datetime = new DateTime($time);
        return $datetime->format($format);
    } catch (Exception $e) {
        return $time;
    }
}

/**
 * Get current date and time
 */
function getCurrentDateTime() {
    $now = new DateTime();
    return [
        'date' => $now->format('Y-m-d'),
        'time' => $now->format('H:i:s'),
        'datetime' => $now->format('Y-m-d H:i:s'),
        'display_date' => $now->format('F j, Y'),
        'display_time' => $now->format('g:i A'),
    ];
}

/**
 * Get membership status
 */
function getMembershipStatus($expirationDate, $gracePeriod = 7) {
    if (empty($expirationDate)) return MEMBERSHIP_EXPIRED;
    
    $now = new DateTime();
    $expiration = new DateTime($expirationDate);
    $expiration->modify('+' . $gracePeriod . ' days');
    
    if ($now > $expiration) {
        return MEMBERSHIP_EXPIRED;
    } elseif ($now > new DateTime($expirationDate)) {
        return 'expiring_soon';
    } else {
        return MEMBERSHIP_ACTIVE;
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Redirect
 */
function redirect($url, $statusCode = 302) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }
    echo '<script>window.location.href = "' . $url . '";</script>';
    exit;
}

/**
 * Flash messages
 */
function displaySuccess($message) { $_SESSION['success_message'] = $message; }
function displayError($message) { $_SESSION['error_message'] = $message; }
function displayWarning($message) { $_SESSION['warning_message'] = $message; }
function displayInfo($message) { $_SESSION['info_message'] = $message; }

/**
 * Get flash messages
 */
function getFlashMessages() {
    $messages = [];
    $types = ['success', 'error', 'warning', 'info'];
    foreach ($types as $type) {
        $key = $type . '_message';
        if (isset($_SESSION[$key])) {
            $messages[$type] = $_SESSION[$key];
            unset($_SESSION[$key]);
        }
    }
    return $messages;
}

/**
 * Calculate age
 */
function calculateAge($dob) {
    if (empty($dob)) return null;
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get days difference
 */
function daysDifference($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    return $datetime1->diff($datetime2)->days;
}

/**
 * Check if date is in the past
 */
function isDatePast($date) {
    $now = new DateTime();
    $checkDate = new DateTime($date);
    return $checkDate < $now;
}
?>