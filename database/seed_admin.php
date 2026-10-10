<?php
// Creates/updates the default admin. Run: php database/seed_admin.php
require __DIR__ . '/../api/config/db.php';

$hash = password_hash('admin', PASSWORD_DEFAULT);
$stmt = db()->prepare(
    'INSERT INTO admins (username, password_hash, must_change_password) VALUES (?, ?, 0)
     ON CONFLICT(username) DO UPDATE SET password_hash = excluded.password_hash, must_change_password = 0'
);
$stmt->execute(['admin', $hash]);
echo "✅ Admin account ready (username: admin, password: admin)\n";