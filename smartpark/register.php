<?php
/**
 * SmartPark - Register (register.php)
 * ICT312 Advanced Web Information Systems
 */
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$formData   = ['name'=>'','email'=>'','phone'=>''];
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $formErrors['general'] = 'Invalid form submission. Please refresh and try again.';

    } elseif (!checkRateLimit('register', 5, 600)) {
        $formErrors['general'] = 'Too many registration attempts. Please wait 10 minutes.';

    } else {
        $name     = trim($_POST['name']             ?? '');
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $phone    = trim($_POST['phone']            ?? '');
        $password = $_POST['password']              ?? '';
        $confirm  = $_POST['confirm_password']      ?? '';
        $captcha  = !empty($_POST['captcha_confirm']);

        $formData = compact('name', 'email', 'phone');

        // Validate
        if (strlen($name) < 2)
            $formErrors['name'] = 'Please enter your full name (at least 2 characters).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $formErrors['email'] = 'Please enter a valid email address.';
        if (strlen($password) < 8)
            $formErrors['password'] = 'Password must be at least 8 characters.';
        elseif (!preg_match('/[A-Z]/', $password))
            $formErrors['password'] = 'Password must contain at least one uppercase letter.';
        elseif (!preg_match('/[0-9]/', $password))
            $formErrors['password'] = 'Password must contain at least one number.';
        if ($password !== $confirm)
            $formErrors['confirm_password'] = 'Passwords do not match.';
        if (!$captcha)
            $formErrors['captcha'] = 'Please tick the checkbox to confirm you are not a robot.';

        if (empty($formErrors)) {
            try {
                $db = getDB();

                // Check if email already exists
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $formErrors['email'] = 'An account with this email already exists. Try logging in instead.';
                } else {
                    // Hash password
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                    // Insert user
                    $stmt = $db->prepare("
                        INSERT INTO users (name, email, password_hash, phone, role, created_at)
                        VALUES (?, ?, ?, ?, 'driver', NOW())
                    ");
                    $stmt->execute([$name, $email, $hash, $phone ?: null]);
                    $newUserId = $db->lastInsertId();

                    // Log registration
                    $db->prepare("
                        INSERT INTO audit_log (user_id, event_type, description, ip_address)
                        VALUES (?, 'register', 'New user registration', ?)
                    ")->execute([$newUserId, $_SERVER['REMOTE_ADDR'] ?? '']);

                    // Log them in
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['name']    = $name;
                    $_SESSION['email']   = $email;
                    $_SESSION['role']    = 'driver';
                    $_SESSION['flash_success'] = 'Welcome to SmartPark, ' . $name . '! Your account is ready.';
                    header('Location: dashboard.php');
                    exit;
                }

            } catch (PDOException $e) {
                error_log('[SmartPark Register] ' . $e->getMessage());
                $formErrors['general'] = 'A system error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Create Account';
$activeNav = '';
require 'includes/header.php';
?>

<div style="background:linear-gradient(135deg,var(--primary) 0%,#0d2440 100%);padding:3rem 0;">
  <div class="container-sm text-center">
    <div style="font-size:2.5rem;margin-bottom:0.5rem;">&#x1F17F;</div>
    <h1 style="color:white;font-size:2rem;">Create Your Account</h1>
    <p style="color:rgba(255,255,255,0.75);margin-top:0.4rem;">
      Free to join &mdash; find and reserve parking across Sydney
    </p>
  </div>
</div>

<div class="container-sm" style="padding-top:2rem;padding-bottom:3rem;">

  <?php if (!empty($formErrors['general'])): ?>
    <div class="alert alert-danger">
      <span>&#x274C;</span><span><?= h($formErrors['general']) ?></span>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <h3>Account Details</h3>
      <p class="text-muted mt-1">Fields marked * are required</p>
    </div>

    <form method="POST" action="register.php" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label for="name">Full Name *</label>
        <input type="text" id="name" name="name"
               autocomplete="name"
               maxlength="100"
               placeholder="e.g. Jane Smith"
               value="<?= h($formData['name']) ?>"
               class="<?= !empty($formErrors['name']) ? 'error' : '' ?>"
               required>
        <?php if (!empty($formErrors['name'])): ?>
          <div class="field-error show"><?= h($formErrors['name']) ?></div>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email"
               autocomplete="email"
               maxlength="150"
               placeholder="you@example.com"
               value="<?= h($formData['email']) ?>"
               class="<?= !empty($formErrors['email']) ? 'error' : '' ?>"
               required>
        <?php if (!empty($formErrors['email'])): ?>
          <div class="field-error show"><?= h($formErrors['email']) ?></div>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="phone">
          Phone Number
          <span class="text-muted">(optional)</span>
        </label>
        <input type="tel" id="phone" name="phone"
               autocomplete="tel"
               maxlength="20"
               placeholder="+61 4xx xxx xxx"
               value="<?= h($formData['phone']) ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">
            Password *
            <span class="text-muted">(min 8 chars)</span>
          </label>
          <input type="password" id="password" name="password"
                 autocomplete="new-password"
                 class="<?= !empty($formErrors['password']) ? 'error' : '' ?>"
                 oninput="updatePwdStrength(this.value)"
                 required>
          <div class="pwd-strength">
            <div class="pwd-bar">
              <div class="pwd-fill" id="pwdBar"></div>
            </div>
            <div class="pwd-label" id="pwdLabel"></div>
          </div>
          <?php if (!empty($formErrors['password'])): ?>
            <div class="field-error show"><?= h($formErrors['password']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm Password *</label>
          <input type="password" id="confirm_password" name="confirm_password"
                 autocomplete="new-password"
                 class="<?= !empty($formErrors['confirm_password']) ? 'error' : '' ?>"
                 required>
          <?php if (!empty($formErrors['confirm_password'])): ?>
            <div class="field-error show"><?= h($formErrors['confirm_password']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="alert alert-info" style="font-size:0.82rem;">
        <span>&#x1F512;</span>
        <span>
          Your data is protected under the Australian Privacy Act 1988.
          We only collect what is needed to provide this service.
        </span>
      </div>

      <div class="captcha-box"
           style="<?= !empty($formErrors['captcha']) ? 'border-color:var(--danger);' : '' ?>">
        <input type="checkbox"
               class="captcha-check"
               id="captcha_confirm"
               name="captcha_confirm">
        <label for="captcha_confirm" class="captcha-label">
          I am not a robot
        </label>
        <div class="captcha-logo">&#x1F6E1; Verify</div>
      </div>
      <?php if (!empty($formErrors['captcha'])): ?>
        <div class="field-error show" style="margin-bottom:0.75rem;">
          <?= h($formErrors['captcha']) ?>
        </div>
      <?php endif; ?>

      <button type="submit"
              class="btn btn-primary btn-block btn-lg"
              id="submitBtn"
              disabled>
        Create My Account
      </button>
    </form>
  </div>

  <p class="text-center text-muted mt-2">
    Already have an account?
    <a href="login.php"><strong>Log In &rarr;</strong></a>
  </p>
</div>

<script>
document.getElementById('captcha_confirm').addEventListener('change', function () {
    document.getElementById('submitBtn').disabled = !this.checked;
});

function updatePwdStrength(pwd) {
    var score  = 0;
    if (pwd.length >= 8)          score++;
    if (/[A-Z]/.test(pwd))        score++;
    if (/[0-9]/.test(pwd))        score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;
    var colors = ['','#e74c3c','#f39c12','#27ae60','#1a6b3a'];
    var labels = ['','Weak','Fair','Good','Strong'];
    var bar    = document.getElementById('pwdBar');
    var lbl    = document.getElementById('pwdLabel');
    bar.style.width      = (score * 25) + '%';
    bar.style.background = colors[score] || '';
    lbl.textContent      = score ? 'Strength: ' + labels[score] : '';
}
</script>

<?php require 'includes/footer.php'; ?>