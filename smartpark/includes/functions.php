<?php
/**
 * SmartPark - Pure PHP helpers (no HTML output)
 * Include this BEFORE header.php when you need redirects or POST logic first.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
function isLoggedIn() { return !empty($_SESSION['user_id']); }
function isAdmin()    { return !empty($_SESSION['role']) && $_SESSION['role'] === 'admin'; }

function requireLogin($redirect = 'login.php') {
    if (!isLoggedIn()) {
        $_SESSION['flash_warning'] = 'Please log in to access that page.';
        header('Location: ' . $redirect); exit;
    }
}
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['flash_danger'] = 'Access denied.';
        header('Location: index.php'); exit;
    }
}
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCsrfToken($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . h(generateCsrfToken()) . '">';
}
function checkRateLimit($key, $max = 5, $window = 300) {
    $k = 'rl_' . $key; $now = time();
    if (empty($_SESSION[$k]) || $now > $_SESSION[$k]['reset_at'])
        $_SESSION[$k] = ['count' => 0, 'reset_at' => $now + $window];
    return ++$_SESSION[$k]['count'] <= $max;
}
function flash($type = 'info') {
    $key = 'flash_' . $type;
    if (!empty($_SESSION[$key])) {
        $msg = $_SESSION[$key]; unset($_SESSION[$key]);
        return '<div class="alert alert-'.$type.'" data-auto-dismiss><span></span><span>'.h($msg).'</span></div>';
    }
    return '';
}
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=smartpark;charset=utf8mb4','root','',[
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('[SmartPark DB] '.$e->getMessage());
            die('<p style="padding:2rem">Database unavailable. Please try again later.</p>');
        }
    }
    return $pdo;
}
