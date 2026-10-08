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
$email = '';
$success = '';

if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $pdo->prepare('SELECT id, name, password FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                logAudit($pdo, $user['id'], 'LOGIN_SUCCESS', 'User logged in');
                header('Location: dashboard.php');
                exit;
            } else {
                logAudit($pdo, null, 'LOGIN_FAILED', 'Failed login attempt for email: ' . $email);
                $errors[] = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log('Database error during login: ' . $e->getMessage());
            $errors[] = 'A database error occurred. Please try again later.';
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="card auth-card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> Login</h3>
        </div>
        <div class="card-body p-4">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= escapeOutput($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= escapeOutput($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= escapeOutput($email) ?>" required autocomplete="email">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Login</button>
            </form>
            <div class="mt-3 text-center">
                <a href="register.php" class="text-decoration-none">Don't have an account? <strong>Register here</strong></a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
