<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();

// Mark all notifications as read on page load
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$user_id]);

// Fetch notifications
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Notifications';

$type_icons = [
    'proposal_received' => 'fas fa-envelope',
    'proposal_rejected' => 'fas fa-times-circle',
    'request_resolved'  => 'fas fa-check-circle',
    'badge_awarded'     => 'fas fa-award',
];

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
    <?php $sidebar_active = 'notifications'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Notifications'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Alerts</span>
                    <h1 class="page-title">Your <span class="accent">notifications</span></h1>
                    <p class="page-subtitle">Stay updated on your help requests, invitations, and achievements.</p>
                </div>
            </div>

            <?php if (empty($notifications)): ?>
                <div style="text-align:center;padding:80px 20px;color:var(--text-muted);">
                    <i class="fas fa-bell" style="font-size:56px;margin-bottom:20px;opacity:0.3;display:block;"></i>
                    <h2 style="margin:0 0 8px;font-size:20px;color:var(--text-primary);">No notifications yet</h2>
                    <p style="margin:0;font-size:15px;max-width:420px;margin:0 auto;">They'll appear here when someone invites you, responds to your request, or when you earn a new badge.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($notifications as $note): ?>
                        <?php
                            $icon = $type_icons[$note['type']] ?? 'fas fa-bell';
                            $is_unread = !$note['is_read'];
                        ?>
                        <a href="<?php echo htmlspecialchars($note['link'] ?: '#'); ?>" class="notification-item" style="<?php echo $is_unread ? 'border-left:3px solid var(--brand-500);background:var(--brand-50);' : ''; ?>">
                            <div style="display:flex;align-items:center;gap:14px;padding:16px;border-radius:var(--r-lg);border:1px solid var(--border-soft);background:var(--surface);transition:background var(--t-fast),border-color var(--t-fast);text-decoration:none;color:inherit;">
                                <div style="width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:var(--brand-50);color:var(--brand-600);font-size:16px;flex-shrink:0;">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <p style="margin:0;font-size:14px;color:var(--text);line-height:1.4;"><?php echo htmlspecialchars($note['message']); ?></p>
                                    <p style="margin:4px 0 0;font-size:12px;color:var(--text-muted);"><?php echo date('M j, g:i A', strtotime($note['created_at'])); ?></p>
                                </div>
                                <i class="fas fa-chevron-right" style="color:var(--text-soft);font-size:12px;"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
