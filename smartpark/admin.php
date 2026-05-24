<?php
/**
 * SmartPark - Admin Panel (admin.php)
 * ICT312 Advanced Web Information Systems
 */
require_once 'includes/functions.php';
requireLogin('login.php');
requireAdmin();

$section  = $_GET['section'] ?? 'overview';
$sections = ['overview','parks','bookings','users','zones','subscriptions'];
if (!in_array($section, $sections)) $section = 'overview';

// ---- Handle POST ----
$adminMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $adminMsg = 'error:Invalid request. Please try again.';

    // Add car park
    } elseif (isset($_POST['add_park'])) {
        $parkName = trim($_POST['park_name']    ?? '');
        $suburb   = trim($_POST['suburb']       ?? '');
        $address  = trim($_POST['address']      ?? '');
        $totalSp  = intval($_POST['total_spots'] ?? 0);
        if (!$parkName || !$suburb || !$address || $totalSp < 1) {
            $adminMsg = 'error:Please fill in all fields to add a car park.';
        } else {
            try {
                $db   = getDB();
                $stmt = $db->prepare("
                    INSERT INTO car_parks (name, address, suburb, total_spots, occupied, active)
                    VALUES (?, ?, ?, ?, 0, 1)
                ");
                $stmt->execute([$parkName, $address, $suburb, $totalSp]);
                $adminMsg = 'success:Car park "' . h($parkName) . '" added successfully.';
            } catch (PDOException $e) {
                error_log('[SmartPark add park] ' . $e->getMessage());
                $adminMsg = 'error:Could not add car park. Please try again.';
            }
        }

    // Toggle park status
    } elseif (isset($_POST['toggle_status'])) {
        $parkId = intval($_POST['park_id']);
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                UPDATE car_parks SET active = IF(active = 1, 0, 1) WHERE park_id = ?
            ");
            $stmt->execute([$parkId]);
            $adminMsg = 'success:Car park status updated successfully.';
        } catch (PDOException $e) {
            error_log('[SmartPark toggle park] ' . $e->getMessage());
            $adminMsg = 'error:Could not update car park status.';
        }

    // Add zone
    } elseif (isset($_POST['add_zone'])) {
        $zoneParkId = intval($_POST['park_id']         ?? 0);
        $zoneLabel  = trim($_POST['zone_label']        ?? '');
        $spotCount  = intval($_POST['spot_count']      ?? 0);
        $ratePerHr  = floatval($_POST['rate_per_hour'] ?? 0);
        if (!$zoneParkId || !$zoneLabel || $spotCount < 1 || $ratePerHr <= 0) {
            $adminMsg = 'error:Please fill in all fields to add a zone.';
        } else {
            try {
                $db   = getDB();
                $stmt = $db->prepare("
                    INSERT INTO parking_zones (park_id, zone_label, spot_count, rate_per_hour)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$zoneParkId, $zoneLabel, $spotCount, $ratePerHr]);
                $adminMsg = 'success:Zone "' . h($zoneLabel) . '" added successfully.';
            } catch (PDOException $e) {
                error_log('[SmartPark add zone] ' . $e->getMessage());
                $adminMsg = 'error:Could not add zone. Please try again.';
            }
        }

    // Delete zone
    } elseif (isset($_POST['delete_zone'])) {
        $zoneId = intval($_POST['zone_id']);
        try {
            $db   = getDB();
            $stmt = $db->prepare("DELETE FROM parking_zones WHERE zone_id = ?");
            $stmt->execute([$zoneId]);
            $adminMsg = 'success:Zone deleted successfully.';
        } catch (PDOException $e) {
            error_log('[SmartPark delete zone] ' . $e->getMessage());
            $adminMsg = 'error:Could not delete zone. Please try again.';
        }

    // Remove user
    } elseif (isset($_POST['remove_user'])) {
        $userId = intval($_POST['user_id']);
        try {
            $db   = getDB();
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
            $stmt->execute([$userId]);
            $adminMsg = 'success:User removed successfully.';
        } catch (PDOException $e) {
            error_log('[SmartPark remove user] ' . $e->getMessage());
            $adminMsg = 'error:Could not remove user.';
        }

    // Cancel subscription
    } elseif (isset($_POST['admin_cancel_sub'])) {
        $subId = $_POST['sub_id'] ?? '';
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                UPDATE subscriptions
                SET status = 'cancelled', cancelled_at = CURDATE()
                WHERE sub_ref = ?
            ");
            $stmt->execute([$subId]);
            $adminMsg = 'success:Subscription ' . $subId . ' cancelled successfully.';
        } catch (PDOException $e) {
            error_log('[SmartPark cancel sub] ' . $e->getMessage());
            $adminMsg = 'error:Could not cancel subscription.';
        }

    // Reactivate subscription
    } elseif (isset($_POST['admin_reactivate_sub'])) {
        $subId = $_POST['sub_id'] ?? '';
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                UPDATE subscriptions
                SET status = 'active',
                    end_date = DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
                    cancelled_at = NULL
                WHERE sub_ref = ?
            ");
            $stmt->execute([$subId]);
            $adminMsg = 'success:Subscription ' . $subId . ' reactivated successfully.';
        } catch (PDOException $e) {
            error_log('[SmartPark reactivate sub] ' . $e->getMessage());
            $adminMsg = 'error:Could not reactivate subscription.';
        }

    // Change subscription plan
    } elseif (isset($_POST['admin_change_plan'])) {
        $subId    = $_POST['sub_id']   ?? '';
        $newPlan  = $_POST['new_plan'] ?? '';
        $planPrices = ['basic'=>49.00,'standard'=>89.00,'premium'=>149.00];
        $planNames  = ['basic'=>'Basic','standard'=>'Standard','premium'=>'Premium'];
        if (!isset($planPrices[$newPlan])) {
            $adminMsg = 'error:Invalid plan selected.';
        } else {
            try {
                $db   = getDB();
                $stmt = $db->prepare("
                    UPDATE subscriptions
                    SET plan_id = ?, plan_name = ?, price = ?
                    WHERE sub_ref = ?
                ");
                $stmt->execute([$newPlan, $planNames[$newPlan], $planPrices[$newPlan], $subId]);
                $adminMsg = 'success:Subscription changed to ' . $planNames[$newPlan] . ' plan ($' . $planPrices[$newPlan] . '/month).';
            } catch (PDOException $e) {
                error_log('[SmartPark change plan] ' . $e->getMessage());
                $adminMsg = 'error:Could not change plan.';
            }
        }

    // Change subscription car park
    } elseif (isset($_POST['admin_change_park'])) {
        $subId     = $_POST['sub_id']       ?? '';
        $newParkId = intval($_POST['new_park_id'] ?? 0);
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                UPDATE subscriptions SET park_id = ? WHERE sub_ref = ?
            ");
            $stmt->execute([$newParkId, $subId]);
            $adminMsg = 'success:Subscription car park updated successfully.';
        } catch (PDOException $e) {
            error_log('[SmartPark change park] ' . $e->getMessage());
            $adminMsg = 'error:Could not change car park.';
        }

    // Extend subscription
    } elseif (isset($_POST['admin_extend_sub'])) {
        $subId = $_POST['sub_id'] ?? '';
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                UPDATE subscriptions
                SET end_date = DATE_ADD(end_date, INTERVAL 1 MONTH),
                    status   = 'active'
                WHERE sub_ref = ?
            ");
            $stmt->execute([$subId]);
            $adminMsg = 'success:Subscription extended by 1 month.';
        } catch (PDOException $e) {
            error_log('[SmartPark extend sub] ' . $e->getMessage());
            $adminMsg = 'error:Could not extend subscription.';
        }
    }
}

// ---- Load all data from database ----
try {
    $db = getDB();

    $parks = $db->query("
        SELECT c.park_id AS id, c.name, c.suburb, c.address,
               c.total_spots AS total, c.occupied, c.active,
               COALESCE(MIN(z.rate_per_hour), 0) AS rate
        FROM car_parks c
        LEFT JOIN parking_zones z ON z.park_id = c.park_id
        GROUP BY c.park_id
        ORDER BY c.suburb
    ")->fetchAll();

    $zones = $db->query("
        SELECT z.zone_id AS id, z.park_id, c.name AS park,
               z.zone_label AS label, z.spot_count AS spots,
               z.rate_per_hour AS rate
        FROM parking_zones z
        JOIN car_parks c ON c.park_id = z.park_id
        ORDER BY c.name, z.zone_label
    ")->fetchAll();

    $users = $db->query("
        SELECT u.user_id AS id, u.name, u.email, u.role, u.created_at AS joined,
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.user_id) AS bookings
        FROM users u
        ORDER BY u.created_at DESC
    ")->fetchAll();

    $bookings = $db->query("
        SELECT b.booking_id AS id, u.name AS user, c.name AS park,
               b.spot_number AS spot, b.start_time AS start,
               b.end_time AS end, b.status, b.total_amount AS total
        FROM bookings b
        JOIN users u     ON u.user_id  = b.user_id
        JOIN car_parks c ON c.park_id  = b.park_id
        ORDER BY b.created_at DESC
    ")->fetchAll();

    $subscriptions = $db->query("
        SELECT s.sub_ref AS id, s.user_id,
               u.name AS user_name, u.email AS user_email,
               s.plan_id, s.plan_name, s.park_id,
               c.name AS park_name, c.suburb,
               s.price, s.start_date, s.end_date,
               s.status, s.cancelled_at, s.created_at
        FROM subscriptions s
        JOIN users u     ON u.user_id = s.user_id
        JOIN car_parks c ON c.park_id = s.park_id
        ORDER BY s.created_at DESC
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[SmartPark admin load] ' . $e->getMessage());
    $parks = $zones = $users = $bookings = $subscriptions = [];
}

$totalRevenue = array_sum(array_column($bookings, 'total'));
$totalSpots   = array_sum(array_column($parks, 'total'));
$totalOccupied= array_sum(array_column($parks, 'occupied'));
$subRevenue   = array_sum(array_map(
    fn($s) => $s['status'] === 'active' ? $s['price'] : 0,
    $subscriptions
));

$pageTitle = 'Admin Panel';
$activeNav = 'admin';
require 'includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>&#x2699; Admin Panel</h1>
    <p>Manage car parks, bookings, zones, users and subscriptions. Logged in as <?= h($_SESSION['name'] ?? 'Admin') ?></p>
  </div>
</div>

<div class="container section-sm">

  <?php if ($adminMsg):
    [$type, $msg] = explode(':', $adminMsg, 2); ?>
    <div class="alert alert-<?= $type ?>" data-auto-dismiss>
      <span><?= $type === 'success' ? '&#x2705;' : '&#x274C;' ?></span>
      <span><?= h($msg) ?></span>
    </div>
  <?php endif; ?>

  <div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">
      <div class="admin-sidebar-header"><h3>&#x2699; Admin Menu</h3></div>
      <ul class="admin-nav">
        <li><a href="admin.php?section=overview"      class="<?= $section==='overview'      ?'active':'' ?>"><span class="icon">&#x1F4CA;</span>Overview</a></li>
        <li><a href="admin.php?section=parks"         class="<?= $section==='parks'         ?'active':'' ?>"><span class="icon">&#x1F3D9;</span>Car Parks</a></li>
        <li><a href="admin.php?section=zones"         class="<?= $section==='zones'         ?'active':'' ?>"><span class="icon">&#x1F5FA;</span>Zones &amp; Pricing</a></li>
        <li><a href="admin.php?section=bookings"      class="<?= $section==='bookings'      ?'active':'' ?>"><span class="icon">&#x1F4C5;</span>All Bookings</a></li>
        <li><a href="admin.php?section=subscriptions" class="<?= $section==='subscriptions' ?'active':'' ?>"><span class="icon">&#x1F4CB;</span>Subscriptions</a></li>
        <li><a href="admin.php?section=users"         class="<?= $section==='users'         ?'active':'' ?>"><span class="icon">&#x1F465;</span>Users</a></li>
        <li style="border-top:1px solid var(--border);margin-top:0.5rem;padding-top:0.5rem;">
          <a href="index.php"><span class="icon">&#x1F3E0;</span>Back to Site</a>
        </li>
      </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <div>

      <!-- ===== OVERVIEW ===== -->
      <?php if ($section === 'overview'): ?>

        <div class="grid-2" style="gap:1rem;margin-bottom:1.25rem;">
          <div class="stat-card">
            <div class="stat-icon stat-icon-blue">&#x1F3D9;</div>
            <div><div class="stat-value"><?= count($parks) ?></div><div class="stat-label">Total Car Parks</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon-green">&#x1F17F;</div>
            <div><div class="stat-value"><?= $totalSpots - $totalOccupied ?></div><div class="stat-label">Available Spots</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon-orange">&#x1F4C5;</div>
            <div><div class="stat-value"><?= count($bookings) ?></div><div class="stat-label">Total Bookings</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon-red">&#x1F4B2;</div>
            <div>
              <div class="stat-value" style="font-size:1.3rem;">$<?= number_format($totalRevenue, 0) ?></div>
              <div class="stat-label">Booking Revenue</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon-blue">&#x1F4CB;</div>
            <div>
              <div class="stat-value"><?= count(array_filter($subscriptions, fn($s) => $s['status']==='active')) ?></div>
              <div class="stat-label">Active Subscriptions</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon-green">&#x1F4B0;</div>
            <div>
              <div class="stat-value" style="font-size:1.3rem;">$<?= number_format($subRevenue, 0) ?></div>
              <div class="stat-label">Subscription Revenue/mo</div>
            </div>
          </div>
        </div>

        <!-- Occupancy Report -->
        <div class="card mb-2">
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3>&#x1F4CA; Occupancy Report</h3>
            <span class="text-muted" style="font-size:0.82rem;">Updated <?= date('g:i A') ?></span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>Car Park</th><th>Suburb</th><th>Capacity</th><th>Occupancy</th><th>Rate</th><th>Status</th></tr>
              </thead>
              <tbody>
                <?php foreach ($parks as $p):
                  $pct = $p['total'] > 0 ? round(($p['occupied'] / $p['total']) * 100) : 0;
                  $bc  = $pct > 80 ? 'avail-low' : ($pct > 50 ? 'avail-mid' : 'avail-high');
                ?>
                  <tr>
                    <td><strong><?= h($p['name']) ?></strong></td>
                    <td><?= h($p['suburb']) ?></td>
                    <td><?= $p['occupied'] ?> / <?= $p['total'] ?></td>
                    <td style="min-width:140px;">
                      <div style="display:flex;align-items:center;gap:0.4rem;">
                        <div class="avail-bar" style="flex:1;">
                          <div class="avail-fill <?= $bc ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span style="font-size:0.78rem;width:34px;"><?= $pct ?>%</span>
                      </div>
                    </td>
                    <td>$<?= number_format($p['rate'], 2) ?>/hr</td>
                    <td><?= $p['active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-muted">Inactive</span>' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3>&#x1F4C5; Recent Bookings</h3>
            <a href="admin.php?section=bookings" class="btn btn-dark btn-sm">View All</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>User</th><th>Car Park</th><th>Start</th><th>Total</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($bookings, 0, 5) as $b):
                  $bc = $b['status']==='completed' ? 'badge-success' : ($b['status']==='cancelled' ? 'badge-danger' : 'badge-info');
                ?>
                  <tr>
                    <td><?= $b['id'] ?></td>
                    <td><?= h($b['user']) ?></td>
                    <td><?= h($b['park']) ?></td>
                    <td><?= date('d M Y g:i A', strtotime($b['start'])) ?></td>
                    <td><strong>$<?= number_format($b['total'], 2) ?></strong></td>
                    <td><span class="badge <?= $bc ?>"><?= ucfirst($b['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <!-- ===== CAR PARKS ===== -->
      <?php elseif ($section === 'parks'): ?>

        <div class="card mb-2">
          <div class="card-header"><h3>&#x2795; Add New Car Park</h3></div>
          <form method="POST" action="admin.php?section=parks">
            <?= csrfField() ?>
            <div class="form-row">
              <div class="form-group">
                <label>Park Name</label>
                <input type="text" name="park_name" placeholder="e.g. Surry Hills Carpark" maxlength="150" required>
              </div>
              <div class="form-group">
                <label>Suburb</label>
                <input type="text" name="suburb" placeholder="e.g. Surry Hills" maxlength="100" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" placeholder="e.g. 123 Crown Street" maxlength="255" required>
              </div>
              <div class="form-group">
                <label>Total Spots</label>
                <input type="number" name="total_spots" min="1" max="1000" placeholder="50" required>
              </div>
            </div>
            <button type="submit" name="add_park" class="btn btn-primary">Add Car Park</button>
          </form>
        </div>

        <div class="card">
          <?php
            $activeParkCount   = count(array_filter($parks, fn($p) => $p['active']));
            $inactiveParkCount = count($parks) - $activeParkCount;
          ?>
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3>&#x1F3D9; All Car Parks (<?= count($parks) ?>)</h3>
            <div style="display:flex;gap:0.5rem;">
              <span class="badge badge-success"><?= $activeParkCount ?> Active</span>
              <span class="badge badge-muted"><?= $inactiveParkCount ?> Inactive</span>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>Name</th><th>Suburb</th><th>Spots</th><th>Rate</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($parks as $p): ?>
                  <tr>
                    <td><?= $p['id'] ?></td>
                    <td><strong><?= h($p['name']) ?></strong></td>
                    <td><?= h($p['suburb']) ?></td>
                    <td><?= $p['total'] - $p['occupied'] ?> / <?= $p['total'] ?> free</td>
                    <td>$<?= number_format($p['rate'], 2) ?>/hr</td>
                    <td><?= $p['active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-muted">Inactive</span>' ?></td>
                    <td>
                      <form method="POST" action="admin.php?section=parks" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="park_id" value="<?= $p['id'] ?>">
                        <button type="submit" name="toggle_status" class="btn btn-sm btn-dark">
                          <?= $p['active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <!-- ===== BOOKINGS ===== -->
      <?php elseif ($section === 'bookings'): ?>

        <div class="card">
          <div class="card-header" style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <h3>&#x1F4C5; All Bookings (<?= count($bookings) ?>)</h3>
            <span class="text-muted" style="font-size:0.85rem;align-self:center;">
              Total Revenue: <strong>$<?= number_format($totalRevenue, 2) ?></strong>
            </span>
          </div>
          <div style="padding:0 0 1rem;">
            <input type="text" id="bookingSearch"
                   placeholder="&#x1F50D; Search by user, car park, or spot..."
                   oninput="filterBookings(this.value)"
                   style="width:100%;padding:0.6rem 0.9rem;border:1.5px solid var(--border);border-radius:var(--radius);font-size:0.9rem;">
          </div>
          <div class="table-wrap">
            <table id="bookingsTable">
              <thead>
                <tr><th>#</th><th>User</th><th>Car Park</th><th>Spot</th><th>Start</th><th>End</th><th>Total</th><th>Status</th></tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $b):
                  $bc = $b['status']==='completed' ? 'badge-success' : ($b['status']==='cancelled' ? 'badge-danger' : 'badge-info');
                ?>
                  <tr>
                    <td><?= $b['id'] ?></td>
                    <td><?= h($b['user']) ?></td>
                    <td><?= h($b['park']) ?></td>
                    <td><?= h($b['spot']) ?></td>
                    <td><?= date('d M Y', strtotime($b['start'])) ?><br><span class="text-muted"><?= date('g:i A', strtotime($b['start'])) ?></span></td>
                    <td><?= date('d M Y', strtotime($b['end'])) ?><br><span class="text-muted"><?= date('g:i A', strtotime($b['end'])) ?></span></td>
                    <td><strong>$<?= number_format($b['total'], 2) ?></strong></td>
                    <td><span class="badge <?= $bc ?>"><?= ucfirst($b['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <script>
        function filterBookings(q) {
            q = q.toLowerCase();
            document.querySelectorAll('#bookingsTable tbody tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }
        </script>

      <!-- ===== SUBSCRIPTIONS ===== -->
      <?php elseif ($section === 'subscriptions'): ?>

        <?php
          $activeSubs    = array_filter($subscriptions, fn($s) => $s['status']==='active');
          $cancelledSubs = array_filter($subscriptions, fn($s) => $s['status']==='cancelled');
          $expiredSubs   = array_filter($subscriptions, fn($s) => $s['status']==='expired');
          $subMonthlyRev = array_sum(array_map(fn($s) => $s['price'], $activeSubs));
        ?>

        <div class="grid-3" style="gap:1rem;margin-bottom:1.25rem;">
          <div class="stat-card">
            <div class="stat-icon stat-icon-green">&#x2705;</div>
            <div><div class="stat-value"><?= count($activeSubs) ?></div><div class="stat-label">Active</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon-red">&#x274C;</div>
            <div><div class="stat-value"><?= count($cancelledSubs) + count($expiredSubs) ?></div><div class="stat-label">Cancelled / Expired</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon-orange">&#x1F4B0;</div>
            <div>
              <div class="stat-value" style="font-size:1.2rem;">$<?= number_format($subMonthlyRev, 0) ?></div>
              <div class="stat-label">Monthly Revenue</div>
            </div>
          </div>
        </div>

        <!-- Active subscriptions -->
        <div class="card mb-2">
          <div class="card-header">
            <h3>&#x2705; Active Subscriptions (<?= count($activeSubs) ?>)</h3>
          </div>
          <?php if (empty($activeSubs)): ?>
            <p class="text-muted" style="padding:1rem;">No active subscriptions.</p>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:1.25rem;padding:0.5rem 0;">
              <?php foreach ($activeSubs as $sub): ?>
                <div style="border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;border-left:4px solid var(--success);">

                  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.85rem;">
                    <div>
                      <h4><?= h($sub['user_name']) ?> <span class="text-muted" style="font-weight:400;font-size:0.85rem;">&lt;<?= h($sub['user_email']) ?>&gt;</span></h4>
                      <p class="text-muted" style="font-size:0.82rem;margin-top:0.2rem;">
                        Ref: <?= h($sub['id']) ?> &nbsp;&middot;&nbsp;
                        <?= h($sub['plan_name']) ?> Plan &nbsp;&middot;&nbsp;
                        <?= h($sub['park_name']) ?>
                      </p>
                    </div>
                    <span class="badge badge-success">Active</span>
                  </div>

                  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;padding:0.75rem;background:var(--bg);border-radius:var(--radius);margin-bottom:1rem;">
                    <div>
                      <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">Start Date</div>
                      <div style="font-weight:600;"><?= date('d M Y', strtotime($sub['start_date'])) ?></div>
                    </div>
                    <div>
                      <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">Next Billing</div>
                      <div style="font-weight:600;"><?= date('d M Y', strtotime($sub['end_date'])) ?></div>
                    </div>
                    <div>
                      <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">Monthly Price</div>
                      <div style="font-weight:700;color:var(--primary);">$<?= number_format($sub['price'], 2) ?></div>
                    </div>
                  </div>

                  <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:flex-end;">

                    <!-- Change Plan -->
                    <form method="POST" action="admin.php?section=subscriptions" style="display:flex;gap:0.4rem;align-items:center;">
                      <?= csrfField() ?>
                      <input type="hidden" name="sub_id" value="<?= h($sub['id']) ?>">
                      <select name="new_plan" style="padding:0.4rem 0.6rem;border:1.5px solid var(--border);border-radius:var(--radius);font-size:0.85rem;">
                        <option value="basic"    <?= $sub['plan_id']==='basic'   ?'selected':'' ?>>Basic &mdash; $49/mo</option>
                        <option value="standard" <?= $sub['plan_id']==='standard'?'selected':'' ?>>Standard &mdash; $89/mo</option>
                        <option value="premium"  <?= $sub['plan_id']==='premium' ?'selected':'' ?>>Premium &mdash; $149/mo</option>
                      </select>
                      <button type="submit" name="admin_change_plan" class="btn btn-dark btn-sm">Change Plan</button>
                    </form>

                    <!-- Change Car Park -->
                    <form method="POST" action="admin.php?section=subscriptions" style="display:flex;gap:0.4rem;align-items:center;">
                      <?= csrfField() ?>
                      <input type="hidden" name="sub_id" value="<?= h($sub['id']) ?>">
                      <select name="new_park_id" style="padding:0.4rem 0.6rem;border:1.5px solid var(--border);border-radius:var(--radius);font-size:0.85rem;">
                        <?php foreach ($parks as $p): ?>
                          <option value="<?= $p['id'] ?>" <?= $sub['park_id']===$p['id']?'selected':'' ?>>
                            <?= h($p['suburb']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" name="admin_change_park" class="btn btn-dark btn-sm">Change Park</button>
                    </form>

                    <!-- Extend -->
                    <form method="POST" action="admin.php?section=subscriptions" style="display:inline;">
                      <?= csrfField() ?>
                      <input type="hidden" name="sub_id" value="<?= h($sub['id']) ?>">
                      <button type="submit" name="admin_extend_sub" class="btn btn-success btn-sm">+1 Month</button>
                    </form>

                    <!-- Cancel -->
                    <form method="POST" action="admin.php?section=subscriptions" style="display:inline;"
                          onsubmit="return confirm('Cancel this subscription?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="sub_id" value="<?= h($sub['id']) ?>">
                      <button type="submit" name="admin_cancel_sub" class="btn btn-danger btn-sm">&#x2715; Cancel</button>
                    </form>

                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Cancelled and Expired -->
        <?php if (!empty($cancelledSubs) || !empty($expiredSubs)): ?>
          <div class="card">
            <div class="card-header"><h3>&#x1F552; Cancelled &amp; Expired</h3></div>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr><th>Ref</th><th>User</th><th>Plan</th><th>Car Park</th><th>End Date</th><th>Price</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                  <?php foreach (array_merge(array_values($cancelledSubs), array_values($expiredSubs)) as $sub):
                    $bc = $sub['status']==='cancelled' ? 'badge-danger' : 'badge-warning';
                  ?>
                    <tr>
                      <td><?= h($sub['id']) ?></td>
                      <td><?= h($sub['user_name']) ?></td>
                      <td><?= h($sub['plan_name']) ?></td>
                      <td><?= h($sub['park_name']) ?></td>
                      <td><?= date('d M Y', strtotime($sub['end_date'])) ?></td>
                      <td>$<?= number_format($sub['price'], 2) ?>/mo</td>
                      <td><span class="badge <?= $bc ?>"><?= ucfirst($sub['status']) ?></span></td>
                      <td>
                        <form method="POST" action="admin.php?section=subscriptions" style="display:inline;"
                              onsubmit="return confirm('Reactivate this subscription?');">
                          <?= csrfField() ?>
                          <input type="hidden" name="sub_id" value="<?= h($sub['id']) ?>">
                          <button type="submit" name="admin_reactivate_sub" class="btn btn-success btn-sm">Reactivate</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

      <!-- ===== USERS ===== -->
      <?php elseif ($section === 'users'): ?>

        <div class="card">
          <div class="card-header"><h3>&#x1F465; Registered Users (<?= count($users) ?>)</h3></div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Bookings</th><th>Joined</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td><?= $u['id'] ?></td>
                    <td><strong><?= h($u['name']) ?></strong></td>
                    <td><?= h($u['email']) ?></td>
                    <td>
                      <?= $u['role']==='admin'
                        ? '<span class="badge badge-info">Admin</span>'
                        : '<span class="badge badge-muted">Driver</span>' ?>
                    </td>
                    <td><?= $u['bookings'] ?></td>
                    <td><?= date('d M Y', strtotime($u['joined'])) ?></td>
                    <td>
                      <?php if ($u['role'] !== 'admin'): ?>
                        <form method="POST" action="admin.php?section=users" style="display:inline;"
                              onsubmit="return confirm('Remove this user? This cannot be undone.');">
                          <?= csrfField() ?>
                          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                          <button type="submit" name="remove_user" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted" style="font-size:0.8rem;">Protected</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <!-- ===== ZONES ===== -->
      <?php elseif ($section === 'zones'): ?>

        <div class="card mb-2">
          <div class="card-header"><h3>&#x2795; Add Parking Zone</h3></div>
          <form method="POST" action="admin.php?section=zones">
            <?= csrfField() ?>
            <div class="form-group">
              <label>Car Park</label>
              <select name="park_id" required>
                <option value="">— Select car park —</option>
                <?php foreach ($parks as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Zone Label</label>
                <input type="text" name="zone_label" placeholder="Zone A - Ground" maxlength="50" required>
              </div>
              <div class="form-group">
                <label>Spot Count</label>
                <input type="number" name="spot_count" min="1" placeholder="20" required>
              </div>
            </div>
            <div class="form-group">
              <label>Rate per Hour ($)</label>
              <input type="number" name="rate_per_hour" min="0.5" step="0.5" placeholder="5.00" required>
            </div>
            <button type="submit" name="add_zone" class="btn btn-primary">Add Zone</button>
          </form>
        </div>

        <div class="card">
          <div class="card-header"><h3>&#x1F5FA; All Zones (<?= count($zones) ?>)</h3></div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>#</th><th>Car Park</th><th>Zone</th><th>Spots</th><th>Rate/hr</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($zones as $z): ?>
                  <tr>
                    <td><?= $z['id'] ?></td>
                    <td><?= h($z['park']) ?></td>
                    <td><?= h($z['label']) ?></td>
                    <td><?= $z['spots'] ?></td>
                    <td>$<?= number_format($z['rate'], 2) ?></td>
                    <td>
                      <form method="POST" action="admin.php?section=zones" style="display:inline;"
                            onsubmit="return confirm('Delete this zone?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="zone_id" value="<?= $z['id'] ?>">
                        <button type="submit" name="delete_zone" class="btn btn-sm btn-danger">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>

    </div><!-- end main -->
  </div><!-- end admin-layout -->
</div>

<?php require 'includes/footer.php'; ?>