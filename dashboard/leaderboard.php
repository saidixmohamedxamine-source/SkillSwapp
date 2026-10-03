<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
require_once dirname(__DIR__) . '/includes/badge_functions.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();

$is_admin_super = in_array($_SESSION['role'] ?? '', ['supervisor', 'administrator']);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$limit = $is_admin_super ? 50 : 10;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare('
    SELECT u.user_id, u.first_name, u.last_name, l.score, l.specialization, l.average_rating
    FROM learners l
    JOIN users u ON l.learner_id = u.user_id
    WHERE u.suspended = 0
    ORDER BY l.score DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$per_page, $offset]);
$top_learners = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($is_admin_super) {
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM learners l JOIN users u ON l.learner_id = u.user_id WHERE u.suspended = 0");
    $total = (int)$count_stmt->fetchColumn();
    $total_to_show = min($limit, $total);
    $total_pages = (int)ceil($total_to_show / $per_page);
} else {
    $total_pages = 1;
}

$page_title = 'Leaderboard';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'leaderboard'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Leaderboard'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Rankings</span>
                    <h1 class="page-title">Top <span class="accent">Learners</span></h1>
                    <p class="page-subtitle">Highest scoring learners by XP.</p>
                </div>
            </div>

            <div class="charts-grid" style="grid-template-columns:1fr;">
                <div class="chart-card">
                    <?php if (empty($top_learners)): ?>
                    <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px;">No learners found.</p>
                    <?php else: ?>
                    <?php foreach ($top_learners as $i => $learner):
                        $rank = $offset + $i + 1;
                        $badge_labels = [1 => 'Top', 2 => '2nd', 3 => '3rd'];
                        $badge_label = $badge_labels[$rank] ?? "#$rank";
                        $name = trim($learner['first_name'] . ' ' . $learner['last_name']);
                        $level_info = getUserLevel($pdo, (int)$learner['user_id']);
                        $full = floor($learner['average_rating']);
                        $fraction = $learner['average_rating'] - $full;
                        $hasHalf = $fraction >= 0.25 && $full < 5;
                    ?>
                    <div class="mentor-card" style="padding:18px 22px;margin-bottom:16px;background:#f8fafc;">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                            <div class="mentor-card-left">
                                <div class="mentor-avatar" style="background:<?php echo $rank <= 3 ? 'linear-gradient(135deg,var(--brand-500),var(--accent-500))' : 'var(--slate-300)'; ?>;"><?php echo $rank; ?></div>
                                <div>
                                    <h2 style="font-size:18px;margin-bottom:4px;"><?php echo htmlspecialchars($name); ?></h2>
                                    <div class="mentor-meta" style="gap:8px;font-size:13px;color:#6b7280;flex-wrap:wrap;">
                                        <span><strong><?php echo number_format($learner['score']); ?></strong> XP</span>
                                        <span>&bull;</span>
                                        <span><?php echo htmlspecialchars($learner['specialization'] ?: 'General'); ?></span>
                                        <?php if ($learner['average_rating'] > 0): ?>
                                        <span>&bull;</span>
                                        <span style="display:inline-flex;align-items:center;gap:2px;">
                                            <?php $starColor = getRatingColor($learner['average_rating']); ?>
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <?php if ($s <= $full): ?>
                                                    <span class="fas fa-star" style="color:<?php echo $starColor; ?>;font-size:11px;"></span>
                                                <?php elseif ($hasHalf && $s == $full + 1): ?>
                                                    <span class="fas fa-star-half-alt" style="color:<?php echo $starColor; ?>;font-size:11px;"></span>
                                                <?php else: ?>
                                                    <span class="fa-regular fa-star" style="color:#d1d5db;font-size:11px;"></span>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            <span style="margin-left:2px;"><?php echo number_format($learner['average_rating'], 1); ?></span>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align:right;display:flex;align-items:center;gap:10px;">
                                <?php if ($level_info): ?>
                                <span class="level-badge" style="font-size:12px;padding:6px 10px;">Level <?php echo $level_info['level']; ?></span>
                                <?php endif; ?>
                                <span class="mentor-badge" style="background:#e0e7ff;color:#4338ca;padding:8px 14px;"><?php echo $badge_label; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php else: ?>
                        <button class="pagination-btn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
                        <?php endif; ?>
                        <span>Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">Next <i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                        <button class="pagination-btn" disabled>Next <i class="fas fa-chevron-right"></i></button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
