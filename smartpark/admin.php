<?php
require_once 'includes/functions.php';
/**
 * SmartPark - Admin Panel (admin.php)
 * ICT312 Advanced Web Information Systems
 *
 * RBAC: Admin role only. Every section verifies admin status.
 */
$pageTitle = 'Admin Panel';
$activeNav = 'admin';
require 'includes/header.php';
requireLogin('login.php');
requireAdmin();

$section = $_GET['section'] ?? 'overview';
$sections = ['overview', 'parks', 'bookings', 'users', 'zones'];
if (!in_array($section, $sections)) $section = 'overview';

// --- Demo data (In production: PDO queries) ---
$parks = [
  ['id'=>1,'name'=>'Sydney CBD Parking Centre',   'suburb'=>'Sydney',         'total'=>80,'occupied'=>54,'active'=>1,'rate'=>8.00],
  ['id'=>2,'name'=>'Parramatta Westfield Parking','suburb'=>'Parramatta',     'total'=>60,'occupied'=>18,'active'=>1,'rate'=>5.00],
  ['id'=>3,'name'=>'Bondi Junction Carpark',      'suburb'=>'Bondi Junction', 'total'=>40,'occupied'=>38,'active'=>1,'rate'=>7.00],
  ['id'=>4,'name'=>'Chatswood Station Parking',   'suburb'=>'Chatswood',      'total'=>50,'occupied'=>22,'active'=>1,'rate'=>4.50],
  ['id'=>5,'name'=>'Newtown Community Parking',   'suburb'=>'Newtown',        'total'=>30,'occupied'=>10,'active'=>0,'rate'=>4.00],
];

$users = [
  ['id'=>1,'name'=>'Admin User',  'email'=>'admin@smartpark.com','role'=>'admin', 'bookings'=>0, 'joined'=>'2025-01-15'],
  ['id'=>2,'name'=>'Jane Smith',  'email'=>'jane@example.com',  'role'=>'driver','bookings'=>3, 'joined'=>'2025-02-20'],
  ['id'=>3,'name'=>'Mark Johnson','email'=>'mark@example.com',  'role'=>'driver','bookings'=>2, 'joined'=>'2025-03-05'],
  ['id'=>4,'name'=>'Lucy Chen',   'email'=>'lucy@example.com',  'role'=>'driver','bookings'=>1, 'joined'=>'2025-03-18'],
];

$bookings = [
  ['id'=>1,'user'=>'Jane Smith',  'park'=>'Sydney CBD',       'spot'=>'A-05','start'=>'2025-04-15 10:00','end'=>'2025-04-15 12:00','status'=>'completed','total'=>16.00],
  ['id'=>2,'user'=>'Jane Smith',  'park'=>'Parramatta',       'spot'=>'A-12','start'=>'2025-04-18 09:00','end'=>'2025-04-18 12:00','status'=>'completed','total'=>15.00],
  ['id'=>3,'user'=>'Jane Smith',  'park'=>'Bondi Junction',   'spot'=>'A-03','start'=>date('Y-m-d H:i', strtotime('+1 day')),'end'=>date('Y-m-d H:i', strtotime('+1 day +2 hours')),'status'=>'confirmed','total'=>14.00],
  ['id'=>4,'user'=>'Mark Johnson','park'=>'Parramatta',       'spot'=>'B-07','start'=>'2025-04-17 14:00','end'=>'2025-04-17 18:00','status'=>'completed','total'=>26.00],
  ['id'=>5,'user'=>'Mark Johnson','park'=>'Chatswood Station','spot'=>'B-02','start'=>date('Y-m-d H:i', strtotime('+2 days')),'end'=>date('Y-m-d H:i', strtotime('+2 days +1 hour')),'status'=>'confirmed','total'=>4.50],
];

$totalRevenue = array_sum(array_column($bookings, 'total'));
$totalSpots   = array_sum(array_column($parks, 'total'));
$totalOccupied= array_sum(array_column($parks, 'occupied'));

// Handle POST (add/edit park)
$adminMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $adminMsg = 'error:Invalid request. Please try again.';
  } else {
    if (isset($_POST['add_park'])) {
      // In production: INSERT into car_parks via PDO
      $adminMsg = 'success:Car park "' . h($_POST['park_name']) . '" added successfully.';
    } elseif (isset($_POST['toggle_status'])) {
      $adminMsg = 'success:Car park status updated.';
    }
  }
}
?>

<div class="page-header">
  <div class="container">
    <h1>⚙ Admin Panel</h1>
    <p>Manage car parks, bookings, zones, and users. Logged in as <?= h($_SESSION['name'] ?? 'Admin') ?></p>
  </div>
</div>

<div class="container section-sm">
  <?php if ($adminMsg):
    [$type, $msg] = explode(':', $adminMsg, 2);
  ?>
    <div class="alert alert-<?= $type ?>" data-auto-dismiss>
      <span><?= $type === 'success' ? '✅' : '❌' ?></span>
      <span><?= $msg ?></span>
    </div>
  <?php endif; ?>

  <div class="admin-layout">
    <!-- ===== SIDEBAR ===== -->
    <aside class="admin-sidebar">
      <div class="admin-sidebar-header">
        <h3>⚙ Admin Menu</h3>
      </div>
      <ul class="admin-nav">
        <li><a href="admin.php?section=overview" class="<?= $section==='overview' ? 'active' : '' ?>"><span class="icon">📊</span>Overview</a></li>
        <li><a href="admin.php?section=parks"    class="<?= $section==='parks'    ? 'active' : '' ?>"><span class="icon">🏙</span>Car Parks</a></li>
        <li><a href="admin.php?section=zones"    class="<?= $section==='zones'    ? 'active' : '' ?>"><span class="icon">🗺</span>Zones &amp; Pricing</a></li>
        <li><a href="admin.php?section=bookings" class="<?= $section==='bookings' ? 'active' : '' ?>"><span class="icon">📅</span>All Bookings</a></li>
        <li><a href="admin.php?section=users"    class="<?= $section==='users'    ? 'active' : '' ?>"><span class="icon">👥</span>Users</a></li>
        <li style="border-top:1px solid var(--border);margin-top:0.5rem;padding-top:0.5rem;">
          <a href="index.php"><span class="icon">🏠</span>Back to Site</a>
        </li>
      </ul>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <div>

      <!-- ===== OVERVIEW ===== -->
      <?php if ($section === 'overview'): ?>
        <div class="grid-2" style="gap:1rem;margin-bottom:1.25rem;">
          <div class="stat-card"><div class="stat-icon stat-icon-blue">🏙</div><div><div class="stat-value"><?= count($parks) ?></div><div class="stat-label">Total Car Parks</div></div></div>
          <div class="stat-card"><div class="stat-icon stat-icon-green">🅿</div><div><div class="stat-value"><?= $totalSpots - $totalOccupied ?></div><div class="stat-label">Available Spots</div></div></div>
          <div class="stat-card"><div class="stat-icon stat-icon-orange">📅</div><div><div class="stat-value"><?= count($bookings) ?></div><div class="stat-label">Total Bookings</div></div></div>
          <div class="stat-card"><div class="stat-icon stat-icon-red">💲</div><div><div class="stat-value" style="font-size:1.3rem;">$<?= number_format($totalRevenue, 0) ?></div><div class="stat-label">Total Revenue</div></div></div>
        </div>

        <!-- Occupancy Report -->
        <div class="card mb-2">
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3>📊 Occupancy Report</h3>
            <span class="text-muted" style="font-size:0.82rem;">Updated <?= date('g:i A') ?></span>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Car Park</th><th>Suburb</th><th>Capacity</th><th>Occupancy</th><th>Rate</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($parks as $p):
                  $pct = round(($p['occupied'] / $p['total']) * 100);
                  $barClass = $pct > 80 ? 'avail-low' : ($pct > 50 ? 'avail-mid' : 'avail-high');
                ?>
                  <tr>
                    <td><strong><?= h($p['name']) ?></strong></td>
                    <td><?= h($p['suburb']) ?></td>
                    <td><?= $p['occupied'] ?> / <?= $p['total'] ?></td>
                    <td style="min-width:140px;">
                      <div style="display:flex;align-items:center;gap:0.4rem;">
                        <div class="avail-bar" style="flex:1;"><div class="avail-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div></div>
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

        <!-- Recent bookings -->
        <div class="card">
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3>📅 Recent Bookings</h3>
            <a href="admin.php?section=bookings" class="btn btn-dark btn-sm">View All</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>User</th><th>Car Park</th><th>Start</th><th>Total</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($bookings, 0, 3) as $b): ?>
                  <tr>
                    <td><?= $b['id'] ?></td>
                    <td><?= h($b['user']) ?></td>
                    <td><?= h($b['park']) ?></td>
                    <td><?= date('d M Y g:i A', strtotime($b['start'])) ?></td>
                    <td><strong>$<?= number_format($b['total'], 2) ?></strong></td>
                    <td>
                      <?php
                        $bc = $b['status'] === 'completed' ? 'badge-success' : ($b['status'] === 'cancelled' ? 'badge-danger' : 'badge-info');
                        echo '<span class="badge ' . $bc . '">' . ucfirst($b['status']) . '</span>';
                      ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <!-- ===== CAR PARKS ===== -->
      <?php elseif ($section === 'parks'): ?>
        <!-- Add new park form -->
        <div class="card mb-2">
          <div class="card-header"><h3>➕ Add New Car Park</h3></div>
          <form method="POST" action="admin.php?section=parks" onsubmit="return validateForm(this)">
            <?= csrfField() ?>
            <div class="form-row">
              <div class="form-group">
                <label>Park Name</label>
                <input type="text" name="park_name" data-required placeholder="e.g. Surry Hills Carpark" maxlength="150">
              </div>
              <div class="form-group">
                <label>Suburb</label>
                <input type="text" name="suburb" data-required placeholder="e.g. Surry Hills" maxlength="100">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" data-required placeholder="e.g. 123 Crown Street" maxlength="255">
              </div>
              <div class="form-group">
                <label>Total Spots</label>
                <input type="number" name="total_spots" data-required min="1" max="1000" placeholder="50">
              </div>
            </div>
            <button type="submit" name="add_park" class="btn btn-primary">Add Car Park</button>
          </form>
        </div>

        <!-- Parks list -->
        <div class="card">
          <div class="card-header"><h3>🏙 All Car Parks (<?= count($parks) ?>)</h3></div>
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
                      <a href="admin.php?section=zones&park_id=<?= $p['id'] ?>" class="btn btn-sm btn-dark">Zones</a>
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
          <div class="card-header" style="display:flex;justify-content:space-between;">
            <h3>📅 All Bookings (<?= count($bookings) ?>)</h3>
            <span class="text-muted" style="font-size:0.85rem;">Total Revenue: <strong>$<?= number_format($totalRevenue, 2) ?></strong></span>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>User</th><th>Car Park</th><th>Spot</th><th>Start</th><th>End</th><th>Total</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($bookings as $b):
                  $bc = $b['status'] === 'completed' ? 'badge-success' : ($b['status'] === 'cancelled' ? 'badge-danger' : 'badge-info');
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

      <!-- ===== USERS ===== -->
      <?php elseif ($section === 'users'): ?>
        <div class="card">
          <div class="card-header"><h3>👥 Registered Users (<?= count($users) ?>)</h3></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Bookings</th><th>Joined</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td><?= $u['id'] ?></td>
                    <td><strong><?= h($u['name']) ?></strong></td>
                    <td><?= h($u['email']) ?></td>
                    <td>
                      <?php if ($u['role'] === 'admin'): ?>
                        <span class="badge badge-info">Admin</span>
                      <?php else: ?>
                        <span class="badge badge-muted">Driver</span>
                      <?php endif; ?>
                    </td>
                    <td><?= $u['bookings'] ?></td>
                    <td><?= date('d M Y', strtotime($u['joined'])) ?></td>
                    <td>
                      <?php if ($u['role'] !== 'admin'): ?>
                        <button class="btn btn-sm btn-danger" onclick="if(confirmDelete('Remove this user?')){}">Remove</button>
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
          <div class="card-header"><h3>🗺 Add Parking Zone</h3></div>
          <form method="POST" action="admin.php?section=zones" onsubmit="return validateForm(this)">
            <?= csrfField() ?>
            <div class="form-group">
              <label>Car Park</label>
              <select name="park_id" data-required>
                <?php foreach ($parks as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Zone Label</label>
                <input type="text" name="zone_label" data-required placeholder="Zone A - Ground" maxlength="50">
              </div>
              <div class="form-group">
                <label>Spot Count</label>
                <input type="number" name="spot_count" data-required min="1" placeholder="20">
              </div>
            </div>
            <div class="form-group">
              <label>Rate per Hour ($)</label>
              <input type="number" name="rate_per_hour" data-required min="0.5" step="0.5" placeholder="5.00">
            </div>
            <button type="submit" name="add_zone" class="btn btn-primary">Add Zone</button>
          </form>
        </div>

        <div class="card">
          <div class="card-header"><h3>🗺 All Zones</h3></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>Car Park</th><th>Zone</th><th>Spots</th><th>Rate/hr</th><th>Actions</th></tr></thead>
              <tbody>
                <?php
                $zones = [
                  ['id'=>1,'park'=>'Sydney CBD','label'=>'Zone A - Ground','spots'=>40,'rate'=>8.00],
                  ['id'=>2,'park'=>'Sydney CBD','label'=>'Zone B - Level 1','spots'=>40,'rate'=>6.50],
                  ['id'=>3,'park'=>'Parramatta','label'=>'Zone A - Short Stay','spots'=>30,'rate'=>5.00],
                  ['id'=>4,'park'=>'Parramatta','label'=>'Zone B - Long Stay','spots'=>30,'rate'=>3.50],
                  ['id'=>5,'park'=>'Bondi Junction','label'=>'Zone A - Street','spots'=>20,'rate'=>7.00],
                ];
                foreach ($zones as $z): ?>
                  <tr>
                    <td><?= $z['id'] ?></td>
                    <td><?= h($z['park']) ?></td>
                    <td><?= h($z['label']) ?></td>
                    <td><?= $z['spots'] ?></td>
                    <td>$<?= number_format($z['rate'], 2) ?></td>
                    <td>
                      <button class="btn btn-sm btn-dark">Edit</button>
                      <button class="btn btn-sm btn-danger" onclick="confirmDelete('Delete this zone?')">Delete</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div><!-- end main content -->
  </div><!-- end admin-layout -->
</div>

<?php require 'includes/footer.php'; ?>
