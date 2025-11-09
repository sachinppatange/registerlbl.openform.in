<?php
// Admin logout page: destroys session and redirects to login
require_once __DIR__ . '/admin_auth.php';

admin_logout();

// Redirect to admin login page
header('Location: admin_login.php');
exit;