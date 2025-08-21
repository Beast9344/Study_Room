<?php
/**
 * Authentication and Session Management Utilities
 */

// Prevent direct access
defined('ROOT_PATH') || define('ROOT_PATH', realpath(dirname(__FILE__) . '/../'));
if (!defined('ROOT_PATH')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access not permitted');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Check if user is authenticated
 */
function is_authenticated() {
    return isset($_SESSION['user']['id']) && !empty($_SESSION['user']['id']);
}

/**
 * Check if user has specific role
 */
function has_role($role) {
    return is_authenticated() && $_SESSION['user']['role'] === $role;
}

/**
 * Require authentication
 */
function require_auth() {
    if (!is_authenticated()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit();
    }
}

/**
 * Require specific role
 */
function require_role($role) {
    require_auth();
    if (!has_role($role)) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied. Insufficient permissions.');
    }
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Secure logout
 */
function secure_logout() {
    // Unset all session variables
    $_SESSION = array();

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();
}

/**
 * Password hashing wrapper
 */
function secure_password_hash($password) {
    return password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));
}

/**
 * Password verification wrapper
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Regenerate session ID periodically
 */
function regenerate_session() {
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Get current authenticated user
 */
function current_user() {
    return is_authenticated() ? $_SESSION['user'] : null;
}

/**
 * Secure redirect
 */
function safe_redirect($url) {
    if (!headers_sent()) {
        header("Location: " . filter_var($url, FILTER_SANITIZE_URL));
        exit();
    }
    echo '<script>window.location.href="' . htmlspecialchars($url) . '";</script>';
    exit();
}