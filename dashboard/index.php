<?php
/**
 * Dashboard Home Page
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
require_once dirname(__DIR__) . '/includes/badge_functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') === 'administrator') {
    header('Location: statistics.php');
    exit;
}
if (($_SESSION['role'] ?? '') === 'supervisor') {
    header('Location: skill_requests.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get dashboard metrics
$pending_requests = 0;
$resolved_requests = 0;
$xp = 0;
$badges_count = 0;
$recent_requests = [];
$pdo = $db->getPDO();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM help_requests WHERE learner_id = ? AND request_status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_requests = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM help_requests WHERE learner_id = ? AND request_status = 'resolved'");
    $stmt->execute([$user_id]);
    $resolved_requests = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT score FROM learners WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $xp = (int)$stmt->fetchColumn();

    // Check and award any new qualifying badges
    checkAndAwardBadges($pdo, $user_id);

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM learner_badges WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $badges_count = (int)$stmt->fetchColumn();

    $level_info = getUserLevel($pdo, $user_id);
    $level_xps = [1 => 0, 100, 250, 500, 800, 1200, 1700, 2300, 3000, 4000];
    $current_level = $level_info['level'];
    $next_level = min($current_level + 1, 10);
    $current_floor = $level_xps[$current_level];
    $next_ceiling = $level_xps[$next_level];
    $progress_pct = ($next_ceiling > $current_floor)
        ? round(($xp - $current_floor) / ($next_ceiling - $current_floor) * 100)
        : 100;

    $stmt = $pdo->prepare('
        SELECT hr.title, hr.request_status, hr.published_at, u.first_name, u.last_name
        FROM help_requests hr
        LEFT JOIN users u ON hr.learner_id = u.user_id
        WHERE hr.request_status = ?
        ORDER BY hr.published_at DESC
        LIMIT 3
    ');
    $stmt->execute(['resolved']);
    $raw_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw_requests as $r) {
        $recent_requests[] = [
            'title' => $r['title'],
            'skill_category' => trim($r['first_name'] . ' ' . $r['last_name']),
            'status' => $r['request_status'],
            'created_at' => $r['published_at'],
        ];
    }

$page_title = 'Dashboard';

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'dashboard'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Dashboard'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="dashboard-top">
                <span class="eyebrow">Workspace overview</span>
                <h1>Welcome back, <span class="accent"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span></h1>
                <p>Here's a snapshot of your skills, requests, and progress today.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-title">Pending requests</div>
                    <div class="stat-value"><?php echo number_format($pending_requests); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
                    <div class="stat-title">Resolved requests</div>
                    <div class="stat-value"><?php echo number_format($resolved_requests); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-title">XP</div>
                    <div class="stat-value"><?php echo number_format($xp); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-award"></i></div>
                    <div class="stat-title">Badges</div>
                    <div class="stat-value"><?php echo number_format($badges_count); ?></div>
                </div>
            </div>

            <div class="dashboard-content-grid">
                <section class="card recent-requests-card">
                    <div class="content-header">
                        <div>
                            <h2>Help History</h2>
                            <p>Browse the most recent resolved requests.</p>
                        </div>
                        <a href="help_requests.php" class="button secondary small">View all</a>
                    </div>

                    <div class="request-list">
                        <?php if (!empty($recent_requests)): ?>
                            <?php foreach ($recent_requests as $request): ?>
                                <div class="request-item">
                                    <div>
                                        <h3><?php echo htmlspecialchars($request['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($request['skill_category']); ?></p>
                                    </div>
                                    <span class="request-time"><?php echo date('M j', strtotime($request['created_at'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="request-empty">
                                <p>No recent requests yet. Create one to get help from peers.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <aside class="right-panel">
                    <div class="card quick-actions-card">
                        <h2>Quick actions</h2>
                        <a href="create_request.php" class="button primary"><i class="fas fa-circle-plus"></i> Request help</a>
                        <a href="my_help_requests.php" class="button secondary"><i class="fas fa-list"></i> My Requests</a>
                    </div>

                    <div class="card progress-card">
                        <div class="content-header">
                            <div>
                                <h2>Your progress</h2>
                                <p>Level <?php echo $current_level; ?> &mdash; <?php echo htmlspecialchars($level_info['title']); ?> &bull; <?php echo number_format($xp); ?> / <?php echo number_format($next_ceiling); ?> XP</p>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo max(5, min(100, $progress_pct)); ?>%;"></div>
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
