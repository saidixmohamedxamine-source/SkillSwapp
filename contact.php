<?php
/**
 * Contact Page
 */

require_once __DIR__ . '/includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$page_title = 'Contact';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message_text = isset($_POST['message']) ? trim($_POST['message']) : '';

    if (empty($name) || empty($email) || empty($subject) || empty($message_text)) {
        $error = 'Please fill in all fields.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $message = 'Thank you for your message! We will get back to you soon.';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php $sidebar_active = 'contact'; include __DIR__ . '/includes/dashboard_sidebar.php'; ?>

    <div class="app-main">
        <?php $topbar_title = 'Contact'; include __DIR__ . '/includes/dashboard_topbar.php'; ?>

        <main class="app-content">
            <header class="page-header">
                <div>
                    <h1 class="page-title">Contact us</h1>
                    <p class="page-subtitle">Questions, feedback, or partnership ideas? We'll get back within a day.</p>
                </div>
            </header>

            <div class="contact-grid">
                <aside class="contact-info">
                    <h2>Reach us directly</h2>
                    <div class="contact-info-list">
                        <div class="contact-info-item">
                            <span class="contact-info-icon"><i class="fas fa-envelope"></i></span>
                            <div>
                                <h3>Email</h3>
                                <p>hello@ismo-skillswap.com</p>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <span class="contact-info-icon"><i class="fas fa-location-dot"></i></span>
                            <div>
                                <h3>Campus</h3>
                                <p>ISMO Student Center, Building B</p>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <span class="contact-info-icon"><i class="fas fa-clock"></i></span>
                            <div>
                                <h3>Hours</h3>
                                <p>Mon – Fri, 9:00 – 18:00</p>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <span class="contact-info-icon"><i class="fas fa-circle-question"></i></span>
                            <div>
                                <h3>Help center</h3>
                                <p>Browse FAQs and guides in your dashboard.</p>
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="contact-form">
                    <h2>Send us a message</h2>
                    <p class="contact-form-sub">Tell us a bit about what you need and we'll point you in the right direction.</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><span><?php echo htmlspecialchars($error); ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($message); ?></span></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="name">Your name</label>
                                <input type="text" id="name" name="name" placeholder="Jane Doe" required>
                            </div>
                            <div class="form-field">
                                <label for="email">Your email</label>
                                <input type="email" id="email" name="email" placeholder="jane@example.com" required>
                            </div>
                            <div class="form-field full-width">
                                <label for="subject">Subject</label>
                                <input type="text" id="subject" name="subject" placeholder="How can we help?" required>
                            </div>
                            <div class="form-field full-width">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" rows="6" placeholder="Write your message…" required></textarea>
                            </div>
                        </div>
                        <button type="submit" class="button primary">
                            <i class="fas fa-paper-plane"></i> Send message
                        </button>
                    </form>
                </section>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
