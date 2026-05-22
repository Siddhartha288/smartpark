<?php
require_once 'includes/functions.php';
requireLogin('login.php');

try {
    $db   = getDB();
    $stmt = $db->query("
        SELECT c.park_id AS id, c.name, c.suburb,
               COALESCE(MIN(z.rate_per_hour), 0) AS rate
        FROM car_parks c
        LEFT JOIN parking_zones z ON z.park_id = c.park_id
        WHERE c.active = 1
        GROUP BY c.park_id
        ORDER BY c.suburb
    ");
    $carParks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[SmartPark reserve] ' . $e->getMessage());
    $carParks = [];
}

$selectedParkId = max(1, intval($_GET['park_id'] ?? ($carParks[0]['id'] ?? 1)));
$selectedPark   = $carParks[0] ?? ['id'=>1,'name'=>'SmartPark','suburb'=>'Sydney','rate'=>5.00];
foreach ($carParks as $p) {
    if ($p['id'] === $selectedParkId) { $selectedPark = $p; break; }
}

$formError   = '';
$formSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $formError = 'Invalid form submission. Please try again.';
    } else {
        $parkId    = intval($_POST['park_id']    ?? 0);
        $spotNum   = trim($_POST['spot_number']  ?? '');
        $startTime = trim($_POST['start_time']   ?? '');
        $endTime   = trim($_POST['end_time']     ?? '');

        if (!$parkId || !$spotNum || !$startTime || !$endTime) {
            $formError = 'Please fill in all fields and select a parking spot.';
        } elseif (strtotime($endTime) <= strtotime($startTime)) {
            $formError = 'End time must be after start time.';
        } elseif (strtotime($startTime) < time() - 120) {
            $formError = 'Start time cannot be in the past.';
        } elseif ((strtotime($endTime) - strtotime($startTime)) > 43200) {
            $formError = 'Bookings cannot exceed 12 hours.';
        } else {
            try {
                $db = getDB();

                // Check spot not already booked
                $stmt = $db->prepare("
                    SELECT booking_id FROM bookings
                    WHERE park_id = ? AND spot_number = ?
                      AND status IN ('confirmed','pending')
                      AND start_time < ? AND end_time > ?
                    LIMIT 1
                ");
                $stmt->execute([$parkId, $spotNum, $endTime, $startTime]);
                if ($stmt->fetch()) {
                    $formError = 'Sorry, that spot is already booked for that time. Please choose another.';
                } else {
                    // Get zone
                    $stmt = $db->prepare("
                        SELECT zone_id FROM parking_zones WHERE park_id = ? ORDER BY zone_id LIMIT 1
                    ");
                    $stmt->execute([$parkId]);
                    $zone   = $stmt->fetch();
                    $zoneId = $zone ? $zone['zone_id'] : 1;

                    $confirmedTotal = calcBookingTotal($startTime, $endTime, $selectedPark['rate']);

                    // Insert booking
                    $stmt = $db->prepare("
                        INSERT INTO bookings
                            (user_id, zone_id, park_id, spot_number, start_time, end_time, total_amount, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed')
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'], $zoneId, $parkId,
                        $spotNum, $startTime, $endTime, $confirmedTotal
                    ]);
                    $bookingId = $db->lastInsertId();

                    // Insert payment record
                    $db->prepare("
                        INSERT INTO payments (booking_id, amount, method, status)
                        VALUES (?, ?, 'card', 'pending')
                    ")->execute([$bookingId, $confirmedTotal]);

                    $bookingRef  = 'SP-' . strtoupper(substr(md5($bookingId . uniqid()), 0, 8));
                    $formSuccess = 'Booking confirmed! Reference: <strong>' . h($bookingRef) . '</strong>'
                                 . ' &mdash; Total: <strong>$' . number_format($confirmedTotal, 2) . '</strong>.'
                                 . ' <a href="dashboard.php">View in My Dashboard &rarr;</a>';
                }
            } catch (PDOException $e) {
                error_log('[SmartPark reserve] ' . $e->getMessage());
                $formError = 'A system error occurred. Please try again.';
            }
        }
    }
}

// Generate demo spots
$allSpots = [];
for ($i = 1; $i <= 30; $i++) {
    $zone  = $i <= 15 ? 'A' : 'B';
    $num   = $i <= 15 ? $i : $i - 15;
    $label = $zone . '-' . str_pad($num, 2, '0', STR_PAD_LEFT);

    // Check if spot is booked in DB
    $occupied = false;
    try {
        $stmt = getDB()->prepare("
            SELECT booking_id FROM bookings
            WHERE park_id = ? AND spot_number = ?
              AND status IN ('confirmed','pending')
              AND start_time <= NOW() AND end_time >= NOW()
            LIMIT 1
        ");
        $stmt->execute([$selectedParkId, $label]);
        $occupied = (bool)$stmt->fetch();
    } catch (PDOException $e) {
        $occupied = in_array($i, [3,7,11,14,18,22,25]);
    }

    $allSpots[] = ['label'=>$label, 'zone'=>$zone, 'occupied'=>$occupied];
}
$spotsA = array_filter($allSpots, fn($s) => $s['zone']==='A');
$spotsB = array_filter($allSpots, fn($s) => $s['zone']==='B');

$pageTitle = 'Reserve a Spot';
$activeNav = 'reserve';
require 'includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>&#x1F17F; Reserve a Parking Spot</h1>
    <p>Choose your car park, time, and spot &mdash; confirmed instantly</p>
  </div>
</div>

<div class="container section-sm">

  <div class="steps" style="margin-bottom:2rem;">
    <div class="step active">&#x2460; Select Details</div>
    <div class="step">&#x2461; Choose Spot</div>
    <div class="step">&#x2462; Confirm</div>
  </div>

  <?php if ($formSuccess): ?>
    <div class="alert alert-success"><span>&#x2705;</span><span><?= $formSuccess ?></span></div>
  <?php endif; ?>
  <?php if ($formError): ?>
    <div class="alert alert-danger"><span>&#x274C;</span><span><?= h($formError) ?></span></div>
  <?php endif; ?>

  <form method="POST" action="reserve.php">
    <?= csrfField() ?>
    <input type="hidden" name="spot_number" id="selected_spot" value="">
    <input type="hidden" name="total_amount" id="hidden_total" value="0">

    <div class="grid-2" style="gap:1.75rem;align-items:start;">
      <div>
        <div class="card">
          <div class="card-header"><h3>&#x1F4CB; Booking Details</h3></div>
          <div class="form-group">
            <label for="park_id">Car Park</label>
            <select name="park_id" id="park_id" onchange="onParkChange(this)">
              <?php foreach ($carParks as $p): ?>
                <option value="<?= $p['id'] ?>" data-rate="<?= $p['rate'] ?>"
                  <?= $p['id']===$selectedParkId?'selected':'' ?>>
                  <?= h($p['name']) ?> &mdash; $<?= number_format($p['rate'],2) ?>/hr
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="start_time">Start Date &amp; Time</label>
              <input type="datetime-local" name="start_time" id="start_time" required
                     min="<?= date('Y-m-d\TH:i') ?>"
                     value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>"
                     onchange="calcTotal()">
            </div>
            <div class="form-group">
              <label for="end_time">End Date &amp; Time</label>
              <input type="datetime-local" name="end_time" id="end_time" required
                     min="<?= date('Y-m-d\TH:i') ?>"
                     value="<?= date('Y-m-d\TH:i', strtotime('+3 hours')) ?>"
                     onchange="calcTotal()">
            </div>
          </div>
        </div>

        <div class="card mt-2">
          <div class="card-header"><h3>&#x1F4B0; Booking Summary</h3></div>
          <div class="booking-summary">
            <div class="booking-summary-row"><span>Car Park</span><span id="sum_park"><?= h($selectedPark['name']) ?></span></div>
            <div class="booking-summary-row"><span>Selected Spot</span><span id="sum_spot">Not selected</span></div>
            <div class="booking-summary-row"><span>Duration</span><span id="sum_dur">2h 0m</span></div>
            <div class="booking-summary-row"><span>Rate</span><span id="sum_rate">$<?= number_format($selectedPark['rate'],2) ?>/hr</span></div>
            <div class="booking-summary-row total"><span>Estimated Total</span><span id="sum_total">$<?= number_format($selectedPark['rate']*2,2) ?></span></div>
          </div>
          <div class="alert alert-warning" id="costWarning" hidden style="font-size:0.85rem;">
            <span>&#x26A0;</span><span></span>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg" onclick="return checkSpot()">
            &#x2705; Confirm Reservation
          </button>
          <p class="text-muted text-center mt-1" style="font-size:0.8rem;">Cancel up to 1 hour before start time.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>&#x1F5FA; Choose Your Spot</h3>
          <p class="text-muted" style="font-size:0.85rem;margin-top:0.2rem;" id="grid_park_name"><?= h($selectedPark['name']) ?></p>
        </div>
        <div class="spot-legend">
          <div class="spot-legend-item"><div class="spot-legend-dot" style="background:#edfaf3;border-color:var(--success);"></div><span>Available</span></div>
          <div class="spot-legend-item"><div class="spot-legend-dot" style="background:#fde8e6;border-color:var(--danger);"></div><span>Occupied</span></div>
          <div class="spot-legend-item"><div class="spot-legend-dot" style="background:var(--accent);border-color:var(--accent-hover);"></div><span>Selected</span></div>
        </div>

        <p style="font-size:0.82rem;font-weight:700;color:var(--text-muted);margin:1rem 0 0.4rem;text-transform:uppercase;letter-spacing:0.4px;">Zone A</p>
        <div class="spot-grid">
          <?php foreach ($spotsA as $spot): ?>
            <div class="spot <?= $spot['occupied']?'spot-occupied':'spot-available' ?>"
                 data-spot-id="<?= h($spot['label']) ?>"
                 title="Spot <?= h($spot['label']) ?>">
              <?= h($spot['label']) ?>
              <span style="font-size:0.55rem;margin-top:0.1rem;"><?= $spot['occupied']?'&#x1F697;':'&#x2713;' ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <p style="font-size:0.82rem;font-weight:700;color:var(--text-muted);margin:1rem 0 0.4rem;text-transform:uppercase;letter-spacing:0.4px;">Zone B</p>
        <div class="spot-grid">
          <?php foreach ($spotsB as $spot): ?>
            <div class="spot <?= $spot['occupied']?'spot-occupied':'spot-available' ?>"
                 data-spot-id="<?= h($spot['label']) ?>"
                 title="Spot <?= h($spot['label']) ?>">
              <?= h($spot['label']) ?>
              <span style="font-size:0.55rem;margin-top:0.1rem;"><?= $spot['occupied']?'&#x1F697;':'&#x2713;' ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div id="spotMsg" class="alert alert-info mt-2" style="display:none;">
          <span>&#x2705;</span><span>Spot <strong id="spotMsgId"></strong> selected.</span>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
var currentRate = <?= $selectedPark['rate'] ?>;

document.querySelectorAll('.spot-available').forEach(function(spot) {
    spot.addEventListener('click', function() {
        document.querySelectorAll('.spot').forEach(function(s){ s.classList.remove('spot-selected'); });
        spot.classList.add('spot-selected');
        var id = spot.dataset.spotId;
        document.getElementById('selected_spot').value = id;
        document.getElementById('sum_spot').textContent = 'Spot ' + id;
        document.getElementById('spotMsgId').textContent = id;
        document.getElementById('spotMsg').style.display = 'flex';
        calcTotal();
    });
});

function onParkChange(sel) {
    var opt = sel.options[sel.selectedIndex];
    currentRate = parseFloat(opt.dataset.rate);
    document.getElementById('sum_park').textContent = opt.text.split(' — ')[0];
    document.getElementById('sum_rate').textContent = '$' + currentRate.toFixed(2) + '/hr';
    document.getElementById('grid_park_name').textContent = opt.text.split(' — ')[0];
    calcTotal();
}

function calcTotal() {
    var start = new Date(document.getElementById('start_time').value);
    var end   = new Date(document.getElementById('end_time').value);
    if (isNaN(start) || isNaN(end) || end <= start) return;
    var hrs   = (end - start) / 3600000;
    var total = (hrs * currentRate).toFixed(2);
    var h     = Math.floor(hrs);
    var m     = Math.round((hrs - h) * 60);
    document.getElementById('sum_dur').textContent   = h + 'h ' + (m ? m+'m' : '0m');
    document.getElementById('sum_total').textContent = '$' + total;
    document.getElementById('hidden_total').value    = total;
    var warning = document.getElementById('costWarning');
    if (warning) {
        warning.hidden = parseFloat(total) < 50;
        warning.querySelector('span:last-child').textContent =
            'This booking will cost $' + total + '. Make sure your time selection is correct.';
    }
}

function checkSpot() {
    if (!document.getElementById('selected_spot').value) {
        alert('Please select a parking spot before confirming.');
        return false;
    }
    return true;
}
calcTotal();
</script>

<?php require 'includes/footer.php'; ?>