<?php
/**
 * Authentication and Session Management
 *
 * Provides secure session initialisation and helper functions
 * for authentication checks throughout the application.
 */

/**
 * Initialise the session with secure cookie parameters.
 */
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly'  => true,
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
}

/**
 * Require the user to be authenticated.
 * Redirects to the login page if not logged in.
 */
function requireLogin(): void
{
    initSession();
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check whether a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

/**
 * Return the authenticated user's ID from the server-side session.
 */
function getCurrentUserId(): int
{
    return (int) $_SESSION['user_id'];
}

/**
 * Return the authenticated user's display name from the session.
 */
function getCurrentUserName(): string
{
    return $_SESSION['user_name'] ?? 'User';
}
