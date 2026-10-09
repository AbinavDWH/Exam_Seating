<?php
// Creates/updates the default admin. Run: php database/seed_admin.php
require __DIR__ . '/../api/config/db.php';

$hash = password_hash('Admin@123', PASSWORD_DEFAULT);
$stmt = db()->prepare(
    'INSERT INTO admins (username, password_hash, must_change_password) VALUES (?, ?, 1)
     ON CONFLICT(username) DO NOTHING'
);
$stmt->execute(['admin', $hash]);
echo "✅ Admin account ready\n";