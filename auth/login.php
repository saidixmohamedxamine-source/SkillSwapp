<?php
/**
 * Login Page
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../database/connection.php';
session_start();

$page_title = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $pdo = $db->getPDO();
        $stmt = $pdo->prepare('SELECT user_id, email, password, first_name, last_name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
                // Check if account is suspended
                $stmt = $pdo->prepare('SELECT suspended FROM users WHERE user_id = ?');
                $stmt->execute([$user['user_id']]);
                if ((int)$stmt->fetchColumn() === 1) {
                    $error = 'Your account has been suspended. Contact an administrator.';
                } else {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                    $_SESSION['user_email'] = $user['email'];

                    $admin_check = $pdo->prepare('SELECT admin_id FROM administrators WHERE admin_id = ?');
                    $admin_check->execute([$user['user_id']]);
                    if ($admin_check->fetch()) {
                        $_SESSION['role'] = 'administrator';
                        $a_stmt = $pdo->prepare('SELECT access_level FROM administrators WHERE admin_id = ?');
                        $a_stmt->execute([$user['user_id']]);
                        $_SESSION['access_level'] = (int)$a_stmt->fetchColumn();
                    } else {
                        $super_check = $pdo->prepare('SELECT supervisor_id FROM supervisors WHERE supervisor_id = ?');
                        $super_check->execute([$user['user_id']]);
                        if ($super_check->fetch()) {
                            $_SESSION['role'] = 'supervisor';
                        } else {
                            $_SESSION['role'] = 'learner';
                        }
                    }

                    header('Location: ../dashboard/index.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-icon"><i class="fas fa-arrow-right-to-bracket"></i></div>
        <h1>Welcome back</h1>
        <p class="subtitle">Sign in to your <?php echo SITE_NAME; ?> account.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <style>
.password-wrapper{position:relative}
.password-wrapper input{padding-right:40px!important}
.password-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);cursor:pointer;color:#9ca3af;font-size:18px;background:none;border:none;outline:none;padding:6px;z-index:2}
.password-toggle:hover{color:#374151}
</style>
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="student@ismo.edu" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <button type="button" class="password-toggle" tabindex="-1" aria-label="Afficher le mot de passe"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <div class="form-row">
                <label class="checkbox-group">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="button primary">Sign in</button>
        </form>

        <p class="auth-link">
            Don't have an account? <a href="register.php">Create one</a>
        </p>
    </div>
</div>

<script>
document.addEventListener("click",function(e){var btn=e.target.closest(".password-toggle");if(!btn)return;var input=btn.parentNode.querySelector("input");if(!input)return;var isPwd=input.getAttribute("type")==="password";input.setAttribute("type",isPwd?"text":"password");var icon=btn.querySelector("i");if(icon)icon.className=isPwd?"fas fa-eye-slash":"fas fa-eye"});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
