<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

$pageTitle = 'Delete Category';
$error = '';
$categoryId = $_GET['id'] ?? null;

if (!$categoryId || !is_numeric($categoryId)) {
    $_SESSION['flash_error'] = "Invalid category ID.";
    header("Location: categories.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$categoryId, $userId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        logAudit($pdo, $userId, 'UNAUTHORIZED_ACCESS', 'Attempted to delete non-owned category ID: ' . $categoryId);
        $_SESSION['flash_error'] = "Category not found or permission denied.";
        header("Location: categories.php");
        exit;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ? AND user_id = ?");
    $countStmt->execute([$categoryId, $userId]);
    $transactionCount = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_error'] = "An error occurred while fetching the category.";
    header("Location: categories.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } else {
        $postCategoryId = $_POST['id'] ?? null;
        if ($postCategoryId != $categoryId) {
             $error = "ID mismatch.";
        } elseif ($transactionCount > 0) {
             $error = "Cannot delete category with existing transactions. Please reassign or delete those transactions first.";
        } else {
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
                $deleteStmt->execute([$categoryId, $userId]);
                logAudit($pdo, $userId, 'CATEGORY_DELETE', 'Deleted category ID: ' . $categoryId);
                $_SESSION['flash_success'] = "Category deleted successfully.";
                header("Location: categories.php");
                exit;
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $error = "An error occurred while deleting the category.";
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2>Delete Category</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escapeOutput($error) ?></div>
<?php endif; ?>

<div class="content-card">
    <p>Are you sure you want to delete the category <strong><?= escapeOutput($category['name']) ?></strong>?</p>
    
    <?php if ($transactionCount > 0): ?>
        <div class="alert alert-warning">
            This category has <strong><?= escapeOutput($transactionCount) ?></strong> associated transactions. 
            You cannot delete it until these transactions are reassigned or deleted.
        </div>
    <?php endif; ?>

    <form method="POST" action="delete_category.php?id=<?= urlencode($categoryId) ?>">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= escapeOutput($categoryId) ?>">
        <button type="submit" class="btn btn-danger" <?= $transactionCount > 0 ? 'disabled' : '' ?>>Delete Category</button>
        <a href="categories.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
