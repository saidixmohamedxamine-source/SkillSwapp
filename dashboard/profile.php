<?php
/**
 * Profile Page
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

// Create reviews table if not exists
$create_reviews_table = "CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reviewer_id INT NOT NULL,
    reviewed_user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

try {
    $pdo->exec($create_reviews_table);
} catch (Exception $e) {
    // ignore create table errors for now
}

$user = [
    'first_name' => 'User',
    'last_name' => '',
    'username' => '',
    'email' => '',
    'role' => 'learner',
];

    $stmt = $pdo->prepare('SELECT email, first_name, last_name, photo, bio FROM users WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $user = [
            'first_name' => $row['first_name'] ?: 'User',
            'last_name' => $row['last_name'] ?: '',
            'username' => $row['email'],
            'email' => $row['email'],
            'role' => 'learner',
            'photo' => $row['photo'] ?? '',
            'bio' => $row['bio'] ?? '',
        ];
    }

    $admin_check = $pdo->prepare('SELECT admin_id FROM administrators WHERE admin_id = ?');
    $admin_check->execute([$user_id]);
    if ($admin_check->fetch()) {
        $user['role'] = 'administrator';
    } else {
    $super_check = $pdo->prepare('SELECT supervisor_id FROM supervisors WHERE supervisor_id = ?');
    $super_check->execute([$user_id]);
    if ($super_check->fetch()) {
        $user['role'] = 'supervisor';
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM learners WHERE learner_id = ?');
        $stmt->execute([$user_id]);
        if ($stmt->fetchColumn()) {
            $user['role'] = 'learner';
        }
    }
}

$helped = 0;
$total_points = 0;
require_once dirname(__DIR__) . '/includes/badge_functions.php';

$badges = 0;
$skills = [];
$skill_count = 0;
$reviews = [];

    $stmt = $pdo->prepare('SELECT specialization, score, available FROM learners WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $learner = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_points = $learner ? (int)$learner['score'] : 0;

    $supervisor_filiere = '';
    if ($user['role'] === 'supervisor') {
        $stmt = $pdo->prepare('SELECT filiere FROM supervisors WHERE supervisor_id = ?');
        $stmt->execute([$user_id]);
        $supervisor_filiere = $stmt->fetchColumn() ?: '';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM help_requests WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $helped = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM learner_skills WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $skill_count = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM learner_badges WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $badges = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT b.badge_id, b.badge_name, b.image FROM learner_badges lb JOIN badges b ON lb.badge_id = b.badge_id WHERE lb.learner_id = ?');
    $stmt->execute([$user_id]);
    $user_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT s.skill_name, ls.skill_level AS level FROM learner_skills ls JOIN skills s ON ls.skill_id = s.skill_id WHERE ls.learner_id = ? LIMIT 4');
    $stmt->execute([$user_id]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT u.first_name, u.last_name, r.rating, r.comment, r.created_at FROM reviews r JOIN users u ON r.reviewer_id = u.user_id WHERE r.reviewed_user_id = ? ORDER BY r.created_at DESC LIMIT 5');
    $stmt->execute([$user_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT average_rating FROM learners WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $avg_rating = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM help_proposals WHERE mentor_id = ?');
    $stmt->execute([$user_id]);
    $session_count = (int)$stmt->fetchColumn();

$level_info = null;
if (!in_array($user['role'], ['supervisor', 'administrator'])) {
    $level_info = getUserLevel($pdo, $user_id);
}

$is_edit = isset($_GET['edit']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_edit) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    $update = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, bio = ? WHERE user_id = ?');
    $update->execute([$first_name, $last_name, $bio, $user_id]);

    if ($user['role'] === 'learner') {
        if (isset($_POST['specialization'])) {
            $spec = $_POST['specialization'] ?: null;
            $update_spec = $pdo->prepare('UPDATE learners SET specialization = ?, available = ? WHERE learner_id = ?');
            $update_spec->execute([$spec, isset($_POST['available']) ? 1 : 0, $user_id]);
        } else {
            $update_avail = $pdo->prepare('UPDATE learners SET available = ? WHERE learner_id = ?');
            $update_avail->execute([isset($_POST['available']) ? 1 : 0, $user_id]);
        }
    }

    header('Location: profile.php');
    exit;
}

$page_title = 'Profile';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'profile'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Profile'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="profile-content">
            <?php if ($is_edit): ?>
            <form method="post" class="profile-container" style="text-align:left;">
            <?php else: ?>
            <div class="profile-container">
            <?php endif; ?>
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar-large">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <?php if ($is_edit): ?>
                        <div class="profile-name" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                            <input type="text" name="first_name" class="form-input" style="width:auto;flex:1;min-width:100px;text-align:center;" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            <input type="text" name="last_name" class="form-input" style="width:auto;flex:1;min-width:100px;text-align:center;" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                        <?php else: ?>
                        <div class="profile-name"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></div>
                        <?php endif; ?>
                        <div class="profile-username">
                            @<?php echo htmlspecialchars($user['username'] ?: 'profile'); ?>
                            <span class="profile-role"><?php echo htmlspecialchars($user['role']); ?></span>
                        </div>
                    </div>

                    <div class="profile-rating">
                        <?php if ($user['role'] === 'learner' || $user['role'] === 'supervisor'): ?>
                        <div class="stars">
                            <?php if ($avg_rating > 0): ?>
                                <?php $full = floor($avg_rating); ?>
                                <?php $fraction = $avg_rating - $full; ?>
                                <?php $hasHalf = $fraction >= 0.25 && $full < 5; ?>
                                <?php $starColor = getRatingColor($avg_rating); ?>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $full): ?>
                                        <span class="fas fa-star" style="color:<?php echo $starColor; ?>;"></span>
                                    <?php elseif ($hasHalf && $i == $full + 1): ?>
                                        <span class="fas fa-star-half-alt" style="color:<?php echo $starColor; ?>;"></span>
                                    <?php else: ?>
                                        <span class="fa-regular fa-star" style="color:#d1d5db;"></span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span class="rating-value">(<?php echo number_format($avg_rating, 1); ?>)</span>
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
                        <?php if ($user['role'] === 'learner'): ?>
                        <div class="info-item"><span class="info-icon fas fa-graduation-cap"></span>
                            <?php if ($is_edit): ?>
                            <select name="specialization" class="form-select" style="font-size:13px;padding:6px 10px;min-height:auto;">
                                <option value="">— Select specialization —</option>
                                <option value="Développement digital" <?php echo ($learner['specialization'] ?? '') === 'Développement digital' ? 'selected' : ''; ?>>Développement digital</option>
                                <option value="Infrastructure digitale" <?php echo ($learner['specialization'] ?? '') === 'Infrastructure digitale' ? 'selected' : ''; ?>>Infrastructure digitale</option>
                                <option value="Intelligence Artificielle" <?php echo ($learner['specialization'] ?? '') === 'Intelligence Artificielle' ? 'selected' : ''; ?>>Intelligence Artificielle</option>
                                <option value="Infographie" <?php echo ($learner['specialization'] ?? '') === 'Infographie' ? 'selected' : ''; ?>>Infographie</option>
                            </select>
                            <?php else: ?>
                            <span class="info-text"><?php echo htmlspecialchars($learner['specialization'] ?: 'General Studies'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="info-item"><span class="info-icon fas fa-clock"></span>
                            <?php if ($is_edit): ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="available" value="1" <?php echo $learner['available'] ? 'checked' : ''; ?>>
                                <span style="font-size:14px;font-weight:500;">Available for mentorship</span>
                            </label>
                            <?php else: ?>
                            <span class="info-text" style="color:<?php echo $learner['available'] ? 'var(--success-600)' : 'var(--danger-500)'; ?>;">
                                <i class="fas fa-<?php echo $learner['available'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                <?php echo $learner['available'] ? 'Available' : 'Not Available'; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($user['role'] === 'supervisor'): ?>
                        <div class="info-item"><span class="info-icon fas fa-building"></span><span class="info-text"><?php echo htmlspecialchars($supervisor_filiere ?: 'N/A'); ?></span></div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-stats">
                        <?php if ($user['role'] === 'learner'): ?>
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

                    <?php if ($is_edit): ?>
                    <button type="submit" class="edit-profile-btn">Save Changes</button>
                    <a href="profile.php" class="edit-profile-btn" style="background:transparent;color:var(--brand-700);border-color:var(--brand-200);margin-top:8px;">Cancel</a>
                    <?php else: ?>
                    <a href="?edit=1" class="edit-profile-btn">Edit Profile</a>
                    <?php if ($user['role'] === 'learner'): ?>
                    <a href="skills_passport.php" class="edit-profile-btn" style="background:transparent;color:var(--brand-700);border-color:var(--brand-200);margin-top:8px;"><i class="fas fa-passport"></i> Skills Passport</a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="profile-right">
                    <div class="about-section">
                        <div class="section-title">About Me</div>
                        <?php if ($is_edit): ?>
                        <textarea name="bio" class="form-textarea" placeholder="Write something about yourself..."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                        <?php else: ?>
                        <p class="about-text"><?php echo htmlspecialchars($user['bio'] ?: $user['first_name'] . ' is a ' . $user['role'] . ' passionate about learning and helping others.'); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($user['role'] === 'learner'): ?>
                    <div class="skills-section">
                        <div class="skills-header">
                            <div>
                                <div class="section-title">My Skills</div>
                            </div>
                        </div>
                        <div class="skills-grid">
                            <?php foreach ($skills as $skill): ?>
                                <?php
                                    $level_class = strtolower($skill['level']);
                                    $width = 80;
                                    if ($level_class === 'beginner') {
                                        $width = 55;
                                    } elseif ($level_class === 'intermediate') {
                                        $width = 70;
                                    }
                                    if ($level_class === 'advanced') {
                                        $width = 95;
                                    } elseif ($level_class === 'expert') {
                                        $width = 100;
                                    }
                                ?>
                                <div class="skill-item">
                                    <div class="skill-header">
                                        <div class="skill-name"><?php echo htmlspecialchars($skill['skill_name']); ?></div>
                                        <span class="skill-level <?php echo htmlspecialchars($level_class); ?>"><?php echo htmlspecialchars($skill['level']); ?></span>
                                    </div>
                                    <div class="skill-bar">
                                        <div class="skill-progress <?php echo htmlspecialchars($level_class); ?>" style="width: <?php echo $width; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'learner'): ?>
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
                    <?php endif; ?>

                    <?php if ($user['role'] === 'learner'): ?>
                    <div class="reviews-section">
                        <div class="reviews-header">
                            <span class="reviews-icon fas fa-star"></span>
                            <div class="section-title">Recent Reviews</div>
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
            <?php if ($is_edit): ?>
            </form>
            <?php else: ?>
            </div>
            <?php endif; ?>
        </div>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
