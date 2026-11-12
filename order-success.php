<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$reference = trim((string) ($_GET['ref'] ?? ''));
if (!preg_match('/^ORD-\d{8}-[A-Z0-9]{6}$/', $reference)) {
    http_response_code(404);
    $order = false;
} else {
    $statement = $pdo->prepare(
        'SELECT reference, total, created_at FROM orders WHERE reference = :reference LIMIT 1'
    );
    $statement->execute(['reference' => $reference]);
    $order = $statement->fetch();
    if (!$order) {
        http_response_code(404);
    }
}

$pageTitle = $order ? 'Order confirmed' : 'Order not found';
require __DIR__ . '/partials/header.php';
?>
<section class="order-confirmation">
    <?php if ($order): ?>
        <span class="confirmation-mark" aria-hidden="true">✓</span>
        <p class="eyebrow">Thank you for shopping with us</p>
        <h1>Your order is confirmed.</h1>
        <p class="confirmation-copy">We have received your order and will be in touch with delivery updates.</p>
        <div class="confirmation-reference">
            <span>Order reference</span>
            <strong><?= e($order['reference']) ?></strong>
        </div>
        <div class="confirmation-total">
            <span>Order total</span>
            <strong><?= e(money($order['total'])) ?></strong>
        </div>
        <a class="button button-dark" href="index.php">Continue shopping <span aria-hidden="true">→</span></a>
        <a class="confirmation-track-link" href="order-lookup.php?ref=<?= rawurlencode((string) $order['reference']) ?>">Track this order <span aria-hidden="true">→</span></a>
    <?php else: ?>
        <p class="eyebrow">A little detour</p>
        <h1>We couldn't find that order.</h1>
        <p class="confirmation-copy">Check the order reference or head back to the shop.</p>
        <a class="button button-dark" href="index.php">Back to the shop</a>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
