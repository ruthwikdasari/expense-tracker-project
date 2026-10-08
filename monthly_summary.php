<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

$pageTitle = 'Monthly Summary';

$currentMonth = date('n');
$currentYear = date('Y');

$selectedMonth = isset($_GET['month']) && is_numeric($_GET['month']) && $_GET['month'] >= 1 && $_GET['month'] <= 12 ? (int)$_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) && is_numeric($_GET['year']) && $_GET['year'] >= ($currentYear - 5) && $_GET['year'] <= ($currentYear + 1) ? (int)$_GET['year'] : $currentYear;

try {
    $incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'income' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
    $incomeStmt->execute([$userId, $selectedMonth, $selectedYear]);
    $totalIncome = $incomeStmt->fetchColumn();

    $expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'expense' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
    $expenseStmt->execute([$userId, $selectedMonth, $selectedYear]);
    $totalExpense = $expenseStmt->fetchColumn();

    $balance = $totalIncome - $totalExpense;

    $catExpenseStmt = $pdo->prepare("SELECT c.name, COALESCE(SUM(t.amount), 0) as total FROM categories c LEFT JOIN transactions t ON c.id = t.category_id AND t.user_id = ? AND t.type = 'expense' AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ? WHERE c.user_id = ? GROUP BY c.id, c.name HAVING total > 0 ORDER BY total DESC");
    $catExpenseStmt->execute([$userId, $selectedMonth, $selectedYear, $userId]);
    $expenseCategories = $catExpenseStmt->fetchAll(PDO::FETCH_ASSOC);

    $catIncomeStmt = $pdo->prepare("SELECT c.name, COALESCE(SUM(t.amount), 0) as total FROM categories c LEFT JOIN transactions t ON c.id = t.category_id AND t.user_id = ? AND t.type = 'income' AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ? WHERE c.user_id = ? GROUP BY c.id, c.name HAVING total > 0 ORDER BY total DESC");
    $catIncomeStmt->execute([$userId, $selectedMonth, $selectedYear, $userId]);
    $incomeCategories = $catIncomeStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_error'] = "An error occurred while fetching the summary.";
    $totalIncome = 0;
    $totalExpense = 0;
    $balance = 0;
    $expenseCategories = [];
    $incomeCategories = [];
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

<div class="filter-section card mb-4">
    <div class="card-body">
        <form method="GET" action="monthly_summary.php" class="row g-3 align-items-center">
            <div class="col-auto">
                <label for="month" class="col-form-label">Month:</label>
            </div>
            <div class="col-auto">
                <select name="month" id="month" class="form-select">
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $monthName = date('F', mktime(0, 0, 0, $m, 1));
                        $selected = ($m == $selectedMonth) ? 'selected' : '';
                        echo '<option value="' . escapeOutput((string)$m) . '" ' . $selected . '>' . escapeOutput($monthName) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="year" class="col-form-label">Year:</label>
            </div>
            <div class="col-auto">
                <select name="year" id="year" class="form-select">
                    <?php
                    for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++) {
                        $selected = ($y == $selectedYear) ? 'selected' : '';
                        echo '<option value="' . escapeOutput((string)$y) . '" ' . $selected . '>' . escapeOutput((string)$y) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">View Summary</button>
            </div>
        </form>
    </div>
</div>

<h3 class="mb-4">Summary for <?= escapeOutput(date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear))) ?></h3>

<div class="row mb-4 g-3">
    <div class="col-md-4">
        <div class="card stat-card card-income h-100">
            <div class="card-body text-center">
                <i class="bi bi-arrow-down-circle stat-icon"></i>
                <div class="stat-value mt-2">₹ <?= escapeOutput(number_format($totalIncome, 2)) ?></div>
                <div class="stat-label mt-1">Total Income</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card card-expense h-100">
            <div class="card-body text-center">
                <i class="bi bi-arrow-up-circle stat-icon"></i>
                <div class="stat-value mt-2">₹ <?= escapeOutput(number_format($totalExpense, 2)) ?></div>
                <div class="stat-label mt-1">Total Expenses</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card card-balance h-100">
            <div class="card-body text-center">
                <i class="bi bi-wallet2 stat-icon"></i>
                <div class="stat-value mt-2">₹ <?= escapeOutput(number_format($balance, 2)) ?></div>
                <div class="stat-label mt-1">Net Balance</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">Category-wise Expenses</div>
            <div class="card-body">
                <?php if (empty($expenseCategories)): ?>
                    <p class="text-muted">No expenses recorded for this month.</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead><tr><th>Category</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($expenseCategories as $cat): ?>
                                <tr>
                                    <td><?= escapeOutput($cat['name']) ?></td>
                                    <td class="text-end">₹ <?= escapeOutput(number_format($cat['total'], 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">Category-wise Income</div>
            <div class="card-body">
                <?php if (empty($incomeCategories)): ?>
                    <p class="text-muted">No income recorded for this month.</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead><tr><th>Category</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($incomeCategories as $cat): ?>
                                <tr>
                                    <td><?= escapeOutput($cat['name']) ?></td>
                                    <td class="text-end">₹ <?= escapeOutput(number_format($cat['total'], 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
