<?php
require 'config/config.php';
session_start();

// Test all three credentials manually
$users = ['admin' => 'Admin123!', 'frontdesk' => 'Front123!', 'trainer1' => 'Trainer123!'];

foreach ($users as $user => $pass) {
    // Simulate what login.php does
    $username = sanitize($user);
    $password = $pass;
    
    if (!verifyCSRFToken()) {
        echo "$user: CSRF failed<br>";
    } elseif (empty($username) || empty($password)) {
        echo "$user: empty fields<br>";
    } else {
        // Manual authenticate
        $stmt = $pdo->prepare('SELECT user_id, username, password_hash, full_name, role, status FROM users WHERE username = ? AND status = ?');
        $stmt->execute([$username, 'active']);
        $userData = $stmt->fetch();
        
        if ($userData && password_verify($password, $userData['password_hash'])) {
            echo "$user: VALID - " . $userData['full_name'] . " (role: " . $userData['role'] . ")<br>";
        } else {
            echo "$user: INVALID<br>";
        }
    }
}
?>