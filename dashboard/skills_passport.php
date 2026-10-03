<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
require_once dirname(__DIR__) . '/includes/badge_functions.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'learner') {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();

$stmt = $pdo->prepare('SELECT first_name, last_name, email, photo, bio FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT specialization, score, average_rating FROM learners WHERE learner_id = ?');
$stmt->execute([$user_id]);
$learner = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['specialization' => null, 'score' => 0, 'average_rating' => null];

$stmt = $pdo->prepare('SELECT ls.skill_id, s.skill_name, s.category, ls.skill_level AS level FROM learner_skills ls JOIN skills s ON ls.skill_id = s.skill_id WHERE ls.learner_id = ? ORDER BY s.category, s.skill_name');
$stmt->execute([$user_id]);
$all_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$skills_by_category = [];
foreach ($all_skills as $s) {
    $cat = $s['category'] ?: 'Other';
    $skills_by_category[$cat][] = $s;
}

$stmt = $pdo->prepare('SELECT b.badge_name, b.image, lb.awarded_at FROM learner_badges lb JOIN badges b ON lb.badge_id = b.badge_id WHERE lb.learner_id = ? ORDER BY lb.awarded_at');
$stmt->execute([$user_id]);
$earned_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM help_proposals WHERE mentor_id = ? AND proposal_status = ?');
$stmt->execute([$user_id, 'accepted']);
$help_sessions = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM learner_skills WHERE learner_id = ?');
$stmt->execute([$user_id]);
$total_skills = (int)$stmt->fetchColumn();

$level_info = getUserLevel($pdo, $user_id);

$skills_level_counts = ['beginner' => 0, 'intermediate' => 0, 'advanced' => 0, 'expert' => 0];
foreach ($all_skills as $s) {
    $lvl = strtolower($s['level']);
    if (isset($skills_level_counts[$lvl])) $skills_level_counts[$lvl]++;
}

$page_title = 'Skills Passport';
include dirname(__DIR__) . '/includes/header.php';
?>
<style>
  .passport-wrapper { max-width: 800px; margin: 0 auto; padding: 32px 24px; }
  .passport-actions { display: flex; gap: 12px; margin-bottom: 32px; justify-content: center; }
  .passport-actions .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; border: none; border-radius: var(--r-sm); font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background var(--t-fast), transform var(--t-fast); }
  .passport-actions .btn-primary { background: var(--brand-600); color: #fff; }
  .passport-actions .btn-primary:hover { background: var(--brand-700); transform: translateY(-1px); }
  .passport-actions .btn-secondary { background: var(--slate-100); color: var(--text); border: 1px solid var(--border); }
  .passport-actions .btn-secondary:hover { background: var(--slate-200); }

  .passport { background: #fff; border-radius: var(--r-xl); box-shadow: var(--shadow-lg); overflow: hidden; }
  .passport-header { background: linear-gradient(135deg, var(--brand-600), var(--accent-600)); color: #fff; padding: 48px 40px 36px; text-align: center; }
  .passport-avatar { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: grid; place-items: center; font-size: 32px; font-weight: 700; margin: 0 auto 12px; border: 3px solid rgba(255,255,255,0.4); }
  .passport-name { font-size: 28px; font-weight: 800; margin-bottom: 4px; }
  .passport-title { font-size: 14px; opacity: 0.85; margin-bottom: 16px; }
  .passport-level-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 8px 20px; border-radius: var(--r-pill); font-size: 13px; font-weight: 700; backdrop-filter: blur(4px); }

  .passport-body { padding: 36px 40px; }

  .passport-section { margin-bottom: 32px; }
  .passport-section:last-child { margin-bottom: 0; }
  .passport-section-title { font-size: 18px; font-weight: 700; color: var(--text); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid var(--brand-100); display: flex; align-items: center; gap: 10px; }
  .passport-section-title .icon { color: var(--brand-500); font-size: 16px; }

  .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
  .info-card { background: var(--slate-50); border-radius: var(--r-lg); padding: 16px; text-align: center; }
  .info-card .value { font-size: 24px; font-weight: 800; color: var(--brand-700); }
  .info-card .label { font-size: 12px; color: var(--text-muted); margin-top: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }

  .skill-category { margin-bottom: 20px; }
  .skill-category:last-child { margin-bottom: 0; }
  .skill-category-name { font-size: 14px; font-weight: 700; color: var(--brand-600); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
  .skill-row { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border-soft); }
  .skill-row:last-child { border-bottom: none; }
  .skill-row .name { flex: 1; font-size: 14px; font-weight: 600; color: var(--text); }
  .skill-row .bar-wrap { flex: 0 0 200px; height: 8px; border-radius: var(--r-pill); background: var(--slate-200); overflow: hidden; }
  .skill-row .bar-fill { height: 100%; border-radius: var(--r-pill); transition: width var(--t-base); }
  .skill-row .bar-fill.beginner { background: linear-gradient(90deg, var(--warning-500), var(--warning-700)); width: 55%; }
  .skill-row .bar-fill.intermediate { background: linear-gradient(90deg, var(--accent-500), var(--accent-600)); width: 70%; }
  .skill-row .bar-fill.advanced { background: linear-gradient(90deg, var(--brand-500), var(--brand-700)); width: 95%; }
  .skill-row .bar-fill.expert { background: linear-gradient(90deg, var(--success-500), var(--success-600)); width: 100%; }
  .skill-row .level-tag { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--r-pill); text-transform: capitalize; }
  .skill-row .level-tag.beginner { background: var(--warning-50); color: var(--warning-700); }
  .skill-row .level-tag.intermediate { background: #f3e8ff; color: var(--accent-600); }
  .skill-row .level-tag.advanced { background: var(--brand-50); color: var(--brand-700); }
  .skill-row .level-tag.expert { background: #d1fae5; color: var(--success-700); }

  .badges-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 16px; }
  .badge-card { text-align: center; padding: 16px 8px; background: var(--slate-50); border-radius: var(--r-lg); }
  .badge-card .badge-icon { font-size: 28px; color: var(--brand-500); margin-bottom: 8px; }
  .badge-card .badge-name { font-size: 12px; font-weight: 700; color: var(--text); }

  .passport-footer { text-align: center; padding: 24px 40px; border-top: 1px solid var(--border-soft); font-size: 12px; color: var(--text-muted); }

  @media print {
    body { background: #fff !important; }
    .sidebar, .app-main, .app-shell, .app-content, .topbar, .dashboard-layout { display: block !important; padding: 0 !important; margin: 0 !important; }
    .app-shell, .app-main, .app-content { display: block !important; }
    .passport-actions, .sidebar, .topbar, footer, .app-header, .dashboard-sidebar { display: none !important; }
    .passport-wrapper { max-width: 100%; padding: 0; }
    .passport { box-shadow: none; border: 1px solid #ddd; border-radius: 0; }
    .passport-header { padding: 36px 30px 28px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .passport-body { padding: 24px 30px; }
    .passport-level-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .skill-row .bar-fill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .info-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .skill-row { break-inside: avoid; }
    .passport-section { break-inside: avoid; }
    .passport { page-break-after: avoid; }
  }
</style>
<div class="app-shell">
    <?php $sidebar_active = 'profile'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>
    <div class="app-main">
        <?php $topbar_title = 'Skills Passport'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>
        <main class="app-content">
            <div class="passport-wrapper">
                <div class="passport-actions">
                    <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-download"></i> Download PDF</button>
                    <a href="profile.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Profile</a>
                </div>

                <div class="passport" id="passport">
                    <div class="passport-header">
                        <div class="passport-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                        <div class="passport-name"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></div>
                        <div class="passport-title"><?php echo htmlspecialchars($learner['specialization'] ?: 'General Studies'); ?></div>
                        <div class="passport-level-badge"><i class="fas fa-star"></i> Level <?php echo $level_info['level']; ?> — <?php echo htmlspecialchars($level_info['title']); ?> · <?php echo number_format($level_info['score']); ?> XP</div>
                    </div>

                    <div class="passport-body">
                        <div class="passport-section">
                            <div class="passport-section-title"><span class="icon fas fa-chart-simple"></span> Overview</div>
                            <div class="info-grid">
                                <div class="info-card"><div class="value"><?php echo $total_skills; ?></div><div class="label">Skills</div></div>
                                <div class="info-card"><div class="value"><?php echo number_format($learner['score']); ?></div><div class="label">Total XP</div></div>
                                <div class="info-card"><div class="value"><?php echo $help_sessions; ?></div><div class="label">Help Sessions</div></div>
                                <div class="info-card"><div class="value"><?php echo count($earned_badges); ?></div><div class="label">Badges</div></div>
                                <?php if ($learner['average_rating']): ?>
                                <div class="info-card"><div class="value"><?php echo number_format($learner['average_rating'], 1); ?></div><div class="label">Rating</div></div>
                                <?php endif; ?>
                                <div class="info-card"><div class="value"><?php echo $level_info['level']; ?></div><div class="label">Level</div></div>
                            </div>
                        </div>

                        <div class="passport-section">
                            <div class="passport-section-title"><span class="icon fas fa-bolt"></span> Skills (<?php echo $total_skills; ?>)</div>
                            <?php if (empty($all_skills)): ?>
                            <p style="color:var(--text-muted);font-size:14px;">No skills added yet.</p>
                            <?php else: ?>
                            <?php foreach ($skills_by_category as $category => $skills): ?>
                            <div class="skill-category">
                                <div class="skill-category-name"><?php echo htmlspecialchars($category); ?></div>
                                <?php foreach ($skills as $s): ?>
                                <?php $lc = strtolower($s['level']); ?>
                                <div class="skill-row">
                                    <span class="name"><?php echo htmlspecialchars($s['skill_name']); ?></span>
                                    <div class="bar-wrap"><div class="bar-fill <?php echo $lc; ?>"></div></div>
                                    <span class="level-tag <?php echo $lc; ?>"><?php echo htmlspecialchars($s['level']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="passport-section">
                            <div class="passport-section-title"><span class="icon fas fa-trophy"></span> Badges & Achievements</div>
                            <?php if (empty($earned_badges)): ?>
                            <p style="color:var(--text-muted);font-size:14px;">No badges earned yet.</p>
                            <?php else: ?>
                            <div class="badges-grid">
                                <?php foreach ($earned_badges as $b): ?>
                                <div class="badge-card">
                                    <div class="badge-icon <?php echo htmlspecialchars($b['image'] ? 'fas ' . $b['image'] : 'fas fa-award'); ?>"></div>
                                    <div class="badge-name"><?php echo htmlspecialchars($b['badge_name']); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="passport-footer">
                        Generated on <?php echo date('F j, Y'); ?> — Skills Passport · <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
