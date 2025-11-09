<?php
// Helper for protected pages and API endpoints.
// Tolerant: start session if not already started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Page-protection: redirect to login page when not authenticated.
 */
function require_auth(): void {
    if (empty($_SESSION['auth_user'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Return current authenticated user's identifier (E.164 phone) or null.
 */
function current_user(): ?string {
    return $_SESSION['auth_user'] ?? null;
}

/**
 * Simple boolean check for logged-in user.
 */
function is_logged_in(): bool {
    return !empty($_SESSION['auth_user']);
}

/**
 * API/AJAX protection: return JSON 401 when not authenticated.
 * Use this at the top of AJAX endpoints (initiate_payment.php, verify_payment.php).
 */
function require_auth_json(): void {
    if (empty($_SESSION['auth_user'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
        exit;
    }
}

/**
 * CSRF check for JSON/API endpoints.
 * Accepts token via POST field 'csrf' or HTTP header 'X-CSRF-Token'.
 * On failure returns JSON 400 and exits.
 */
function require_csrf_json(): void {
    $sessionCsrf = $_SESSION['csrf'] ?? '';
    $posted = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted = $_POST['csrf'] ?? '';
    }
    // allow header fallback
    if (empty($posted)) {
        $posted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X-CSRF-TOKEN'] ?? '';
    }

    if (!is_string($sessionCsrf) || !is_string($posted) || !hash_equals($sessionCsrf, $posted)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

/**
 * Convenience: small JSON auth summary for pages that need it.
 */
function auth_status(): array {
    return [
        'logged_in' => is_logged_in(),
        'user' => current_user()
    ];
}
?>