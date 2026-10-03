<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'administrator' && $_SESSION['role'] !== 'supervisor')) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
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

// --- Supervisor filiere ---
$supervisor_filiere = '';
if ($role === 'supervisor') {
    $stmt = $pdo->prepare('SELECT filiere FROM supervisors WHERE supervisor_id = ?');
    $stmt->execute([$user_id]);
    $supervisor_filiere = $stmt->fetchColumn() ?: '';
}

// --- User counts (admin only) ---
$user_counts = [];
if ($role === 'administrator') {
    $user_counts['total_users'] = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $user_counts['learners'] = $pdo->query('SELECT COUNT(*) FROM learners')->fetchColumn();
    $user_counts['supervisors'] = $pdo->query('SELECT COUNT(*) FROM supervisors')->fetchColumn();
    $user_counts['admins'] = $pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn();
}

// --- Skill request stats ---
$skill_pending = 0;
$skill_approved_month = 0;
$skill_rejected_month = 0;
$skill_total_processed = 0;

if ($role === 'supervisor' && $supervisor_filiere) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM skill_requests sr JOIN skills s ON sr.skill_id = s.skill_id WHERE sr.status = 'pending' AND s.category = ?");
    $stmt->execute([$supervisor_filiere]);
    $skill_pending = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM skill_requests sr JOIN skills s ON sr.skill_id = s.skill_id WHERE sr.status = 'approved' AND sr.reviewed_by = ? AND sr.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $stmt->execute([$user_id]);
    $skill_approved_month = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM skill_requests WHERE status = 'rejected' AND reviewed_by = ? AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $stmt->execute([$user_id]);
    $skill_rejected_month = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM skill_requests WHERE status IN ('approved','rejected') AND reviewed_by = ?");
    $stmt->execute([$user_id]);
    $skill_total_processed = (int)$stmt->fetchColumn();
} elseif ($role === 'administrator') {
    $skill_pending = (int)$pdo->query("SELECT COUNT(*) FROM skill_requests WHERE status = 'pending'")->fetchColumn();
    $skill_approved_month = (int)$pdo->query("SELECT COUNT(*) FROM skill_requests WHERE status = 'approved' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
    $skill_rejected_month = (int)$pdo->query("SELECT COUNT(*) FROM skill_requests WHERE status = 'rejected' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
    $skill_total_processed = (int)$pdo->query("SELECT COUNT(*) FROM skill_requests WHERE status IN ('approved','rejected')")->fetchColumn();
}

$skill_stat_cards = [
    ['label' => 'Pending Requests', 'value' => number_format($skill_pending), 'icon' => 'fas fa-clock'],
    ['label' => 'Approved This Month', 'value' => number_format($skill_approved_month), 'icon' => 'fas fa-check-circle'],
    ['label' => 'Rejected This Month', 'value' => number_format($skill_rejected_month), 'icon' => 'fas fa-times-circle'],
    ['label' => 'Total Processed', 'value' => number_format($skill_total_processed), 'icon' => 'fas fa-clipboard-list'],
];

// --- Weekly resolved help (last 7 weeks) ---
$weekly_resolved = [];
$stmt = $pdo->query("
    SELECT YEARWEEK(published_at, 1) AS yw, COUNT(*) AS cnt
    FROM help_requests
    WHERE request_status = 'resolved' AND published_at >= DATE_SUB(CURDATE(), INTERVAL 7 WEEK)
    GROUP BY YEARWEEK(published_at, 1)
    ORDER BY yw
");
$weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$weekly_map = [];
foreach ($weekly_data as $w) {
    $weekly_map[$w['yw']] = (int)$w['cnt'];
}

$weekly_resolved = [];
$max_weekly = 0;
for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime("-$i week");
    $yw = (int)date('oW', $ts);
    $cnt = $weekly_map[$yw] ?? 0;
    $label = date('M j', $ts);
    $weekly_resolved[] = ['label' => $label, 'value' => $cnt];
    $max_weekly = max($max_weekly, $cnt);
}
if ($max_weekly === 0) $max_weekly = 1;

// --- Skill distribution (top 7) ---
$skill_distribution = [];
$stmt = $pdo->query("
    SELECT s.skill_name, COUNT(ls.learner_id) AS learner_count
    FROM skills s
    JOIN learner_skills ls ON s.skill_id = ls.skill_id
    GROUP BY s.skill_id, s.skill_name
    ORDER BY learner_count DESC
    LIMIT 7
");
$skill_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
$max_skill = 0;
foreach ($skill_distribution as $s) {
    $max_skill = max($max_skill, (int)$s['learner_count']);
}
if ($max_skill === 0) $max_skill = 1;

// --- Top mentors by XP (top 5) ---
$top_mentors = [];
$stmt = $pdo->query("
    SELECT u.first_name, u.last_name, l.score, l.specialization
    FROM learners l
    JOIN users u ON l.learner_id = u.user_id
    ORDER BY l.score DESC
    LIMIT 5
");
$top_mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Statistics';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'statistics'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Statistics'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Insights</span>
                    <h1 class="page-title"><span class="accent">Statistics</span> dashboard</h1>
                    <p class="page-subtitle">Platform analytics, skill requests, and mentor rankings.</p>
                </div>
            </div>

            <?php if ($role === 'administrator'): ?>
            <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
                <div class="stat-card">
                    <div class="stat-content"><h3>Total Users</h3><div class="stat-value"><?php echo number_format($user_counts['total_users']); ?></div><div class="stat-note">All registered</div></div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-content"><h3>Learners</h3><div class="stat-value"><?php echo number_format($user_counts['learners']); ?></div><div class="stat-note">Students</div></div>
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-content"><h3>Supervisors</h3><div class="stat-value"><?php echo number_format($user_counts['supervisors']); ?></div><div class="stat-note">Oversight</div></div>
                    <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-content"><h3>Administrators</h3><div class="stat-value"><?php echo number_format($user_counts['admins']); ?></div><div class="stat-note">Management</div></div>
                    <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-content"><h3>Pending Requests</h3><div class="stat-value"><?php echo $skill_stat_cards[0]['value']; ?></div><div class="stat-note">Awaiting review</div></div>
                    <div class="stat-icon"><i class="fas fa-clock" style="color:var(--brand-500);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-content"><h3>Approved This Month</h3><div class="stat-value"><?php echo $skill_stat_cards[1]['value']; ?></div><div class="stat-note">Accepted</div></div>
                    <div class="stat-icon"><i class="fas fa-check-circle" style="color:var(--success-500);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-content"><h3>Rejected This Month</h3><div class="stat-value"><?php echo $skill_stat_cards[2]['value']; ?></div><div class="stat-note">Declined</div></div>
                    <div class="stat-icon"><i class="fas fa-times-circle" style="color:var(--danger-500);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-content"><h3>Total Processed</h3><div class="stat-value"><?php echo $skill_stat_cards[3]['value']; ?></div><div class="stat-note">All time</div></div>
                    <div class="stat-icon"><i class="fas fa-clipboard-list" style="color:var(--accent-500);"></i></div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h2>Weekly Resolved Help</h2>
                        <span class="stat-note">Last 7 weeks</span>
                    </div>
                    <div class="chart-plot chart-line">
                        <svg viewBox="0 0 500 200" style="width:100%;height:200px;display:block;">
                            <?php foreach ($weekly_resolved as $i => $w): ?>
                            <?php $x = ($i + 0.5) / count($weekly_resolved) * 500; ?>
                            <line x1="<?php echo $x; ?>" y1="0" x2="<?php echo $x; ?>" y2="200" style="stroke:var(--border)" stroke-dasharray="2,4" stroke-width="1"/>
                            <?php endforeach; ?>
                            <?php
                            $points = [];
                            $dot_positions = [];
                            foreach ($weekly_resolved as $i => $w) {
                                $x = ($i + 0.5) / count($weekly_resolved) * 500;
                                $y = 200 - max(10, round(($w['value'] / $max_weekly) * 170));
                                $points[] = "$x,$y";
                                $dot_positions[] = ['x' => $x, 'y' => $y];
                            }
                            ?>
                            <polyline points="<?php echo implode(' ', $points); ?>" fill="none" style="stroke:var(--brand-500)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
                            <?php foreach ($dot_positions as $dp): ?>
                            <circle cx="<?php echo $dp['x']; ?>" cy="<?php echo $dp['y']; ?>" r="4.5" style="fill:var(--brand-500)" stroke="#fff" stroke-width="2"/>
                            <?php endforeach; ?>
                        </svg>
                        <div class="chart-labels">
                            <?php foreach ($weekly_resolved as $w): ?>
                            <span><?php echo htmlspecialchars($w['label']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h2>Skill Distribution</h2>
                        <span class="stat-note">Learners per skill</span>
                    </div>
                    <div class="chart-bars">
                        <?php if (empty($skill_distribution)): ?>
                        <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px;">No skills assigned yet.</p>
                        <?php else: ?>
                        <?php foreach ($skill_distribution as $s): ?>
                        <?php $pct = max(5, round(($s['learner_count'] / $max_skill) * 100)); ?>
                        <div class="bar-item">
                            <span><?php echo htmlspecialchars($s['skill_name']); ?></span>
                            <div class="bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="charts-grid" style="grid-template-columns:1fr;">
                <div class="chart-card">
                    <div class="chart-header" style="display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <h2>Top Mentors by XP</h2>
                            <span class="stat-note">Highest scoring mentors</span>
                        </div>
                        <a href="leaderboard.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if (empty($top_mentors)): ?>
                    <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px;">No mentors found.</p>
                    <?php else: ?>
                    <?php foreach ($top_mentors as $i => $mentor): ?>
                    <?php
                        $rank = $i + 1;
                        $badge_labels = [1 => 'Top', 2 => '2nd', 3 => '3rd', 4 => '#4', 5 => '#5'];
                        $badge_label = $badge_labels[$rank] ?? "#$rank";
                        $name = trim($mentor['first_name'] . ' ' . $mentor['last_name']);
                    ?>
                    <div class="mentor-card" style="padding:18px 22px; margin-bottom:16px; background:#f8fafc;">
                        <div class="mentor-card-left">
                            <div class="mentor-avatar" style="background:<?php echo $rank <= 3 ? 'linear-gradient(135deg,var(--brand-500),var(--accent-500))' : 'var(--slate-300)'; ?>;"><?php echo $rank; ?></div>
                            <div>
                                <h2 style="font-size:18px; margin-bottom:6px;"><?php echo htmlspecialchars($name); ?></h2>
                                <div class="mentor-meta" style="gap:12px; font-size:13px; color:#6b7280;">
                                    <span><strong><?php echo number_format($mentor['score']); ?></strong> XP</span>
                                    <span>&bull;</span>
                                    <span><?php echo htmlspecialchars($mentor['specialization'] ?: 'General'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span class="mentor-badge" style="background:#e0e7ff; color:#4338ca; padding:8px 14px;"><?php echo $badge_label; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php';
