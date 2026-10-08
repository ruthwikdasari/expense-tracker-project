<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request.';
        header('Location: transactions.php');
        exit;
    }
    
    $transactionId = sanitizeInput($_POST['id'] ?? '');
    if (!is_numeric($transactionId)) {
        $_SESSION['flash_error'] = 'Invalid transaction ID.';
        header('Location: transactions.php');
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?');
    $stmt->execute([$transactionId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        logAudit($pdo, $userId, 'TRANSACTION_DELETE', 'Deleted transaction ID: ' . $transactionId);
        $_SESSION['flash_success'] = 'Transaction deleted successfully.';
    } else {
        logAudit($pdo, $userId, 'UNAUTHORIZED_ACCESS', 'Attempted to delete transaction ID: ' . $transactionId);
        $_SESSION['flash_error'] = 'Transaction not found or access denied.';
    }
    
    header('Location: transactions.php');
    exit;
}

$transactionId = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';

if (!is_numeric($transactionId)) {
    $_SESSION['flash_error'] = 'Invalid transaction ID.';
    header('Location: transactions.php');
    exit;
}

$stmt = $pdo->prepare('SELECT t.*, c.name as category_name FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.id = ? AND t.user_id = ?');
$stmt->execute([$transactionId, $userId]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    logAudit($pdo, $userId, 'UNAUTHORIZED_ACCESS', 'Attempted to delete transaction ID: ' . $transactionId);
    $_SESSION['flash_error'] = 'Transaction not found.';
    header('Location: transactions.php');
    exit;
}

$pageTitle = 'Delete Transaction';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <h2><?php echo escapeOutput($pageTitle); ?></h2>

    <div class="card alert-warning mt-3">
        <div class="card-body">
            <h5 class="card-title text-danger">Are you sure you want to delete this transaction?</h5>
            <p class="card-text">This action cannot be undone.</p>
            
            <ul class="list-group mb-4">
                <li class="list-group-item"><strong>Type:</strong> <?php echo escapeOutput(ucfirst($transaction['type'])); ?></li>
                <li class="list-group-item"><strong>Amount:</strong> ₹<?php echo escapeOutput(number_format((float)$transaction['amount'], 2)); ?></li>
                <li class="list-group-item"><strong>Category:</strong> <?php echo escapeOutput($transaction['category_name']); ?></li>
                <li class="list-group-item"><strong>Description:</strong> <?php echo escapeOutput($transaction['description']); ?></li>
                <li class="list-group-item"><strong>Date:</strong> <?php echo escapeOutput($transaction['transaction_date']); ?></li>
            </ul>

            <form method="POST" action="delete_transaction.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="id" value="<?php echo escapeOutput($transaction['id']); ?>">
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
                <a href="transactions.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
