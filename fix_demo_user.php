<?php
require_once __DIR__ . '/config/config.php';

$pdo = getDBConnection();
$hash = password_hash('Password123!', PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'demo@example.com'");
$stmt->execute([$hash]);

echo "Demo user password updated successfully to hash: " . $hash . "\n";
