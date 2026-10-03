<?php
/**
 * Home Page
 */

require_once __DIR__ . '/includes/config.php';
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/index.php');
    exit;
}

$page_title = 'Home';

$title_words   = ['THE', 'SKILL', 'SHARING', 'REVOLUTION', 'FOR', 'STUDENTS'];
$hero_labels   = [
    ['icon' => 'fa-bolt',           'label' => 'Share Your Skills'],
    ['icon' => 'fa-user-graduate',  'label' => 'Find Peer Mentors'],
    ['icon' => 'fa-award',          'label' => 'Earn Real Badges'],
];
include __DIR__ . '/includes/header.php';
?>

<main class="home-page">
    <header class="hero-header">
        <div class="hero-brand" aria-label="<?php echo htmlspecialchars(SITE_NAME); ?>">
            <span class="brand-mark">IS</span>
            <span class="brand-name"><?php echo SITE_NAME; ?></span>
        </div>

        <div class="hero-header-actions">
            <a href="auth/login.php" class="hero-link">SIGN IN</a>
            <a href="auth/register.php" class="hero-cta">
                GET STARTED <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </header>

    <section class="hero-section">
        <h1 class="hero-title">
            <?php foreach ($title_words as $i => $word): ?>
                <span class="hero-word" style="--i: <?php echo $i; ?>;"><?php echo $word; ?></span>
            <?php endforeach; ?>
        </h1>

        <p class="hero-subtitle">
            We empower students with peer-driven learning to turn knowledge into real, shareable skills.
        </p>

        <div class="hero-labels">
            <?php foreach ($hero_labels as $i => $item): ?>
                <div class="hero-label" style="--i: <?php echo $i; ?>;">
                    <i class="fas <?php echo $item['icon']; ?>"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="hero-actions">
            <a href="auth/register.php" class="hero-cta hero-cta-lg">
                GET STARTED <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>
</main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
