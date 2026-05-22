<?php
require_once 'includes/functions.php';
requireLogin('login.php');

$cancelMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $cancelMsg = 'error:Invalid request. Please try again.';
    } else {
        $cancelledId = intval($_POST['booking_id']);
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                UPDATE bookings SET status = 'cancelled'
                WHERE booking_id = ? AND user_id = ? AND status IN ('confirmed','pending')
            ");
            $stmt->execute([$cancelledId, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $cancelMsg = 'success:Booking #' . $cancelledId . ' has been cancelled successfully.';
            } else {
                $cancelMsg = 'error:Booking not found or already cancelled.';
            }
        } catch (PDOException $e) {
            error_log('[SmartPark cancel] ' . $e->getMessage());
            $cancelMsg = 'error:Could not cancel booking. Please try again.';
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!empty($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
        $newName  = trim($_POST['name']  ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        $newPhone = trim($_POST['phone'] ?? '');
        if ($newName && $newEmail && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                $db   = getDB();
                $stmt = $db->prepare("
                    UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?
                ");
                $stmt->execute([$newName, $newEmail, $newPhone ?: null, $_SESSION['user_id']]);
                $_SESSION['name']  = $newName;
                $_SESSION['email'] = $newEmail;
                $_SESSION['flash_success'] = 'Profile updated successfully.';
                header('Location: dashboard.php?tab=profile');
                exit;
            } catch (PDOException $e) {
                error_log('[SmartPark profile] ' . $e->getMessage());
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!empty($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd     = $_POST['password']          ?? '';
        $confirmPwd = $_POST['confirm_password']  ?? '';
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($currentPwd, $user['password_hash'])) {
                $cancelMsg = 'error:Current password is incorrect.';
            } elseif (strlen($newPwd) < 8) {
                $cancelMsg = 'error:New password must be at least 8 characters.';
            } elseif ($newPwd !== $confirmPwd) {
                $cancelMsg = 'error:New passwords do not match.';
            } else {
                $hash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                   ->execute([$hash, $_SESSION['user_id']]);
                $_SESSION['flash_success'] = 'Password changed successfully.';
                header('Location: dashboard.php?tab=profile');
                exit;
            }
        } catch (PDOException $e) {
            error_log('[SmartPark password] ' . $e->getMessage());
            $cancelMsg = 'error:Could not update password. Please try again.';
        }
    }
}

// Fetch bookings from DB
try {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT b.booking_id AS id, c.name AS park, c.suburb,
               b.spot_number AS spot, b.start_time AS start,
               b.end_time AS end, b.status, b.total_amount AS total
        FROM bookings b
        JOIN car_parks c ON c.park_id = b.park_id
        WHERE b.user_id = ?
          AND b.status IN ('confirmed','pending')
        ORDER BY b.start_time ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $upcomingBookings = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT b.booking_id AS id, c.name AS park, c.suburb,
               b.spot_number AS spot, b.start_time AS start,
               b.end_time AS end, b.status, b.total_amount AS total
        FROM bookings b
        JOIN car_parks c ON c.park_id = b.park_id
        WHERE b.user_id = ?
          AND b.status IN ('completed','cancelled')
        ORDER BY b.start_time DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pastBookings = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT name, email, phone FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userProfile = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[SmartPark dashboard] ' . $e->getMessage());
    $upcomingBookings = [];
    $pastBookings     = [];
    $userProfile      = [
        'name'  => $_SESSION['name']  ?? '',
        'email' => $_SESSION['email'] ?? '',
        'phone' => '',
    ];
}

$totalSpent    = array_sum(array_column(array_merge($upcomingBookings, $pastBookings), 'total'));
$totalBookings = count($upcomingBookings) + count($pastBookings);
$activeTab     = $_GET['tab'] ?? 'upcoming';

$pageTitle = 'My Dashboard';
$activeNav = 'dash';
require 'includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>&#x1F464; My Dashboard</h1>
    <p>Welcome back, <?= h($_SESSION['name'] ?? 'User') ?>! Manage your reservations and profile here.</p>
  </div>
</div>

<div class="container section-sm">

  <?php
  if ($cancelMsg) {
      [$type, $msg] = explode(':', $cancelMsg, 2);
      echo '<div class="alert alert-'.$type.'" data-auto-dismiss>
              <span></span><span>'.h($msg).'</span>
            </div>';
  }
  echo flash('success');
  echo flash('warning');
  echo flash('danger');
  ?>

  <!-- Stats -->
  <div class="grid-4 mb-2">
    <div class="stat-card">
      <div class="stat-icon stat-icon-blue">&#x1F4C5;</div>
      <div>
        <div class="stat-value"><?= count($upcomingBookings) ?></div>
        <div class="stat-label">Upcoming</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-green">&#x2705;</div>
      <div>
        <div class="stat-value"><?= count($pastBookings) ?></div>
        <div class="stat-label">Past Bookings</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-orange">&#x1F17F;</div>
      <div>
        <div class="stat-value"><?= $totalBookings ?></div>
        <div class="stat-label">Total Bookings</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-red">&#x1F4B2;</div>
      <div>
        <div class="stat-value" style="font-size:1.3rem;">
          $<?= number_format($totalSpent, 0) ?>
        </div>
        <div class="stat-label">Total Spent</div>
      </div>
    </div>
  </div>

  <?php if ($totalSpent > 0): ?>
    <div class="card mb-2" style="padding:1rem 1.5rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
        <div>
          <span style="font-size:0.85rem;color:var(--text-muted);">Total spent</span>
          <div style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;color:var(--primary);">
            $<?= number_format($totalSpent, 2) ?>
          </div>
        </div>
        <div style="text-align:right;">
          <span style="font-size:0.85rem;color:var(--text-muted);">Average per booking</span>
          <div style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;color:var(--primary);">
            $<?= number_format($totalSpent / max($totalBookings, 1), 2) ?>
          </div>
        </div>
        <a href="search.php" class="btn btn-primary btn-sm">Book Again</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div data-tabs>
    <div class="tabs">
      <button class="tab-btn <?= $activeTab==='upcoming' ? 'active':'' ?>" data-tab="upcoming">
        &#x1F4C5; Upcoming (<?= count($upcomingBookings) ?>)
      </button>
      <button class="tab-btn <?= $activeTab==='history' ? 'active':'' ?>" data-tab="history">
        &#x1F552; History (<?= count($pastBookings) ?>)
      </button>
      <button class="tab-btn <?= $activeTab==='profile' ? 'active':'' ?>" data-tab="profile">
        &#x1F464; My Profile
      </button>
    </div>

    <!-- UPCOMING -->
    <div class="tab-content <?= $activeTab==='upcoming' ? 'active':'' ?>" data-tab-content="upcoming">
      <?php if (empty($upcomingBookings)): ?>
        <div class="card text-center" style="padding:3rem;">
          <div style="font-size:3rem;margin-bottom:1rem;">&#x1F4C5;</div>
          <h3>No upcoming bookings</h3>
          <p class="text-muted mt-1">You have no active reservations.</p>
          <a href="search.php" class="btn btn-primary mt-2">Find Parking Now</a>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:1rem;">
          <?php foreach ($upcomingBookings as $b): ?>
            <div class="card" style="border-left:4px solid var(--success);">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;">
                <div>
                  <h4><?= h($b['park']) ?></h4>
                  <p class="text-muted" style="font-size:0.85rem;margin-top:0.2rem;">
                    &#x1F4CD; <?= h($b['suburb']) ?> &nbsp;&middot;&nbsp;
                    &#x1F17F; Spot <?= h($b['spot']) ?> &nbsp;&middot;&nbsp;
                    Booking #<?= $b['id'] ?>
                  </p>
                </div>
                <?php
                  $startTs = strtotime($b['start']);
                  $soon    = ($startTs !== false && $startTs <= (time() + 86400));
                ?>
                <?php if ($soon): ?>
                  <span class="badge badge-warning">Starting Soon</span>
                <?php else: ?>
                  <span class="badge badge-success">Confirmed</span>
                <?php endif; ?>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;
                          margin-top:0.85rem;padding:0.85rem;
                          background:var(--bg);border-radius:var(--radius);">
                <div>
                  <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">Start</div>
                  <div style="font-weight:600;"><?= date('D d M Y', strtotime($b['start'])) ?></div>
                  <div class="text-muted"><?= date('g:i A', strtotime($b['start'])) ?></div>
                </div>
                <div>
                  <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">End</div>
                  <div style="font-weight:600;"><?= date('D d M Y', strtotime($b['end'])) ?></div>
                  <div class="text-muted"><?= date('g:i A', strtotime($b['end'])) ?></div>
                </div>
                <div>
                  <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">Total</div>
                  <div style="font-weight:700;font-size:1.1rem;color:var(--primary);">
                    $<?= number_format($b['total'], 2) ?>
                  </div>
                </div>
              </div>

              <div style="display:flex;gap:0.5rem;margin-top:0.85rem;">
                <form method="POST" action="dashboard.php"
                      onsubmit="return confirm('Are you sure you want to cancel this booking?');"
                      style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm">
                    &#x2715; Cancel
                  </button>
                </form>
                <a href="reserve.php" class="btn btn-dark btn-sm">&#x1F504; Rebook</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="mt-2">
        <a href="search.php" class="btn btn-primary">+ New Reservation</a>
      </div>
    </div>

    <!-- HISTORY -->
    <div class="tab-content <?= $activeTab==='history' ? 'active':'' ?>" data-tab-content="history">
      <?php if (empty($pastBookings)): ?>
        <div class="card text-center" style="padding:3rem;">
          <div style="font-size:3rem;margin-bottom:1rem;">&#x1F552;</div>
          <h3>No booking history yet</h3>
          <p class="text-muted mt-1">Your completed and cancelled bookings will appear here.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th><th>Car Park</th><th>Spot</th>
                <th>Date</th><th>Duration</th><th>Total</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pastBookings as $b):
                $startTs  = strtotime($b['start']);
                $endTs    = strtotime($b['end']);
                $durSecs  = ($startTs && $endTs) ? ($endTs - $startTs) : 0;
                $durHours = floor($durSecs / 3600);
                $durMins  = round(($durSecs % 3600) / 60);
                $durStr   = $durHours . 'h' . ($durMins ? ' ' . $durMins . 'm' : '');
              ?>
                <tr>
                  <td>#<?= $b['id'] ?></td>
                  <td>
                    <strong><?= h($b['park']) ?></strong><br>
                    <span class="text-muted" style="font-size:0.8rem;"><?= h($b['suburb']) ?></span>
                  </td>
                  <td><?= h($b['spot']) ?></td>
                  <td>
                    <?= $startTs ? date('d M Y', $startTs) : '' ?><br>
                    <span class="text-muted"><?= $startTs ? date('g:i A', $startTs) : '' ?></span>
                  </td>
                  <td><?= $durStr ?></td>
                  <td><strong>$<?= number_format($b['total'], 2) ?></strong></td>
                  <td>
                    <?php if ($b['status'] === 'cancelled'): ?>
                      <span class="badge badge-danger">Cancelled</span>
                    <?php elseif ($b['status'] === 'completed'): ?>
                      <span class="badge badge-success">Completed</span>
                    <?php else: ?>
                      <span class="badge badge-muted"><?= ucfirst(h($b['status'])) ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- PROFILE -->
    <div class="tab-content <?= $activeTab==='profile' ? 'active':'' ?>" data-tab-content="profile">
      <div class="grid-2" style="align-items:start;gap:1.5rem;">

        <div class="card">
          <div class="card-header"><h3>&#x1F464; Profile Information</h3></div>
          <form method="POST" action="dashboard.php">
            <?= csrfField() ?>
            <div class="form-group">
              <label for="prof_name">Full Name</label>
              <input type="text" id="prof_name" name="name" maxlength="100"
                     value="<?= h($userProfile['name'] ?? $_SESSION['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="prof_email">Email Address</label>
              <input type="email" id="prof_email" name="email"
                     value="<?= h($userProfile['email'] ?? $_SESSION['email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="prof_phone">Phone <span class="text-muted">(optional)</span></label>
              <input type="tel" id="prof_phone" name="phone"
                     placeholder="+61 4xx xxx xxx"
                     value="<?= h($userProfile['phone'] ?? '') ?>">
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary btn-block">
              Save Changes
            </button>
          </form>
        </div>

        <div class="card">
          <div class="card-header"><h3>&#x1F510; Change Password</h3></div>
          <form method="POST" action="dashboard.php">
            <?= csrfField() ?>
            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" autocomplete="current-password">
            </div>
            <div class="form-group">
              <label>New Password <span class="text-muted">(min 8 chars)</span></label>
              <input type="password" name="password" id="newPwd"
                     autocomplete="new-password"
                     oninput="updatePwdStrength2(this.value)">
              <div class="pwd-strength">
                <div class="pwd-bar"><div class="pwd-fill" id="pwdBar2"></div></div>
                <div class="pwd-label" id="pwdLabel2"></div>
              </div>
            </div>
            <div class="form-group">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" autocomplete="new-password">
            </div>
            <button type="submit" name="change_password" class="btn btn-dark btn-block">
              Update Password
            </button>
          </form>

          <div style="border-top:1px solid var(--border);margin-top:1.25rem;padding-top:1rem;">
            <h4 style="color:var(--danger);font-size:0.95rem;">&#x26A0; Data &amp; Privacy</h4>
            <p class="text-muted" style="font-size:0.85rem;margin-top:0.4rem;">
              You can request deletion of your account and all associated personal data.
            </p>
            <a href="contact.php" class="btn btn-danger btn-sm mt-1">
              Request Account Deletion
            </a>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<script>
function updatePwdStrength2(pwd) {
    var score = 0;
    if (pwd.length >= 8)          score++;
    if (/[A-Z]/.test(pwd))        score++;
    if (/[0-9]/.test(pwd))        score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;
    var bar    = document.getElementById('pwdBar2');
    var lbl    = document.getElementById('pwdLabel2');
    var colors = ['','#e74c3c','#f39c12','#27ae60','#1a6b3a'];
    var labels = ['','Weak','Fair','Good','Strong'];
    if (bar) { bar.style.width = (score * 25) + '%'; bar.style.background = colors[score] || ''; }
    if (lbl) lbl.textContent = score ? 'Strength: ' + labels[score] : '';
}
</script>

<?php require 'includes/footer.php'; ?>