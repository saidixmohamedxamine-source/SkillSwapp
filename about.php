<?php
/**
 * About Page
 */

require_once __DIR__ . '/includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$page_title = 'About';

include __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'about'; include __DIR__ . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'About'; include __DIR__ . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <section class="about-hero">
                <div class="about-hero-content">
                    <span class="about-eyebrow"><?php echo SITE_SUBTITLE; ?></span>
                    <h1>About <?php echo SITE_NAME; ?></h1>
                    <p>
                        A student skill-sharing platform built to make learning feel more connected,
                        practical, and community-driven.
                    </p>
                    <div class="about-actions">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="auth/register.php" class="button primary">Create an account</a>
                            <a href="auth/login.php" class="button secondary">Sign in</a>
                        <?php else: ?>
                            <a href="dashboard/index.php" class="button primary">Open dashboard</a>
                            <a href="dashboard/search.php" class="button secondary">Find mentors</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="about-hero-panel">
                    <div class="about-icon">
                        <i class="fas fa-people-arrows"></i>
                    </div>
                    <h2>Learn together, grow faster.</h2>
                    <p>
                        Students can share what they know, request help, find mentors, and build
                        confidence through peer-to-peer learning.
                    </p>
                </div>
            </section>

            <section class="about-highlights" aria-label="Platform highlights">
                <article class="highlight-card">
                    <div class="highlight-icon"><i class="fas fa-lightbulb"></i></div>
                    <h2>Share Your Skills</h2>
                    <p>Showcase your strengths and help classmates learn through clear, practical support.</p>
                </article>

                <article class="highlight-card">
                    <div class="highlight-icon"><i class="fas fa-user-graduate"></i></div>
                    <h2>Find Mentors</h2>
                    <p>Connect with students who already understand the topics you want to improve.</p>
                </article>

                <article class="highlight-card">
                    <div class="highlight-icon"><i class="fas fa-hands-helping"></i></div>
                    <h2>Build Community</h2>
                    <p>Create meaningful learning relationships around shared goals and real progress.</p>
                </article>
            </section>

            <section class="about-story">
                <article class="mission-card">
                    <span class="section-kicker">Our Mission</span>
                    <h2>Make peer learning easy to start and easy to trust.</h2>
                    <p>
                        Our mission is to create a vibrant community where students can share their
                        knowledge, learn from one another, and grow together. We believe that
                        peer-to-peer learning is one of the most effective ways to develop new skills
                        and build meaningful connections.
                    </p>
                </article>

                <article class="offer-card">
                    <span class="section-kicker">What We Offer</span>
                    <h2>Everything students need to connect.</h2>
                    <ul class="offer-list">
                        <li><i class="fas fa-check"></i><span>A platform to showcase and share your skills.</span></li>
                        <li><i class="fas fa-check"></i><span>Access to mentors and experts in different fields.</span></li>
                        <li><i class="fas fa-check"></i><span>Tools to request help and find learning partners.</span></li>
                        <li><i class="fas fa-check"></i><span>A supportive community dedicated to mutual growth.</span></li>
                    </ul>
                </article>
            </section>

            <section class="about-steps">
                <div class="steps-header">
                    <span class="section-kicker">How It Works</span>
                    <h2>Start with one skill, one request, or one helpful reply.</h2>
                </div>

                <div class="steps-grid">
                    <article class="step-card">
                        <span>01</span>
                        <h3>Create your profile</h3>
                        <p>Add your interests, skills, and the topics you want to learn.</p>
                    </article>

                    <article class="step-card">
                        <span>02</span>
                        <h3>Share or request help</h3>
                        <p>Offer what you know or ask the community for support.</p>
                    </article>

                    <article class="step-card">
                        <span>03</span>
                        <h3>Connect with students</h3>
                        <p>Find the right people, exchange knowledge, and keep improving.</p>
                    </article>
                </div>
            </section>

            <section class="about-cta">
                <div>
                    <span class="section-kicker">Ready to begin?</span>
                    <h2>Turn your classroom knowledge into a shared advantage.</h2>
                </div>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="auth/register.php" class="button secondary">Join now</a>
                <?php else: ?>
                    <a href="dashboard/index.php" class="button secondary">Open dashboard</a>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
