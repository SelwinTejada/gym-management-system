<?php
/**
 * Logout Handler
 * Gym Management System
 * 
 * File: logout.php
 * Purpose: Log out user and destroy session
 */

// Load configuration
require_once 'config/config.php';

// Logout user
logoutUser(true);
?>