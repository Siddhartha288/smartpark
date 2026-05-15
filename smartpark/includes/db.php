<?php
/**
 * SmartPark - Database Configuration & Connection
 * ICT312 Advanced Web Information Systems
 *
 * SECURITY: This file must be placed OUTSIDE the web root in production,
 * or at minimum not be directly accessible. Never commit DB credentials to Git.
 */

// ---- Database Credentials ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartpark');
define('DB_USER', 'root');
define('DB_PASS', '');       // Change in production
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO instance with secure defaults.
 * Uses prepared statements; direct string interpolation is never used.
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,  // Use real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error, show user-friendly message (never expose details)
            error_log('[SmartPark DB Error] ' . $e->getMessage());
            die('<div style="padding:2rem;font-family:sans-serif;"><h2>Database connection failed</h2>
                 <p>We are unable to connect to the database. Please try again later, 
                 or contact the system administrator.</p></div>');
        }
    }

    return $pdo;
}

/**
 * CSRF token helpers
 */
function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

/**
 * Rate limiting helper (simple session-based for demo)
 */
function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $sessionKey = 'rl_' . $key;
    $now = time();

    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    if ($now > $_SESSION[$sessionKey]['reset_at']) {
        $_SESSION[$sessionKey] = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    $_SESSION[$sessionKey]['count']++;
    return $_SESSION[$sessionKey]['count'] <= $maxAttempts;
}
