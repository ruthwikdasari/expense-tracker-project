<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();

$userId = getCurrentUserId();
$userName = getCurrentUserName();

try {
    // 1. Total Income
    $stmtIncome = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'income'");
    $stmtIncome->execute([$userId]);
    $totalIncome = (float) $stmtIncome->fetchColumn();

    // 2. Total Expenses
    $stmtExpense = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'expense'");
    $stmtExpense->execute([$userId]);
    $totalExpense = (float) $stmtExpense->fetchColumn();

    // 3. Balance
    $balance = $totalIncome - $totalExpense;

    // 4. Transaction count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
    $stmtCount->execute([$userId]);
    $transactionCount = (int) $stmtCount->fetchColumn();

    // 5. Recent transactions
    $stmtRecent = $pdo->prepare("SELECT t.*, c.name as category_name FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? ORDER BY t.transaction_date DESC, t.created_at DESC LIMIT 5");
    $stmtRecent->execute([$userId]);
    $recentTransactions = $stmtRecent->fetchAll();

} catch (PDOException $e) {
    error_log('Database error on dashboard: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Welcome back, <?= escapeOutput($userName) ?>!</h2>

    <div class="row mb-4 g-3">
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card card-income h-100">
                <div class="card-body text-center">
                    <i class="bi bi-arrow-down-circle stat-icon"></i>
                    <div class="stat-value mt-2">₹<?= number_format($totalIncome, 2) ?></div>
                    <div class="stat-label mt-1">Total Income</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card card-expense h-100">
                <div class="card-body text-center">
                    <i class="bi bi-arrow-up-circle stat-icon"></i>
                    <div class="stat-value mt-2">₹<?= number_format($totalExpense, 2) ?></div>
                    <div class="stat-label mt-1">Total Expenses</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card card-balance h-100">
                <div class="card-body text-center">
                    <i class="bi bi-wallet2 stat-icon"></i>
                    <div class="stat-value mt-2">₹<?= number_format($balance, 2) ?></div>
                    <div class="stat-label mt-1">Net Balance</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card card-transactions h-100">
                <div class="card-body text-center">
                    <i class="bi bi-list-ul stat-icon"></i>
                    <div class="stat-value mt-2"><?= number_format($transactionCount) ?></div>
                    <div class="stat-label mt-1">Total Transactions</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card content-card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Recent Transactions</h5>
            <a href="add_transaction.php" class="btn btn-sm btn-primary">Add Transaction</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentTransactions)): ?>
                <p class="text-muted mb-0">No transactions found. <a href="add_transaction.php">Add a transaction</a> to get started!</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $t): ?>
                                <tr>
                                    <td><?= escapeOutput($t['transaction_date']) ?></td>
                                    <td>
                                        <?php if ($t['type'] === 'income'): ?>
                                            <span class="badge bg-success badge-income">Income</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger badge-expense">Expense</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= escapeOutput($t['category_name']) ?></td>
                                    <td><?= escapeOutput($t['description']) ?></td>
                                    <td>
                                        <?php if ($t['type'] === 'income'): ?>
                                            <span class="amount-income text-success">+ ₹<?= number_format($t['amount'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="amount-expense text-danger">- ₹<?= number_format($t['amount'], 2) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
