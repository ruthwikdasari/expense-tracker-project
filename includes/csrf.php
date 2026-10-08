<?php
/**
 * CSRF (Cross-Site Request Forgery) Protection
 *
 * Generates a per-session token and validates it on form submissions.
 * Uses hash_equals() for timing-safe comparison.
 */

/**
 * Generate or retrieve the current CSRF token stored in the session.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 */
function validateCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Return a hidden HTML input field containing the CSRF token.
 * Include this inside every state-changing form.
 */
function csrfField(): string
{
    $token = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}
