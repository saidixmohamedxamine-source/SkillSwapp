<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
require_once dirname(__DIR__) . '/includes/notification_functions.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();
$error_message = '';
$success_message = '';

$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
if (!$request_id) {
    header('Location: my_help_requests.php');
    exit;
}

// Cancel — delete the request and go back
if (isset($_GET['cancel'])) {
    $stmt = $pdo->prepare('DELETE FROM help_requests WHERE request_id = ? AND learner_id = ?');
    $stmt->execute([$request_id, $user_id]);
    header('Location: my_help_requests.php');
    exit;
}

    $stmt = $pdo->prepare('SELECT * FROM help_requests WHERE request_id = ? AND learner_id = ?');
    $stmt->execute([$request_id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        header('Location: my_help_requests.php');
        exit;
    }

$stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$learner_name = $stmt->fetchColumn();

$mentors = [];
$stmt = $pdo->prepare('
    SELECT u.user_id, u.first_name, u.last_name,
           l.average_rating, l.available, l.specialization
    FROM learners l
    JOIN users u ON l.learner_id = u.user_id
    WHERE l.learner_id != ? AND u.suspended = 0
    ORDER BY l.average_rating DESC
');
$stmt->execute([$user_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $skills_stmt = $pdo->prepare('
        SELECT s.skill_name
        FROM learner_skills ls
        JOIN skills s ON ls.skill_id = s.skill_id
        WHERE ls.learner_id = ?
    ');
    $skills_stmt->execute([$row['user_id']]);
    $row['skills'] = $skills_stmt->fetchAll(PDO::FETCH_COLUMN);
    $mentors[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['mentors'] ?? [];
    if (!empty($selected)) {
        $check_stmt = $pdo->prepare('SELECT 1 FROM help_proposals hp JOIN help_requests hr ON hp.request_id = hr.request_id WHERE hr.learner_id = ? AND hp.mentor_id = ? AND hp.proposal_status = ?');
        $insert_stmt = $pdo->prepare('INSERT INTO help_proposals (request_id, mentor_id, proposal_status) VALUES (?, ?, ?)');
        $invited = 0;
        $skipped = 0;
        foreach ($selected as $mentor_id) {
            $mentor_id = (int)$mentor_id;
            $check_stmt->execute([$user_id, $mentor_id, 'pending']);
            if ($check_stmt->fetch()) {
                $skipped++;
            } else {
                $insert_stmt->execute([$request_id, $mentor_id, 'pending']);
                sendNotification($pdo, $mentor_id, 'proposal_received', $learner_name . ' invited you to help with "' . $request['title'] . '".', SITE_URL . 'dashboard/mentor_invites.php');
                $invited++;
            }
        }
        if ($invited > 0) {
            $success_message = $invited . ' mentor(s) invited!';
            header('Refresh: 2; url=my_help_requests.php');
        } else {
            $stmt = $pdo->prepare('DELETE FROM help_requests WHERE request_id = ? AND learner_id = ?');
            $stmt->execute([$request_id, $user_id]);
            $error_message = 'No mentors invited — all selected mentors already have a pending request with you.';
        }
    } else {
        $stmt = $pdo->prepare('DELETE FROM help_requests WHERE request_id = ? AND learner_id = ?');
        $stmt->execute([$request_id, $user_id]);
        header('Location: my_help_requests.php');
        exit;
    }
}

$page_title = 'Select Mentors';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
    <?php $sidebar_active = 'create_request'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>
    <div class="app-main">
        <?php $topbar_title = 'Select Mentors'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Step 2 of 2</span>
                    <h1 class="page-title">Select <span class="accent">mentors</span></h1>
                    <p class="page-subtitle">Choose one or more mentors to invite for "<strong><?php echo htmlspecialchars($request['title']); ?></strong>"</p>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="search-results" style="margin-bottom: 24px;">
                    <?php if (empty($mentors)): ?>
                        <p style="text-align:center;padding:40px;color:var(--text-muted);">No mentors available yet.</p>
                    <?php else: ?>
                        <?php foreach ($mentors as $mentor): ?>
                            <?php
                                $initial = strtoupper(substr($mentor['first_name'], 0, 1) . substr($mentor['last_name'], 0, 1));
                                $full_name = trim($mentor['first_name'] . ' ' . $mentor['last_name']);
                                $full = floor($mentor['average_rating']);
                                $fraction = $mentor['average_rating'] - $full;
                                $hasHalf = $fraction >= 0.25 && $full < 5;
                            ?>
                            <label class="mentor-card" style="cursor:<?php echo $mentor['available'] ? 'pointer' : 'default'; ?>;display:flex;align-items:center;gap:14px;opacity:<?php echo $mentor['available'] ? '1' : '0.55'; ?>;<?php echo $mentor['available'] ? '' : 'pointer-events:none;'; ?>">
                                <input type="checkbox" name="mentors[]" value="<?php echo $mentor['user_id']; ?>" style="width:20px;height:20px;flex-shrink:0;" <?php echo $mentor['available'] ? '' : 'disabled'; ?>>
                                <div class="mentor-avatar"><?php echo htmlspecialchars($initial); ?></div>
                                <div style="flex:1;">
                                    <h2><?php echo htmlspecialchars($full_name); ?></h2>
                                    <div class="mentor-rating">
                                        <?php if ($mentor['average_rating'] > 0): ?>
                                            <?php $starColor = getRatingColor($mentor['average_rating']); ?>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $full): ?>
                                                    <span class="fas fa-star" style="color:<?php echo $starColor; ?>;"></span>
                                                <?php elseif ($hasHalf && $i == $full + 1): ?>
                                                    <span class="fas fa-star-half-alt" style="color:<?php echo $starColor; ?>;"></span>
                                                <?php else: ?>
                                                    <span class="fa-regular fa-star" style="color:#d1d5db;"></span>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            <span><?php echo number_format($mentor['average_rating'], 1); ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:13px;">No reviews yet</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mentor-tags">
                                        <?php foreach ($mentor['skills'] as $skill): ?>
                                            <span><?php echo htmlspecialchars($skill); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mentor-meta">
                                        <span><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($mentor['specialization'] ?: 'N/A'); ?></span>
                                        <?php if ($mentor['available']): ?>
                                            <span style="color:var(--success-600);"><i class="fas fa-check-circle"></i> Available</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);"><i class="fas fa-clock"></i> Not Available</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="form-actions-row">
                    <button type="submit" class="button primary"><i class="fas fa-paper-plane"></i> Invite selected mentors</button>
                    <a href="select_mentors.php?request_id=<?php echo $request_id; ?>&cancel=1" class="button secondary">Cancel</a>
                </div>
            </form>
        </main>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
