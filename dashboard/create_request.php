<?php
/**
 * Create Request Page
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') === 'administrator') {
    header('Location: statistics.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();
$error_message = '';
$success_message = '';

function getLearnerId(PDO $pdo, int $user_id): int {
    $stmt = $pdo->prepare('SELECT learner_id FROM learners WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        return (int)$row['learner_id'];
    }

    $insert = $pdo->prepare('INSERT INTO learners (learner_id, specialization, score) VALUES (?, NULL, 0)');
    $insert->execute([$user_id]);
    return $user_id;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $error_message = 'Title is required.';
    } elseif (empty($description)) {
        $error_message = 'Description is required.';
    } else {
        try {
            $learner_id = getLearnerId($pdo, $user_id);
            $stmt = $pdo->prepare(
                'INSERT INTO help_requests (learner_id, title, description, request_status) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$learner_id, $title, $description, 'pending']);
            $request_id = (int)$pdo->lastInsertId();
            header('Location: select_mentors.php?request_id=' . $request_id);
            exit;
        } catch (Exception $e) {
            $error_message = 'Error creating request: ' . $e->getMessage();
        }
    }
}

$page_title = 'Create Request';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'create_request'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'New Request'; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">New request</span>
                    <h1 class="page-title">Create a <span class="accent">help request</span></h1>
                    <p class="page-subtitle">Post a clear request so other students can connect and help you.</p>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;">
                    <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="form-card">
                <div class="form-field full-width">
                    <label for="request-title">Title <span style="color: #ef4444;">*</span></label>
                    <input id="request-title" name="title" class="form-input" type="text" placeholder="Enter a clear request title" required />
                </div>

                <div class="form-field full-width">
                    <label for="request-description">Description <span style="color: #ef4444;">*</span></label>
                    <textarea id="request-description" name="description" class="form-textarea" placeholder="Provide detailed information about what you need help with..." required></textarea>
                </div>

                <div class="form-actions-row">
                    <button type="submit" class="button primary"><i class="fas fa-paper-plane"></i> Submit request</button>
                    <a href="my_help_requests.php" class="button secondary">Cancel</a>
                </div>
            </form>
        </main>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
