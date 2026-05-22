<?php
require_once 'includes/functions.php';
$pageTitle = 'Home';
$activeNav = 'home';

try {
    $db   = getDB();
    $stmt = $db->query("
        SELECT c.park_id, c.name, c.suburb, c.total_spots AS total, c.occupied, c.active,
               COALESCE(MIN(z.rate_per_hour), 0) AS rate
        FROM car_parks c
        LEFT JOIN parking_zones z ON z.park_id = c.park_id
        WHERE c.active = 1
        GROUP BY c.park_id
        ORDER BY c.suburb
    ");
    $parks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[SmartPark index] ' . $e->getMessage());
    $parks = [];
}

$totalSpots    = array_sum(array_column($parks, 'total'));
$totalOccupied = array_sum(array_column($parks, 'occupied'));
$totalAvail    = $totalSpots - $totalOccupied;

require 'includes/header.php';
?>

<section class="hero">
  <div class="hero-inner">
    <div class="hero-text">
      <h1 class="hero-title">Find &amp; Reserve<br><span>Parking</span> in Seconds</h1>
      <p class="hero-subtitle">
        SmartPark is a secure, real-time urban parking management system.
        Search available spots across the city, reserve online, and never circle the block again.
      </p>
      <div class="hero-actions">
        <a href="search.php" class="btn btn-primary btn-lg">&#x1F50D; Find Parking</a>
        <?php if (!isLoggedIn()): ?>
          <a href="register.php" class="btn btn-outline btn-lg">Create Account</a>
        <?php else: ?>
          <a href="dashboard.php" class="btn btn-outline btn-lg">My Dashboard</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="hero-visual">
      <div class="hero-visual-title">&#x1F7E2; Live Availability</div>
      <div class="hero-spot-grid">
        <?php
        $heroSpots = ['available','available','occupied','available','available','occupied',
                      'occupied','available','available','occupied','available','available',
                      'occupied','available','available','available','occupied','occupied',
                      'available','occupied','available','available','available','occupied'];
        foreach ($heroSpots as $i => $state):
            $label = chr(65 + (int)($i / 6)) . '-' . str_pad(($i % 6) + 1, 2, '0', STR_PAD_LEFT);
            $style = $state === 'available'
                ? 'border-color:rgba(39,174,96,0.6);background:rgba(39,174,96,0.15);color:rgba(39,174,96,0.9);'
                : 'border-color:rgba(231,76,60,0.5);background:rgba(231,76,60,0.1);color:rgba(231,76,60,0.7);';
        ?>
          <div class="hero-spot" style="<?= $style ?>"><?= h($label) ?></div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:1rem;margin-top:0.85rem;font-size:0.78rem;color:rgba(255,255,255,0.7);">
        <span>&#x1F7E2; Available</span>
        <span>&#x1F534; Occupied</span>
      </div>
    </div>
  </div>
</section>

<section style="background:white;border-bottom:1px solid var(--border);padding:1.5rem 0;">
  <div class="container">
    <div class="grid-4">
      <div class="stat-card">
        <div class="stat-icon stat-icon-blue">&#x1F17F;</div>
        <div><div class="stat-value"><?= $totalSpots ?></div><div class="stat-label">Total Parking Spots</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-green">&#x2705;</div>
        <div><div class="stat-value"><?= $totalAvail ?></div><div class="stat-label">Available Right Now</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-orange">&#x1F3D9;</div>
        <div><div class="stat-value"><?= count($parks) ?></div><div class="stat-label">Car Parks</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon-red">&#x1F4CD;</div>
        <div><div class="stat-value">5</div><div class="stat-label">Sydney Suburbs</div></div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <?= flash('success') ?><?= flash('warning') ?><?= flash('danger') ?>

    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.25rem;">
      <div>
        <h2>Parking Availability</h2>
        <p class="text-muted">Live overview across all registered car parks. Last updated: <strong><?= date('g:i A, d M Y') ?></strong></p>
      </div>
      <a href="search.php" class="btn btn-dark btn-sm">View All &rarr;</a>
    </div>

    <?php if (empty($parks)): ?>
      <div class="alert alert-warning">
        <span>&#x26A0;</span><span>No car parks available at the moment. Please check back later.</span>
      </div>
    <?php else: ?>
      <div class="avail-section">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Car Park</th><th>Suburb</th><th>Availability</th>
                <th>Available</th><th>From</th><th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($parks as $park):
                $avail = $park['total'] - $park['occupied'];
                $pct   = $park['total'] > 0 ? round(($avail / $park['total']) * 100) : 0;
                $fillClass = $pct > 50 ? 'avail-high' : ($pct > 20 ? 'avail-mid' : 'avail-low');
                $avInfo    = availabilityLabel($avail, $park['total']);
              ?>
                <tr>
                  <td><strong><?= h($park['name']) ?></strong></td>
                  <td><?= h($park['suburb']) ?></td>
                  <td style="min-width:160px;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                      <div class="avail-bar" style="flex:1;">
                        <div class="avail-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
                      </div>
                      <span style="font-size:0.78rem;color:var(--text-muted);width:36px;"><?= $pct ?>%</span>
                    </div>
                  </td>
                  <td><?= $avail ?> / <?= $park['total'] ?> <span class="badge <?= $avInfo['class'] ?>"><?= $avInfo['label'] ?></span></td>
                  <td><strong>$<?= number_format($park['rate'], 2) ?></strong>/hr</td>
                  <td><a href="search.php?suburb=<?= urlencode($park['suburb']) ?>" class="btn btn-dark btn-sm">Reserve</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section" style="background:white;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container text-center">
    <h2>How SmartPark Works</h2>
    <p class="text-muted mt-1" style="max-width:520px;margin:0 auto 2rem;">Three simple steps to secure your spot.</p>
    <div class="grid-3">
      <div class="card" style="text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:0.75rem;">&#x1F50D;</div>
        <h3>1. Search</h3>
        <p class="text-muted mt-1">Enter your suburb, date, and time to see real-time availability at nearby car parks.</p>
      </div>
      <div class="card" style="text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:0.75rem;">&#x1F17F;</div>
        <h3>2. Choose a Spot</h3>
        <p class="text-muted mt-1">Select your preferred zone and parking spot from the interactive availability grid.</p>
      </div>
      <div class="card" style="text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:0.75rem;">&#x2705;</div>
        <h3>3. Confirm</h3>
        <p class="text-muted mt-1">Review your booking summary and confirm. A reference is saved to your dashboard.</p>
      </div>
    </div>
    <div class="mt-3">
      <?php if (!isLoggedIn()): ?>
        <a href="register.php" class="btn btn-primary btn-lg">Get Started &mdash; It&apos;s Free</a>
      <?php else: ?>
        <a href="reserve.php" class="btn btn-primary btn-lg">Reserve a Spot Now</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center;">
      <div>
        <h2>Built with Security &amp; Privacy in Mind</h2>
        <p class="text-muted mt-1" style="line-height:1.8;">SmartPark follows OWASP Top 10 guidelines and the Australian Privacy Act 1988.</p>
        <ul style="list-style:none;margin-top:1.25rem;display:flex;flex-direction:column;gap:0.65rem;">
          <li style="display:flex;align-items:center;gap:0.6rem;"><span style="color:var(--success);">&#x1F510;</span> Passwords hashed with bcrypt (cost 12)</li>
          <li style="display:flex;align-items:center;gap:0.6rem;"><span style="color:var(--success);">&#x1F6E1;</span> CSRF protection on all state-changing actions</li>
          <li style="display:flex;align-items:center;gap:0.6rem;"><span style="color:var(--success);">&#x1F512;</span> SQL injection prevented via PDO prepared statements</li>
          <li style="display:flex;align-items:center;gap:0.6rem;"><span style="color:var(--success);">&#x1F464;</span> Role-based access control (RBAC)</li>
          <li style="display:flex;align-items:center;gap:0.6rem;"><span style="color:var(--success);">&#x1F310;</span> HTTPS encrypted data transmission</li>
          <li style="display:flex;align-items:center;gap:0.6rem;"><span style="color:var(--success);">&#x267F;</span> WCAG 2.1 Level AA accessible design</li>
        </ul>
      </div>
      <div class="card" style="padding:1.75rem;">
        <h4 style="margin-bottom:1rem;">&#x1F3D9; Sydney Coverage</h4>
        <?php foreach ($parks as $park):
          $avail = $park['total'] - $park['occupied'];
          $pct   = $park['total'] > 0 ? round(($avail / $park['total']) * 100) : 0;
          $fillClass = $pct > 50 ? 'avail-high' : ($pct > 20 ? 'avail-mid' : 'avail-low');
        ?>
          <div style="margin-bottom:0.85rem;">
            <div style="display:flex;justify-content:space-between;font-size:0.88rem;margin-bottom:0.25rem;">
              <span><?= h($park['suburb']) ?></span>
              <span class="text-muted"><?= $avail ?> spots free</span>
            </div>
            <div class="avail-bar">
              <div class="avail-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php require 'includes/footer.php'; ?>