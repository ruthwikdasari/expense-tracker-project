<?php
/**
 * Input Validation and Output Encoding
 *
 * Central validation functions used across the application.
 * All user input MUST be validated server-side.
 */

/**
 * Sanitise input by trimming whitespace.
 */
function sanitizeInput(string $input): string
{
    return trim($input);
}

/**
 * Escape output for safe HTML rendering (XSS protection).
 */
function escapeOutput(?string $input): string
{
    return htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format and length.
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && strlen($email) <= 255;
}

/**
 * Validate password strength.
 *
 * @return string[] Array of error messages. Empty array means valid.
 */
function validatePassword(string $password): array
{
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (strlen($password) > 128) {
        $errors[] = 'Password must not exceed 128 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }
    return $errors;
}

/**
 * Validate a monetary amount.
 */
function validateAmount($amount): bool
{
    return is_numeric($amount)
        && (float) $amount > 0
        && (float) $amount <= 99999999.99;
}

/**
 * Validate transaction type against an allowlist.
 */
function validateTransactionType(string $type): bool
{
    return in_array($type, ['income', 'expense'], true);
}

/**
 * Validate a date string in Y-m-d format.
 */
function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/**
 * Validate a person's name (2–100 characters).
 */
function validateName(string $name): bool
{
    $len = mb_strlen($name, 'UTF-8');
    return $len >= 2 && $len <= 100;
}

/**
 * Validate a transaction description (max 500 characters).
 */
function validateDescription(string $desc): bool
{
    return mb_strlen($desc, 'UTF-8') <= 500;
}

/**
 * Validate a category name (1–100 characters).
 */
function validateCategoryName(string $name): bool
{
    $len = mb_strlen($name, 'UTF-8');
    return $len >= 1 && $len <= 100;
}
