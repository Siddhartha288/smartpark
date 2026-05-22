<?php
require_once 'includes/functions.php';
$pageTitle = 'Find Parking';
$activeNav = 'search';

$filterSuburb  = trim($_GET['suburb']   ?? '');
$filterDate    = trim($_GET['date']     ?? date('Y-m-d'));
$filterTime    = trim($_GET['time']     ?? '');
$filterMaxRate = floatval($_GET['max_rate'] ?? 0);
$filterSort    = trim($_GET['sort']     ?? 'availability');

try {
    $db  = getDB();
    $sql = "
        SELECT c.park_id AS id, c.name, c.address, c.suburb,
               c.total_spots AS total, c.occupied, c.active,
               COALESCE(MIN(z.rate_per_hour), 0) AS rate,
               GROUP_CONCAT(DISTINCT z.zone_label ORDER BY z.zone_label SEPARATOR '|') AS zones_raw
        FROM car_parks c
        LEFT JOIN parking_zones z ON z.park_id = c.park_id
        WHERE c.active = 1
    ";
    $params = [];
    if ($filterSuburb) {
        $sql .= " AND c.suburb LIKE ?";
        $params[] = '%' . $filterSuburb . '%';
    }
    if ($filterMaxRate > 0) {
        $sql .= " AND c.park_id IN (
            SELECT park_id FROM parking_zones WHERE rate_per_hour <= ?
        )";
        $params[] = $filterMaxRate;
    }
    $sql .= " GROUP BY c.park_id";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $allParks = $stmt->fetchAll();

    foreach ($allParks as &$p) {
        $p['zones']    = $p['zones_raw'] ? explode('|', $p['zones_raw']) : ['Zone A'];
        $p['features'] = ['CCTV', 'Secure'];
    }
    unset($p);

} catch (PDOException $e) {
    error_log('[SmartPark search] ' . $e->getMessage());
    $allParks = [];
}

// Sort
usort($allParks, function($a, $b) use ($filterSort) {
    switch ($filterSort) {
        case 'rate_asc':  return $a['rate'] <=> $b['rate'];
        case 'rate_desc': return $b['rate'] <=> $a['rate'];
        case 'name':      return strcmp($a['name'], $b['name']);
        default:
            return ($b['total'] - $b['occupied']) <=> ($a['total'] - $a['occupied']);
    }
});

$displayParks = $allParks;
$suburbs      = [];
try {
    $s       = getDB()->query("SELECT DISTINCT suburb FROM car_parks WHERE active=1 ORDER BY suburb");
    $suburbs = $s->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

require 'includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>&#x1F50D; Find Parking</h1>
    <p>Search available car parks across Sydney in real time</p>
  </div>
</div>

<div class="container section-sm">

  <div class="search-filters">
    <form method="GET" action="search.php">
      <div class="search-filters-row">
        <div class="form-group" style="margin:0;">
          <label for="suburb">&#x1F4CD; Suburb</label>
          <select name="suburb" id="suburb">
            <option value="">All Suburbs</option>
            <?php foreach ($suburbs as $s): ?>
              <option value="<?= h($s) ?>" <?= $filterSuburb===$s?'selected':'' ?>><?= h($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label for="date">&#x1F4C5; Date</label>
          <input type="date" name="date" id="date" value="<?= h($filterDate) ?>" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label for="time">&#x23F0; Arrival Time</label>
          <input type="time" name="time" id="time" value="<?= h($filterTime) ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label for="max_rate">&#x1F4B2; Max Rate ($/hr)</label>
          <select name="max_rate" id="max_rate">
            <option value="">Any Rate</option>
            <option value="4"  <?= $filterMaxRate==4 ?'selected':'' ?>>Up to $4.00</option>
            <option value="5"  <?= $filterMaxRate==5 ?'selected':'' ?>>Up to $5.00</option>
            <option value="7"  <?= $filterMaxRate==7 ?'selected':'' ?>>Up to $7.00</option>
            <option value="10" <?= $filterMaxRate==10?'selected':'' ?>>Up to $10.00</option>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label for="sort">&#x2195; Sort By</label>
          <select name="sort" id="sort">
            <option value="availability" <?= $filterSort==='availability'?'selected':'' ?>>Most Available</option>
            <option value="rate_asc"     <?= $filterSort==='rate_asc'    ?'selected':'' ?>>Price: Low to High</option>
            <option value="rate_desc"    <?= $filterSort==='rate_desc'   ?'selected':'' ?>>Price: High to Low</option>
            <option value="name"         <?= $filterSort==='name'        ?'selected':'' ?>>Name A&ndash;Z</option>
          </select>
        </div>
        <div style="display:flex;align-items:flex-end;">
          <button type="submit" class="btn btn-primary" style="height:42px;">Search</button>
        </div>
      </div>
    </form>
  </div>

  <?php
    $totalFreeSpots = array_sum(array_map(fn($p) => $p['total'] - $p['occupied'], $displayParks));
  ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <p class="text-muted">
      Showing <strong><?= count($displayParks) ?></strong> car park<?= count($displayParks)!==1?'s':'' ?>
      <?= $filterSuburb ? ' in <strong>'.h($filterSuburb).'</strong>' : '' ?>
      &mdash; <strong style="color:var(--success);"><?= $totalFreeSpots ?> spots</strong> available
    </p>
    <?php if ($filterSuburb || $filterMaxRate): ?>
      <a href="search.php" class="text-muted" style="font-size:0.85rem;">&#x2715; Clear filters</a>
    <?php endif; ?>
  </div>

  <?php if (empty($displayParks)): ?>
    <div class="card text-center" style="padding:3rem;">
      <div style="font-size:3rem;margin-bottom:1rem;">&#x1F697;</div>
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
        $avail     = $park['total'] - $park['occupied'];
        $pct       = $park['total'] > 0 ? round(($avail / $park['total']) * 100) : 0;
        $fillClass = $pct > 50 ? 'avail-high' : ($pct > 20 ? 'avail-mid' : 'avail-low');
        $avInfo    = availabilityLabel($avail, $park['total']);
      ?>
        <div class="park-card" data-suburb="<?= h($park['suburb']) ?>" data-rate="<?= $park['rate'] ?>">
          <div class="park-card-header">
            <div>
              <div class="park-card-name"><?= h($park['name']) ?></div>
              <div class="park-card-suburb">&#x1F4CD; <?= h($park['address']) ?>, <?= h($park['suburb']) ?></div>
            </div>
            <span class="badge <?= $avInfo['class'] ?>"><?= $avInfo['label'] ?></span>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:0.82rem;margin-bottom:0.3rem;">
              <span class="text-muted">Availability</span>
              <span><?= $avail ?> of <?= $park['total'] ?> spots free</span>
            </div>
            <div class="avail-bar">
              <div class="avail-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <div class="park-card-meta">
            <span>&#x1F3E2; <?= implode(', ', $park['zones']) ?></span>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-top:0.6rem;">
            <?php foreach ($park['features'] as $feat): ?>
              <span class="badge badge-muted" style="font-size:0.72rem;"><?= h($feat) ?></span>
            <?php endforeach; ?>
          </div>
          <div class="park-card-footer">
            <div class="park-rate">$<?= number_format($park['rate'],2) ?> <small>/ hour</small></div>
            <?php if (isLoggedIn()): ?>
              <a href="reserve.php?park_id=<?= $park['id'] ?>" class="btn btn-primary btn-sm">Reserve Now &rarr;</a>
            <?php else: ?>
              <a href="login.php" class="btn btn-dark btn-sm">Login to Reserve</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="alert alert-info mt-3">
    <span>&#x2139;</span>
    <span>
      Availability is updated by car park administrators. All reservations are confirmed instantly.
      <?php if (!isLoggedIn()): ?>
        <a href="register.php">Create a free account</a> to reserve a spot.
      <?php endif; ?>
    </span>
  </div>
</div>

<?php require 'includes/footer.php'; ?>