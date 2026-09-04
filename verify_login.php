<?php
require 'config/database.php';

$testUsers = [
    ['username' => 'admin', 'password' => 'Admin123!'],
    ['username' => 'frontdesk', 'password' => 'Front123!'], 
    ['username' => 'trainer1', 'password' => 'Trainer123!']
];

foreach ($testUsers as $userCreds) {
    $username = $userCreds['username'];
    $password = $userCreds['password'];
    
    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, full_name, role, status FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        $valid = password_verify($password, $user['password_hash']);
        echo "$username: " . ($valid ? "VALID" : "INVALID") . " - " . $user['full_name'] . "<br>";
    } else {
        echo "$username: User not found<br>";
    }
}
?>