<?php
/**
 * Help History Page
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();
require_once '../includes/badge_functions.php';
$role = getUserRole($pdo, $user_id);
$help_requests = [];
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT
        hr.request_id AS id,
        hr.learner_id AS trainee_id,
        hr.title, hr.description,
        hr.request_status AS status,
        hr.published_at AS created_at,
        u.first_name, u.last_name, u.email,
        mu.first_name AS mentor_first_name,
        mu.last_name AS mentor_last_name
    FROM help_requests hr
    LEFT JOIN users u ON hr.learner_id = u.user_id
    LEFT JOIN help_proposals hp ON hr.request_id = hp.request_id AND hp.proposal_status = 'accepted'
    LEFT JOIN users mu ON hp.mentor_id = mu.user_id
    WHERE hr.request_status = 'resolved'
";
if ($search) {
    $like = '%' . $search . '%';
    $sql .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ?
                 OR CONCAT(mu.first_name, ' ', mu.last_name) LIKE ?)";
}
$sql .= " ORDER BY hr.published_at DESC";

$stmt = $pdo->prepare($sql);
if ($search) {
    $stmt->execute([$like, $like]);
} else {
    $stmt->execute();
}
$help_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$page_title = 'Help History';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'help_requests'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Help History'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">History</span>
                    <h1 class="page-title"><span class="accent">Help</span> history</h1>
                    <p class="page-subtitle">Review all resolved requests across the community.</p>
                </div>
                <div class="help-actions-row">
                    <form method="GET" style="display:flex;gap:8px;">
                        <input type="text" name="q" placeholder="Search by learner or mentor..."
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="padding:8px 12px;border:1px solid var(--border);border-radius:var(--r-md);font-size:14px;min-width:260px;">
                        <button type="submit" class="button primary"><i class="fas fa-search"></i></button>
                        <?php if ($search): ?>
                            <a href="help_requests.php" class="button secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                    <?php if ($role !== 'administrator' && $role !== 'supervisor'): ?>
                        <a href="create_request.php" class="button primary"><i class="fas fa-plus"></i> Create request</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="help-card-list">
                <?php if (empty($help_requests)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p style="font-size: 16px; margin-bottom: 8px;">No resolved requests yet</p>
                        <p style="font-size: 14px;">Completed requests will appear here once mentors resolve them.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($help_requests as $request): ?>
                        <?php
                            $initials = '';
                            if (!empty($request['first_name']) || !empty($request['last_name'])) {
                                $initials = strtoupper(substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1));
                            } else {
                                $initials = strtoupper(substr($request['email'], 0, 2));
                            }
                        ?>
                        <article class="help-card">
                            <div class="help-card-main">
                                <div class="profile-badge-circle"><?php echo htmlspecialchars($initials); ?></div>
                                <div>
                                    <h3 class="help-card-title"><?php echo htmlspecialchars($request['title']); ?></h3>
                                    <div class="help-card-tags">
                                        <span class="tag info"><?php echo ucfirst(htmlspecialchars($request['status'])); ?></span>
                                    </div>
                                    <p class="help-card-description"><?php echo htmlspecialchars($request['description']); ?></p>
                                    <div class="help-card-footer">
                                        <span><?php echo htmlspecialchars(trim($request['first_name'] . ' ' . $request['last_name'])) ?: htmlspecialchars($request['email']); ?></span>
                                        <?php if ($request['mentor_first_name']): ?>
                                        <span>&rarr; <?php echo htmlspecialchars(trim($request['mentor_first_name'] . ' ' . $request['mentor_last_name'])); ?></span>
                                        <?php endif; ?>
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
