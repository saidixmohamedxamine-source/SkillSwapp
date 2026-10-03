<?php
/**
 * Register Page
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once '../database/connection.php';
session_start();

$page_title = 'Register';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';

    $name_parts = preg_split('/\s+/', $full_name, 2, PREG_SPLIT_NO_EMPTY);
    $first_name = $name_parts[0] ?? '';
    $last_name = $name_parts[1] ?? '';

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $pdo = $db->getPDO();

        $email_stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $email_stmt->execute([$email]);
        if ($email_stmt->fetch()) {
            $error = 'Email already exists.';
        }

        if (empty($error)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)');
            $ok = $insert_stmt->execute([$first_name, $last_name, $email, $hashed_password]);

            if ($ok) {
                $new_user_id = (int)$pdo->lastInsertId();

                $learner_stmt = $pdo->prepare('INSERT INTO learners (learner_id, specialization, score) VALUES (?, ?, 0)');
                $learner_stmt->execute([$new_user_id, $specialization ?: null]);

                $success = 'Account created successfully! Redirecting to login…';
                header('refresh:2;url=login.php');
            } else {
                $error = 'An error occurred while creating your account. Please try again.';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-icon"><i class="fas fa-user-plus"></i></div>
        <h1>Join <?php echo SITE_NAME; ?></h1>
        <p class="subtitle">Create your account and start sharing skills with peers.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($success); ?></span></div>
        <?php endif; ?>

        <style>
.password-wrapper{position:relative}
.password-wrapper input{padding-right:40px!important}
.password-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);cursor:pointer;color:#9ca3af;font-size:18px;background:none;border:none;outline:none;padding:6px;z-index:2}
.password-toggle:hover{color:#374151}
</style>
        <form method="POST" class="register-form">
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" placeholder="Jane Doe" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="student@ismo.edu" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="specialization">Specialization</label>
                <select id="specialization" name="specialization">
                    <option value="" <?php echo empty($specialization) ? 'selected' : ''; ?>>Sélectionnez votre spécialisation</option>
                    <option value="Infrastructure digitale" <?php echo ($specialization ?? '') === 'Infrastructure digitale' ? 'selected' : ''; ?>>Infrastructure digitale</option>
                    <option value="Développement digital" <?php echo ($specialization ?? '') === 'Développement digital' ? 'selected' : ''; ?>>Développement digital</option>
                    <option value="Intelligence Artificielle" <?php echo ($specialization ?? '') === 'Intelligence Artificielle' ? 'selected' : ''; ?>>Intelligence Artificielle</option>
                    <option value="Infographie" <?php echo ($specialization ?? '') === 'Infographie' ? 'selected' : ''; ?>>Infographie</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required>
                    <button type="button" class="password-toggle" tabindex="-1" aria-label="Afficher le mot de passe"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="button primary">Create account</button>
        </form>

        <p class="auth-link">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
    </div>
</div>

<script>
document.addEventListener("click",function(e){var btn=e.target.closest(".password-toggle");if(!btn)return;var input=btn.parentNode.querySelector("input");if(!input)return;var isPwd=input.getAttribute("type")==="password";input.setAttribute("type",isPwd?"text":"password");var icon=btn.querySelector("i");if(icon)icon.className=isPwd?"fas fa-eye-slash":"fas fa-eye"});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
