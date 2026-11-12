<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/admin_auth.php';

if (admin_is_authenticated()) {
    header('Location: index.php');
    exit;
}

$errorMessage = '';
$username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'Your session could not be verified. Refresh the page and try again.';
    } elseif (!admin_is_configured()) {
        $errorMessage = 'Admin login is not configured. Follow the setup instructions in README.md.';
    } else {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (admin_attempt_login($username, $password)) {
            header('Location: index.php');
            exit;
        }
        $errorMessage = 'The username or password was not recognized.';
    }
}

$adminPageTitle = 'Admin sign in';
require __DIR__ . '/partials/login_header.php';
?>
<section class="admin-login">
    <p class="eyebrow">Store management</p>
    <h1>Welcome back.</h1>
    <p class="admin-login-copy">Sign in to manage orders and view shop activity.</p>
    <?php if (!admin_is_configured()): ?>
        <p class="notice notice-error" role="alert">Admin login is not configured. Set <code>SHOP_ADMIN_USERNAME</code> and <code>SHOP_ADMIN_PASSWORD_HASH</code> for Apache as described in README.md.</p>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <p class="notice notice-error" role="alert"><?= e($errorMessage) ?></p>
    <?php endif; ?>
    <form class="admin-login-form" method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="admin-username">Username</label>
        <input id="admin-username" name="username" type="text" maxlength="100" autocomplete="username" value="<?= e($username) ?>" required>
        <label for="admin-password">Password</label>
        <input id="admin-password" name="password" type="password" autocomplete="current-password" required>
        <button class="button button-dark" type="submit">Sign in <span aria-hidden="true">→</span></button>
    </form>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
