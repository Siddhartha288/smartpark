<?php
/**
 * SmartPark - Login (login.php)
 * POST logic runs BEFORE header.php outputs HTML so header() redirects work.
 */

// Load helpers only (no HTML yet)
require_once 'includes/functions.php';

// Redirect logged-in users away
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin.php' : 'dashboard.php'));
    exit;
}

$formError = '';
$formEmail = '';

// Demo users - password is: Admin@1234
// Using direct comparison for demo; production would use password_verify() with DB hash
$demoUsers = [
    'admin@smartpark.com' => ['id'=>1,'name'=>'Admin User',   'role'=>'admin',  'password'=>'Admin@1234'],
    'jane@example.com'    => ['id'=>2,'name'=>'Jane Smith',   'role'=>'driver', 'password'=>'Admin@1234'],
    'mark@example.com'    => ['id'=>3,'name'=>'Mark Johnson', 'role'=>'driver', 'password'=>'Admin@1234'],
    'lucy@example.com'    => ['id'=>4,'name'=>'Lucy Chen',    'role'=>'driver', 'password'=>'Admin@1234'],
];

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
            $user = $demoUsers[$email] ?? null;

            // Demo: direct string compare. Production: password_verify($password, $user['hash'])
            if ($user && $password === $user['password']) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $email;
                $_SESSION['role']    = $user['role'];
                $_SESSION['flash_success'] = 'Welcome back, ' . $user['name'] . '!';
                header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php'));
                exit;
            } else {
                $formError = 'Incorrect email or password. Please try again.';
            }
        }
    }
}

// NOW output HTML
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

  <div class="alert alert-info" style="margin-bottom:1rem;">
    <span>&#x1F4A1;</span>
    <div>
      <strong>Demo Credentials:</strong><br>
      Admin: admin@smartpark.com &nbsp;/&nbsp; Admin@1234<br>
      Driver: jane@example.com &nbsp;/&nbsp; Admin@1234
    </div>
  </div>

  <?php if ($formError): ?>
    <div class="alert alert-danger">
      <span>&#x274C;</span><span><?= h($formError) ?></span>
    </div>
  <?php endif; ?>

  <?= flash('success') ?>
  <?= flash('warning') ?>

  <div class="card">
    <div class="card-header"><h3>&#x1F510; Log In</h3></div>

    <form method="POST" action="login.php" id="loginForm" novalidate>
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
        <div class="hint">Forgot your password? <a href="contact.php">Contact us</a> for help.</div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Log In &rarr;</button>
    </form>
  </div>

  <p class="text-center text-muted mt-2">
    Don't have an account? <a href="register.php"><strong>Create one free &rarr;</strong></a>
  </p>
</div>

<?php require 'includes/footer.php'; ?>
