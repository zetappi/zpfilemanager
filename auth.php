<?php
// Simple session‑based authentication stub for the File Manager module.
// Projects that embed this module should start the session and set a user identifier
// (e.g., $_SESSION['user_id'] or $_SESSION['user']). This file checks for that value
// and aborts the request with HTTP 401 if the user is not authenticated.

if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Authentication can be required by defining the constant REQUIRE_AUTH as true.
// Example: define('REQUIRE_AUTH', true); in your project before including this file.
if (defined('REQUIRE_AUTH') && REQUIRE_AUTH && empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
?>