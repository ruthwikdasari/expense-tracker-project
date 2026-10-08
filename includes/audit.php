<?php
/**
 * Audit Logging
 *
 * Records security-relevant events for accountability and forensics.
 * NEVER log passwords, password hashes, or sensitive credentials.
 */

/**
 * Log an audit event to the database.
 *
 * @param PDO      $pdo     Database connection
 * @param int|null $userId  User ID (null for unauthenticated events like failed login)
 * @param string   $action  Action identifier (e.g. 'LOGIN_SUCCESS', 'TRANSACTION_CREATE')
 * @param string   $details Additional context (never include passwords or secrets)
 */
function logAudit(PDO $pdo, ?int $userId, string $action, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)'
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (PDOException $e) {
        // Log to error log but never break the application flow
        error_log('Audit logging failed: ' . $e->getMessage());
    }
}
