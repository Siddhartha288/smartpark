<?php
/**
 * SmartPark - Header (HTML output)
 * Includes functions.php so all helpers are available.
 */
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'SmartPark';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?> | SmartPark</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>
<nav class="navbar">
  <div class="nav-inner">
    <a class="nav-brand" href="index.php">
      <span class="brand-icon">&#x1F17F;</span>Smart<span>Park</span>
    </a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php"  <?= $activeNav==='home'   ?'class="active"':'' ?>>Home</a></li>
      <li><a href="search.php" <?= $activeNav==='search' ?'class="active"':'' ?>>Find Parking</a></li>
      <?php if (isLoggedIn()): ?>
        <li><a href="reserve.php"   <?= $activeNav==='reserve'?'class="active"':'' ?>>Reserve</a></li>
        <li><a href="dashboard.php" <?= $activeNav==='dash'   ?'class="active"':'' ?>>My Dashboard</a></li>
        <?php if (isAdmin()): ?>
          <li><a href="admin.php" <?= $activeNav==='admin'?'class="active"':'' ?>>Admin</a></li>
        <?php endif; ?>
      <?php endif; ?>
      <li><a href="contact.php" <?= $activeNav==='contact'?'class="active"':'' ?>>Contact</a></li>
    </ul>
    <div class="nav-actions" id="navActions">
      <?php if (isLoggedIn()): ?>
        <span style="color:rgba(255,255,255,0.75);font-size:0.85rem;">Hi, <?= h($_SESSION['name'] ?? 'User') ?></span>
        <a href="logout.php" class="btn btn-outline btn-sm">Log Out</a>
      <?php else: ?>
        <a href="register.php" class="btn btn-outline btn-sm">Register</a>
        <a href="login.php"    class="btn btn-primary  btn-sm">Log In</a>
      <?php endif; ?>
    </div>
    <button class="nav-hamburger" id="hamburger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>
<main id="main-content">
