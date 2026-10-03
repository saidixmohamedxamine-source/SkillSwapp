<?php
/**
 * My Help Requests Page
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

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
$pdo = $db->getPDO();

$stmt = $pdo->prepare('
    SELECT
        hr.request_id AS id,
        hr.title,
        hr.description,
        hr.request_status AS status,
        hr.published_at AS created_at
    FROM help_requests hr
    WHERE hr.learner_id = ?
    ORDER BY hr.published_at DESC
');
$stmt->execute([$user_id]);
$my_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

function timeAgo($datetime)
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    }
    return floor($diff / 86400) . ' days ago';
}

$page_title = 'My Help Requests';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'my_help_requests'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'My Help Requests'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Requests</span>
                    <h1 class="page-title">My <span class="accent">help</span> requests</h1>
                    <p class="page-subtitle">Track and manage the requests you've submitted.</p>
                </div>
                <div class="help-actions-row">
                    <a href="create_request.php" class="button primary"><i class="fas fa-plus"></i> Create request</a>
                </div>
            </div>

            <div class="help-card-list">
                <?php if (empty($my_requests)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p style="font-size: 16px; margin-bottom: 8px;">No requests yet</p>
                        <p style="font-size: 14px;">Submit a help request to get assistance from mentors.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($my_requests as $request): ?>
                        <article class="help-card">
                            <div class="help-card-main">
                                <div class="profile-badge-circle">ME</div>
                                <div>
                                    <h3 class="help-card-title"><?php echo htmlspecialchars($request['title']); ?></h3>
                                    <div class="help-card-tags">
                                        <span class="tag info"><?php echo ucfirst(htmlspecialchars($request['status'])); ?></span>
                                    </div>
                                    <p class="help-card-description"><?php echo htmlspecialchars($request['description']); ?></p>
                                    <div class="help-card-footer">
                                        <span>You</span>
                                        <span><?php echo timeAgo($request['created_at']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
