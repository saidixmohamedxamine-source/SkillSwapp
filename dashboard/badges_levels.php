<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
require_once dirname(__DIR__) . '/includes/badge_functions.php';
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

$user = ['first_name' => 'User', 'last_name' => '', 'username' => ''];

$stmt = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $user = [
        'first_name' => $row['first_name'] ?: 'User',
        'last_name' => $row['last_name'] ?: '',
        'username' => $row['email'],
    ];
}

// Ensure badges are up to date
checkAndAwardBadges($pdo, $user_id);

// Get actual score from DB
$stmt = $pdo->prepare('SELECT score FROM learners WHERE learner_id = ?');
$stmt->execute([$user_id]);
$total_points = (int)$stmt->fetchColumn();

// Get earned badge IDs
$stmt = $pdo->prepare('SELECT badge_id FROM learner_badges WHERE learner_id = ?');
$stmt->execute([$user_id]);
$earned_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all badge definitions from DB
$stmt = $pdo->query('SELECT * FROM badges ORDER BY required_points ASC');
$db_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$levels = [
    ['rank' => 1, 'title' => 'Newcomer', 'xp' => 0],
    ['rank' => 2, 'title' => 'Learner', 'xp' => 100],
    ['rank' => 3, 'title' => 'Contributor', 'xp' => 250],
    ['rank' => 4, 'title' => 'Helper', 'xp' => 500],
    ['rank' => 5, 'title' => 'Mentor', 'xp' => 800],
    ['rank' => 6, 'title' => 'Expert', 'xp' => 1200],
    ['rank' => 7, 'title' => 'Expert Helper', 'xp' => 1700],
    ['rank' => 8, 'title' => 'Master', 'xp' => 2300],
    ['rank' => 9, 'title' => 'Legend', 'xp' => 3000],
    ['rank' => 10, 'title' => 'Champion', 'xp' => 4000],
];

// Calculate current level from actual DB score
$current_level = 1;
foreach ($levels as $lv) {
    if ($total_points >= $lv['xp']) {
        $current_level = $lv['rank'];
    }
}
$current_level_title = $levels[$current_level - 1]['title'];
$next_level = $levels[$current_level] ?? null;
$next_level_xp = $next_level ? $next_level['xp'] : $levels[count($levels) - 1]['xp'];
$prev_xp = $levels[$current_level - 1]['xp'];
$current_xp = min($next_level_xp, $total_points);
$progress_required = $next_level_xp - $prev_xp;
$progress_earned = $current_xp - $prev_xp;
$progress_ratio = $progress_required > 0 ? max(0, min(1, $progress_earned / $progress_required)) : 1;

$page_title = 'Badges & Levels';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'badges'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Badges & Levels'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Progress</span>
                    <h1 class="page-title"><span class="accent">Badges</span> &amp; levels</h1>
                    <p class="page-subtitle">Track your progress, earn badges, and level up.</p>
                </div>
                <span class="badge primary">Signed in as <?php echo htmlspecialchars($user['username'] ?: trim($user['first_name'] . ' ' . $user['last_name'])); ?></span>
            </div>

            <div class="level-summary-grid">
                <div class="level-card level-current">
                    <div class="level-card-top">
                        <span>Current Level</span>
                        <i class="fas fa-award"></i>
                    </div>
                    <div>
                        <div class="level-number"><?php echo $current_level; ?></div>
                        <div class="level-label"><?php echo htmlspecialchars($current_level_title); ?></div>
                    </div>
                </div>

                <div class="level-card level-stats">
                    <div class="level-card-top">
                        <span>Total Points</span>
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="level-number"><?php echo number_format($total_points); ?></div>
                        <div class="level-label">XP earned</div>
                    </div>
                </div>

                <div class="level-card level-progress">
                    <div class="level-card-top">
                        <span>Progress to Level <?php echo min(10, $current_level + 1); ?></span>
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div>
                        <div class="progress-bar">
                            <div class="progress-fill level-fill" style="width: <?php echo max(5, round($progress_ratio * 100)); ?>%;"></div>
                        </div>
                        <div class="progress-text"><?php echo number_format($current_xp); ?> / <?php echo number_format($next_level_xp); ?> XP</div>
                    </div>
                </div>
            </div>

            <?php
            // Group earned badges by role
            $badge_sections = [];
            foreach ($db_badges as $badge) {
                $badge_role = $badge['role'] ?? 'learner';
                if ($badge_role === 'mentor') $badge_role = 'learner';
                if (in_array($badge['badge_id'], $earned_ids)) {
                    $badge_sections[$badge_role][] = $badge;
                }
            }

            $section_labels = [
                'learner'       => ['icon' => 'fa-graduation-cap', 'title' => 'Student Badges'],
                'supervisor'    => ['icon' => 'fa-clipboard-check','title' => 'Supervisor Badges'],
                'administrator' => ['icon' => 'fa-shield-halved',  'title' => 'Administrator Badges'],
            ];
            ?>

            <?php if (!empty($badge_sections)): ?>
                <?php foreach ($badge_sections as $role_key => $badges): ?>
                    <?php $label = $section_labels[$role_key] ?? ['icon' => 'fa-medal', 'title' => ucfirst($role_key) . ' Badges']; ?>
                    <div class="badge-section">
                        <div class="section-title-with-icon">
                            <i class="section-icon fas <?php echo $label['icon']; ?>"></i>
                            <h2><?php echo $label['title']; ?></h2>
                        </div>
                        <div class="badges-grid">
                            <?php foreach ($badges as $badge): ?>
                                <div class="badge-card earned">
                                    <div class="badge-icon earned">
                                        <i class="<?php echo getBadgeIcon($badge['badge_name']); ?>"></i>
                                    </div>
                                    <h3><?php echo htmlspecialchars($badge['badge_name']); ?></h3>
                                    <p><?php
                                        $req = (int)$badge['required_points'];
                                        if ($badge['role'] === 'learner') {
                                            echo $req . ' ' . ($req === 1 ? 'accepted proposal required' : 'accepted proposals required');
                                        } elseif ($badge['role'] === 'mentor') {
                                            echo $req . ' ' . ($req === 1 ? 'accepted invite required' : 'accepted invites required');
                                        } elseif ($badge['role'] === 'administrator') {
                                            echo 'Special badge';
                                        } else {
                                            echo $req . ' XP required';
                                        }
                                    ?></p>
                                    <div class="badge-status">Earned</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <p>No badges earned yet. Keep learning to earn your first badge!</p>
                </div>
            <?php endif; ?>

            <div class="level-progression-section">
                <div class="section-title-with-icon">
                    <i class="section-icon fas fa-layer-group"></i>
                    <h2>Level Progression</h2>
                </div>

                <div class="level-list">
                    <?php foreach ($levels as $level): ?>
                        <?php $is_current = $level['rank'] === $current_level; ?>
                        <?php $is_upcoming = $level['rank'] > $current_level; ?>
                        <div class="level-item <?php echo $is_current ? 'current' : ($is_upcoming ? 'upcoming' : 'achieved'); ?>">
                            <div class="level-dot <?php echo $is_current ? 'current-dot' : ($is_upcoming ? 'upcoming-dot' : 'achieved-dot'); ?>"><?php echo $level['rank']; ?></div>
                            <div class="level-item-content">
                                <h3><?php echo htmlspecialchars($level['title']); ?></h3>
                                <p><?php echo number_format($level['xp']); ?> XP required</p>
                            </div>
                            <div class="level-status-group">
                                <?php if ($is_current): ?>
                                    <span class="level-current-tag">Current Level</span>
                                <?php else: ?>
                                    <span class="level-badge-tag"><?php echo $level['rank'] < $current_level ? 'Achieved' : 'Upcoming'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php';

