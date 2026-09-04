<?php
require 'config/database.php';
$stmt = $pdo->query("SELECT username, password_hash FROM users");
while ($r = $stmt->fetch()) {
    echo $r['username'] . ' => ' . substr($r['password_hash'], 0, 30) . "...\n";
}
?>