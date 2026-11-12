<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/admin_auth.php';
admin_require_auth();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    http_response_code(404);
}
$statusTransitions = [
    'pending' => ['paid', 'cancelled'],
    'paid' => ['shipped', 'cancelled'],
    'shipped' => ['delivered'],
    'delivered' => [],
    'cancelled' => [],
];
$notice = '';
$errorMessage = '';

if ($id !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'Your session could not be verified. Refresh the page and try again.';
    } else {
        $requestedStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        try {
            $pdo->beginTransaction();
            $orderLock = $pdo->prepare('SELECT id, status FROM orders WHERE id = ? FOR UPDATE');
            $orderLock->execute([$id]);
            $lockedOrder = $orderLock->fetch();
            if (!$lockedOrder) {
                throw new DomainException('That order could not be found.');
            }

            $currentStatus = (string) $lockedOrder['status'];
            if (!in_array($requestedStatus, $statusTransitions[$currentStatus] ?? [], true)) {
                throw new DomainException('That status change is not allowed. Refresh and try again.');
            }

            if ($requestedStatus === 'cancelled') {
                $itemLock = $pdo->prepare(
                    'SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id FOR UPDATE'
                );
                $itemLock->execute([$id]);
                $restoreStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                foreach ($itemLock->fetchAll() as $item) {
                    $restoreStock->execute([(int) $item['quantity'], (int) $item['product_id']]);
                    if ($restoreStock->rowCount() !== 1) {
                        throw new RuntimeException('Unable to restore inventory for cancelled order.');
                    }
                }
            }

            $updateStatus = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
            $updateStatus->execute([$requestedStatus, $id, $currentStatus]);
            if ($updateStatus->rowCount() !== 1) {
                throw new DomainException('The order changed before it could be updated. Refresh and try again.');
            }
            $pdo->commit();
            $_SESSION['admin_order_notice'] = 'Order status updated to ' . $requestedStatus . '.';
            header('Location: order.php?id=' . $id);
            exit;
        } catch (DomainException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $error->getMessage();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin order status update failed: ' . $error->getMessage());
            $errorMessage = 'We could not update this order. No changes were saved.';
        }
    }
}

if (isset($_SESSION['admin_order_notice'])) {
    $notice = (string) $_SESSION['admin_order_notice'];
    unset($_SESSION['admin_order_notice']);
}

$order = false;
$items = [];
if ($id !== false) {
    $orderStatement = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
    $orderStatement->execute(['id' => $id]);
    $order = $orderStatement->fetch();
    if ($order) {
        $itemsStatement = $pdo->prepare(
            'SELECT product_id, product_name, unit_price, quantity
             FROM order_items WHERE order_id = :order_id ORDER BY product_name'
        );
        $itemsStatement->execute(['order_id' => $id]);
        $items = $itemsStatement->fetchAll();
    } else {
        http_response_code(404);
    }
}

$adminPageTitle = $order ? 'Order ' . $order['reference'] : 'Order not found';
require __DIR__ . '/partials/header.php';
?>
<section class="admin-page">
    <div class="admin-page-heading">
        <div>
            <p class="eyebrow"><a href="orders.php">Orders</a> / Order details</p>
            <h1><?= $order ? e($order['reference']) : 'Order not found' ?></h1>
        </div>
        <a class="admin-back-link" href="orders.php"><span aria-hidden="true">←</span> All orders</a>
    </div>

    <?php if ($notice !== ''): ?><p class="notice notice-success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><p class="notice notice-error" role="alert"><?= e($errorMessage) ?></p><?php endif; ?>

    <?php if (!$order): ?>
        <section class="admin-panel"><p>This order could not be found.</p></section>
    <?php else: ?>
        <div class="admin-order-layout">
            <div>
                <section class="admin-panel order-customer-panel">
                    <div class="admin-panel-heading">
                        <div>
                            <p class="eyebrow">Placed <?= e(date('M j, Y · g:i a', strtotime((string) $order['created_at']))) ?></p>
                            <h2>Customer details</h2>
                        </div>
                        <span class="status-badge status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
                    </div>
                    <dl class="order-customer-details">
                        <div><dt>Name</dt><dd><?= e($order['customer_name']) ?></dd></div>
                        <div><dt>Email</dt><dd><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></dd></div>
                        <div><dt>Phone</dt><dd><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></dd></div>
                        <div><dt>Delivery address</dt><dd><?= nl2br(e($order['address'])) ?></dd></div>
                    </dl>
                </section>

                <section class="admin-panel admin-line-items-panel">
                    <div class="admin-panel-heading">
                        <div>
                            <p class="eyebrow">Purchase snapshot</p>
                            <h2>Line items</h2>
                        </div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-line-items">
                            <thead><tr><th scope="col">Product</th><th scope="col">Unit price</th><th scope="col">Quantity</th><th scope="col">Total</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e($item['product_name']) ?></td>
                                    <td><?= e(money($item['unit_price'])) ?></td>
                                    <td><?= (int) $item['quantity'] ?></td>
                                    <td class="admin-order-total"><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr><th colspan="3">Order total</th><th><?= e(money($order['total'])) ?></th></tr></tfoot>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="admin-panel admin-status-panel">
                <p class="eyebrow">Order workflow</p>
                <h2>Update status</h2>
                <?php $nextStatuses = $statusTransitions[$order['status']] ?? []; ?>
                <?php if ($nextStatuses !== []): ?>
                    <form class="admin-status-form" method="post" action="order.php?id=<?= (int) $order['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label for="next-status">Move from <strong><?= e(ucfirst($order['status'])) ?></strong> to</label>
                        <select id="next-status" name="status" required>
                            <?php foreach ($nextStatuses as $nextStatus): ?>
                                <option value="<?= e($nextStatus) ?>"><?= e(ucfirst($nextStatus)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button button-dark" type="submit">Save status <span aria-hidden="true">→</span></button>
                    </form>
                    <?php if (in_array('cancelled', $nextStatuses, true)): ?>
                        <p class="admin-cancel-note">Cancelling restores the quantities to product stock.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="admin-terminal-note">This order is <?= e($order['status']) ?>. No further status changes are available.</p>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
