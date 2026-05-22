<?php
/**
 * SmartPark - Monthly Subscription (subscription.php)
 * ICT312 Advanced Web Information Systems
 */
require_once 'includes/functions.php';
requireLogin('login.php');

// Subscription plans
$plans = [
    [
        'id'           => 'basic',
        'name'         => 'Basic',
        'price'        => 49.00,
        'color'        => 'var(--primary)',
        'icon'         => '&#x1F17F;',
        'features'     => [
            '1 reserved spot per month',
            'Access to 2 car parks',
            'Up to 40 hours parking/month',
            'Standard zones only',
            'Email booking confirmation',
            'Cancel anytime',
        ],
        'not_included' => [
            'Priority spot selection',
            'Premium zones',
            '24/7 phone support',
        ],
    ],
    [
        'id'           => 'standard',
        'name'         => 'Standard',
        'price'        => 89.00,
        'color'        => 'var(--accent)',
        'icon'         => '&#x2B50;',
        'badge'        => 'Most Popular',
        'features'     => [
            '1 reserved spot per month',
            'Access to all 5 car parks',
            'Up to 80 hours parking/month',
            'Standard and premium zones',
            'Email and SMS confirmation',
            'Priority spot selection',
            'Cancel anytime',
        ],
        'not_included' => [
            '24/7 phone support',
        ],
    ],
    [
        'id'           => 'premium',
        'name'         => 'Premium',
        'price'        => 149.00,
        'color'        => '#6c3fc5',
        'icon'         => '&#x1F451;',
        'features'     => [
            '2 reserved spots per month',
            'Access to all 5 car parks',
            'Unlimited parking hours',
            'All zones including VIP',
            'Email and SMS confirmation',
            'Priority spot selection',
            '24/7 phone support',
            'Free cancellation anytime',
            'Monthly invoice provided',
        ],
        'not_included' => [],
    ],
];

// Car parks from database
try {
    $db      = getDB();
    $stmt    = $db->query("SELECT park_id AS id, name, suburb FROM car_parks WHERE active = 1 ORDER BY suburb");
    $carParks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[SmartPark subscription] ' . $e->getMessage());
    $carParks = [];
}

$formError   = '';
$formSuccess = false;
$newSub      = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $formError = 'Invalid form submission. Please try again.';

    } elseif (isset($_POST['cancel_subscription'])) {

        $subId = $_POST['sub_id'] ?? '';

        // Update in database
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                UPDATE subscriptions
                SET status = 'cancelled', cancelled_at = CURDATE()
                WHERE sub_ref = ? AND user_id = ?
            ");
            $stmt->execute([$subId, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log('[SmartPark cancel sub] ' . $e->getMessage());
        }

        $_SESSION['flash_success'] = 'Your subscription has been cancelled. It remains active until the end of the billing period.';
        header('Location: subscription.php');
        exit;

    } elseif (isset($_POST['subscribe'])) {

        $planId    = $_POST['plan_id']    ?? '';
        $parkId    = intval($_POST['park_id']    ?? 0);
        $startDate = trim($_POST['start_date']   ?? '');

        // Find valid plan
        $validPlan = null;
        foreach ($plans as $p) {
            if ($p['id'] === $planId) { $validPlan = $p; break; }
        }

        // Find valid park
        $validPark = null;
        foreach ($carParks as $p) {
            if ($p['id'] === $parkId) { $validPark = $p; break; }
        }

        if (!$validPlan) {
            $formError = 'Please select a valid plan.';
        } elseif (!$validPark) {
            $formError = 'Please select a car park.';
        } elseif (!$startDate || strtotime($startDate) < strtotime('today')) {
            $formError = 'Please select a valid start date.';
        } else {

            // Check for existing active subscription for same park
            try {
                $db   = getDB();
                $stmt = $db->prepare("
                    SELECT sub_id FROM subscriptions
                    WHERE user_id = ? AND park_id = ? AND status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$_SESSION['user_id'], $parkId]);
                $existing = $stmt->fetch();
            } catch (PDOException $e) {
                error_log('[SmartPark sub check] ' . $e->getMessage());
                $existing = false;
            }

            if ($existing) {
                $formError = 'You already have an active subscription for this car park.';
            } else {
                $subRef  = 'SUB-' . strtoupper(substr(md5(uniqid()), 0, 8));
                $endDate = date('Y-m-d', strtotime($startDate . ' +1 month'));

                // Save to database
                try {
                    $db   = getDB();
                    $stmt = $db->prepare("
                        INSERT INTO subscriptions
                            (sub_ref, user_id, park_id, plan_id, plan_name, price, start_date, end_date, status, created_at)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                    ");
                    $stmt->execute([
                        $subRef,
                        $_SESSION['user_id'],
                        $parkId,
                        $planId,
                        $validPlan['name'],
                        $validPlan['price'],
                        $startDate,
                        $endDate,
                    ]);

                    $newSub = [
                        'id'         => $subRef,
                        'plan_id'    => $planId,
                        'plan_name'  => $validPlan['name'],
                        'park_id'    => $parkId,
                        'park_name'  => $validPark['name'],
                        'suburb'     => $validPark['suburb'],
                        'price'      => $validPlan['price'],
                        'start_date' => $startDate,
                        'end_date'   => $endDate,
                        'status'     => 'active',
                    ];

                    $formSuccess = true;

                } catch (PDOException $e) {
                    error_log('[SmartPark subscribe] ' . $e->getMessage());
                    $formError = 'A system error occurred. Please try again.';
                }
            }
        }
    }
}

// Load subscriptions from database
try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT s.sub_ref AS id, s.plan_id, s.plan_name, s.park_id,
               c.name AS park_name, c.suburb, s.price,
               s.start_date, s.end_date, s.status,
               s.cancelled_at, s.created_at
        FROM subscriptions s
        JOIN car_parks c ON c.park_id = s.park_id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $allSubs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[SmartPark sub load] ' . $e->getMessage());
    $allSubs = [];
}

$activeSubs    = array_filter($allSubs, fn($s) => $s['status'] === 'active');
$cancelledSubs = array_filter($allSubs, fn($s) => $s['status'] === 'cancelled');

$pageTitle = 'Monthly Subscription';
$activeNav = 'subscription';
require 'includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>&#x1F4C5; Monthly Parking Subscription</h1>
    <p>Reserve your spot every month &mdash; save money and never worry about parking again</p>
  </div>
</div>

<div class="container section-sm">

  <?= flash('success') ?>
  <?= flash('warning') ?>

  <?php if ($formError): ?>
    <div class="alert alert-danger">
      <span>&#x274C;</span><span><?= h($formError) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($formSuccess && $newSub): ?>
    <div class="card text-center" style="padding:3rem;max-width:560px;margin:0 auto 2rem;">
      <div style="font-size:4rem;margin-bottom:1rem;">&#x2705;</div>
      <h2 style="color:var(--success);">Subscription Activated!</h2>
      <p class="text-muted mt-1">Your monthly parking subscription is now active.</p>
      <div class="booking-summary mt-2" style="text-align:left;">
        <div class="booking-summary-row"><span>Reference</span><strong><?= h($newSub['id']) ?></strong></div>
        <div class="booking-summary-row"><span>Plan</span><strong><?= h($newSub['plan_name']) ?></strong></div>
        <div class="booking-summary-row"><span>Car Park</span><span><?= h($newSub['park_name']) ?></span></div>
        <div class="booking-summary-row"><span>Start Date</span><span><?= date('d M Y', strtotime($newSub['start_date'])) ?></span></div>
        <div class="booking-summary-row"><span>Next Billing</span><span><?= date('d M Y', strtotime($newSub['end_date'])) ?></span></div>
        <div class="booking-summary-row total">
          <span>Monthly Cost</span>
          <strong>$<?= number_format($newSub['price'], 2) ?>/month</strong>
        </div>
      </div>
      <div style="display:flex;gap:0.75rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;">
        <a href="subscription.php" class="btn btn-primary">View My Subscriptions</a>
        <a href="dashboard.php"    class="btn btn-dark">Go to Dashboard</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- ===== ACTIVE SUBSCRIPTIONS ===== -->
  <?php if (!empty($activeSubs)): ?>
    <div style="margin-bottom:2rem;">
      <h2 style="margin-bottom:1rem;">&#x1F4CB; My Active Subscriptions</h2>
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach ($activeSubs as $sub): ?>
          <div class="card" style="border-left:4px solid var(--success);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.75rem;">
              <div>
                <h4><?= h($sub['park_name']) ?></h4>
                <p class="text-muted" style="font-size:0.85rem;margin-top:0.25rem;">
                  &#x1F4CD; <?= h($sub['suburb']) ?> &nbsp;&middot;&nbsp;
                  Plan: <strong><?= h($sub['plan_name']) ?></strong> &nbsp;&middot;&nbsp;
                  Ref: <?= h($sub['id']) ?>
                </p>
              </div>
              <span class="badge badge-success">Active</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;
                        margin-top:0.85rem;padding:0.85rem;
                        background:var(--bg);border-radius:var(--radius);">
              <div>
                <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">Start Date</div>
                <div style="font-weight:600;"><?= date('d M Y', strtotime($sub['start_date'])) ?></div>
              </div>
              <div>
                <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">Next Billing</div>
                <div style="font-weight:600;"><?= date('d M Y', strtotime($sub['end_date'])) ?></div>
              </div>
              <div>
                <div class="text-muted" style="font-size:0.75rem;text-transform:uppercase;margin-bottom:0.2rem;">Monthly Cost</div>
                <div style="font-weight:700;font-size:1.1rem;color:var(--primary);">
                  $<?= number_format($sub['price'], 2) ?>
                </div>
              </div>
            </div>

            <div style="margin-top:0.85rem;">
              <form method="POST" action="subscription.php"
                    onsubmit="return confirm('Cancel this subscription? It will remain active until <?= date('d M Y', strtotime($sub['end_date'])) ?>.');"
                    style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="sub_id" value="<?= h($sub['id']) ?>">
                <button type="submit" name="cancel_subscription" class="btn btn-danger btn-sm">
                  &#x2715; Cancel Subscription
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ===== PLANS ===== -->
  <h2 style="margin-bottom:0.5rem;">&#x1F4B0; Choose a Plan</h2>
  <p class="text-muted" style="margin-bottom:1.5rem;">All plans renew automatically each month. Cancel anytime.</p>

  <div class="grid-3" style="margin-bottom:2rem;">
    <?php foreach ($plans as $plan): ?>
      <div class="card" style="position:relative;border-top:4px solid <?= $plan['color'] ?>;
           <?= isset($plan['badge']) ? 'box-shadow:var(--shadow-md);' : '' ?>">

        <?php if (isset($plan['badge'])): ?>
          <div style="position:absolute;top:-1px;right:1rem;
                      background:<?= $plan['color'] ?>;color:var(--primary);
                      font-size:0.72rem;font-weight:700;padding:0.2rem 0.6rem;
                      border-radius:0 0 6px 6px;letter-spacing:0.3px;">
            <?= h($plan['badge']) ?>
          </div>
        <?php endif; ?>

        <div style="text-align:center;padding:0.5rem 0 1rem;">
          <div style="font-size:2rem;margin-bottom:0.5rem;"><?= $plan['icon'] ?></div>
          <h3 style="color:<?= $plan['color'] ?>;"><?= h($plan['name']) ?></h3>
          <div style="font-family:'Syne',sans-serif;font-size:2.2rem;font-weight:800;
                      color:var(--primary);margin:0.5rem 0 0.1rem;">
            $<?= number_format($plan['price'], 2) ?>
          </div>
          <div class="text-muted" style="font-size:0.85rem;">per month</div>
        </div>

        <div style="border-top:1px solid var(--border);padding-top:1rem;margin-bottom:1rem;">
          <?php foreach ($plan['features'] as $f): ?>
            <div style="display:flex;align-items:flex-start;gap:0.5rem;margin-bottom:0.5rem;font-size:0.88rem;">
              <span style="color:var(--success);flex-shrink:0;">&#x2713;</span>
              <span><?= h($f) ?></span>
            </div>
          <?php endforeach; ?>
          <?php foreach ($plan['not_included'] as $f): ?>
            <div style="display:flex;align-items:flex-start;gap:0.5rem;margin-bottom:0.5rem;font-size:0.88rem;opacity:0.45;">
              <span style="flex-shrink:0;">&#x2715;</span>
              <span><?= h($f) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <button class="btn btn-block btn-lg"
                style="background:<?= $plan['color'] ?>;
                       color:<?= $plan['id']==='standard' ? 'var(--primary)' : 'white' ?>;
                       border-color:<?= $plan['color'] ?>;"
                onclick="openSubscribeForm('<?= $plan['id'] ?>','<?= h($plan['name']) ?>',<?= $plan['price'] ?>)">
          Get <?= h($plan['name']) ?> Plan
        </button>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ===== SUBSCRIBE FORM ===== -->
  <div id="subscribeFormSection" style="display:none;max-width:600px;margin:0 auto 2rem;">
    <div class="card">
      <div class="card-header">
        <h3>&#x1F4DD; Complete Your Subscription</h3>
        <p class="text-muted mt-1" id="formPlanLabel"></p>
      </div>

      <form method="POST" action="subscription.php" id="subscribeForm">
        <?= csrfField() ?>
        <input type="hidden" name="plan_id" id="form_plan_id" value="">

        <div class="form-group">
          <label for="park_id">Select Car Park</label>
          <select name="park_id" id="park_id" required>
            <option value="">— Choose a car park —</option>
            <?php foreach ($carParks as $p): ?>
              <option value="<?= $p['id'] ?>"><?= h($p['name']) ?> &mdash; <?= h($p['suburb']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="start_date">Subscription Start Date</label>
          <input type="date" name="start_date" id="start_date" required
                 min="<?= date('Y-m-d') ?>"
                 value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
        </div>

        <div class="booking-summary">
          <div class="booking-summary-row"><span>Plan</span><span id="sum_plan_name">—</span></div>
          <div class="booking-summary-row"><span>Billing</span><span>Monthly, auto-renews</span></div>
          <div class="booking-summary-row"><span>Cancel</span><span>Anytime, no fees</span></div>
          <div class="booking-summary-row total">
            <span>Monthly Total</span><span id="sum_plan_price">—</span>
          </div>
        </div>

        <div class="alert alert-info" style="font-size:0.82rem;">
          <span>&#x2139;</span>
          <span>
            By subscribing you agree to automatic monthly billing.
            Cancel anytime before your renewal date.
            Protected under the Australian Consumer Law.
          </span>
        </div>

        <div style="display:flex;gap:0.75rem;">
          <button type="submit" name="subscribe" class="btn btn-primary btn-lg" style="flex:1;">
            &#x2705; Confirm Subscription
          </button>
          <button type="button" class="btn btn-dark" onclick="closeSubscribeForm()">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===== CANCELLED SUBSCRIPTIONS ===== -->
  <?php if (!empty($cancelledSubs)): ?>
    <div style="margin-top:2rem;">
      <h3 style="margin-bottom:1rem;">&#x1F552; Past Subscriptions</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Reference</th><th>Plan</th><th>Car Park</th>
              <th>Start</th><th>End</th><th>Price</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cancelledSubs as $sub): ?>
              <tr>
                <td><?= h($sub['id']) ?></td>
                <td><?= h($sub['plan_name']) ?></td>
                <td><?= h($sub['park_name']) ?></td>
                <td><?= date('d M Y', strtotime($sub['start_date'])) ?></td>
                <td><?= date('d M Y', strtotime($sub['end_date'])) ?></td>
                <td>$<?= number_format($sub['price'], 2) ?>/mo</td>
                <td><span class="badge badge-danger">Cancelled</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- ===== FAQ ===== -->
  <div style="margin-top:2.5rem;">
    <h3 style="margin-bottom:1rem;">&#x2753; Subscription FAQ</h3>
    <div style="display:flex;flex-direction:column;gap:0.75rem;max-width:700px;">
      <?php
      $faqs = [
        ['q'=>'Can I cancel anytime?',
         'a'=>'Yes. You can cancel at any time from this page. Your subscription remains active until the end of the current billing period.'],
        ['q'=>'Will I be charged automatically?',
         'a'=>'Yes, subscriptions automatically renew each month. You will receive an email reminder 3 days before each renewal.'],
        ['q'=>'Can I change my car park?',
         'a'=>'You can cancel your current subscription and create a new one for a different car park. Changes take effect from the next billing cycle.'],
        ['q'=>'Is there a discount for annual billing?',
         'a'=>'Annual billing is coming soon. Subscribe to our newsletter to be notified when annual plans become available.'],
        ['q'=>'What happens if my spot is unavailable?',
         'a'=>'As a subscriber you have priority access. If your usual spot is unavailable we will allocate the nearest available spot at no extra charge.'],
      ];
      foreach ($faqs as $faq): ?>
        <details style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
          <summary style="padding:0.75rem 1rem;font-weight:600;font-size:0.9rem;cursor:pointer;background:var(--bg);">
            <?= h($faq['q']) ?>
          </summary>
          <div style="padding:0.75rem 1rem;font-size:0.88rem;color:var(--text-muted);border-top:1px solid var(--border);">
            <?= h($faq['a']) ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>
function openSubscribeForm(planId, planName, planPrice) {
    document.getElementById('form_plan_id').value        = planId;
    document.getElementById('formPlanLabel').textContent = planName + ' Plan — $' + planPrice.toFixed(2) + '/month';
    document.getElementById('sum_plan_name').textContent  = planName;
    document.getElementById('sum_plan_price').textContent = '$' + planPrice.toFixed(2) + '/month';
    var section = document.getElementById('subscribeFormSection');
    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeSubscribeForm() {
    document.getElementById('subscribeFormSection').style.display = 'none';
}
</script>

<?php require 'includes/footer.php'; ?>