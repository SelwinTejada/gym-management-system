<?php
require 'config/database.php';

// Generate new password hashes for default credentials
$passwords = [
    'admin' => 'Admin123!',
    'frontdesk' => 'Front123!', 
    'trainer1' => 'Trainer123!'
];

foreach ($passwords as $username => $password) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $result = $stmt->execute([$hash, $username]);
    
    // Verify the update
    $check = $pdo->prepare("SELECT password_hash FROM users WHERE username = ?");
    $check->execute([$username]);
    $row = $check->fetch();
    
    $matches = password_verify($password, $row['password_hash']);
    
    echo "$username: ";
    echo $matches ? "UPDATED and VERIFIED" : "UPDATED but VERIFY FAILED";
    echo "<br>";
    echo "  Hash starts with: " . substr($hash, 0, 20) . "<br><br>";
}
?>