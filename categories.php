<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();
$userId = getCurrentUserId();

$pageTitle = 'Categories';

try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
    $stmt->execute([$userId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_error'] = "An error occurred while fetching categories.";
    $categories = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Categories</h2>
    <a href="add_category.php" class="btn btn-primary">Add Category</a>
</div>

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

<div class="content-card">
    <?php if (empty($categories)): ?>
        <p class="text-muted">No categories found. Click 'Add Category' to create one.</p>
    <?php else: ?>
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= escapeOutput($category['name']) ?></td>
                        <td><?= escapeOutput(date('M j, Y H:i', strtotime($category['created_at']))) ?></td>
                        <td>
                            <a href="edit_category.php?id=<?= urlencode($category['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <a href="delete_category.php?id=<?= urlencode($category['id']) ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
