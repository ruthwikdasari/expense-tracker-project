<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

$pageTitle = 'Reports';

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d');

$fromDate = isset($_GET['from_date']) ? sanitizeInput($_GET['from_date']) : $defaultFrom;
$toDate = isset($_GET['to_date']) ? sanitizeInput($_GET['to_date']) : $defaultTo;

if (!validateDate($fromDate) || !validateDate($toDate) || $fromDate > $toDate) {
    $fromDate = $defaultFrom;
    $toDate = $defaultTo;
}

try {
    $incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?");
    $incomeStmt->execute([$userId, $fromDate, $toDate]);
    $totalIncome = $incomeStmt->fetchColumn();

    $expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?");
    $expenseStmt->execute([$userId, $fromDate, $toDate]);
    $totalExpense = $expenseStmt->fetchColumn();

    $balance = $totalIncome - $totalExpense;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ?");
    $countStmt->execute([$userId, $fromDate, $toDate]);
    $transactionCount = $countStmt->fetchColumn();

    $catExpenseStmt = $pdo->prepare("SELECT c.name, SUM(t.amount) as total, COUNT(*) as count FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date BETWEEN ? AND ? GROUP BY c.id, c.name ORDER BY total DESC");
    $catExpenseStmt->execute([$userId, $fromDate, $toDate]);
    $expenseCategories = $catExpenseStmt->fetchAll(PDO::FETCH_ASSOC);

    $catIncomeStmt = $pdo->prepare("SELECT c.name, SUM(t.amount) as total, COUNT(*) as count FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? AND t.type = 'income' AND t.transaction_date BETWEEN ? AND ? GROUP BY c.id, c.name ORDER BY total DESC");
    $catIncomeStmt->execute([$userId, $fromDate, $toDate]);
    $incomeCategories = $catIncomeStmt->fetchAll(PDO::FETCH_ASSOC);

    $txnStmt = $pdo->prepare("SELECT t.*, c.name as category_name FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ? ORDER BY t.transaction_date DESC");
    $txnStmt->execute([$userId, $fromDate, $toDate]);
    $transactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_error'] = "An error occurred while generating the report.";
    $totalIncome = 0; $totalExpense = 0; $balance = 0; $transactionCount = 0;
    $expenseCategories = []; $incomeCategories = []; $transactions = [];
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    logAudit($pdo, $userId, 'REPORT_EXPORT', 'CSV export for period: ' . $fromDate . ' to ' . $toDate);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $output = fopen('php://output', 'w');
    // UTF-8 BOM for proper Excel display
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Date', 'Type', 'Category', 'Description', 'Amount']);
    foreach ($transactions as $txn) {
        // Prefix cells starting with =, +, -, @ with a tab to prevent CSV formula injection
        $safeDescription = $txn['description'];
        if (preg_match('/^[=+\-@]/', $safeDescription)) {
            $safeDescription = "\t" . $safeDescription;
        }
        fputcsv($output, [
            $txn['transaction_date'],
            ucfirst($txn['type']),
            $txn['category_name'],
            $safeDescription,
            $txn['amount']
        ]);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<?php
if (isset($_SESSION['flash_success'])):
    echo '<div class="alert alert-success">' . escapeOutput($_SESSION['flash_success']) . '</div>';
    unset($_SESSION['flash_success']);
endif;
if (isset($_SESSION['flash_error'])):
    echo '<div class="alert alert-danger">' . escapeOutput($_SESSION['flash_error']) . '</div>';
    unset($_SESSION['flash_error']);
endif;
?>

<div class="filter-section card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="reports.php" class="row g-3 align-items-center">
            <div class="col-auto">
                <label for="from_date" class="col-form-label">From Date:</label>
            </div>
            <div class="col-auto">
                <input type="date" class="form-control" id="from_date" name="from_date" value="<?= escapeOutput($fromDate) ?>" required>
            </div>
            <div class="col-auto">
                <label for="to_date" class="col-form-label">To Date:</label>
            </div>
            <div class="col-auto">
                <input type="date" class="form-control" id="to_date" name="to_date" value="<?= escapeOutput($toDate) ?>" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-end mb-3 no-print">
    <button onclick="window.print()" class="btn btn-secondary me-2">Print Report</button>
    <a href="reports.php?from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&export=csv" class="btn btn-success">Download CSV</a>
</div>

<style>
@media print {
    .no-print { display: none !important; }
}
</style>

<div class="content-card">
    <h3 class="mb-4">Financial Report: <?= escapeOutput(date('M j, Y', strtotime($fromDate))) ?> to <?= escapeOutput(date('M j, Y', strtotime($toDate))) ?></h3>

    <div class="row mb-4 g-3">
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card card-income h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-value fs-4">₹ <?= escapeOutput(number_format($totalIncome, 2)) ?></div>
                    <div class="stat-label">Total Income</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card card-expense h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-value fs-4">₹ <?= escapeOutput(number_format($totalExpense, 2)) ?></div>
                    <div class="stat-label">Total Expenses</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card card-balance h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-value fs-4">₹ <?= escapeOutput(number_format($balance, 2)) ?></div>
                    <div class="stat-label">Net Balance</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card card-transactions h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-value fs-4"><?= escapeOutput((string)$transactionCount) ?></div>
                    <div class="stat-label">Transactions</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <h5>Category-wise Expense Breakdown</h5>
            <?php if (empty($expenseCategories)): ?>
                <p class="text-muted">No expenses in this period.</p>
            <?php else: ?>
                <table class="table table-bordered table-sm">
                    <thead class="table-light"><tr><th>Category</th><th>Count</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($expenseCategories as $cat): ?>
                            <tr>
                                <td><?= escapeOutput($cat['name']) ?></td>
                                <td><?= escapeOutput($cat['count']) ?></td>
                                <td class="text-end">₹ <?= escapeOutput(number_format($cat['total'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <h5>Category-wise Income Breakdown</h5>
            <?php if (empty($incomeCategories)): ?>
                <p class="text-muted">No income in this period.</p>
            <?php else: ?>
                <table class="table table-bordered table-sm">
                    <thead class="table-light"><tr><th>Category</th><th>Count</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($incomeCategories as $cat): ?>
                            <tr>
                                <td><?= escapeOutput($cat['name']) ?></td>
                                <td><?= escapeOutput($cat['count']) ?></td>
                                <td class="text-end">₹ <?= escapeOutput(number_format($cat['total'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <h5>Transaction Details</h5>
    <?php if (empty($transactions)): ?>
        <p class="text-muted">No transactions found in this period.</p>
    <?php else: ?>
        <table class="table table-striped table-bordered table-sm">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $txn): ?>
                    <tr>
                        <td><?= escapeOutput(date('Y-m-d', strtotime($txn['transaction_date']))) ?></td>
                        <td><?= escapeOutput(ucfirst($txn['type'])) ?></td>
                        <td><?= escapeOutput($txn['category_name']) ?></td>
                        <td><?= escapeOutput($txn['description'] ?: '-') ?></td>
                        <td class="text-end">₹ <?= escapeOutput(number_format($txn['amount'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
