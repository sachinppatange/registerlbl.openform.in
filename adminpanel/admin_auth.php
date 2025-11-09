<?php
// Admin authentication helper functions
// Use in all adminpanel pages for session check, logout, etc.

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the current session is a valid logged-in admin.
 * @return bool
 */
function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin_auth_user']);
}

/**
 * Gets the current admin's phone (E.164 format) if logged in, else null.
 * @return string|null
 */
function get_admin_phone(): ?string {
    return $_SESSION['admin_auth_user'] ?? null;
}

/**
 * Logs out the admin by clearing session.
 */
function admin_logout(): void {
    unset($_SESSION['admin_auth_user']);
    session_destroy();
}