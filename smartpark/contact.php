<?php
require_once 'includes/functions.php';

$formData    = ['name'=>'','email'=>'','phone'=>'','subject'=>'','message'=>''];
$formErrors  = [];
$formSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $formErrors['general'] = 'Invalid form submission. Please try again.';
    } elseif (!checkRateLimit('contact', 3, 300)) {
        $formErrors['general'] = 'Too many submissions. Please wait a few minutes.';
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim(strtolower($_POST['email'] ?? ''));
        $phone   = trim($_POST['phone']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        $formData = compact('name','email','phone','subject','message');

        if (!$name || strlen($name) < 2)
            $formErrors['name']    = 'Please enter your name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $formErrors['email']   = 'Please enter a valid email address.';
        if ($phone && !preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $phone))
            $formErrors['phone']   = 'Please enter a valid phone number.';
        if (!$subject)
            $formErrors['subject'] = 'Please select a subject.';
        if (!$message || strlen($message) < 10)
            $formErrors['message'] = 'Message must be at least 10 characters.';
        if (strlen($message) > 2000)
            $formErrors['message'] = 'Message must be under 2000 characters.';

        if (empty($formErrors)) {
            try {
                $db   = getDB();
                $stmt = $db->prepare("
                    INSERT INTO contact_messages (name, email, phone, subject, message, status)
                    VALUES (?, ?, ?, ?, ?, 'unread')
                ");
                $stmt->execute([$name, $email, $phone ?: null, $subject, $message]);
                $formSuccess = true;
                $formData    = ['name'=>'','email'=>'','phone'=>'','subject'=>'','message'=>''];
            } catch (PDOException $e) {
                error_log('[SmartPark contact] ' . $e->getMessage());
                $formErrors['general'] = 'A system error occurred. Please try again later.';
            }
        }
    }
}

$subjects = [
    'General Enquiry','Booking Issue','Account Problem',
    'Billing Question','Report a Bug','Accessibility Feedback',
    'Request Account Deletion','Other',
];

$pageTitle = 'Contact Us';
$activeNav = 'contact';
require 'includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>&#x1F4EC; Contact Us</h1>
    <p>Questions, feedback, or support requests &mdash; we&apos;re here to help</p>
  </div>
</div>

<div class="container section-sm">
  <div class="grid-2" style="gap:2rem;align-items:start;">
    <div>
      <?php if ($formSuccess): ?>
        <div class="alert alert-success">
          <span>&#x2705;</span>
          <div><strong>Message sent!</strong><br>Thank you for contacting us. We aim to respond within 1&ndash;2 business days.</div>
        </div>
      <?php endif; ?>

      <?php if (!empty($formErrors['general'])): ?>
        <div class="alert alert-danger"><span>&#x274C;</span><span><?= h($formErrors['general']) ?></span></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header"><h3>Send a Message</h3></div>
        <form method="POST" action="contact.php" novalidate>
          <?= csrfField() ?>

          <div class="form-row">
            <div class="form-group">
              <label for="name">Your Name *</label>
              <input type="text" id="name" name="name" maxlength="100" placeholder="Jane Smith"
                     value="<?= h($formData['name']) ?>"
                     class="<?= !empty($formErrors['name'])?'error':'' ?>" required>
              <?php if (!empty($formErrors['name'])): ?>
                <div class="field-error show"><?= h($formErrors['name']) ?></div>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label for="email">Email Address *</label>
              <input type="email" id="email" name="email" placeholder="you@example.com"
                     value="<?= h($formData['email']) ?>"
                     class="<?= !empty($formErrors['email'])?'error':'' ?>" required>
              <?php if (!empty($formErrors['email'])): ?>
                <div class="field-error show"><?= h($formErrors['email']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label for="phone">Phone Number <span class="text-muted">(optional)</span></label>
            <input type="tel" id="phone" name="phone" placeholder="+61 4xx xxx xxx" maxlength="20"
                   value="<?= h($formData['phone']) ?>"
                   class="<?= !empty($formErrors['phone'])?'error':'' ?>">
            <?php if (!empty($formErrors['phone'])): ?>
              <div class="field-error show"><?= h($formErrors['phone']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label for="subject">Subject *</label>
            <select id="subject" name="subject"
                    class="<?= !empty($formErrors['subject'])?'error':'' ?>"
                    onchange="showResponseTime(this.value)" required>
              <option value="">— Select a subject —</option>
              <?php foreach ($subjects as $s): ?>
                <option value="<?= h($s) ?>" <?= $formData['subject']===$s?'selected':'' ?>><?= h($s) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($formErrors['subject'])): ?>
              <div class="field-error show"><?= h($formErrors['subject']) ?></div>
            <?php endif; ?>
          </div>

          <div class="alert alert-warning" id="responseTimeBox" hidden style="font-size:0.82rem;">
            <span>&#x23F1;</span><span id="responseTimeMsg"></span>
          </div>

          <div class="form-group">
            <label for="message">Message * <span class="text-muted">(10&ndash;2000 characters)</span></label>
            <textarea id="message" name="message" maxlength="2000"
                      placeholder="Please describe your enquiry in detail..."
                      class="<?= !empty($formErrors['message'])?'error':'' ?>"
                      oninput="updateCharCount(this,'charCount',2000)"><?= h($formData['message']) ?></textarea>
            <div style="display:flex;justify-content:space-between;">
              <?php if (!empty($formErrors['message'])): ?>
                <div class="field-error show"><?= h($formErrors['message']) ?></div>
              <?php else: ?><div></div><?php endif; ?>
              <span id="charCount" class="text-muted" style="font-size:0.78rem;">0 / 2000</span>
            </div>
          </div>

          <div class="alert alert-info" style="font-size:0.82rem;">
            <span>&#x1F512;</span>
            <span>Your message is transmitted securely. We do not share your details with third parties.</span>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">Send Message &rarr;</button>
        </form>
      </div>
    </div>

    <div>
      <div class="card mb-2">
        <div class="card-header"><h3>&#x1F4CD; Get in Touch</h3></div>
        <div style="display:flex;flex-direction:column;gap:1.1rem;">
          <div style="display:flex;gap:0.85rem;align-items:flex-start;">
            <div style="font-size:1.3rem;flex-shrink:0;">&#x1F4E7;</div>
            <div>
              <div style="font-weight:600;font-size:0.9rem;">Email Support</div>
              <div class="text-muted" style="font-size:0.88rem;">support@smartpark.com.au</div>
              <div class="text-muted" style="font-size:0.8rem;">Response within 1&ndash;2 business days</div>
            </div>
          </div>
          <div style="display:flex;gap:0.85rem;align-items:flex-start;">
            <div style="font-size:1.3rem;flex-shrink:0;">&#x1F4DE;</div>
            <div>
              <div style="font-weight:600;font-size:0.9rem;">Phone</div>
              <div class="text-muted" style="font-size:0.88rem;">(02) 9000 0000</div>
              <div class="text-muted" style="font-size:0.8rem;">Mon&ndash;Fri, 9am&ndash;5pm AEST</div>
            </div>
          </div>
          <div style="display:flex;gap:0.85rem;align-items:flex-start;">
            <div style="font-size:1.3rem;flex-shrink:0;">&#x1F4CD;</div>
            <div>
              <div style="font-weight:600;font-size:0.9rem;">Postal Address</div>
              <div class="text-muted" style="font-size:0.88rem;">SmartPark HQ<br>302 Elizabeth Street<br>Sydney NSW 2000</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>&#x2753; Common Questions</h3></div>
        <div style="display:flex;flex-direction:column;gap:0.85rem;">
          <?php
          $faqs = [
            ['q'=>'How do I cancel a booking?',   'a'=>'Go to your Dashboard and click Cancel Booking. Cancellations must be made at least 1 hour before start time.'],
            ['q'=>'Is my payment secure?',         'a'=>'Yes. All transactions are processed over HTTPS. We do not store full card numbers on our servers.'],
            ['q'=>'Can I extend my booking?',      'a'=>'Contact us via this form or call our support line. Extensions are subject to availability.'],
            ['q'=>'How do I delete my account?',   'a'=>'Select Request Account Deletion as the subject above, or visit Dashboard &rarr; Profile &rarr; Data &amp; Privacy.'],
          ];
          foreach ($faqs as $faq): ?>
            <details style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
              <summary style="padding:0.75rem 1rem;font-weight:600;font-size:0.9rem;cursor:pointer;background:var(--bg);">
                <?= h($faq['q']) ?>
              </summary>
              <div style="padding:0.75rem 1rem;font-size:0.88rem;color:var(--text-muted);border-top:1px solid var(--border);">
                <?= $faq['a'] ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function updateCharCount(textarea, countId, max) {
    var el = document.getElementById(countId);
    if (el) {
        var len = textarea.value.length;
        el.textContent = len + ' / ' + max;
        el.style.color = len > max * 0.9 ? 'var(--warning)' : 'var(--text-muted)';
    }
}
function showResponseTime(subject) {
    var box = document.getElementById('responseTimeBox');
    var msg = document.getElementById('responseTimeMsg');
    var times = {
        'Booking Issue':            'Booking issues are prioritised — we aim to respond within 4 hours.',
        'Billing Question':         'Billing queries are responded to within 1 business day.',
        'Report a Bug':             'Bug reports are reviewed within 1–2 business days.',
        'Request Account Deletion': 'Account deletion requests are processed within 3 business days under the Australian Privacy Act.',
        'Account Problem':          'Account issues are prioritised — we aim to respond within 4 hours.',
    };
    if (times[subject]) {
        msg.textContent = times[subject];
        box.hidden = false;
    } else {
        box.hidden = true;
    }
}
</script>

<?php require 'includes/footer.php'; ?>