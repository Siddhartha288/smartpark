<?php
require_once 'includes/functions.php';
/**
 * SmartPark - Contact & Feedback (contact.php)
 * ICT312 Advanced Web Information Systems
 *
 * Security: CSRF, server-side validation, rate limiting
 */
$pageTitle = 'Contact Us';
$activeNav = 'contact';
require 'includes/header.php';

$formData    = ['name'=>'','email'=>'','subject'=>'','message'=>''];
$formErrors  = [];
$formSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $formErrors['general'] = 'Invalid form submission. Please try again.';
  } elseif (!checkRateLimit('contact', 3, 300)) {
    $formErrors['general'] = 'Too many submissions. Please wait a few minutes.';
  } else {
    $name    = trim(htmlspecialchars($_POST['name']    ?? '', ENT_QUOTES, 'UTF-8'));
    $email   = trim(strtolower($_POST['email']   ?? ''));
    $subject = trim($_POST['subject']  ?? '');
    $message = trim(htmlspecialchars($_POST['message']  ?? '', ENT_QUOTES, 'UTF-8'));

    $formData = compact('name','email','subject','message');

    // Server-side validation
    if (!$name || strlen($name) < 2)            $formErrors['name']    = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $formErrors['email'] = 'Please enter a valid email address.';
    if (!$subject)                               $formErrors['subject'] = 'Please select a subject.';
    if (!$message || strlen($message) < 10)     $formErrors['message'] = 'Message must be at least 10 characters.';
    if (strlen($message) > 2000)                $formErrors['message'] = 'Message must be under 2000 characters.';

    if (empty($formErrors)) {
      // In production: send email via PHP mail() or a library like PHPMailer
      // mail('support@smartpark.com', '[SmartPark] ' . $subject, $message, 'From: ' . $email);
      // Also log to DB for audit trail
      $formSuccess = true;
      $formData    = ['name'=>'','email'=>'','subject'=>'','message'=>''];
    }
  }
}

$subjects = [
  'General Enquiry',
  'Booking Issue',
  'Account Problem',
  'Billing Question',
  'Report a Bug',
  'Accessibility Feedback',
  'Request Account Deletion',
  'Other',
];
?>

<div class="page-header">
  <div class="container">
    <h1>📬 Contact Us</h1>
    <p>Questions, feedback, or support requests — we're here to help</p>
  </div>
</div>

<div class="container section-sm">
  <div class="grid-2" style="gap:2rem;align-items:start;">

    <!-- ===== FORM ===== -->
    <div>
      <?php if ($formSuccess): ?>
        <div class="alert alert-success">
          <span>✅</span>
          <div>
            <strong>Message sent!</strong><br>
            Thank you for contacting us. We aim to respond within 1–2 business days.
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($formErrors['general'])): ?>
        <div class="alert alert-danger"><span>❌</span><span><?= h($formErrors['general']) ?></span></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h3>Send a Message</h3>
        </div>

        <form method="POST" action="contact.php" id="contactForm" novalidate onsubmit="return validateForm(this)">
          <?= csrfField() ?>

          <div class="form-row">
            <div class="form-group">
              <label for="name">Your Name *</label>
              <input type="text" id="name" name="name"
                     data-required maxlength="100"
                     placeholder="Jane Smith"
                     value="<?= h($formData['name']) ?>"
                     class="<?= isset($formErrors['name']) ? 'error' : '' ?>">
              <?php if (isset($formErrors['name'])): ?>
                <div class="field-error show"><?= h($formErrors['name']) ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label for="email">Email Address *</label>
              <input type="email" id="email" name="email"
                     data-required
                     placeholder="you@example.com"
                     value="<?= h($formData['email']) ?>"
                     class="<?= isset($formErrors['email']) ? 'error' : '' ?>">
              <?php if (isset($formErrors['email'])): ?>
                <div class="field-error show"><?= h($formErrors['email']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label for="subject">Subject *</label>
            <select id="subject" name="subject"
                    data-required
                    class="<?= isset($formErrors['subject']) ? 'error' : '' ?>">
              <option value="">— Select a subject —</option>
              <?php foreach ($subjects as $s): ?>
                <option value="<?= h($s) ?>" <?= $formData['subject'] === $s ? 'selected' : '' ?>>
                  <?= h($s) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($formErrors['subject'])): ?>
              <div class="field-error show"><?= h($formErrors['subject']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label for="message">Message * <span class="text-muted">(10–2000 characters)</span></label>
            <textarea id="message" name="message"
                      data-required maxlength="2000"
                      placeholder="Please describe your enquiry in detail..."
                      class="<?= isset($formErrors['message']) ? 'error' : '' ?>"
                      oninput="updateCharCount(this, 'charCount', 2000)"><?= h($formData['message']) ?></textarea>
            <div style="display:flex;justify-content:space-between;">
              <?php if (isset($formErrors['message'])): ?>
                <div class="field-error show"><?= h($formErrors['message']) ?></div>
              <?php else: ?><div></div><?php endif; ?>
              <span id="charCount" class="text-muted" style="font-size:0.78rem;">0 / 2000</span>
            </div>
          </div>

          <div class="alert alert-info" style="font-size:0.82rem;">
            <span>🔒</span>
            <span>Your message is transmitted securely. We do not share your details with third parties.</span>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">Send Message →</button>
        </form>
      </div>
    </div>

    <!-- ===== CONTACT INFO ===== -->
    <div>
      <div class="card mb-2">
        <div class="card-header"><h3>📍 Get in Touch</h3></div>

        <div style="display:flex;flex-direction:column;gap:1.1rem;">
          <div style="display:flex;gap:0.85rem;align-items:flex-start;">
            <div style="font-size:1.3rem;flex-shrink:0;">📧</div>
            <div>
              <div style="font-weight:600;font-size:0.9rem;">Email Support</div>
              <div class="text-muted" style="font-size:0.88rem;">support@smartpark.com.au</div>
              <div class="text-muted" style="font-size:0.8rem;">Response within 1–2 business days</div>
            </div>
          </div>

          <div style="display:flex;gap:0.85rem;align-items:flex-start;">
            <div style="font-size:1.3rem;flex-shrink:0;">📞</div>
            <div>
              <div style="font-weight:600;font-size:0.9rem;">Phone</div>
              <div class="text-muted" style="font-size:0.88rem;">(02) 9000 0000</div>
              <div class="text-muted" style="font-size:0.8rem;">Mon–Fri, 9am–5pm AEST</div>
            </div>
          </div>

          <div style="display:flex;gap:0.85rem;align-items:flex-start;">
            <div style="font-size:1.3rem;flex-shrink:0;">📍</div>
            <div>
              <div style="font-weight:600;font-size:0.9rem;">Postal Address</div>
              <div class="text-muted" style="font-size:0.88rem;">SmartPark HQ<br>302 Elizabeth Street<br>Sydney NSW 2000</div>
            </div>
          </div>
        </div>
      </div>

      <!-- FAQ -->
      <div class="card">
        <div class="card-header"><h3>❓ Common Questions</h3></div>
        <div style="display:flex;flex-direction:column;gap:0.85rem;">
          <?php
          $faqs = [
            ['q'=>'How do I cancel a booking?','a'=>'Go to your Dashboard and click "Cancel Booking" next to the reservation. Cancellations must be made at least 1 hour before the start time.'],
            ['q'=>'Is my payment secure?','a'=>'Yes. All transactions are processed over HTTPS. We do not store full card numbers on our servers.'],
            ['q'=>'Can I extend my booking?','a'=>'Contact us via this form or call our support line. Extensions are subject to availability.'],
            ['q'=>'How do I delete my account?','a'=>'Select "Request Account Deletion" as the subject above, or visit your Dashboard → Profile → Data & Privacy.'],
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

  </div>
</div>

<script>
function updateCharCount(textarea, countId, max) {
  const el = document.getElementById(countId);
  if (el) {
    const len = textarea.value.length;
    el.textContent = len + ' / ' + max;
    el.style.color = len > max * 0.9 ? 'var(--warning)' : 'var(--text-muted)';
  }
}
</script>

<?php require 'includes/footer.php'; ?>
