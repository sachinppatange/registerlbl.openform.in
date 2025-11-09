<?php
// Helper for protected pages.
// NOTE: The page including this file must call session_start() before including.

function require_auth(): void {
    if (empty($_SESSION['auth_user'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): ?string {
    return $_SESSION['auth_user'] ?? null;
}