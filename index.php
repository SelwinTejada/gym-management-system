<?php
/**
 * Entry Point
 * Gym Management System
 * 
 * File: index.php
 * Purpose: Redirect to login or dashboard
 */

// Load configuration
require_once 'config/config.php';

// Check if user is logged in
if (isAuthenticated()) {
    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;
} else {
    // Redirect to login
    header('Location: login.php');
    exit;
}
?>