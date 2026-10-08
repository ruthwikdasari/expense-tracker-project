<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

$errors = [];
$type = '';
$amount = '';
$categoryId = '';
$description = '';
$transactionDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $type = sanitizeInput($_POST['type'] ?? '');
        $amount = sanitizeInput($_POST['amount'] ?? '');
        $categoryId = sanitizeInput($_POST['category_id'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $transactionDate = sanitizeInput($_POST['transaction_date'] ?? '');

        if (!validateTransactionType($type)) {
            $errors[] = 'Invalid transaction type.';
        }
        if (!validateAmount($amount)) {
            $errors[] = 'Invalid amount.';
        }
        if (!is_numeric($categoryId)) {
            $errors[] = 'Invalid category format.';
        }
        if (!validateDescription($description)) {
            $errors[] = 'Invalid description format or length.';
        }
        if (!validateDate($transactionDate)) {
            $errors[] = 'Invalid transaction date.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
            $stmt->execute([$categoryId, $userId]);
            if ($stmt->rowCount() === 0) {
                $errors[] = 'Invalid category selected.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt->execute([$userId, $categoryId, $type, $amount, $description, $transactionDate])) {
                logAudit($pdo, $userId, 'TRANSACTION_CREATE', 'Added ' . $type . ' of ₹' . number_format((float)$amount, 2));
                $_SESSION['flash_success'] = 'Transaction added successfully.';
                header('Location: transactions.php');
                exit;
            } else {
                $errors[] = 'Failed to save transaction.';
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT id, name FROM categories WHERE user_id = ? ORDER BY name');
$stmt->execute([$userId]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Add Transaction';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <h2><?php echo escapeOutput($pageTitle); ?></h2>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?php echo escapeOutput($_SESSION['flash_success']); ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo escapeOutput($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card content-card">
        <div class="card-body">
            <form method="POST" action="add_transaction.php">
                <?php echo csrfField(); ?>
                
                <div class="mb-3">
                    <label for="type" class="form-label">Type</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="">Select Type</option>
                        <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Income</option>
                        <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount (₹)</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo escapeOutput($amount); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo escapeOutput($cat['id']); ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo escapeOutput($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo escapeOutput($description); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="transaction_date" class="form-label">Transaction Date</label>
                    <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo escapeOutput($transactionDate); ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Transaction</button>
                <a href="transactions.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
