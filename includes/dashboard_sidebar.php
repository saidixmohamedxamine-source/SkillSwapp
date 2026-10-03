<?php
/**
 * Dashboard Sidebar Partial
 *
 * Expects:
 *   - $sidebar_active (string|null): one of dashboard, profile, skills, help_requests,
 *     my_help_requests, create_request, search, notifications, mentor_invites,
 *     skill_requests, badges, statistics, about, contact, settings
 */
$is_authenticated = isset($_SESSION['user_id']);
$active = $sidebar_active ?? '';
$user_display = trim($_SESSION['user_name'] ?? '');
if ($user_display === '') {
    $user_display = $_SESSION['user_name'] ?? 'Student';
}
$user_initial = strtoupper(substr($user_display, 0, 1));
$raw_role = $_SESSION['role'] ?? '';
$role_labels = [
    'administrator' => 'Administrator',
    'supervisor'    => 'Supervisor',
    'learner'       => 'Student',
];
$user_role = $role_labels[$raw_role] ?? 'Student';

$user_level_info = null;
if ($is_authenticated) {
    require_once __DIR__ . '/../database/connection.php';
    require_once __DIR__ . '/badge_functions.php';
    $pdo = $db->getPDO();
    if (!in_array($raw_role, ['supervisor', 'administrator'])) {
        $user_level_info = getUserLevel($pdo, (int)$_SESSION['user_id']);
    }
    $sidebar_unread = 0;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $sidebar_unread = (int)$stmt->fetchColumn();

    $notifications_enabled = true;
    $stmt = $pdo->prepare('SELECT notifications_enabled FROM users WHERE user_id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $row = $stmt->fetchColumn();
    if ($row !== false) $notifications_enabled = (bool)$row;
} else {
    $sidebar_unread = 0;
    $notifications_enabled = true;
}

$app_links = [
    ['key' => 'profile',         'label' => 'Profile',        'icon' => 'fas fa-user',            'href' => SITE_URL . 'dashboard/profile.php'],
    ['key' => 'help_requests',     'label' => 'Help History',     'icon' => 'fas fa-circle-question', 'href' => SITE_URL . 'dashboard/help_requests.php'],
    ['key' => 'search',          'label' => 'Find People',    'icon' => 'fas fa-magnifying-glass','href' => SITE_URL . 'dashboard/search.php'],
    ['key' => 'notifications',   'label' => 'Notifications',  'icon' => 'fas fa-bell',            'href' => SITE_URL . 'dashboard/notifications.php'],
];

if (!in_array($raw_role, ['supervisor', 'administrator'])) {
    array_unshift($app_links, ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-house', 'href' => SITE_URL . 'dashboard/index.php']);
    $app_links[] = ['key' => 'my_help_requests', 'label' => 'My Help Requests', 'icon' => 'fas fa-list', 'href' => SITE_URL . 'dashboard/my_help_requests.php'];
}

if ($raw_role === 'learner') {
    $app_links[] = ['key' => 'create_request', 'label' => 'New Request', 'icon' => 'fas fa-circle-plus', 'href' => SITE_URL . 'dashboard/create_request.php'];
}

if ($raw_role === 'learner') {
    $app_links[] = ['key' => 'mentor_invites', 'label' => 'My Invitations', 'icon' => 'fas fa-envelope-open-text', 'href' => SITE_URL . 'dashboard/mentor_invites.php'];
}

if ($raw_role === 'supervisor') {
    $app_links[] = ['key' => 'skill_requests', 'label' => 'Skill Requests', 'icon' => 'fas fa-clipboard-list', 'href' => SITE_URL . 'dashboard/skill_requests.php'];
}

if ($raw_role === 'administrator' || $raw_role === 'supervisor') {
    $app_links[] = ['key' => 'statistics', 'label' => 'Statistics', 'icon' => 'fas fa-chart-line', 'href' => SITE_URL . 'dashboard/statistics.php'];
}

$progress_links = [];
$progress_links[] = ['key' => 'leaderboard', 'label' => 'Leaderboard', 'icon' => 'fas fa-trophy', 'href' => SITE_URL . 'dashboard/leaderboard.php'];
if (!in_array($raw_role, ['supervisor', 'administrator'])) {
    $progress_links[] = ['key' => 'badges', 'label' => 'Badges & Levels', 'icon' => 'fas fa-award', 'href' => SITE_URL . 'dashboard/badges_levels.php'];
}

$general_links = [
    ['key' => 'about',    'label' => 'About',    'icon' => 'fas fa-circle-info', 'href' => SITE_URL . 'about.php'],
    ['key' => 'contact',  'label' => 'Contact',  'icon' => 'fas fa-envelope',    'href' => SITE_URL . 'contact.php'],
    ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fas fa-gear',        'href' => SITE_URL . 'dashboard/settings.php'],
];

if ($raw_role === 'administrator' && ($_SESSION['access_level'] ?? 0) >= 10) {
    $general_links[] = ['key' => 'admin_users', 'label' => 'User Management', 'icon' => 'fas fa-user-shield', 'href' => SITE_URL . 'dashboard/admin_users.php'];
}

$render_group = function (array $items, string $label = '') use ($active, $sidebar_unread, $notifications_enabled) {
    if ($label !== '') {
        echo '<button type="button" class="sidebar-label-toggle">';
        echo '<span class="sidebar-label">' . htmlspecialchars($label) . '</span>';
        echo '<i class="fas fa-chevron-down sidebar-label-arrow"></i>';
        echo '</button>';
    }
    echo '<nav class="sidebar-menu">';
    foreach ($items as $link) {
        $is_active = $link['key'] === $active ? ' class="active"' : '';
        echo '<a href="' . $link['href'] . '"' . $is_active . '>';
        echo '<i class="' . $link['icon'] . '"></i><span>' . htmlspecialchars($link['label']) . '</span>';
            if ($link['key'] === 'notifications' && $sidebar_unread > 0 && $notifications_enabled) {
                echo '<span class="sidebar-notification-badge">' . ($sidebar_unread > 99 ? '99+' : $sidebar_unread) . '</span>';
            }
        echo '</a>';
    }
    echo '</nav>';
};
?>
<aside class="app-sidebar" id="appSidebar">
    <a href="<?php echo SITE_URL; ?>dashboard/index.php" class="sidebar-brand">
        <span class="brand-mark">IS</span>
        <span class="brand-text">
            <span class="brand-title"><?php echo htmlspecialchars(SITE_NAME); ?></span>
            <span class="brand-subtitle"><?php echo htmlspecialchars($user_role); ?></span>
        </span>
    </a>

    <div class="sidebar-section">
        <?php $render_group($app_links, 'Workspace'); ?>
    </div>

    <?php if (!empty($progress_links)): ?>
    <div class="sidebar-section">
        <?php $render_group($progress_links, 'Progress'); ?>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">
        <?php $render_group($general_links, 'General'); ?>
    </div>

    <div class="sidebar-footer">
        <?php if ($is_authenticated): ?>
            <div class="sidebar-user">
                <span class="user-avatar-small"><?php echo htmlspecialchars($user_initial); ?></span>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($user_display); ?></div>
                    <div class="sidebar-user-role"><?php echo htmlspecialchars($user_role); ?></div>
                    <?php if ($user_level_info): ?>
                    <div class="sidebar-user-level">Level <?php echo $user_level_info['level']; ?> &mdash; <?php echo htmlspecialchars($user_level_info['title']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?php echo SITE_URL; ?>auth/logout.php" class="sidebar-logout">
                <i class="fas fa-arrow-right-from-bracket"></i><span>Log out</span>
            </a>
        <?php else: ?>
            <div class="sidebar-guest">
                <a href="<?php echo SITE_URL; ?>auth/login.php" class="button secondary block">
                    <i class="fas fa-arrow-right-to-bracket"></i> Sign in
                </a>
                <a href="<?php echo SITE_URL; ?>auth/register.php" class="button primary block">
                    <i class="fas fa-user-plus"></i> Sign up
                </a>
            </div>
        <?php endif; ?>
    </div>
</aside>
