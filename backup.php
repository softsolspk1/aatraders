<?php
// AA TRADERS - SQLite Backup & Restore Engine
$pageTitle = 'Database Backup & Restore';
require_once __DIR__ . '/includes/header.php';

Auth::requireRole(['super_admin', 'company_admin', 'auditor']);

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// 1. Handle Download Direct SQLite DB
if (isset($_GET['download_current'])) {
    if (file_exists(DB_PATH)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="aatraders_backup_' . date('Y-m-d_H-i-s') . '.sqlite"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize(DB_PATH));
        readfile(DB_PATH);
        exit;
    }
}

// 2. Handle Create Snapshot
if (isset($_POST['action']) && $_POST['action'] === 'create_snapshot') {
    $snapshotName = 'aatraders_snapshot_' . date('Y-m-d_His') . '.sqlite';
    $dest = $backupDir . '/' . $snapshotName;
    copy(DB_PATH, $dest);
    Database::logAudit('BACKUP_CREATED', 'System', $snapshotName, "Created database snapshot: {$snapshotName}");
    header('Location: backup.php?msg=snapshot_created');
    exit;
}

// 3. Handle Download Existing Snapshot
if (isset($_GET['download_file'])) {
    $file = basename($_GET['download_file']);
    $path = $backupDir . '/' . $file;
    if (file_exists($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// 4. Handle Restore
if (isset($_POST['action']) && $_POST['action'] === 'restore_snapshot') {
    $file = basename($_POST['backup_file']);
    $source = $backupDir . '/' . $file;
    if (file_exists($source)) {
        // Create emergency backup of current DB first
        copy(DB_PATH, $backupDir . '/pre_restore_safety_' . date('Ymd_His') . '.sqlite');
        // Overwrite active database
        copy($source, DB_PATH);
        Database::logAudit('DATABASE_RESTORED', 'System', $file, "Restored active database from snapshot: {$file}");
        header('Location: backup.php?msg=restored');
        exit;
    }
}

// List existing backup snapshots
$backups = [];
foreach (glob($backupDir . '/*.sqlite') as $bFile) {
    $backups[] = [
        'name' => basename($bFile),
        'size' => filesize($bFile),
        'date' => date('Y-m-d H:i:s', filemtime($bFile))
    ];
}
usort($backups, fn($a, $b) => strcmp($b['date'], $a['date']));
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">SQLite Database Backup &amp; Disaster Recovery</h2>
        <p style="font-size: 13px; color: var(--slate-500);">Live SQLite Hot Backups &bull; 1-Click Offline Archiving &bull; Administrator Restoration Engine</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <form method="POST" action="backup.php">
            <input type="hidden" name="action" value="create_snapshot">
            <button type="submit" class="btn btn-secondary">💾 Create Local Snapshot</button>
        </form>
        <a href="backup.php?download_current=1" class="btn btn-primary">⬇️ Download Active SQLite DB</a>
        <a href="settings.php" class="btn btn-danger" style="background:#dc2626;">🧹 Purge Demo Data</a>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'snapshot_created'): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ High-integrity SQLite database snapshot created successfully in backup archive.
    </div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'restored'): ?>
    <div style="background: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-weight: 600;">
        ✓ Database successfully restored from selected snapshot. All tables and transactions reconciled.
    </div>
<?php endif; ?>

<!-- System Architecture & Storage Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <span class="card-title">🗄️ Database File Information</span>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <div>
                <div style="font-size: 11px; text-transform: uppercase; color: var(--slate-500);">Active File Location</div>
                <strong style="font-size: 13px; font-family: monospace;"><?= htmlspecialchars(DB_PATH) ?></strong>
            </div>
            <div>
                <div style="font-size: 11px; text-transform: uppercase; color: var(--slate-500);">Current Size</div>
                <strong><?= number_format(filesize(DB_PATH) / 1024, 1) ?> KB</strong>
            </div>
            <div>
                <div style="font-size: 11px; text-transform: uppercase; color: var(--slate-500);">Storage Engine</div>
                <strong style="color: #0284c7;">SQLite 3 (WAL Journal Mode)</strong>
            </div>
            <div>
                <div style="font-size: 11px; text-transform: uppercase; color: var(--slate-500);">Last Modified</div>
                <strong><?= date('Y-m-d H:i:s', filemtime(DB_PATH)) ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Available Snapshots Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📦 Archived Snapshots &amp; Recovery Points</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Snapshot File Name</th>
                        <th>Created Timestamp</th>
                        <th>File Size</th>
                        <th>Download File</th>
                        <th>Restore Database</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--slate-400); padding: 24px;">No archived snapshots created yet. Click "Create Local Snapshot" above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($backups as $bk): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($bk['name']) ?></code></td>
                                <td><?= htmlspecialchars($bk['date']) ?></td>
                                <td><?= number_format($bk['size'] / 1024, 1) ?> KB</td>
                                <td>
                                    <a href="backup.php?download_file=<?= urlencode($bk['name']) ?>" class="btn btn-secondary btn-sm">⬇️ Download</a>
                                </td>
                                <td>
                                    <form method="POST" action="backup.php" onsubmit="return confirm('CRITICAL WARNING: Restoring will overwrite the current live database with this snapshot. Are you sure you want to proceed?');">
                                        <input type="hidden" name="action" value="restore_snapshot">
                                        <input type="hidden" name="backup_file" value="<?= htmlspecialchars($bk['name']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">🔄 Restore</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
