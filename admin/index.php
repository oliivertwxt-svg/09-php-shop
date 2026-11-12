<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/admin_auth.php';
admin_require_auth();

$salesStatement = $pdo->query(
    "SELECT COALESCE(SUM(total), 0)
     FROM orders
     WHERE created_at >= CURRENT_DATE()
       AND status IN ('paid', 'shipped', 'delivered')"
);
$todaySales = $salesStatement->fetchColumn();

$statusRows = $pdo->query(
    'SELECT status, COUNT(*) AS order_count FROM orders GROUP BY status'
)->fetchAll();
$statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
$ordersByStatus = array_fill_keys($statuses, 0);
foreach ($statusRows as $row) {
    if (isset($ordersByStatus[$row['status']])) {
        $ordersByStatus[$row['status']] = (int) $row['order_count'];
    }
}

$topProducts = $pdo->query(
    "SELECT oi.product_name, SUM(oi.quantity) AS quantity_sold
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'cancelled'
     GROUP BY oi.product_id, oi.product_name
     ORDER BY quantity_sold DESC, oi.product_name ASC
     LIMIT 5"
)->fetchAll();

$adminPageTitle = 'Dashboard';
require __DIR__ . '/partials/header.php';
?>
<section class="admin-page">
    <div class="admin-page-heading">
        <div>
            <p class="eyebrow">Store overview</p>
            <h1>Dashboard</h1>
        </div>
        <a class="button button-dark" href="orders.php">Manage orders <span aria-hidden="true">→</span></a>
    </div>

    <div class="admin-stat-grid">
        <article class="admin-stat-card admin-sales-card">
            <span>Today's sales</span>
            <strong><?= e(money($todaySales)) ?></strong>
            <small>Paid, shipped and delivered orders</small>
        </article>
        <?php foreach ($statuses as $status): ?>
            <a class="admin-stat-card" href="orders.php?status=<?= rawurlencode($status) ?>">
                <span><?= e(ucfirst($status)) ?> orders</span>
                <strong><?= (int) $ordersByStatus[$status] ?></strong>
                <small>View matching orders <span aria-hidden="true">→</span></small>
            </a>
        <?php endforeach; ?>
    </div>

    <section class="admin-panel">
        <div class="admin-panel-heading">
            <div>
                <p class="eyebrow">Best sellers</p>
                <h2>Top products by quantity sold</h2>
            </div>
        </div>
        <?php if ($topProducts === []): ?>
            <p class="admin-empty">No completed sales to report yet.</p>
        <?php else: ?>
            <ol class="top-products-list">
                <?php foreach ($topProducts as $index => $product): ?>
                    <li>
                        <span class="top-product-rank"><?= $index + 1 ?></span>
                        <span class="top-product-name"><?= e($product['product_name']) ?></span>
                        <strong><?= (int) $product['quantity_sold'] ?> sold</strong>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
