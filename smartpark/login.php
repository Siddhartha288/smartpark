<?php
/**
 * SmartPark - Login (login.php)
 * ICT312 Advanced Web Information Systems
 */
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin.php' : 'dashboard.php'));
    exit;
}

$formError = '';
$formEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $formError = 'Invalid form submission. Please refresh and try again.';

    } elseif (!checkRateLimit('login', 5, 300)) {
        $formError = 'Too many login attempts. Please wait 5 minutes and try again.';

    } else {
        $email    = trim(strtolower($_POST['email']    ?? ''));
        $password =              ($_POST['password'] ?? '');
        $formEmail = $email;

        if (!$email || !$password) {
            $formError = 'Please enter both your email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $formError = 'Please enter a valid email address.';
        } else {
            try {
                $db   = getDB();
                $stmt = $db->prepare("
                    SELECT user_id, name, email, password_hash, role
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Update last login
                    $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
                       ->execute([$user['user_id']]);

                    // Log event
                    $db->prepare("
                        INSERT INTO audit_log (user_id, event_type, description, ip_address)
                        VALUES (?, 'login', 'User login successful', ?)
                    ")->execute([$user['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['email']   = $user['email'];
                    $_SESSION['role']    = $user['role'];
                    $_SESSION['flash_success'] = 'Welcome back, ' . $user['name'] . '!';
                    header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php'));
                    exit;

                } else {
    // Log failed attempt
    $db->prepare("
        INSERT INTO audit_log (user_id, event_type, description, ip_address)
        VALUES (NULL, 'login_failed', ?, ?)
    ")->execute(['Failed login for: ' . $email, $_SERVER['REMOTE_ADDR'] ?? '']);

    $formError = 'Incorrect email or password. Please try again.';
}

            } catch (PDOException $e) {
                error_log('[SmartPark Login] ' . $e->getMessage());
                $formError = 'A system error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Log In';
$activeNav = '';
require 'includes/header.php';
?>

<div style="background:linear-gradient(135deg,var(--primary) 0%,#0d2440 100%);padding:3rem 0;">
  <div class="container-sm text-center">
    <div style="font-size:2.5rem;margin-bottom:0.5rem;">&#x1F17F;</div>
    <h1 style="color:white;font-size:2rem;">Welcome Back</h1>
    <p style="color:rgba(255,255,255,0.75);margin-top:0.4rem;">Log in to your SmartPark account</p>
  </div>
</div>

<div class="container-sm" style="padding-top:2rem;padding-bottom:3rem;">

  <?php if ($formError): ?>
    <div class="alert alert-danger">
      <span>&#x274C;</span><span><?= h($formError) ?></span>
    </div>
  <?php endif; ?>

  <?= flash('success') ?>
  <?= flash('warning') ?>

  <div class="card">
    <div class="card-header"><h3>&#x1F510; Log In</h3></div>

    <form method="POST" action="login.php" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               autocomplete="email"
               placeholder="you@example.com"
               value="<?= h($formEmail) ?>"
               required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password"
               placeholder="Your password"
               required>
        <div class="hint">
          Forgot your password? <a href="contact.php">Contact us</a> for help.
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">
        Log In &rarr;
      </button>
    </form>
  </div>

  <p class="text-center text-muted mt-2">
    Don't have an account?
    <a href="register.php"><strong>Create one free &rarr;</strong></a>
  </p>
</div>

<?php require 'includes/footer.php'; ?>