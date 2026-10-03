<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = $db->getPDO();

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

$direct_error = '';
$direct_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direct_mentor_id'])) {
    $mentor_id = (int)$_POST['direct_mentor_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $direct_error = 'Title is required.';
    } elseif (empty($description)) {
        $direct_error = 'Description is required.';
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM help_proposals hp JOIN help_requests hr ON hp.request_id = hr.request_id WHERE hr.learner_id = ? AND hp.mentor_id = ? AND hp.proposal_status = ?');
        $stmt->execute([$user_id, $mentor_id, 'pending']);
        if ($stmt->fetch()) {
            $direct_error = 'You already have a pending request with this mentor.';
        } else {
            try {
                $learner_id = getLearnerId($pdo, $user_id);
                $stmt = $pdo->prepare('INSERT INTO help_requests (learner_id, title, description, request_status) VALUES (?, ?, ?, ?)');
                $stmt->execute([$learner_id, $title, $description, 'pending']);
                $request_id = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO help_proposals (request_id, mentor_id, proposal_status) VALUES (?, ?, ?)');
                $stmt->execute([$request_id, $mentor_id, 'pending']);

                $direct_success = 'Help request sent! Redirecting...';
                header('Refresh: 1; url=my_help_requests.php');
            } catch (Exception $e) {
                $direct_error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

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

$skills_list = $pdo->query('SELECT skill_name FROM skills ORDER BY skill_name')->fetchAll(PDO::FETCH_COLUMN);
if (empty($skills_list)) {
    $skills_list = ['React', 'Python', 'SQL', 'JavaScript'];
}

$filiere_list = $pdo->query("SELECT DISTINCT filiere FROM supervisors WHERE filiere IS NOT NULL ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);
$specialization_list = $pdo->query("SELECT DISTINCT specialization FROM learners WHERE specialization IS NOT NULL ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN);

$selected_role           = $_GET['role'] ?? '';
$selected_filiere        = $_GET['filiere'] ?? '';
$selected_skill          = $_GET['skill'] ?? '';
$selected_specialization = $_GET['specialization'] ?? '';
$selected_availability   = $_GET['availability'] ?? '';
$sort_xp                 = $_GET['sort_xp'] ?? 'desc';

$users = [];

$sql = "
    SELECT u.user_id, u.first_name, u.last_name, u.is_profile_public,
           CASE
               WHEN EXISTS (SELECT 1 FROM administrators a WHERE a.admin_id = u.user_id) THEN 'administrator'
               WHEN EXISTS (SELECT 1 FROM supervisors s WHERE s.supervisor_id = u.user_id) THEN 'supervisor'
               ELSE 'learner'
           END AS role,
           l.average_rating, l.available,
           l.specialization, l.score
    FROM users u
    LEFT JOIN learners l ON u.user_id = l.learner_id
";
$where = [];
$params = [];

if ($selected_role) {
    switch ($selected_role) {
        case 'administrator':
            $where[] = "EXISTS (SELECT 1 FROM administrators a WHERE a.admin_id = u.user_id)";
            break;
        case 'supervisor':
            $where[] = "EXISTS (SELECT 1 FROM supervisors s WHERE s.supervisor_id = u.user_id)";
            if ($selected_filiere) {
                $where[] = "EXISTS (SELECT 1 FROM supervisors s2 WHERE s2.supervisor_id = u.user_id AND s2.filiere = ?)";
                $params[] = $selected_filiere;
            }
            break;
        case 'mentor':

        case 'learner':
            $where[] = "EXISTS (SELECT 1 FROM learners l2 WHERE l2.learner_id = u.user_id)";
            $where[] = "NOT EXISTS (SELECT 1 FROM supervisors s2 WHERE s2.supervisor_id = u.user_id)";
            $where[] = "NOT EXISTS (SELECT 1 FROM administrators a2 WHERE a2.admin_id = u.user_id)";
            break;
    }
}

// Skill filter
if ($selected_skill) {
    $where[] = "EXISTS (SELECT 1 FROM learner_skills ls2 JOIN skills s2 ON ls2.skill_id = s2.skill_id WHERE ls2.learner_id = u.user_id AND s2.skill_name = ?)";
    $params[] = $selected_skill;
}

// Availability filter
if ($selected_availability !== '') {
    $where[] = "l.available = ?";
    $params[] = (int)$selected_availability;
}

// Specialization filter
if ($selected_specialization) {
    $where[] = "l.specialization = ?";
    $params[] = $selected_specialization;
}

// Hide suspended users
$where[] = "u.suspended = 0";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= in_array($selected_role, ['mentor', 'learner']) ? " ORDER BY COALESCE(l.score, 0) " . ($sort_xp === 'asc' ? 'ASC' : 'DESC') : " ORDER BY u.first_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $skills_stmt = $pdo->prepare('
        SELECT s.skill_name
        FROM learner_skills ls
        JOIN skills s ON ls.skill_id = s.skill_id
        WHERE ls.learner_id = ?
    ');
    $skills_stmt->execute([$row['user_id']]);
    $skills = $skills_stmt->fetchAll(PDO::FETCH_COLUMN);

    $user_sessions = 0;
    $sess_stmt = $pdo->prepare('SELECT COUNT(*) FROM help_proposals WHERE mentor_id = ?');
    $sess_stmt->execute([$row['user_id']]);
    $user_sessions = (int)$sess_stmt->fetchColumn();

    $role = $row['role'] ?? 'learner';
    $role_labels = [
        'administrator' => 'Administrator',
        'supervisor' => 'Supervisor',
        'learner' => 'Student',
    ];

    $users[] = [
        'user_id'           => $row['user_id'],
        'name'              => trim($row['first_name'] . ' ' . $row['last_name']),
        'initials'          => strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)),
        'rating'            => $row['average_rating'] ?? 0,
        'reviews'           => $user_sessions,
        'skills'            => $skills,
        'location'          => $row['specialization'] ?: 'N/A',
        'sessions'          => $user_sessions,
        'availability'      => $row['available'] ? 'Available Now' : 'Not Available',
        'available_raw'     => (bool)$row['available'],
        'role'              => $role_labels[$role] ?? 'Student',
        'role_key'          => $role,
        'is_profile_public' => (bool)$row['is_profile_public'],
    ];
}

$page_title = 'Search';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'search'; include dirname(__DIR__) . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Find People'; $hide_topbar_search = true; include dirname(__DIR__) . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Community</span>
                    <h1 class="page-title">Find <span class="accent">people</span></h1>
                    <p class="page-subtitle">Search by name, skill, or role to find the right peer.</p>
                </div>
                <div class="search-input-wrapper">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" class="search-input" placeholder="Find by name">
                </div>
            </div>

            <?php if ($direct_error): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;"><?php echo htmlspecialchars($direct_error); ?></div>
            <?php endif; ?>
            <?php if ($direct_success): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;"><?php echo htmlspecialchars($direct_success); ?></div>
            <?php endif; ?>

            <div class="search-grid">
                <aside class="search-sidebar">
                    <div class="filter-card">
                        <div class="filter-header">
                            <i class="fas fa-filter"></i>
                            <h2>Filters</h2>
                        </div>

                        <form method="GET">
                            <div class="filter-section">
                                <h3>Role</h3>
                                <select name="role" class="form-select" onchange="this.form.submit()">
                                    <option value="">All roles</option>
                                    <option value="administrator" <?php echo $selected_role === 'administrator' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="supervisor" <?php echo $selected_role === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                    <option value="learner" <?php echo $selected_role === 'learner' ? 'selected' : ''; ?>>Student</option>
                                </select>
                            </div>

                            <?php if ($selected_role === 'supervisor'): ?>
                            <div class="filter-section">
                                <h3>Filiere</h3>
                                <select name="filiere" class="form-select" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <?php foreach ($filiere_list as $f): ?>
                                        <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $selected_filiere === $f ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($selected_role === 'learner'): ?>
                            <div class="filter-section">
                                <h3>Sort by XP</h3>
                                <select name="sort_xp" class="form-select" onchange="this.form.submit()">
                                    <option value="desc" <?php echo $sort_xp === 'desc' ? 'selected' : ''; ?>>Highest first</option>
                                    <option value="asc" <?php echo $sort_xp === 'asc' ? 'selected' : ''; ?>>Lowest first</option>
                                </select>
                            </div>
                            <div class="filter-section">
                                <h3>Skill</h3>
                                <select name="skill" class="form-select" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <?php foreach ($skills_list as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $selected_skill === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-section">
                                <h3>Specialization</h3>
                                <select name="specialization" class="form-select" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <?php foreach ($specialization_list as $sp): ?>
                                        <option value="<?php echo htmlspecialchars($sp); ?>" <?php echo $selected_specialization === $sp ? 'selected' : ''; ?>><?php echo htmlspecialchars($sp); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-section">
                                <h3>Availability</h3>
                                <select name="availability" class="form-select" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <option value="1" <?php echo $selected_availability === '1' ? 'selected' : ''; ?>>Available</option>
                                    <option value="0" <?php echo $selected_availability === '0' ? 'selected' : ''; ?>>Not Available</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <a href="search.php" class="primary-button clear-filters-button">Clear Filters</a>
                        </form>
                    </div>
                </aside>

                <section>
                    <div class="results-header">
                        <div class="results-count"><?php echo count($users); ?> <?php echo count($users) === 1 ? 'person' : 'people'; ?> found</div>
                    </div>

                    <div class="search-results">
                        <?php foreach ($users as $user):
                            $full = floor($user['rating']);
                            $fraction = $user['rating'] - $full;
                            $hasHalf = $fraction >= 0.25 && $full < 5;
                        ?>
                            <div class="person-card">
                                <div class="person-card-left">
                                    <div class="person-avatar">
                                        <?php echo htmlspecialchars($user['initials']); ?>
                                    </div>
                                    <div>
                                        <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                                        <div class="person-rating">
                                            <?php if ($user['rating'] > 0): ?>
                                                <?php $starColor = getRatingColor($user['rating']); ?>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $full): ?>
                                                        <span class="fas fa-star" style="color:<?php echo $starColor; ?>;"></span>
                                                    <?php elseif ($hasHalf && $i == $full + 1): ?>
                                                        <span class="fas fa-star-half-alt" style="color:<?php echo $starColor; ?>;"></span>
                                                    <?php else: ?>
                                                        <span class="fa-regular fa-star" style="color:#d1d5db;"></span>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                                <?php echo number_format($user['rating'], 1); ?> (<?php echo $user['reviews']; ?> reviews)
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);font-size:13px;">No reviews yet</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="person-tags">
                                            <?php foreach ($user['skills'] as $skill): ?>
                                                <span><?php echo htmlspecialchars($skill); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="person-meta">
                                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['location']); ?></span>
                                            <span><i class="fas fa-user-graduate"></i> <?php echo $user['sessions']; ?> sessions</span>
                                            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($user['availability']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($user['user_id'] !== $user_id): ?>
                                <div class="person-card-right">
                                    <span class="person-badge"><?php echo htmlspecialchars($user['role']); ?></span>
                                    <?php if ($user['role_key'] === 'learner' && ($_SESSION['role'] ?? '') === 'learner'): ?>
                                    <button type="button" class="primary-button request-help-button"
                                        data-user-id="<?php echo $user['user_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                        <?php echo $user['available_raw'] ? '' : 'disabled style="opacity:0.5;cursor:not-allowed;pointer-events:none;"'; ?>><?php echo $user['available_raw'] ? 'Request Help' : 'Not Available'; ?></button>
                                    <?php endif; ?>
                                    <?php if ($user['is_profile_public'] || in_array($_SESSION['role'] ?? '', ['administrator', 'supervisor'])): ?>
                                    <a href="users_profile.php?id=<?php echo $user['user_id']; ?>" class="secondary-button view-profile-button">View Profile</a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<div id="requestModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-content" style="background:#fff;border-radius:12px;padding:32px;max-width:520px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="margin:0;font-size:20px;">Request help from <span id="modalMentorName" style="color:var(--primary-600);"></span></h2>
            <button type="button" id="modalCloseBtn" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="direct_mentor_id" id="modalMentorId" value="">
            <div class="form-field full-width">
                <label for="modal-title">Title <span style="color:#ef4444;">*</span></label>
                <input id="modal-title" name="title" class="form-input" type="text" placeholder="What do you need help with?" required>
            </div>
            <div class="form-field full-width">
                <label for="modal-description">Description <span style="color:#ef4444;">*</span></label>
                <textarea id="modal-description" name="description" class="form-textarea" placeholder="Describe your request in detail..." required style="min-height:100px;"></textarea>
            </div>
            <div class="form-actions-row" style="margin-top:24px;">
                <button type="submit" class="button primary"><i class="fas fa-paper-plane"></i> Send request</button>
                <button type="button" id="modalCancelBtn" class="button secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    /* --- Request Help modal --- */
    var requestModal = document.getElementById('requestModal');
    var mentorName = document.getElementById('modalMentorName');
    var mentorId = document.getElementById('modalMentorId');
    var closeBtn = document.getElementById('modalCloseBtn');
    var cancelBtn = document.getElementById('modalCancelBtn');

    document.querySelectorAll('.request-help-button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            mentorName.textContent = this.getAttribute('data-name');
            mentorId.value = this.getAttribute('data-user-id');
            requestModal.style.display = 'flex';
        });
    });

    function closeRequestModal() {
        requestModal.style.display = 'none';
    }

    closeBtn.addEventListener('click', closeRequestModal);
    cancelBtn.addEventListener('click', closeRequestModal);
    requestModal.addEventListener('click', function(e) {
        if (e.target === requestModal) closeRequestModal();
    });

    /* --- Live name search filtering --- */
    var searchInput = document.querySelector('.search-input-wrapper .search-input');
    var personCards = document.querySelectorAll('.person-card');
    var resultsCount = document.querySelector('.results-count');
    var totalPeople = personCards.length;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            var visibleCount = 0;

            personCards.forEach(function(card) {
                var nameEl = card.querySelector('h2');
                if (!nameEl) return;
                var name = nameEl.textContent.toLowerCase();
                if (!query || name.indexOf(query) !== -1) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (resultsCount) {
                resultsCount.textContent = visibleCount + ' ' + (visibleCount === 1 ? 'person' : 'people') + ' found';
            }
        });
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php';

