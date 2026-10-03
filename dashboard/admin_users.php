<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$access_level = (int)($_SESSION['access_level'] ?? 0);
if ($_SESSION['role'] !== 'administrator' || $access_level < 10) {
    header('Location: index.php');
    exit;
}

$pdo = $db->getPDO();
$current_user_id = (int)$_SESSION['user_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Toggle suspend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $target_id = (int)$_POST['toggle_user_id'];
    $new_status = (int)$_POST['new_status'];
    if ($target_id !== $current_user_id) {
        $stmt = $pdo->prepare('UPDATE users SET suspended = ? WHERE user_id = ?');
        $stmt->execute([$new_status, $target_id]);
    }
    header('Location: admin_users.php?page=' . $page);
    exit;
}

$total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $pdo->prepare('
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.suspended,
           CASE WHEN a.admin_id IS NOT NULL THEN "administrator"
                WHEN s.supervisor_id IS NOT NULL THEN "supervisor"
                ELSE "learner" END AS role
    FROM users u
    LEFT JOIN administrators a ON u.user_id = a.admin_id
    LEFT JOIN supervisors s ON u.user_id = s.supervisor_id
    ORDER BY u.suspended DESC, u.first_name ASC
    LIMIT ? OFFSET ?
');
$stmt->execute([$per_page, $offset]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'User Management';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'admin_users'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'User Management'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Administration</span>
                    <h1 class="page-title">User <span class="accent">Management</span></h1>
                    <p class="page-subtitle">Manage all registered accounts. Suspended users cannot log in.</p>
                </div>
            </div>

            <div class="chart-card" style="overflow-x:auto;">
                <?php if (empty($users)): ?>
                <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px;">No users found.</p>
                <?php else: ?>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border-soft);text-align:left;">
                            <th style="padding:12px 16px;">Name</th>
                            <th style="padding:12px 16px;">Email</th>
                            <th style="padding:12px 16px;">Role</th>
                            <th style="padding:12px 16px;">Status</th>
                            <th style="padding:12px 16px;text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:12px 16px;font-weight:500;"><?php echo htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])); ?></td>
                            <td style="padding:12px 16px;color:var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td style="padding:12px 16px;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;background:<?php echo $u['role'] === 'administrator' ? 'var(--danger-100)' : ($u['role'] === 'supervisor' ? 'var(--warning-100)' : 'var(--brand-100)'); ?>;color:<?php echo $u['role'] === 'administrator' ? 'var(--danger-700)' : ($u['role'] === 'supervisor' ? 'var(--warning-700)' : 'var(--brand-700)'); ?>;">
                                    <?php echo htmlspecialchars($u['role']); ?>
                                </span>
                            </td>
                            <td style="padding:12px 16px;">
                                <?php if ($u['suspended']): ?>
                                <span style="color:var(--danger-600);font-weight:500;"><i class="fas fa-ban"></i> Suspended</span>
                                <?php else: ?>
                                <span style="color:var(--success-600);font-weight:500;"><i class="fas fa-check-circle"></i> Active</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 16px;text-align:center;">
                                <?php if ((int)$u['user_id'] !== $current_user_id): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $u['suspended'] ? 'Reinstate' : 'Suspend'; ?> <?php echo htmlspecialchars($u['first_name']); ?>?');">
                                    <input type="hidden" name="toggle_user_id" value="<?php echo $u['user_id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $u['suspended'] ? 0 : 1; ?>">
                                    <button type="submit" class="button small" style="<?php echo $u['suspended'] ? 'background:var(--success-600);color:#fff;border-color:var(--success-600);' : 'background:var(--danger-600);color:#fff;border-color:var(--danger-600);'; ?>">
                                        <i class="fas fa-<?php echo $u['suspended'] ? 'check' : 'ban'; ?>"></i>
                                        <?php echo $u['suspended'] ? 'Reinstate' : 'Suspend'; ?>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="color:var(--text-muted);font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

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
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
