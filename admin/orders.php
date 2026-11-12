<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/admin_auth.php';
admin_require_auth();

$statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$dateFrom = is_string($_GET['date_from'] ?? null) ? $_GET['date_from'] : '';
$dateTo = is_string($_GET['date_to'] ?? null) ? $_GET['date_to'] : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$pageSize = 30;
$filters = [];
$params = [];
$filterError = '';
$isValidDate = static function (string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
};

if ($status !== '' && !in_array($status, $statuses, true)) {
    $filterError = 'Choose a valid order status.';
    $status = '';
}
if ($dateFrom !== '' && !$isValidDate($dateFrom)) {
    $filterError = 'Enter a valid start date.';
    $dateFrom = '';
}
if ($dateTo !== '' && !$isValidDate($dateTo)) {
    $filterError = 'Enter a valid end date.';
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $filterError = 'The start date must be on or before the end date.';
}
if ($status !== '') {
    $filters[] = 'o.status = :status';
    $params['status'] = $status;
}
if ($dateFrom !== '') {
    $filters[] = 'o.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $dateAfterTo = (new DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d');
    $filters[] = 'o.created_at < :date_after_to';
    $params['date_after_to'] = $dateAfterTo . ' 00:00:00';
}
$where = $filters === [] ? '' : ' WHERE ' . implode(' AND ', $filters);

$countStatement = $pdo->prepare('SELECT COUNT(*) FROM orders o' . $where);
$countStatement->execute($params);
$totalOrders = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalOrders / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$ordersStatement = $pdo->prepare(
    'SELECT o.id, o.reference, o.customer_name, o.email, o.total, o.status, o.created_at,
            COALESCE(SUM(oi.quantity), 0) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id' . $where . '
     GROUP BY o.id, o.reference, o.customer_name, o.email, o.total, o.status, o.created_at
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT :page_size OFFSET :offset'
);
foreach ($params as $name => $value) {
    $ordersStatement->bindValue(':' . $name, $value);
}
$ordersStatement->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
$ordersStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$ordersStatement->execute();
$orders = $ordersStatement->fetchAll();

$queryFilters = array_filter([
    'status' => $status,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);
$adminPageTitle = 'Orders';
require __DIR__ . '/partials/header.php';
?>
<section class="admin-page">
    <div class="admin-page-heading">
        <div>
            <p class="eyebrow">Order management</p>
            <h1>Orders</h1>
        </div>
        <span class="admin-result-count"><?= $totalOrders ?> <?= $totalOrders === 1 ? 'order' : 'orders' ?></span>
    </div>

    <?php if ($filterError !== ''): ?><p class="notice notice-error" role="alert"><?= e($filterError) ?></p><?php endif; ?>

    <form class="admin-filters" method="get" action="orders.php">
        <label>
            <span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>From</span>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
        </label>
        <label>
            <span>To</span>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>">
        </label>
        <button class="button button-dark" type="submit">Filter orders</button>
        <a class="admin-clear-link" href="orders.php">Clear</a>
    </form>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th scope="col">Order</th>
                <th scope="col">Customer</th>
                <th scope="col">Date</th>
                <th scope="col">Items</th>
                <th scope="col">Total</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="visually-hidden">Details</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><a class="admin-order-ref" href="order.php?id=<?= (int) $order['id'] ?>"><?= e($order['reference']) ?></a></td>
                    <td><span class="admin-customer-name"><?= e($order['customer_name']) ?></span><small><?= e($order['email']) ?></small></td>
                    <td><?= e(date('M j, Y · g:i a', strtotime((string) $order['created_at']))) ?></td>
                    <td><?= (int) $order['item_count'] ?></td>
                    <td class="admin-order-total"><?= e(money($order['total'])) ?></td>
                    <td><span class="status-badge status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td>
                    <td><a class="admin-details-link" href="order.php?id=<?= (int) $order['id'] ?>">View <span aria-hidden="true">→</span></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td class="admin-no-results" colspan="7">No orders match those filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="admin-pagination" aria-label="Order pages">
            <?php if ($page > 1): ?>
                <a href="orders.php?<?= e(http_build_query($queryFilters + ['page' => $page - 1])) ?>">← Previous</a>
            <?php else: ?><span aria-disabled="true">← Previous</span><?php endif; ?>
            <span>Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="orders.php?<?= e(http_build_query($queryFilters + ['page' => $page + 1])) ?>">Next →</a>
            <?php else: ?><span aria-disabled="true">Next →</span><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
