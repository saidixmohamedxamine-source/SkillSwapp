<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
require_once dirname(__DIR__) . '/includes/badge_functions.php';
require_once dirname(__DIR__) . '/includes/notification_functions.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proposal_id'], $_POST['action'])) {
    $proposal_id = (int)$_POST['proposal_id'];
    $action = $_POST['action'];

    if (in_array($action, ['accepted', 'rejected'], true)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE help_proposals SET proposal_status = ? WHERE proposal_id = ? AND mentor_id = ? AND proposal_status = ?');
            $stmt->execute([$action, $proposal_id, $user_id, 'pending']);
            if ($stmt->rowCount()) {
                // Get the request and mentor info for notifications
                $stmt = $pdo->prepare('SELECT hp.request_id, hr.title, hr.learner_id FROM help_proposals hp JOIN help_requests hr ON hp.request_id = hr.request_id WHERE hp.proposal_id = ?');
                $stmt->execute([$proposal_id]);
                $req = $stmt->fetch(PDO::FETCH_ASSOC);

                $mentor_name = '';
                if ($req) {
                    $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $mentor_name = $stmt->fetchColumn();
                }

                if ($action === 'accepted') {
                    if ($req) {
                        // Select mentors who will be auto-rejected
                        $stmt = $pdo->prepare('SELECT mentor_id FROM help_proposals WHERE request_id = ? AND proposal_id != ? AND proposal_status = ?');
                        $stmt->execute([$req['request_id'], $proposal_id, 'pending']);
                        $rejected_mentors = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        // Auto-reject other proposals
                        $stmt = $pdo->prepare('UPDATE help_proposals SET proposal_status = ? WHERE request_id = ? AND proposal_id != ? AND proposal_status = ?');
                        $stmt->execute(['rejected', $req['request_id'], $proposal_id, 'pending']);

                        $stmt = $pdo->prepare('UPDATE help_requests SET request_status = ? WHERE request_id = ?');
                        $stmt->execute(['resolved', $req['request_id']]);

                        // Award XP
                        $creator_id = $req['learner_id'];
                        if ($creator_id) {
                            $stmt = $pdo->prepare('UPDATE learners SET score = score + 50 WHERE learner_id = ?');
                            $stmt->execute([$creator_id]);
                        }
                        $stmt = $pdo->prepare('UPDATE learners SET score = score + 25 WHERE learner_id = ?');
                        $stmt->execute([$user_id]);

                        // Check and award badges for both users
                        checkAndAwardBadges($pdo, $creator_id);
                        checkAndAwardBadges($pdo, $user_id);

                        // Notify the learner (request creator)
                        sendNotification($pdo, $creator_id, 'request_resolved', '"' . $req['title'] . '" has been resolved by ' . $mentor_name . '.', SITE_URL . 'dashboard/my_help_requests.php');

                        // Notify auto-rejected mentors
                        foreach ($rejected_mentors as $mid) {
                            sendNotification($pdo, $mid, 'proposal_rejected', 'Your invitation to help with "' . $req['title'] . '" has been rejected.', SITE_URL . 'dashboard/mentor_invites.php');
                        }
                    }
                    $message = 'Invitation accepted! Other invitations for this request have been rejected.';
                } else {
                    // Mentor rejected — notify the learner
                    if ($req) {
                        sendNotification($pdo, $req['learner_id'], 'proposal_rejected', $mentor_name . ' rejected your invitation for "' . $req['title'] . '".', SITE_URL . 'dashboard/my_help_requests.php');
                    }
                    $message = 'Invitation rejected.';
                }
                $msg_type = 'success';
            } else {
                $message = 'Invitation not found or already processed.';
                $msg_type = 'danger';
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error processing invitation.';
            $msg_type = 'danger';
        }
    }
}

$invites = [];
$stmt = $pdo->prepare('
    SELECT hp.proposal_id, hp.proposal_status, hr.published_at AS invited_at,
           hr.request_id, hr.title, hr.description, hr.request_status,
           u.first_name, u.last_name, u.email
    FROM help_proposals hp
    JOIN help_requests hr ON hp.request_id = hr.request_id
    JOIN users u ON hr.learner_id = u.user_id
    WHERE hp.mentor_id = ? AND hp.proposal_status = ?
    ORDER BY hr.published_at DESC
');
$stmt->execute([$user_id, 'pending']);
$invites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Invitations';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
    <?php $sidebar_active = 'mentor_invites'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'My Invitations'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Supervisor</span>
                    <h1 class="page-title">My <span class="accent">invitations</span></h1>
                    <p class="page-subtitle">Review and respond to help requests you've been invited to.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?>" style="margin-bottom: 24px;">
                    <i class="fas fa-<?php echo $msg_type === 'success' ? 'circle-check' : 'circle-exclamation'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($invites)): ?>
                <div style="text-align:center;padding:80px 20px;color:var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size:56px;margin-bottom:20px;opacity:0.3;display:block;"></i>
                    <h2 style="margin:0 0 8px;font-size:20px;color:var(--text-primary);">No invitations yet</h2>
                    <p style="margin:0;font-size:15px;max-width:400px;margin:0 auto;">When a learner requests your help, you'll see it here and can choose to accept or reject it.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <?php foreach ($invites as $invite):
                        $is_pending = $invite['proposal_status'] === 'pending';
                        $is_accepted = $invite['proposal_status'] === 'accepted';
                    ?>
                        <div class="card">
                            <div class="card-header">
                                <div style="display:flex;align-items:center;gap:14px;">
                                    <div class="mentor-avatar" style="width:52px;height:52px;font-size:18px;flex-shrink:0;">
                                        <?php echo strtoupper(substr($invite['first_name'], 0, 1) . substr($invite['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h3 style="margin:0 0 2px;"><?php echo htmlspecialchars($invite['title']); ?></h3>
                                        <p style="margin:0;font-size:13px;">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars(trim($invite['first_name'] . ' ' . $invite['last_name'])); ?>
                                            &middot; <i class="fas fa-clock"></i> <?php echo date('M j, Y', strtotime($invite['invited_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="badge" style="
                                    <?php if ($is_accepted): ?>
                                        background:var(--success-100);color:var(--success-700);
                                    <?php elseif ($is_rejected): ?>
                                        background:var(--danger-100);color:var(--danger-700);
                                    <?php else: ?>
                                        background:var(--warning-100);color:var(--warning-700);
                                    <?php endif; ?>
                                ">
                                    <i class="fas fa-<?php echo $is_accepted ? 'check-circle' : ($is_rejected ? 'times-circle' : 'hourglass-half'); ?>"></i>
                                    <?php echo ucfirst($invite['proposal_status']); ?>
                                </span>
                            </div>
                            <p style="margin:0 0 16px;font-size:14px;color:var(--text-secondary);line-height:1.6;">
                                <?php echo htmlspecialchars(mb_substr($invite['description'], 0, 300)) . (mb_strlen($invite['description']) > 300 ? '...' : ''); ?>
                            </p>
                            <?php if ($is_pending): ?>
                                <div style="display:flex;gap:10px;padding-top:16px;border-top:1px solid var(--border-soft);">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="proposal_id" value="<?php echo $invite['proposal_id']; ?>">
                                        <input type="hidden" name="action" value="accepted">
                                        <button type="submit" class="button primary" style="display:inline-flex;align-items:center;gap:6px;">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="proposal_id" value="<?php echo $invite['proposal_id']; ?>">
                                        <input type="hidden" name="action" value="rejected">
                                        <button type="submit" class="button secondary" style="display:inline-flex;align-items:center;gap:6px;">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
