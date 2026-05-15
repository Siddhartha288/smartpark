/* ================================================
   SmartPark - Main JavaScript
   ICT312 Advanced Web Information Systems
   ================================================ */

'use strict';

// ---- Navbar Mobile Toggle ----
document.addEventListener('DOMContentLoaded', function () {
  const hamburger = document.querySelector('.nav-hamburger');
  const navLinks  = document.querySelector('.nav-links');
  const navActions = document.querySelector('.nav-actions');

  if (hamburger) {
    hamburger.addEventListener('click', function () {
      navLinks.classList.toggle('open');
    });
  }

  // ---- Active nav link highlight ----
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(function (a) {
    if (a.getAttribute('href') === currentPage) {
      a.classList.add('active');
    }
  });

  // ---- Auto-dismiss alerts after 5s ----
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity 0.5s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 500);
    }, 5000);
  });

  // ---- Tabs ----
  document.querySelectorAll('.tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const tabGroup = btn.closest('[data-tabs]');
      const target   = btn.dataset.tab;
      tabGroup.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
      tabGroup.querySelectorAll('.tab-content').forEach(function (c) { c.classList.remove('active'); });
      btn.classList.add('active');
      const content = tabGroup.querySelector('[data-tab-content="' + target + '"]');
      if (content) content.classList.add('active');
    });
  });

  // ---- Modals ----
  document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const modal = document.getElementById(btn.dataset.modalOpen);
      if (modal) modal.classList.add('active');
    });
  });

  document.querySelectorAll('.modal-close, .modal-overlay').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (el.classList.contains('modal-overlay') && e.target !== el) return;
      document.querySelectorAll('.modal-overlay.active').forEach(function (m) { m.classList.remove('active'); });
    });
  });
});

// ---- Form Validation ----
function validateForm(formEl) {
  let valid = true;

  formEl.querySelectorAll('[data-required]').forEach(function (input) {
    clearError(input);
    if (!input.value.trim()) {
      showError(input, 'This field is required.');
      valid = false;
    }
  });

  const emailInputs = formEl.querySelectorAll('input[type="email"]');
  emailInputs.forEach(function (input) {
    if (input.value && !isValidEmail(input.value)) {
      showError(input, 'Please enter a valid email address.');
      valid = false;
    }
  });

  const pwdInput = formEl.querySelector('#password');
  const pwdConfirm = formEl.querySelector('#confirm_password');
  if (pwdInput && pwdConfirm && pwdConfirm.value) {
    if (pwdInput.value !== pwdConfirm.value) {
      showError(pwdConfirm, 'Passwords do not match.');
      valid = false;
    }
  }

  if (pwdInput && pwdInput.dataset.minlength) {
    if (pwdInput.value.length < parseInt(pwdInput.dataset.minlength)) {
      showError(pwdInput, 'Password must be at least ' + pwdInput.dataset.minlength + ' characters.');
      valid = false;
    }
  }

  return valid;
}

function showError(input, msg) {
  input.classList.add('error');
  let err = input.parentElement.querySelector('.field-error');
  if (!err) {
    err = document.createElement('div');
    err.className = 'field-error';
    input.parentElement.appendChild(err);
  }
  err.textContent = msg;
  err.classList.add('show');
}

function clearError(input) {
  input.classList.remove('error');
  const err = input.parentElement.querySelector('.field-error');
  if (err) err.classList.remove('show');
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ---- Password Strength ----
function initPasswordStrength(inputId, barId, labelId) {
  const input = document.getElementById(inputId);
  const bar   = document.getElementById(barId);
  const label = document.getElementById(labelId);
  if (!input || !bar) return;

  input.addEventListener('input', function () {
    const score = calcStrength(input.value);
    const colors = ['', '#e74c3c', '#f39c12', '#27ae60', '#1a6b3a'];
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    bar.style.width  = (score * 25) + '%';
    bar.style.background = colors[score] || '';
    if (label) label.textContent = score ? 'Strength: ' + labels[score] : '';
  });
}

function calcStrength(pwd) {
  let score = 0;
  if (pwd.length >= 8) score++;
  if (/[A-Z]/.test(pwd)) score++;
  if (/[0-9]/.test(pwd)) score++;
  if (/[^A-Za-z0-9]/.test(pwd)) score++;
  return score;
}

// ---- Parking Spot Selector ----
function initSpotSelector(gridId, hiddenInputId) {
  const grid  = document.getElementById(gridId);
  const input = document.getElementById(hiddenInputId);
  if (!grid) return;

  grid.querySelectorAll('.spot-available').forEach(function (spot) {
    spot.addEventListener('click', function () {
      grid.querySelectorAll('.spot').forEach(function (s) { s.classList.remove('spot-selected'); });
      spot.classList.add('spot-selected');
      if (input) input.value = spot.dataset.spotId;
      updateBookingSummary();
    });
  });
}

// ---- Booking Summary Calculator ----
function updateBookingSummary() {
  const startEl  = document.getElementById('start_time');
  const endEl    = document.getElementById('end_time');
  const rateEl   = document.getElementById('rate_per_hour');
  const totalEl  = document.getElementById('calc_total');
  const durationEl = document.getElementById('calc_duration');
  const spotEl   = document.getElementById('selected_spot_display');
  const spotInput = document.getElementById('selected_spot');

  if (!startEl || !endEl || !rateEl || !totalEl) return;

  const start = new Date(startEl.value);
  const end   = new Date(endEl.value);
  const rate  = parseFloat(rateEl.value) || 0;

  if (isNaN(start) || isNaN(end) || end <= start) { return; }

  const hours    = (end - start) / 3600000;
  const total    = (hours * rate).toFixed(2);
  const h        = Math.floor(hours);
  const m        = Math.round((hours - h) * 60);

  if (durationEl) durationEl.textContent = h + 'h ' + (m ? m + 'm' : '');
  if (totalEl)  totalEl.textContent = '$' + total;
  if (spotEl && spotInput) spotEl.textContent = spotInput.value ? 'Spot ' + spotInput.value : '—';
}

// ---- Admin: Confirm Delete ----
function confirmDelete(msg) {
  return confirm(msg || 'Are you sure you want to delete this? This action cannot be undone.');
}

// ---- Simple CAPTCHA checkbox ----
function initCaptcha(checkboxId, btnId) {
  const cb  = document.getElementById(checkboxId);
  const btn = document.getElementById(btnId);
  if (!cb || !btn) return;
  cb.addEventListener('change', function () {
    btn.disabled = !cb.checked;
  });
  btn.disabled = true;
}

// ---- Search page: live filter (client-side demo) ----
function filterParkCards() {
  const suburb  = document.getElementById('filter_suburb');
  const maxRate = document.getElementById('filter_rate');
  const cards   = document.querySelectorAll('.park-card[data-suburb]');

  cards.forEach(function (card) {
    const cardSuburb = card.dataset.suburb.toLowerCase();
    const cardRate   = parseFloat(card.dataset.rate);
    const suburbOk   = !suburb || !suburb.value || cardSuburb.includes(suburb.value.toLowerCase());
    const rateOk     = !maxRate || !maxRate.value || cardRate <= parseFloat(maxRate.value);
    card.parentElement.style.display = (suburbOk && rateOk) ? '' : 'none';
  });
}

// ---- Animate stats on scroll ----
function animateStat(el, target) {
  const start = Date.now();
  const duration = 900;
  function tick() {
    const elapsed = Date.now() - start;
    const progress = Math.min(elapsed / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.round(ease * target);
    if (progress < 1) requestAnimationFrame(tick);
  }
  tick();
}

if ('IntersectionObserver' in window) {
  const statObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.dataset.stat);
        if (!isNaN(target)) {
          animateStat(el, target);
          statObserver.unobserve(el);
        }
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('[data-stat]').forEach(function (el) {
    statObserver.observe(el);
  });
}
