<?php
$adminPageTitle = $adminPageTitle ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f5f0">
    <title><?= e($adminPageTitle) ?> | Swd Shop Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="index.php"><span class="brand-mark">s</span><span>swd<span class="brand-light">shop</span> <span class="admin-brand-label">admin</span></span></a>
        <nav class="main-nav" aria-label="Admin navigation">
            <a href="../index.php">View shop</a>
            <a href="index.php">Dashboard</a>
            <a href="orders.php">Orders</a>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="admin-logout-button" type="submit">Sign out</button>
            </form>
        </nav>
    </div>
</header>
<main class="page-shell">
