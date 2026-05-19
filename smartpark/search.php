<?php
require_once 'includes/functions.php';
/**
 * SmartPark - Search / Find Parking (search.php)
 * ICT312 Advanced Web Information Systems
 */
$pageTitle = 'Find Parking';
$activeNav = 'search';
require 'includes/header.php';

// --- Simulated car park data (in production, query from DB with PDO) ---
$allParks = [
  ['id'=>1,'name'=>'Sydney CBD Parking Centre',   'address'=>'100 George Street',         'suburb'=>'Sydney',         'total'=>80,'occupied'=>54,'rate'=>8.00,'zones'=>['Zone A','Zone B'],'features'=>['Undercover','24/7','EV Charging']],
  ['id'=>2,'name'=>'Parramatta Westfield Parking','address'=>'Corner Church & Market St', 'suburb'=>'Parramatta',     'total'=>60,'occupied'=>18,'rate'=>5.00,'zones'=>['Zone A','Zone B'],'features'=>['Undercover','CCTV','Disabled Access']],
  ['id'=>3,'name'=>'Bondi Junction Carpark',      'address'=>'500 Oxford Street',         'suburb'=>'Bondi Junction', 'total'=>40,'occupied'=>38,'rate'=>7.00,'zones'=>['Zone A','Zone B'],'features'=>['24/7','CCTV']],
  ['id'=>4,'name'=>'Chatswood Station Parking',   'address'=>'Victoria Ave & Railway St', 'suburb'=>'Chatswood',      'total'=>50,'occupied'=>22,'rate'=>4.50,'zones'=>['Zone A','Zone B'],'features'=>['Covered','Train Access']],
  ['id'=>5,'name'=>'Newtown Community Parking',   'address'=>'80 King Street',            'suburb'=>'Newtown',        'total'=>30,'occupied'=>10,'rate'=>4.00,'zones'=>['Zone A'],'features'=>['Open Air','CCTV']],
];

$suburbs = array_unique(array_column($allParks, 'suburb'));
sort($suburbs);

// Filter
$filterSuburb = trim($_GET['suburb'] ?? '');
$filterDate   = trim($_GET['date']   ?? date('Y-m-d'));
$filterTime   = trim($_GET['time']   ?? '');
$filterMaxRate = floatval($_GET['max_rate'] ?? 0);
$filterSort    = trim($_GET['sort'] ?? 'availability');

$displayParks = $allParks;
if ($filterSuburb) {
  $displayParks = array_filter($displayParks, fn($p) => stripos($p['suburb'], $filterSuburb) !== false);
}
if ($filterMaxRate > 0) {
  $displayParks = array_filter($displayParks, fn($p) => $p['rate'] <= $filterMaxRate);
}

usort($displayParks, function($a, $b) use ($filterSort) {
  switch ($filterSort) {
    case 'rate_asc':  return $a['rate'] <=> $b['rate'];
    case 'rate_desc': return $b['rate'] <=> $a['rate'];
    case 'name':      return strcmp($a['name'], $b['name']);
    default:
      return ($b['total'] - $b['occupied']) <=> ($a['total'] - $a['occupied']);
  }
});
?>

<div class="page-header">
  <div class="container">
    <h1>🔍 Find Parking</h1>
    <p>Search available car parks across Sydney in real time</p>
  </div>
</div>

<div class="container section-sm">

  <!-- ===== SEARCH FILTERS ===== -->
  <div class="search-filters">
    <form method="GET" action="search.php" id="searchForm">
      <div class="search-filters-row">
        <div class="form-group" style="margin:0;">
          <label for="suburb">📍 Suburb</label>
          <select name="suburb" id="suburb">
            <option value="">All Suburbs</option>
            <?php foreach ($suburbs as $s): ?>
              <option value="<?= h($s) ?>" <?= $filterSuburb === $s ? 'selected' : '' ?>><?= h($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label for="date">📅 Date</label>
          <input type="date" name="date" id="date" value="<?= h($filterDate) ?>" min="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group" style="margin:0;">
          <label for="time">⏰ Arrival Time</label>
          <input type="time" name="time" id="time" value="<?= h($filterTime) ?>">
        </div>

        <div class="form-group" style="margin:0;">
          <label for="max_rate">💲 Max Rate ($/hr)</label>
          <select name="max_rate" id="max_rate">
            <option value="">Any Rate</option>
            <option value="4"  <?= $filterMaxRate == 4  ? 'selected' : '' ?>>Up to $4.00</option>
            <option value="5"  <?= $filterMaxRate == 5  ? 'selected' : '' ?>>Up to $5.00</option>
            <option value="7"  <?= $filterMaxRate == 7  ? 'selected' : '' ?>>Up to $7.00</option>
            <option value="10" <?= $filterMaxRate == 10 ? 'selected' : '' ?>>Up to $10.00</option>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label for="sort">↕ Sort By</label>
          <select name="sort" id="sort">
            <option value="availability" <?= $filterSort==='availability' ? 'selected':'' ?>>Most Available</option>
            <option value="rate_asc"     <?= $filterSort==='rate_asc'     ? 'selected':'' ?>>Price: Low to High</option>
            <option value="rate_desc"    <?= $filterSort==='rate_desc'    ? 'selected':'' ?>>Price: High to Low</option>
            <option value="name"         <?= $filterSort==='name'         ? 'selected':'' ?>>Name A–Z</option>
          </select>
        </div>

        <div style="display:flex;align-items:flex-end;">
          <button type="submit" class="btn btn-primary" style="height:42px;">Search</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Results header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <p class="text-muted">
      Showing <strong><?= count($displayParks) ?></strong> car park<?= count($displayParks) !== 1 ? 's' : '' ?>
      <?= $filterSuburb ? ' in <strong>' . h($filterSuburb) . '</strong>' : '' ?>
    </p>
    <?php if ($filterSuburb || $filterMaxRate): ?>
      <a href="search.php" class="text-muted" style="font-size:0.85rem;">✕ Clear filters</a>
    <?php endif; ?>
  </div>

  <!-- ===== RESULTS GRID ===== -->
  <?php if (empty($displayParks)): ?>
    <div class="card text-center" style="padding:3rem;">
      <div style="font-size:3rem;margin-bottom:1rem;">🚗</div>
      <h3>No car parks found</h3>
      <p class="text-muted mt-1">No car parks match your current filters. Try:</p>
      <ul style="text-align:left;display:inline-block;margin:0.75rem 0;color:var(--text-muted);font-size:0.9rem;line-height:2;">
        <li>Selecting a different suburb</li>
        <li>Increasing your max hourly rate</li>
        <li>Clearing all filters to see everything</li>
      </ul><br>
      <a href="search.php" class="btn btn-dark mt-2">Clear All Filters</a>
    </div>
  <?php else: ?>
    <div class="grid-2" style="gap:1.25rem;">
      <?php foreach ($displayParks as $park):
        $avail = $park['total'] - $park['occupied'];
        $pct   = round(($avail / $park['total']) * 100);
        $fillClass = $pct > 50 ? 'avail-high' : ($pct > 20 ? 'avail-mid' : 'avail-low');
        $badgeClass = $pct > 50 ? 'badge-success' : ($pct > 20 ? 'badge-warning' : 'badge-danger');
        $badgeLabel = $pct > 50 ? 'Good Availability' : ($pct > 20 ? 'Limited' : 'Almost Full');
      ?>
        <div class="park-card" data-suburb="<?= h($park['suburb']) ?>" data-rate="<?= $park['rate'] ?>">
          <div class="park-card-header">
            <div>
              <div class="park-card-name"><?= h($park['name']) ?></div>
              <div class="park-card-suburb">📍 <?= h($park['address']) ?>, <?= h($park['suburb']) ?></div>
            </div>
            <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
          </div>

          <!-- Availability bar -->
          <div>
            <div style="display:flex;justify-content:space-between;font-size:0.82rem;margin-bottom:0.3rem;">
              <span class="text-muted">Availability</span>
              <span><?= $avail ?> of <?= $park['total'] ?> spots free</span>
            </div>
            <div class="avail-bar">
              <div class="avail-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>

          <!-- Zones -->
          <div class="park-card-meta">
            <span>🏢 <?= implode(', ', $park['zones']) ?></span>
          </div>

          <!-- Features -->
          <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-top:0.6rem;">
            <?php foreach ($park['features'] as $feat): ?>
              <span class="badge badge-muted" style="font-size:0.72rem;"><?= h($feat) ?></span>
            <?php endforeach; ?>
          </div>

          <div class="park-card-footer">
            <div class="park-rate">
              $<?= number_format($park['rate'], 2) ?> <small>/ hour</small>
            </div>
            <?php if (isLoggedIn()): ?>
              <a href="reserve.php?park_id=<?= $park['id'] ?>" class="btn btn-primary btn-sm">Reserve Now →</a>
            <?php else: ?>
              <a href="login.php" class="btn btn-dark btn-sm">Login to Reserve</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ===== INFO BOX ===== -->
  <div class="alert alert-info mt-3">
    <span>ℹ️</span>
    <span>
      Availability is updated by car park administrators. All reservations are confirmed instantly.
      <?php if (!isLoggedIn()): ?>
        <a href="register.php">Create a free account</a> to reserve a spot.
      <?php endif; ?>
    </span>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
