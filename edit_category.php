<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

$pageTitle = 'Edit Category';
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
        logAudit($pdo, $userId, 'UNAUTHORIZED_ACCESS', 'Attempted to edit non-owned category ID: ' . $categoryId);
        $_SESSION['flash_error'] = "Category not found or permission denied.";
        header("Location: categories.php");
        exit;
    }
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
        } else {
            $name = sanitizeInput($_POST['name'] ?? '');
            if (!validateCategoryName($name)) {
                $error = "Invalid category name. It must be between 1 and 100 characters long.";
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND id != ?");
                    $stmt->execute([$userId, $name, $categoryId]);
                    if ($stmt->fetch()) {
                        $error = "Another category with this name already exists.";
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ? AND user_id = ?");
                        $updateStmt->execute([$name, $categoryId, $userId]);
                        logAudit($pdo, $userId, 'CATEGORY_UPDATE', 'Updated category ID: ' . $categoryId);
                        $_SESSION['flash_success'] = "Category updated successfully.";
                        header("Location: categories.php");
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Database error: " . $e->getMessage());
                    $error = "An error occurred while updating the category.";
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2>Edit Category</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escapeOutput($error) ?></div>
<?php endif; ?>

<div class="content-card">
    <form method="POST" action="edit_category.php?id=<?= urlencode($categoryId) ?>">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= escapeOutput($categoryId) ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Category Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= escapeOutput($_POST['name'] ?? $category['name']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Category</button>
        <a href="categories.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
