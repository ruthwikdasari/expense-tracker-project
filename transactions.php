<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

// Fetch user's categories for the filter
$catStmt = $pdo->prepare('SELECT id, name FROM categories WHERE user_id = ? ORDER BY name');
$catStmt->execute([$userId]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$categoryIdFilter = isset($_GET['category_id']) ? sanitizeInput($_GET['category_id']) : '';
$fromDate = isset($_GET['from_date']) ? sanitizeInput($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? sanitizeInput($_GET['to_date']) : '';

$query = 'SELECT t.*, c.name as category_name FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ?';
$params = [$userId];

if ($search !== '') {
    $query .= ' AND t.description LIKE ?';
    $params[] = '%' . $search . '%';
}

if ($typeFilter !== '' && validateTransactionType($typeFilter)) {
    $query .= ' AND t.type = ?';
    $params[] = $typeFilter;
}

if ($categoryIdFilter !== '' && is_numeric($categoryIdFilter)) {
    $catCheckStmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
    $catCheckStmt->execute([$categoryIdFilter, $userId]);
    if ($catCheckStmt->rowCount() > 0) {
        $query .= ' AND t.category_id = ?';
        $params[] = $categoryIdFilter;
    }
}

if ($fromDate !== '' && validateDate($fromDate)) {
    $query .= ' AND t.transaction_date >= ?';
    $params[] = $fromDate;
}

if ($toDate !== '' && validateDate($toDate)) {
    $query .= ' AND t.transaction_date <= ?';
    $params[] = $toDate;
}

$query .= ' ORDER BY t.transaction_date DESC, t.created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalResults = count($transactions);

$pageTitle = 'Transactions';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?php echo escapeOutput($pageTitle); ?></h2>
        <a href="add_transaction.php" class="btn btn-primary">Add Transaction</a>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?php echo escapeOutput($_SESSION['flash_success']); ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?php echo escapeOutput($_SESSION['flash_error']); ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="filter-section mb-4 p-3 bg-light border rounded">
        <form method="GET" action="transactions.php" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo escapeOutput($search); ?>" placeholder="Description...">
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All Types</option>
                    <option value="income" <?php echo $typeFilter === 'income' ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo $typeFilter === 'expense' ? 'selected' : ''; ?>>Expense</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo escapeOutput($cat['id']); ?>" <?php echo $categoryIdFilter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo escapeOutput($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="from_date" class="form-label">From Date</label>
                <input type="date" class="form-control" id="from_date" name="from_date" value="<?php echo escapeOutput($fromDate); ?>">
            </div>
            <div class="col-md-2">
                <label for="to_date" class="form-label">To Date</label>
                <input type="date" class="form-control" id="to_date" name="to_date" value="<?php echo escapeOutput($toDate); ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
        <div class="mt-2 text-end">
            <a href="transactions.php" class="text-decoration-none">Reset Filters</a>
        </div>
    </div>

    <div class="card content-card">
        <div class="card-header">
            <strong>Total Results: <?php echo escapeOutput((string)$totalResults); ?></strong>
        </div>
        <div class="card-body p-0">
            <?php if ($totalResults > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td><?php echo escapeOutput($t['transaction_date']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $t['type'] === 'income' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo escapeOutput(ucfirst($t['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo escapeOutput($t['category_name']); ?></td>
                                    <td><?php echo escapeOutput($t['description']); ?></td>
                                    <td>₹<?php echo escapeOutput(number_format((float)$t['amount'], 2)); ?></td>
                                    <td>
                                        <a href="view_transaction.php?id=<?php echo escapeOutput($t['id']); ?>" class="btn btn-sm btn-outline-info">View</a>
                                        <a href="edit_transaction.php?id=<?php echo escapeOutput($t['id']); ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                                        <a href="delete_transaction.php?id=<?php echo escapeOutput($t['id']); ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center">
                    <p class="mb-0">No transactions found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
