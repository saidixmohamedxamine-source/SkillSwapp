<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
require_once dirname(__DIR__) . '/includes/badge_functions.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$profile_id) {
    header('Location: search.php');
    exit;
}

$pdo = $db->getPDO();

$user = [];
$stmt = $pdo->prepare('SELECT user_id, first_name, last_name, email, photo, bio, suspended FROM users WHERE user_id = ?');
$stmt->execute([$profile_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: search.php');
    exit;
}

$role = 'learner';
$stmt = $pdo->prepare('SELECT admin_id FROM administrators WHERE admin_id = ?');
$stmt->execute([$profile_id]);
if ($stmt->fetch()) {
    $role = 'administrator';
} else {
    $stmt = $pdo->prepare('SELECT supervisor_id FROM supervisors WHERE supervisor_id = ?');
    $stmt->execute([$profile_id]);
    if ($stmt->fetch()) {
        $role = 'supervisor';
    }
}

$stmt = $pdo->prepare('SELECT specialization, score FROM learners WHERE learner_id = ?');
$stmt->execute([$profile_id]);
$learner = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['specialization' => null, 'score' => 0];

$stmt = $pdo->prepare('SELECT average_rating, available FROM learners WHERE learner_id = ?');
$stmt->execute([$profile_id]);
$mentor_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['average_rating' => null, 'available' => false];

$stmt = $pdo->prepare('SELECT filiere FROM supervisors WHERE supervisor_id = ?');
$stmt->execute([$profile_id]);
$supervisor_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['filiere' => null];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM help_requests WHERE learner_id = ?');
$stmt->execute([$profile_id]);
$helped = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM learner_skills WHERE learner_id = ?');
$stmt->execute([$profile_id]);
$skill_count = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM help_proposals WHERE mentor_id = ?');
$stmt->execute([$profile_id]);
$session_count = (int)$stmt->fetchColumn();

$skills = [];
$stmt = $pdo->prepare('SELECT ls.possession_id, s.skill_name, ls.skill_level AS level FROM learner_skills ls JOIN skills s ON ls.skill_id = s.skill_id WHERE ls.learner_id = ? ORDER BY s.skill_name');
$stmt->execute([$profile_id]);
$skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reviews = [];
$stmt = $pdo->prepare('SELECT u.first_name, u.last_name, r.rating, r.comment, r.created_at FROM reviews r JOIN users u ON r.reviewer_id = u.user_id WHERE r.reviewed_user_id = ? ORDER BY r.created_at DESC LIMIT 5');
$stmt->execute([$profile_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);



$total_points = $learner ? (int)$learner['score'] : 0;
$badges = 0;
$stmt = $pdo->prepare('SELECT COUNT(*) FROM learner_badges WHERE learner_id = ?');
$stmt->execute([$profile_id]);
$badges = (int)$stmt->fetchColumn();

$user_badges = [];
$stmt = $pdo->prepare('SELECT b.badge_id, b.badge_name, b.image FROM learner_badges lb JOIN badges b ON lb.badge_id = b.badge_id WHERE lb.learner_id = ?');
$stmt->execute([$profile_id]);
$user_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$level_info = null;
if (!in_array($role, ['supervisor', 'administrator'])) {
    $level_info = getUserLevel($pdo, $profile_id);
}

$current_user_id = (int)$_SESSION['user_id'];

// Check if current user has a resolved help session with this mentor
$has_resolved_session = false;
if ($current_user_id !== $profile_id) {
    $stmt = $pdo->prepare('SELECT 1 FROM help_requests hr JOIN help_proposals hp ON hr.request_id = hp.request_id WHERE hr.learner_id = ? AND hp.mentor_id = ? AND hr.request_status = ? AND hp.proposal_status = ? LIMIT 1');
    $stmt->execute([$current_user_id, $profile_id, 'resolved', 'accepted']);
    $has_resolved_session = (bool)$stmt->fetchColumn();
}

// Profile privacy check
if ($profile_id !== $current_user_id) {
    $viewer_role = $_SESSION['role'] ?? 'learner';
    $stmt = $pdo->prepare('SELECT is_profile_public FROM users WHERE user_id = ?');
    $stmt->execute([$profile_id]);
    $profile_public = (bool)$stmt->fetchColumn();
    if (!$profile_public && $viewer_role === 'learner') {
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}

$viewer_is_supervisor = false;
$supervisor_filiere = '';
$stmt = $pdo->prepare('SELECT filiere FROM supervisors WHERE supervisor_id = ?');
$stmt->execute([$current_user_id]);
$supervisor_row = $stmt->fetch(PDO::FETCH_ASSOC);
$viewer_is_supervisor = (bool)$supervisor_row;
$supervisor_filiere = $supervisor_row ? $supervisor_row['filiere'] : '';

$available_skills_to_add = [];
if ($viewer_is_supervisor && $role === 'learner' && $profile_id !== $current_user_id) {
    $stmt = $pdo->prepare('SELECT s.skill_id, s.skill_name FROM skills s WHERE s.category = ? AND s.skill_id NOT IN (SELECT COALESCE(skill_id, 0) FROM learner_skills WHERE learner_id = ?) ORDER BY s.skill_name');
    $stmt->execute([$supervisor_filiere, $profile_id]);
    $available_skills_to_add = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$all_skills = [];
$stmt = $pdo->query('SELECT skill_id, skill_name FROM skills ORDER BY skill_name');
$all_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Supervisor — add skill to mentor profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_skill_to_profile'])) {
    $skill_id = (int)($_POST['skill_id'] ?? 0);
    if ($skill_id && $viewer_is_supervisor && $role === 'learner') {
        $stmt = $pdo->prepare('SELECT 1 FROM learner_skills WHERE learner_id = ? AND skill_id = ?');
        $stmt->execute([$profile_id, $skill_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO learner_skills (learner_id, skill_id, skill_level) VALUES (?, ?, ?)');
            $stmt->execute([$profile_id, $skill_id, 'beginner']);
        }
        header('Location: users_profile.php?id=' . $profile_id);
        exit;
    }
}

// Supervisor — remove skill from mentor profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_skill_from_profile'])) {
    $possession_id = (int)($_POST['possession_id'] ?? 0);
    if ($possession_id && $viewer_is_supervisor && $role === 'learner') {
        $stmt = $pdo->prepare('DELETE FROM learner_skills WHERE possession_id = ?');
        $stmt->execute([$possession_id]);
        header('Location: users_profile.php?id=' . $profile_id);
        exit;
    }
}

// Supervisor — upgrade skill level
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_skill_level'])) {
    $possession_id = (int)($_POST['possession_id'] ?? 0);
    if ($possession_id && $viewer_is_supervisor && $role === 'learner') {
        $stmt = $pdo->prepare("UPDATE learner_skills SET skill_level = CASE skill_level WHEN 'beginner' THEN 'intermediate' WHEN 'intermediate' THEN 'advanced' WHEN 'advanced' THEN 'expert' ELSE 'expert' END WHERE possession_id = ? AND learner_id = ?");
        $stmt->execute([$possession_id, $profile_id]);
        header('Location: users_profile.php?id=' . $profile_id);
        exit;
    }
}

// User — submit review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($has_resolved_session && $rating >= 1 && $rating <= 5) {
        $stmt = $pdo->prepare('INSERT INTO reviews (reviewer_id, reviewed_user_id, rating, comment) VALUES (?, ?, ?, ?)');
        $stmt->execute([$current_user_id, $profile_id, $rating, $comment]);
        $stmt = $pdo->prepare('SELECT AVG(rating) FROM reviews WHERE reviewed_user_id = ?');
        $stmt->execute([$profile_id]);
        $avg = round((float)$stmt->fetchColumn(), 2);
        $stmt = $pdo->prepare('UPDATE learners SET average_rating = ? WHERE learner_id = ?');
        $stmt->execute([$avg, $profile_id]);
        header('Location: users_profile.php?id=' . $profile_id . '&review=success');
        exit;
    }
    $review_error = 'Please select a rating (1-5).';
}

// Admin — suspend / reinstate account
$viewer_access_level = (int)($_SESSION['access_level'] ?? 0);
$viewer_is_admin = $_SESSION['role'] === 'administrator' && $viewer_access_level >= 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_suspend'])) {
    if ($viewer_is_admin && $profile_id !== $current_user_id) {
        $new_status = (int)$_POST['new_suspended'];
        $stmt = $pdo->prepare('UPDATE users SET suspended = ? WHERE user_id = ?');
        $stmt->execute([$new_status, $profile_id]);
        header('Location: users_profile.php?id=' . $profile_id);
        exit;
    }
}

$page_title = htmlspecialchars($user['first_name']) . '\'s Profile';
include dirname(__DIR__) . '/includes/header.php';
?>
<?php if (isset($_GET['review']) && $_GET['review'] === 'success'): ?>
<div class="alert alert-success" style="margin-bottom: 24px;">Review submitted successfully!</div>
<?php endif; ?>
<?php if (!empty($review_error)): ?>
<div class="alert alert-danger" style="margin-bottom: 24px;"><?php echo htmlspecialchars($review_error); ?></div>
<?php endif; ?>
<div class="app-shell">
    <?php $sidebar_active = 'search'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Mentor Profile'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="profile-content">
            <div class="profile-container">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar-large" style="position:relative;">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            <?php if ($role === 'supervisor'): ?>
                                <span style="position:absolute;bottom:0;right:0;font-size:14px;background:var(--primary-600);color:#f59e0b;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:3px solid #fff;"><i class="fas fa-crown"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></div>
                        <div class="profile-username">
                            @<?php echo htmlspecialchars($user['email'] ?: 'profile'); ?>
                            <span class="profile-role"><?php echo htmlspecialchars($role); ?></span>
                        </div>
                    </div>

                    <div class="profile-rating">
                        <?php if ($role === 'learner'): ?>
                        <div class="stars">
                            <?php if ($mentor_info['average_rating'] && $mentor_info['average_rating'] > 0): ?>
                                <?php $full = floor($mentor_info['average_rating']); ?>
                                <?php $fraction = $mentor_info['average_rating'] - $full; ?>
                                <?php $hasHalf = $fraction >= 0.25 && $full < 5; ?>
                                <?php $starColor = getRatingColor($mentor_info['average_rating']); ?>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $full): ?>
                                        <span class="fas fa-star" style="color:<?php echo $starColor; ?>;"></span>
                                    <?php elseif ($hasHalf && $i == $full + 1): ?>
                                        <span class="fas fa-star-half-alt" style="color:<?php echo $starColor; ?>;"></span>
                                    <?php else: ?>
                                        <span class="fa-regular fa-star" style="color:#d1d5db;"></span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span class="rating-value">(<?php echo number_format($mentor_info['average_rating'], 1); ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($level_info): ?>
                        <div class="profile-badge">
                            <span class="level-badge">Level <?php echo $level_info['level']; ?> - <?php echo htmlspecialchars($level_info['title']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-info">
                        <div class="info-item"><span class="info-icon fas fa-envelope"></span><span class="info-text"><?php echo htmlspecialchars($user['email']); ?></span></div>
                        <?php if ($role === 'learner' && $learner['specialization']): ?>
                        <div class="info-item"><span class="info-icon fas fa-graduation-cap"></span><span class="info-text"><?php echo htmlspecialchars($learner['specialization']); ?></span></div>
                        <?php elseif ($role === 'supervisor' && $supervisor_info['filiere']): ?>
                        <div class="info-item"><span class="info-icon fas fa-building"></span><span class="info-text"><?php echo htmlspecialchars($supervisor_info['filiere']); ?></span></div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-stats">
                        <?php if ($role === 'learner'): ?>
                        <div class="stat">
                            <div class="stat-number"><?php echo number_format($session_count); ?></div>
                            <div class="stat-label">Sessions</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><?php echo number_format($total_points); ?></div>
                            <div class="stat-label">Points</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><?php echo number_format($badges); ?></div>
                            <div class="stat-label">Badges</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <a href="search.php" class="button secondary block" style="margin-top:16px;"><i class="fas fa-arrow-left"></i> Back to search</a>

                    <?php if ($viewer_is_admin && $profile_id !== $current_user_id): ?>
                    <form method="POST" style="margin-top:12px;">
                        <input type="hidden" name="new_suspended" value="<?php echo $user['suspended'] ? 0 : 1; ?>">
                        <button type="submit" name="toggle_suspend" class="button <?php echo $user['suspended'] ? 'primary' : 'danger'; ?> block" style="<?php echo $user['suspended'] ? '' : 'background:var(--danger-600);color:#fff;border-color:var(--danger-600);'; ?>" onclick="return confirm('<?php echo $user['suspended'] ? 'Reinstate' : 'Suspend'; ?> this account?');">
                            <i class="fas fa-<?php echo $user['suspended'] ? 'check-circle' : 'ban'; ?>"></i>
                            <?php echo $user['suspended'] ? 'Reinstate Account' : 'Suspend Account'; ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="profile-right">
                    <div class="about-section">
                        <div class="section-title">About <?php echo htmlspecialchars($user['first_name']); ?></div>
                        <p class="about-text"><?php echo htmlspecialchars($user['bio'] ?: $user['first_name'] . ' is a ' . $role . ' passionate about learning and helping others.'); ?></p>
                    </div>

                    <?php if ($role === 'learner'): ?>
                    <div class="achievements-section">
                        <div class="achievements-header">
                            <span class="achievements-icon fas fa-trophy"></span>
                            <div>
                                <div class="section-title">Achievements</div>
                            </div>
                        </div>
                        <?php if (!empty($user_badges)): ?>
                        <div class="achievements-grid">
                            <?php foreach ($user_badges as $ub): ?>
                            <div class="achievement-card">
                                <div class="achievement-icon <?php echo htmlspecialchars($ub['image'] ? 'fas ' . $ub['image'] : 'fas fa-award'); ?>"></div>
                                <div class="achievement-title"><?php echo htmlspecialchars($ub['badge_name']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p style="color:var(--text-muted);font-size:13px;">No badges earned yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="skills-section">
                        <div class="skills-header">
                            <div>
                                <div class="section-title">Skills</div>
                            </div>
                            <?php if ($viewer_is_supervisor && $role === 'learner' && $profile_id !== $current_user_id && !empty($available_skills_to_add)): ?>
                            <button type="button" class="button primary small" id="profileAddSkillBtn"><i class="fas fa-plus"></i> Add skill</button>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($skills)): ?>
                        <div class="skills-grid">
                            <?php foreach ($skills as $skill): ?>
                                <?php
                                    $level_class = strtolower($skill['level']);
                                    $width = 80;
                                    if ($level_class === 'beginner') $width = 55;
                                    elseif ($level_class === 'intermediate') $width = 70;
                                    elseif ($level_class === 'advanced') $width = 95;
                                    elseif ($level_class === 'expert') $width = 100;
                                ?>
                                <div class="skill-item">
                                    <?php if ($viewer_is_supervisor && $role === 'learner' && $profile_id !== $current_user_id): ?>
                                    <form method="POST" style="position:absolute;top:6px;right:6px;z-index:2;" onsubmit="return confirm('Remove this skill from the profile?');">
                                        <input type="hidden" name="possession_id" value="<?php echo $skill['possession_id']; ?>">
                                        <button type="submit" name="remove_skill_from_profile" style="width:24px;height:24px;border:none;border-radius:50%;background:var(--danger-100);color:var(--danger-700);cursor:pointer;font-size:12px;display:grid;place-items:center;transition:background var(--t-fast);" title="Remove skill">&times;</button>
                                    </form>
                                    <?php endif; ?>
                                    <div class="skill-header">
                                        <div class="skill-name"><?php echo htmlspecialchars($skill['skill_name']); ?></div>
                                        <span class="skill-level <?php echo htmlspecialchars($level_class); ?>"><?php echo htmlspecialchars($skill['level']); ?></span>
                                        <?php if ($viewer_is_supervisor && $role === 'learner' && $profile_id !== $current_user_id): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Upgrade this skill to the next level?');">
                                            <input type="hidden" name="possession_id" value="<?php echo $skill['possession_id']; ?>">
                                            <button type="submit" name="upgrade_skill_level" class="btn-upgrade" title="Upgrade level"><i class="fas fa-arrow-up"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <div class="skill-bar">
                                        <div class="skill-progress <?php echo htmlspecialchars($level_class); ?>" style="width: <?php echo $width; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p style="color:var(--text-muted);font-size:13px;">No skills added yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="reviews-section">
                        <div class="reviews-header">
                            <span class="reviews-icon fas fa-star"></span>
                            <div class="section-title">Recent Reviews</div>
                            <?php if ($has_resolved_session): ?>
                            <button type="button" class="button primary small" id="reviewBtn" style="margin-left:auto;"><i class="fas fa-plus"></i> Write a Review</button>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($reviews)): ?>
                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div>
                                            <div class="review-author"><?php echo htmlspecialchars(trim($review['first_name'] . ' ' . $review['last_name'])); ?></div>
                                            <div class="review-stars">
                                                <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                                    <span class="fas fa-star" style="color: #fbbf24;"></span>
                                                <?php endfor; ?>
                                                <?php for ($i = $review['rating']; $i < 5; $i++): ?>
                                                    <span class="fas fa-star" style="color: #e5e7eb;"></span>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="review-text"><?php echo htmlspecialchars($review['comment']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p style="color:var(--text-muted);font-size:13px;">No reviews earned yet.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </main>
    </div>
</div>

<div id="profileAddSkillModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-content" style="background:#fff;border-radius:12px;padding:32px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="margin:0;font-size:20px;">Add skill to <?php echo htmlspecialchars($user['first_name']); ?></h2>
            <button type="button" id="profileAddSkillModalClose" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">&times;</button>
        </div>
        <p style="color:var(--text-muted);margin-bottom:20px;">Select a skill from your filiere (<?php echo htmlspecialchars($supervisor_filiere); ?>).</p>
        <form method="POST">
            <div class="form-field full-width">
                <label style="display:block;margin-bottom:10px;font-weight:600;font-size:13px;">Skill <span style="color:#ef4444;">*</span></label>
                <select name="skill_id" required style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-md);font-size:14px;">
                    <option value="">-- Select a skill --</option>
                    <?php foreach ($available_skills_to_add as $ask): ?>
                    <option value="<?php echo $ask['skill_id']; ?>"><?php echo htmlspecialchars($ask['skill_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions-row" style="margin-top:24px;">
                <button type="submit" name="add_skill_to_profile" class="button primary"><i class="fas fa-plus"></i> Add Skill</button>
                <button type="button" id="profileAddSkillModalCancel" class="button secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="reviewModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-content" style="background:#fff;border-radius:12px;padding:32px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="margin:0;font-size:20px;">Write a Review for <?php echo htmlspecialchars($user['first_name']); ?></h2>
            <button type="button" id="reviewModalClose" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">&times;</button>
        </div>
        <form method="POST" onsubmit="return validateReviewForm();">
            <p style="color:var(--text-muted);margin-bottom:16px;">Rate your experience with <?php echo htmlspecialchars($user['first_name']); ?>.</p>
            <div class="star-input" id="starInput">
                <span class="fas fa-star" data-value="1"></span>
                <span class="fas fa-star" data-value="2"></span>
                <span class="fas fa-star" data-value="3"></span>
                <span class="fas fa-star" data-value="4"></span>
                <span class="fas fa-star" data-value="5"></span>
            </div>
            <input type="hidden" name="rating" id="ratingValue" value="0">
            <p id="ratingHint" style="color:var(--text-muted);font-size:12px;margin:4px 0 16px 0;">Click a star to rate</p>
            <div class="form-field full-width">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">Comment <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                <textarea name="comment" class="form-input" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-md);font-size:13px;resize:vertical;min-height:100px;font-family:inherit;" placeholder="Share your experience…"></textarea>
            </div>
            <div class="form-actions-row" style="margin-top:24px;">
                <button type="submit" name="submit_review" class="button primary"><i class="fas fa-paper-plane"></i> Submit Review</button>
                <button type="button" id="reviewModalCancel" class="button secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var profileSkillModal = document.getElementById('profileAddSkillModal');
    var profileSkillBtn = document.getElementById('profileAddSkillBtn');
    var profileSkillClose = document.getElementById('profileAddSkillModalClose');
    var profileSkillCancel = document.getElementById('profileAddSkillModalCancel');

    if (profileSkillBtn && profileSkillModal) {
        profileSkillBtn.addEventListener('click', function() {
            profileSkillModal.style.display = 'flex';
        });
    }

    function closeProfileSkillModal() {
        if (profileSkillModal) profileSkillModal.style.display = 'none';
    }

    if (profileSkillClose) profileSkillClose.addEventListener('click', closeProfileSkillModal);
    if (profileSkillCancel) profileSkillCancel.addEventListener('click', closeProfileSkillModal);
    if (profileSkillModal) {
        profileSkillModal.addEventListener('click', function(e) {
            if (e.target === profileSkillModal) closeProfileSkillModal();
        });
    }

    var reviewModal = document.getElementById('reviewModal');
    var reviewBtn = document.getElementById('reviewBtn');
    var reviewClose = document.getElementById('reviewModalClose');
    var reviewCancel = document.getElementById('reviewModalCancel');

    if (reviewBtn && reviewModal) {
        reviewBtn.addEventListener('click', function() {
            reviewModal.style.display = 'flex';
        });
    }

    function closeReviewModal() {
        if (reviewModal) reviewModal.style.display = 'none';
    }

    if (reviewClose) reviewClose.addEventListener('click', closeReviewModal);
    if (reviewCancel) reviewCancel.addEventListener('click', closeReviewModal);
    if (reviewModal) {
        reviewModal.addEventListener('click', function(e) {
            if (e.target === reviewModal) closeReviewModal();
        });
    }

    var stars = document.querySelectorAll('.star-input .fas');
    var ratingInput = document.getElementById('ratingValue');
    var ratingHint = document.getElementById('ratingHint');

    function highlightStars(val) {
        stars.forEach(function(s) {
            var v = parseInt(s.getAttribute('data-value'));
            if (v <= val) {
                s.className = 'fas fa-star';
                s.style.color = '#f59e0b';
            } else {
                s.className = 'fas fa-star';
                s.style.color = '#e5e7eb';
            }
        });
    }

    if (stars.length && ratingInput) {
        stars.forEach(function(s) {
            s.addEventListener('click', function() {
                var val = parseInt(this.getAttribute('data-value'));
                ratingInput.value = val;
                highlightStars(val);
                if (ratingHint) ratingHint.textContent = val + ' / 5';
            });
            s.addEventListener('mouseenter', function() {
                var val = parseInt(this.getAttribute('data-value'));
                highlightStars(val);
            });
            s.addEventListener('mouseleave', function() {
                var val = parseInt(ratingInput.value);
                highlightStars(val);
            });
        });
    }
});

function validateReviewForm() {
    var rating = parseInt(document.getElementById('ratingValue').value);
    if (rating < 1) {
        alert('Please select a star rating.');
        return false;
    }
    return true;
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
