<?php
/**
 * System Constants
 * Gym Management System
 * 
 * File: config/constants.php
 * Purpose: Global constants used throughout the application
 */

// User Roles
define('ROLE_OWNER', 'owner');
define('ROLE_FRONT_DESK', 'front_desk');
define('ROLE_TRAINER', 'trainer');

// Membership Statuses
define('MEMBERSHIP_ACTIVE', 'active');
define('MEMBERSHIP_EXPIRED', 'expired');
define('MEMBERSHIP_SUSPENDED', 'suspended');
define('MEMBERSHIP_CANCELLED', 'cancelled');

// Customer Types
define('CUSTOMER_STUDENT', 'student');
define('CUSTOMER_REGULAR', 'regular');

// Payment Methods
define('PAYMENT_CASH', 'cash');
define('PAYMENT_GCASH', 'gcash');
define('PAYMENT_BANK_TRANSFER', 'bank_transfer');
define('PAYMENT_CARD', 'card');
define('PAYMENT_OTHER', 'other');

// Transaction Types
define('TRANSACTION_MEMBERSHIP', 'membership');
define('TRANSACTION_RENEWAL', 'renewal');
define('TRANSACTION_WALKIN', 'walkin');
define('TRANSACTION_PT_PACKAGE', 'pt_package');
define('TRANSACTION_PT_SESSION', 'pt_session');
define('TRANSACTION_OTHER', 'other');

// Payment Statuses
define('PAYMENT_COMPLETED', 'completed');
define('PAYMENT_VOID', 'void');
define('PAYMENT_REFUNDED', 'refunded');

// PT Session Statuses
define('PT_SCHEDULED', 'scheduled');
define('PT_COMPLETED', 'completed');
define('PT_CANCELLED', 'cancelled');
define('PT_NO_SHOW', 'no_show');

// PT Package Statuses
define('PT_ACTIVE', 'active');
define('PT_COMPLETED', 'completed');
define('PT_EXPIRED', 'expired');
define('PT_CANCELLED', 'cancelled');

// Notification Types
define('NOTIFICATION_INFO', 'info');
define('NOTIFICATION_WARNING', 'warning');
define('NOTIFICATION_ERROR', 'error');
define('NOTIFICATION_SUCCESS', 'success');

// Default values
define('DEFAULT_MEMBERSHIP_GRACE_PERIOD', 7);
define('DEFAULT_DUPLICATE_CHECKIN_THRESHOLD', 60);
define('DEFAULT_ITEMS_PER_PAGE', 25);
?>