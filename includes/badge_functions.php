<?php

/**
 * Determine the user's role based on table membership.
 */
function getUserRole(PDO $pdo, int $user_id): string
{
    $stmt = $pdo->prepare('SELECT 1 FROM administrators WHERE admin_id = ?');
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn()) return 'administrator';

    $stmt = $pdo->prepare('SELECT 1 FROM supervisors WHERE supervisor_id = ?');
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn()) return 'supervisor';

    $stmt = $pdo->prepare('SELECT 1 FROM learners WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn()) return 'learner';

    return 'learner';
}

/**
 * Check role-based badge conditions for a user and award any new qualifying badges.
 */
function checkAndAwardBadges(PDO $pdo, int $user_id): void
{
    require_once __DIR__ . '/notification_functions.php';

    $role = getUserRole($pdo, $user_id);

    // Get accepted help proposals count (used for both learner and mentor badges)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM help_proposals WHERE mentor_id = ? AND proposal_status = 'accepted'");
    $stmt->execute([$user_id]);
    $accepted_count = (int)$stmt->fetchColumn();

    // Determine which badge roles to check
    $badge_roles = [];
    $badge_roles[] = 'learner';
    $badge_roles[] = 'mentor';

    // Build placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($badge_roles), '?'));
    $stmt = $pdo->prepare("SELECT badge_id, badge_name, required_points, role FROM badges WHERE role IN ($placeholders)");
    $stmt->execute($badge_roles);
    $badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($badges as $badge) {
        $qualifies = ($accepted_count >= (int)$badge['required_points']);

        if (!$qualifies) continue;

        // Check if already awarded
        $stmt = $pdo->prepare('SELECT 1 FROM learner_badges WHERE learner_id = ? AND badge_id = ?');
        $stmt->execute([$user_id, $badge['badge_id']]);
        if ($stmt->fetchColumn()) continue;

        // Find a valid supervisor
        $stmt = $pdo->query('SELECT supervisor_id FROM supervisors LIMIT 1');
        $supervisor_id = $stmt->fetchColumn();
        if (!$supervisor_id) {
            $supervisor_id = 1;
            $stmt = $pdo->prepare('INSERT IGNORE INTO supervisors (supervisor_id, filiere) VALUES (?, ?)');
            $stmt->execute([$supervisor_id, 'System']);
        }

        $stmt = $pdo->prepare('INSERT IGNORE INTO learner_badges (learner_id, badge_id, supervisor_id) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $badge['badge_id'], $supervisor_id]);

        sendNotification($pdo, $user_id, 'badge_awarded', 'Congratulations! You earned the ' . $badge['badge_name'] . ' badge.', SITE_URL . 'dashboard/badges_levels.php');
    }
}

/**
 * Calculate the current level (1–10) and title for a learner based on their score.
 */
function getUserLevel(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare('SELECT score FROM learners WHERE learner_id = ?');
    $stmt->execute([$user_id]);
    $score = (int)$stmt->fetchColumn();

    $levels = [
        1  => ['xp' => 0,    'title' => 'Newcomer'],
        2  => ['xp' => 100,  'title' => 'Learner'],
        3  => ['xp' => 250,  'title' => 'Contributor'],
        4  => ['xp' => 500,  'title' => 'Helper'],
        5  => ['xp' => 800,  'title' => 'Mentor'],
        6  => ['xp' => 1200, 'title' => 'Expert'],
        7  => ['xp' => 1700, 'title' => 'Expert Helper'],
        8  => ['xp' => 2300, 'title' => 'Master'],
        9  => ['xp' => 3000, 'title' => 'Legend'],
        10 => ['xp' => 4000, 'title' => 'Champion'],
    ];

    $rank = 1;
    foreach ($levels as $r => $lv) {
        if ($score >= $lv['xp']) {
            $rank = $r;
        }
    }

    return ['level' => $rank, 'title' => $levels[$rank]['title'], 'score' => $score];
}

/**
 * Get the icon class for a badge by name
 */
function getBadgeIcon(string $badge_name): string
{
    $map = [
        'Beginner'          => 'fas fa-seedling',
        'Scholar'           => 'fas fa-book',
        'Knowledge Seeker'  => 'fas fa-lightbulb',
        'Dedicated Learner' => 'fas fa-graduation-cap',
        'Grand Scholar'     => 'fas fa-star',
        'Assistant'         => 'fas fa-handshake',
        'Guide'             => 'fas fa-compass',
        'Expert Mentor'     => 'fas fa-shield-halved',
        'Master Mentor'     => 'fas fa-crown',
        'Founder'           => 'fas fa-crown',
    ];
    return $map[$badge_name] ?? 'fas fa-medal';
}

function abbreviateSpecialization(?string $specialization): string
{
    $map = [
        'Développement digital'    => 'DEV',
        'Infrastructure digitale'   => 'INFRA',
        'Intelligence Artificielle' => 'IA',
        'Infographie'               => 'GRAPH',
    ];
    return $map[$specialization] ?? ($specialization ?: '—');
}

function getRatingColor(float $rating): string
{
    $rounded = round($rating);
    if ($rounded <= 1) return '#ef4444';
    if ($rounded == 2) return '#f97316';
    if ($rounded == 3) return '#eab308';
    if ($rounded == 4) return '#84cc16';
    return '#22c55e';
}
