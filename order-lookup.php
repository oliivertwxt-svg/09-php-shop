<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$reference = is_string($_POST['reference'] ?? null)
    ? strtoupper(trim($_POST['reference']))
    : (is_string($_GET['ref'] ?? null) ? strtoupper(trim($_GET['ref'])) : '');
$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$errorMessage = '';
$order = false;
$items = [];
$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($submitted) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'Your session could not be verified. Refresh the page and try again.';
    } elseif (
        !preg_match('/^ORD-\d{8}-[A-Z0-9]{6}$/', $reference)
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || strlen($email) > 160
    ) {
        $errorMessage = 'Enter a valid order reference and email address.';
    } else {
        $orderStatement = $pdo->prepare(
            'SELECT id, reference, customer_name, email, total, status, created_at
             FROM orders
             WHERE reference = :reference AND email = :email
             LIMIT 1'
        );
        $orderStatement->execute(['reference' => $reference, 'email' => $email]);
        $order = $orderStatement->fetch();
        if ($order) {
            $itemsStatement = $pdo->prepare(
                'SELECT product_name, unit_price, quantity
                 FROM order_items WHERE order_id = :order_id ORDER BY product_name'
            );
            $itemsStatement->execute(['order_id' => $order['id']]);
            $items = $itemsStatement->fetchAll();
        } else {
            $errorMessage = 'We could not find an order matching those details.';
        }
    }
}

$pageTitle = 'Track your order';
require __DIR__ . '/partials/header.php';
?>
<section class="order-lookup-page">
    <div class="section-heading lookup-heading">
        <div>
            <p class="eyebrow">Here for the update</p>
            <h1>Track your order</h1>
        </div>
        <a class="back-link" href="index.php"><span aria-hidden="true">←</span> Back to shop</a>
    </div>

    <?php if ($errorMessage !== ''): ?><p class="notice notice-error" role="alert"><?= e($errorMessage) ?></p><?php endif; ?>
    <form class="lookup-form" method="post" action="order-lookup.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="lookup-reference">Order reference</label>
        <input id="lookup-reference" name="reference" type="text" maxlength="20" value="<?= e($reference) ?>" placeholder="ORD-20261112-ABC123" required>
        <label for="lookup-email">Email used at checkout</label>
        <input id="lookup-email" name="email" type="email" maxlength="160" autocomplete="email" value="<?= e($email) ?>" required>
        <button class="button button-dark" type="submit">Look up order <span aria-hidden="true">→</span></button>
    </form>

    <?php if ($order): ?>
        <section class="lookup-result" aria-live="polite">
            <div class="lookup-result-heading">
                <div>
                    <p class="eyebrow">Order placed <?= e(date('M j, Y', strtotime((string) $order['created_at']))) ?></p>
                    <h2><?= e($order['reference']) ?></h2>
                </div>
                <span class="status-badge status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
            </div>
            <p class="lookup-greeting">Hello <?= e($order['customer_name']) ?>, here is the latest status of your order.</p>
            <ul class="checkout-items">
                <?php foreach ($items as $item): ?>
                    <li>
                        <span class="checkout-item-quantity"><?= (int) $item['quantity'] ?></span>
                        <span class="checkout-item-name"><?= e($item['product_name']) ?></span>
                        <strong><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="summary-row lookup-total"><span>Order total</span><strong><?= e(money($order['total'])) ?></strong></div>
        </section>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
