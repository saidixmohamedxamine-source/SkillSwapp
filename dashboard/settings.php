<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/database/connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();

// Ensure settings columns exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_profile_public TINYINT(1) DEFAULT 1");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN notifications_enabled TINYINT(1) DEFAULT 1");
} catch (Exception $e) {}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_profile_public = isset($_POST['is_profile_public']) ? 1 : 0;
    $notifications_enabled = isset($_POST['notifications_enabled']) ? 1 : 0;

    $stmt = $pdo->prepare('UPDATE users SET is_profile_public = ?, notifications_enabled = ? WHERE user_id = ?');
    $stmt->execute([$is_profile_public, $notifications_enabled, $user_id]);

    $message = 'Settings updated successfully!';
}

// Fetch current settings
$stmt = $pdo->prepare('SELECT is_profile_public, notifications_enabled FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$is_profile_public = $settings ? (bool)$settings['is_profile_public'] : true;
$notifications_enabled = $settings ? (bool)$settings['notifications_enabled'] : true;

$page_title = 'Settings';

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'settings'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Settings'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">General</span>
                    <h1 class="page-title"><span class="accent">Account</span> settings</h1>
                    <p class="page-subtitle">Manage your profile visibility and notifications.</p>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($message); ?></span></div>
            <?php endif; ?>

            <form method="POST" class="form-card">
                <div class="form-field">
                    <label class="checkbox-group">
                        <input type="checkbox" name="is_profile_public" value="1" <?php echo $is_profile_public ? 'checked' : ''; ?>> Make my profile public
                    </label>
                    <p style="margin:4px 0 0 28px; font-size:13px; color:var(--text-muted);">When disabled, learners and mentors cannot view your profile.</p>
                </div>

                <div class="form-field" style="margin-top:20px;">
                    <label class="checkbox-group">
                        <input type="checkbox" name="notifications_enabled" value="1" <?php echo $notifications_enabled ? 'checked' : ''; ?>> Enable notifications
                    </label>
                    <p style="margin:4px 0 0 28px; font-size:13px; color:var(--text-muted);">When disabled, the unread notification indicator will be hidden.</p>
                </div>

                <div class="form-actions-row" style="margin-top:24px;">
                    <button type="submit" class="button primary"><i class="fas fa-floppy-disk"></i> Save changes</button>
                </div>
            </form>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
