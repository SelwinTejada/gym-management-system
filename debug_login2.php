<?php
// Direct database test
$pdo = new PDO("mysql:host=localhost;dbname=gym_management","root","");

$users = ['admin' => 'Admin123!', 'frontdesk' => 'Front123!', 'trainer1' => 'Trainer123!'];

foreach ($users as $user => $pass) {
    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, full_name, role, status FROM users WHERE username = ? AND status = ?");
    $stmt->execute([$user, 'active']);
    $userData = $stmt->fetch();
    
    if ($userData && password_verify($pass, $userData['password_hash'])) {
        echo "$user: VALID - " . $userData['full_name'] . " (role: " . $userData['role'] . ")<br>";
    } else {
        echo "$user: INVALID<br>";
        if ($userData) {
            $test = password_verify('password', $userData['password_hash']);
            echo "  Note: 'password' hash matches: " . ($test ? 'yes' : 'no') . "<br>";
        }
    }
}
?>