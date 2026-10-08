<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/audit.php';
initSession();

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!validateName($name)) {
            $errors[] = 'Name must be between 2 and 100 characters.';
        }
        if (!validateEmail($email)) {
            $errors[] = 'Invalid email format or length.';
        }
        $passwordErrors = validatePassword($password);
        if (!empty($passwordErrors)) {
            $errors = array_merge($errors, $passwordErrors);
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email is already registered.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
                    $stmt->execute([$name, $email, $hashedPassword]);
                    $newUserId = $pdo->lastInsertId();

                    $defaultCategories = ['Food', 'Travel', 'Shopping', 'Bills', 'Entertainment', 'Education', 'Salary', 'Freelance', 'Other'];
                    $stmtCat = $pdo->prepare('INSERT INTO categories (user_id, name) VALUES (?, ?)');
                    foreach ($defaultCategories as $category) {
                        $stmtCat->execute([$newUserId, $category]);
                    }

                    logAudit($pdo, $newUserId, 'REGISTER', 'New user registered');
                    
                    $_SESSION['register_success'] = 'Registration successful. Please login.';
                    header('Location: login.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Database error during registration: ' . $e->getMessage());
                $errors[] = 'A database error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="card auth-card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0"><i class="bi bi-person-plus"></i> Register</h3>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= escapeOutput($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= escapeOutput($name) ?>" required autocomplete="name">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= escapeOutput($email) ?>" required autocomplete="email">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                    <small class="text-muted">Must be at least 8 characters, include uppercase, lowercase, number, and special character.</small>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Create Account</button>
            </form>
            <div class="mt-3 text-center">
                <a href="login.php" class="text-decoration-none">Already have an account? <strong>Login here</strong></a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
