<?php
declare(strict_types=1);

function admin_is_configured(): bool
{
    $username = getenv('SHOP_ADMIN_USERNAME');
    $passwordHash = getenv('SHOP_ADMIN_PASSWORD_HASH');

    return is_string($username)
        && trim($username) !== ''
        && is_string($passwordHash)
        && password_get_info($passwordHash)['algo'] !== null;
}

function admin_is_authenticated(): bool
{
    return ($_SESSION['admin_authenticated'] ?? false) === true
        && is_string($_SESSION['admin_username'] ?? null);
}

function admin_attempt_login(string $username, string $password): bool
{
    $configuredUsername = getenv('SHOP_ADMIN_USERNAME');
    $passwordHash = getenv('SHOP_ADMIN_PASSWORD_HASH');
    if (!admin_is_configured() || !is_string($configuredUsername) || !is_string($passwordHash)) {
        return false;
    }

    $usernameMatches = hash_equals($configuredUsername, $username);
    $passwordMatches = password_verify($password, $passwordHash);
    if (!$usernameMatches || !$passwordMatches) {
        return false;
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to secure the administrator session.');
    }

    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = $configuredUsername;

    return true;
}

function admin_require_auth(): void
{
    if (!admin_is_authenticated()) {
        header('Location: login.php');
        exit;
    }
}
