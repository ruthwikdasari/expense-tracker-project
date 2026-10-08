<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

initSession();

if (isLoggedIn()) {
    logAudit($pdo, getCurrentUserId(), 'LOGOUT', 'User logged out');
}

$_SESSION = [];
session_destroy();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header('Location: login.php');
exit;
