<?php
/**
 * Dashboard Topbar Partial
 *
 * Expects:
 *   - $topbar_title (string|null): page title shown on the left
 */
$title = $topbar_title ?? ($page_title ?? '');

$topbar_level = null;
$unread_count = 0;
$notifications_enabled = true;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../database/connection.php';
    require_once __DIR__ . '/badge_functions.php';
    $pdo = $db->getPDO();
    if (!in_array($_SESSION['role'] ?? '', ['supervisor', 'administrator'])) {
        $topbar_level = getUserLevel($pdo, (int)$_SESSION['user_id']);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $unread_count = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT notifications_enabled FROM users WHERE user_id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $row = $stmt->fetchColumn();
    if ($row !== false) $notifications_enabled = (bool)$row;
}
?>
<header class="app-topbar">
    <div class="app-topbar-left">
        <button type="button" class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <?php if (!isset($hide_topbar_search) || !$hide_topbar_search): ?>
        <div class="topbar-search">
            <a href="<?php echo SITE_URL; ?>dashboard/search.php" class="topbar-search-btn" aria-label="Go to search page">
                <i class="fas fa-magnifying-glass"></i>
                <span>Find</span>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="app-topbar-right">
        <?php if ($topbar_level): ?>
        <span class="topbar-level" title="Level <?php echo $topbar_level['level']; ?> &mdash; <?php echo htmlspecialchars($topbar_level['title']); ?>">
            <i class="fas fa-award"></i>
            <span><?php echo $topbar_level['level']; ?></span>
        </span>
        <?php endif; ?>
        <a href="<?php echo SITE_URL; ?>dashboard/notifications.php" class="icon-pill" style="position:relative;" title="Notifications" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unread_count > 0 && $notifications_enabled): ?>
            <span style="position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 4px;line-height:1;"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo SITE_URL; ?>dashboard/profile.php" class="icon-pill" title="Profile" aria-label="Profile">
            <i class="fas fa-user"></i>
        </a>
    </div>
</header>
