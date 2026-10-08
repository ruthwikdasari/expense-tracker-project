<?php
/**
 * Database Configuration
 *
 * Update the credentials below for your local environment.
 * In production, use environment variables or a separate config file
 * that is NOT committed to version control.
 */

define('DB_HOST', 'db');
define('DB_NAME', 'expense_tracker');
define('DB_USER', 'root');
define('DB_PASS', 'rootpassword');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Log the real error; show a safe message to the user
    error_log('Database connection failed: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}
