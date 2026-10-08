<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

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
    logAudit($pdo, $userId, 'UNAUTHORIZED_ACCESS', 'Attempted to view transaction ID: ' . $transactionId);
    $_SESSION['flash_error'] = 'Transaction not found.';
    header('Location: transactions.php');
    exit;
}

$pageTitle = 'View Transaction';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <h2><?php echo escapeOutput($pageTitle); ?></h2>

    <div class="card content-card mt-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Type</dt>
                <dd class="col-sm-9">
                    <span class="badge <?php echo $transaction['type'] === 'income' ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo escapeOutput(ucfirst($transaction['type'])); ?>
                    </span>
                </dd>

                <dt class="col-sm-3">Amount</dt>
                <dd class="col-sm-9">₹<?php echo escapeOutput(number_format((float)$transaction['amount'], 2)); ?></dd>

                <dt class="col-sm-3">Category</dt>
                <dd class="col-sm-9"><?php echo escapeOutput($transaction['category_name']); ?></dd>

                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9"><?php echo nl2br(escapeOutput($transaction['description'] !== '' ? $transaction['description'] : 'N/A')); ?></dd>

                <dt class="col-sm-3">Transaction Date</dt>
                <dd class="col-sm-9"><?php echo escapeOutput($transaction['transaction_date']); ?></dd>

                <dt class="col-sm-3">Created At</dt>
                <dd class="col-sm-9"><?php echo escapeOutput($transaction['created_at']); ?></dd>
                
                <dt class="col-sm-3">Last Updated At</dt>
                <dd class="col-sm-9"><?php echo escapeOutput($transaction['updated_at']); ?></dd>
            </dl>
        </div>
        <div class="card-footer bg-light">
            <a href="transactions.php" class="btn btn-secondary">Back to Transactions</a>
            <a href="edit_transaction.php?id=<?php echo escapeOutput($transaction['id']); ?>" class="btn btn-warning">Edit</a>
            <a href="delete_transaction.php?id=<?php echo escapeOutput($transaction['id']); ?>" class="btn btn-danger">Delete</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
