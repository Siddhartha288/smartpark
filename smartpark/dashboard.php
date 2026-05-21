<?php
/**
 * SmartPark - Driver Dashboard (dashboard.php)
 */
require_once 'includes/functions.php';
requireLogin('login.php');  // redirect before any HTML

// Handle cancel booking POST
$cancelMsg = '';
$cancelledId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $cancelMsg = 'error:Invalid request. Please try again.';
    } else {
        $cancelledId = intval($_POST['booking_id']);
        // Production: UPDATE bookings SET status='cancelled' WHERE booking_id=? AND user_id=?
        $cancelMsg = 'success:Booking #' . $cancelledId . ' has been cancelled successfully.';
    }
}

// Demo data
$upcomingBookings = [
    ['id'=>3,'park'=>'Bondi Junction Carpark',    'suburb'=>'Bondi Junction','spot'=>'A-03',
     'start'=>date('Y-m-d H:i', strtotime('+1 day')),
     'end'  =>date('Y-m-d H:i', strtotime('+1 day +2 hours')),'status'=>'confirmed','total'=>14.00],
    ['id'=>5,'park'=>'Chatswood Station Parking',  'suburb'=>'Chatswood',     'spot'=>'B-02',
     'start'=>date('Y-m-d H:i', strtotime('+2 days')),
     'end'  =>date('Y-m-d H:i', strtotime('+2 days +1 hour')),'status'=>'confirmed','total'=>4.50],
];
$pastBookings = [
    ['id'=>1,'park'=>'Sydney CBD Parking Centre',    'suburb'=>'Sydney',     'spot'=>'A-05',
     'start'=>date('Y-m-d H:i', strtotime('-5 days')),
     'end'  =>date('Y-m-d H:i', strtotime('-5 days +2 hours')),'status'=>'completed','total'=>16.00],
    ['id'=>2,'park'=>'Parramatta Westfield Parking', 'suburb'=>'Parramatta', 'spot'=>'A-12',
     'start'=>date('Y-m-d H:i', strtotime('-2 days')),
     'end'  =>date('Y-m-d H:i', strtotime('-2 days +3 hours')),'status'=>'completed','total'=>15.00],
];
$totalSpent    = array_sum(array_column(array_merge($upcomingBookings,$pastBookings),'total'));
$totalBookings = count($upcomingBookings) + count($pastBookings);

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
      [$type,$msg] = explode(':',$cancelMsg,2);
      echo '<div class="alert alert-'.$type.'" data-auto-dismiss><span></span><span>'.h($msg).'</span></div>';
  }
  echo flash('success'); echo flash('warning');
  ?>

  <!-- Stats -->
  <div class="grid-4 mb-2">
    <div class="stat-card"><div class="stat-icon stat-icon-blue">&#x1F4C5;</div><div><div class="stat-value"><?= count($upcomingBookings) ?></div><div class="stat-label">Upcoming</div></div></div>
    <div class="stat-card"><div class="stat-icon stat-icon-green">&#x2705;</div><div><div class="stat-value"><?= count($pastBookings) ?></div><div class="stat-label">Completed</div></div></div>
    <div class="stat-card"><div class="stat-icon stat-icon-orange">&#x1F17F;</div><div><div class="stat-value"><?= $totalBookings ?></div><div class="stat-label">Total Bookings</div></div></div>
    <div class="stat-card"><div class="stat-icon stat-icon-red">&#x1F4B2;</div><div><div class="stat-value" style="font-size:1.3rem;">$<?= number_format($totalSpent,0) ?></div><div class="stat-label">Total Spent</div></div></div>
  </div>

  <!-- Tabs -->
  <div data-tabs>
    <div class="tabs">
      <button class="tab-btn active" data-tab="upcoming">&#x1F4C5; Upcoming (<?= count($upcomingBookings) ?>)</button>
      <button class="tab-btn" data-tab="history">&#x1F552; History (<?= count($pastBookings) ?>)</button>
      <button class="tab-btn" data-tab="profile">&#x1F464; My Profile</button>
    </div>

    <!-- Upcoming -->
    <div class="tab-content active" data-tab-content="upcoming">
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
                <span class="badge badge-success">Confirmed</span>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-top:0.85rem;padding:0.85rem;background:var(--bg);border-radius:var(--radius);">
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
                  <div style="font-weight:700;font-size:1.1rem;color:var(--primary);">$<?= number_format($b['total'],2) ?></div>
                </div>
              </div>
              <div style="display:flex;gap:0.5rem;margin-top:0.85rem;">
                <form method="POST" action="dashboard.php" onsubmit="return confirm('Cancel this booking?');" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm">&#x2715; Cancel</button>
                </form>
                <a href="reserve.php" class="btn btn-dark btn-sm">&#x1F504; Rebook</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="mt-2"><a href="search.php" class="btn btn-primary">+ New Reservation</a></div>
    </div>

    <!-- History -->
    <div class="tab-content" data-tab-content="history">
      <?php if (empty($pastBookings)): ?>
        <div class="card text-center" style="padding:3rem;"><h3>No history yet</h3></div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Car Park</th><th>Spot</th><th>Date</th><th>Duration</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($pastBookings as $b):
                $h = round((strtotime($b['end'])-strtotime($b['start']))/3600,1); ?>
                <tr>
                  <td>#<?= $b['id'] ?></td>
                  <td><strong><?= h($b['park']) ?></strong><br><span class="text-muted" style="font-size:0.8rem;"><?= h($b['suburb']) ?></span></td>
                  <td><?= h($b['spot']) ?></td>
                  <td><?= date('d M Y', strtotime($b['start'])) ?><br><span class="text-muted"><?= date('g:i A', strtotime($b['start'])) ?></span></td>
                  <td><?= $h ?>h</td>
                  <td><strong>$<?= number_format($b['total'],2) ?></strong></td>
                  <td><span class="badge badge-success">Completed</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Profile -->
    <div class="tab-content" data-tab-content="profile">
      <div class="grid-2" style="align-items:start;gap:1.5rem;">
        <div class="card">
          <div class="card-header"><h3>&#x1F464; Profile Information</h3></div>
          <form method="POST" action="dashboard.php">
            <?= csrfField() ?>
            <div class="form-group">
              <label for="prof_name">Full Name</label>
              <input type="text" id="prof_name" name="name" maxlength="100" value="<?= h($_SESSION['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="prof_email">Email Address</label>
              <input type="email" id="prof_email" name="email" value="<?= h($_SESSION['email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="prof_phone">Phone <span class="text-muted">(optional)</span></label>
              <input type="tel" id="prof_phone" name="phone" placeholder="+61 4xx xxx xxx">
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary btn-block">Save Changes</button>
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
              <label>New Password</label>
              <input type="password" name="password" id="newPwd" autocomplete="new-password" oninput="updatePwdStrength2(this.value)">
              <div class="pwd-strength">
                <div class="pwd-bar"><div class="pwd-fill" id="pwdBar2"></div></div>
                <div class="pwd-label" id="pwdLabel2"></div>
              </div>
            </div>
            <div class="form-group">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" autocomplete="new-password">
            </div>
            <button type="submit" name="change_password" class="btn btn-dark btn-block">Update Password</button>
          </form>
          <div style="border-top:1px solid var(--border);margin-top:1.25rem;padding-top:1rem;">
            <h4 style="color:var(--danger);font-size:0.95rem;">&#x26A0; Data &amp; Privacy</h4>
            <p class="text-muted" style="font-size:0.85rem;margin-top:0.4rem;">You can request deletion of your account and all associated personal data.</p>
            <a href="contact.php?subject=Request+Account+Deletion" class="btn btn-danger btn-sm mt-1">Request Account Deletion</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function updatePwdStrength2(pwd) {
    var score = 0;
    if (pwd.length >= 8) score++;
    if (/[A-Z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd)) score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;
    var bar = document.getElementById('pwdBar2');
    var lbl = document.getElementById('pwdLabel2');
    var colors = ['','#e74c3c','#f39c12','#27ae60','#1a6b3a'];
    var labels = ['','Weak','Fair','Good','Strong'];
    if (bar) { bar.style.width = (score*25)+'%'; bar.style.background = colors[score]||''; }
    if (lbl) lbl.textContent = score ? 'Strength: '+labels[score] : '';
}
</script>

<?php require 'includes/footer.php'; ?>
