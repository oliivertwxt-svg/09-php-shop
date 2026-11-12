<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Your session could not be verified. Refresh the page and try again.');
}

unset($_SESSION['admin_authenticated'], $_SESSION['admin_username']);
if (!session_regenerate_id(true)) {
    throw new RuntimeException('Unable to secure the signed-out session.');
}
header('Location: login.php');
exit;
