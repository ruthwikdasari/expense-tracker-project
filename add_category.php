<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

$pageTitle = 'Add Category';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        if (!validateCategoryName($name)) {
            $error = "Invalid category name. It must be between 1 and 100 characters long.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ?");
                $stmt->execute([$userId, $name]);
                if ($stmt->fetch()) {
                    $error = "A category with this name already exists.";
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
                    $insertStmt->execute([$userId, $name]);
                    logAudit($pdo, $userId, 'CATEGORY_CREATE', 'Created category: ' . $name);
                    $_SESSION['flash_success'] = "Category added successfully.";
                    header("Location: categories.php");
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $error = "An error occurred while creating the category.";
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2>Add Category</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escapeOutput($error) ?></div>
<?php endif; ?>

<div class="content-card">
    <form method="POST" action="add_category.php">
        <?= csrfField() ?>
        <div class="mb-3">
            <label for="name" class="form-label">Category Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= escapeOutput($_POST['name'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Category</button>
        <a href="categories.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
