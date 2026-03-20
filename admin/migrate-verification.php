<?php
/**
 * One-time migration: adds email verification columns to the users table.
 * Run this script once from the browser or CLI, then delete or restrict it.
 *
 * URL: http://localhost/FSUU-booking-system-1/admin/migrate-verification.php
 */
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db  = getDB();
$log = [];

$columns = [
    "ALTER TABLE `users` ADD COLUMN `is_verified`       TINYINT(1)   NOT NULL DEFAULT 0     AFTER `role`",
    "ALTER TABLE `users` ADD COLUMN `verification_code` VARCHAR(255) NULL     DEFAULT NULL  AFTER `is_verified`",
    "ALTER TABLE `users` ADD COLUMN `code_expiry`       DATETIME     NULL     DEFAULT NULL  AFTER `verification_code`",
];

foreach ($columns as $sql) {
    if ($db->query($sql)) {
        $log[] = ['ok', $sql];
    } else {
        // 1060 = Duplicate column — column already exists, not a real error
        if ($db->errno === 1060) {
            $log[] = ['skip', $sql . ' (column already exists)'];
        } else {
            $log[] = ['err', $sql . ' → ' . $db->error];
        }
    }
}

// Mark all existing users (registered before this feature) as already verified
$db->query("UPDATE `users` SET `is_verified` = 1 WHERE `is_verified` = 0");
$affected = $db->affected_rows;
$log[] = ['ok', "Marked $affected pre-existing user(s) as verified."];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DB Migration — Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container" style="max-width:700px;">
    <h3 class="mb-4">Email Verification — DB Migration</h3>
    <div class="list-group mb-4">
        <?php foreach ($log as [$status, $msg]): ?>
            <div class="list-group-item list-group-item-<?php
                echo $status === 'ok' ? 'success' : ($status === 'skip' ? 'warning' : 'danger'); ?>">
                <strong><?php echo strtoupper($status); ?>:</strong>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="text-muted small">Migration complete. You may delete this file for security.</p>
    <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
</div>
</body>
</html>
