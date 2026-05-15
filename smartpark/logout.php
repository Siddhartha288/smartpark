<?php
require_once 'includes/functions.php';
$_SESSION = [];
session_destroy();
session_start();
$_SESSION['flash_success'] = 'You have been logged out successfully.';
header('Location: index.php');
exit;
