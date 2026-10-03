<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$raw_role = $_SESSION['role'] ?? '';
$pdo = $db->getPDO();

if ($raw_role !== 'supervisor') {
    header('Location: statistics.php');
    exit;
}

$stmt = $pdo->prepare('SELECT filiere FROM supervisors WHERE supervisor_id = ?');
$stmt->execute([$user_id]);
$filiere = $stmt->fetchColumn() ?: '';

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($request_id && in_array($action, ['approved', 'rejected'], true)) {
        $stmt = $pdo->prepare('SELECT sr.user_id, sr.skill_id, sr.status, s.category FROM skill_requests sr JOIN skills s ON sr.skill_id = s.skill_id WHERE sr.request_id = ? AND sr.status = ?');
        $stmt->execute([$request_id, 'pending']);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($req && $req['category'] === $filiere) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE skill_requests SET status = ?, reviewed_by = ? WHERE request_id = ?');
                $stmt->execute([$action, $user_id, $request_id]);

                if ($action === 'approved') {
                    $stmt = $pdo->prepare('INSERT IGNORE INTO learner_skills (learner_id, skill_id, skill_level) VALUES (?, ?, ?)');
                    $stmt->execute([$req['user_id'], $req['skill_id'], 'beginner']);
                    $message = 'Skill request approved. Mentor can now see it in their profile.';
                } else {
                    $message = 'Skill request rejected.';
                }
                $pdo->commit();
                $msg_type = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error processing request.';
                $msg_type = 'danger';
            }
        } else {
            $message = 'Request not found or not in your filiere.';
            $msg_type = 'danger';
        }
    }
}

$pending_requests = [];
$stmt = $pdo->prepare('
    SELECT sr.request_id, sr.created_at, u.first_name, u.last_name, s.skill_name, s.category
    FROM skill_requests sr
    JOIN users u ON sr.user_id = u.user_id
    JOIN skills s ON sr.skill_id = s.skill_id
    WHERE sr.status = ? AND s.category = ?
    ORDER BY sr.created_at DESC
');
$stmt->execute(['pending', $filiere]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Skill Requests';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
    <?php $sidebar_active = 'skill_requests'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Skill Requests'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Supervisor</span>
                    <h1 class="page-title">Skill <span class="accent">requests</span></h1>
                    <p class="page-subtitle">Review skill requests from mentors in <strong><?php echo htmlspecialchars($filiere); ?></strong>.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?>" style="margin-bottom:24px;">
                    <i class="fas fa-<?php echo $msg_type === 'success' ? 'circle-check' : 'circle-exclamation'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($pending_requests)): ?>
                <div style="text-align:center;padding:80px 20px;color:var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size:56px;margin-bottom:20px;opacity:0.3;display:block;"></i>
                    <h2 style="margin:0 0 8px;font-size:20px;color:var(--text);">No pending requests</h2>
                    <p style="margin:0;font-size:15px;max-width:400px;margin:0 auto;">When a mentor requests a skill from your filiere, it will appear here.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <?php foreach ($pending_requests as $req): ?>
                        <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;">
                            <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0;">
                                <div style="width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:var(--brand-50);color:var(--brand-600);font-weight:700;font-size:15px;flex-shrink:0;">
                                    <?php echo strtoupper(substr($req['first_name'], 0, 1) . substr($req['last_name'], 0, 1)); ?>
                                </div>
                                <div style="min-width:0;">
                                    <h3 style="margin:0 0 2px;font-size:15px;"><?php echo htmlspecialchars($req['skill_name']); ?></h3>
                                    <p style="margin:0;font-size:13px;color:var(--text-muted);">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars(trim($req['first_name'] . ' ' . $req['last_name'])); ?>
                                        &middot; <i class="fas fa-tag"></i> <?php echo htmlspecialchars($req['category']); ?>
                                        &middot; <i class="fas fa-clock"></i> <?php echo date('M j, g:i A', strtotime($req['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;flex-shrink:0;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                    <input type="hidden" name="action" value="approved">
                                    <button type="submit" class="button primary" style="display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-check"></i> Approve</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                    <input type="hidden" name="action" value="rejected">
                                    <button type="submit" class="button secondary" style="display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-times"></i> Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
