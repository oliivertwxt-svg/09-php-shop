<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'Your session could not be verified. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update') {
            $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $quantity === false) {
                cart_queue_notice('Enter a valid product quantity.');
            } else {
                $result = cart_update($id, $quantity);
                if ($result['notice'] !== null) {
                    cart_queue_notice($result['notice']);
                }
            }
        } elseif ($action === 'remove') {
            $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                cart_queue_notice('The selected cart item could not be removed.');
            } else {
                cart_remove($id);
                cart_queue_notice('Item removed from your cart.');
            }
        } elseif ($action === 'clear') {
            cart_clear();
            cart_queue_notice('Your cart has been cleared.');
        } elseif ($action === 'checkout') {
            cart_queue_notice('Checkout is not available yet. Your cart has been kept.');
        } else {
            cart_queue_notice('That cart action is not supported.');
        }

        header('Location: cart.php');
        exit;
    }
}

$items = cart_items();
$total = cart_total();
$notice = cart_take_notice();
$pageTitle = 'Your cart';
require __DIR__ . '/partials/header.php';
?>
<section class="cart-page">
    <div class="section-heading cart-heading">
        <div>
            <p class="eyebrow">Saved for you</p>
            <h1>Your cart</h1>
        </div>
        <a class="back-link" href="index.php"><span aria-hidden="true">←</span> Continue shopping</a>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <p class="notice notice-error" role="alert"><?= e($errorMessage) ?></p>
    <?php endif; ?>
    <?php if ($notice !== ''): ?>
        <p class="notice notice-success" role="status"><?= nl2br(e($notice)) ?></p>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="empty-state cart-empty">
            <span class="empty-icon" aria-hidden="true">⌑</span>
            <h2>Your cart is taking a little break.</h2>
            <p>Find something lovely to bring home.</p>
            <a class="button button-dark" href="index.php">Explore the collection <span aria-hidden="true">→</span></a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-table-wrap">
                <table class="cart-table">
                    <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Price</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Total</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $updateFormId = 'update-' . $item['product_id']; ?>
                        <tr>
                            <td>
                                <div class="cart-product">
                                    <a class="cart-thumb" href="product.php?slug=<?= rawurlencode((string) $item['slug']) ?>">
                                        <img src="<?= e($item['image_url']) ?>" alt="">
                                    </a>
                                    <div>
                                        <a class="cart-product-name" href="product.php?slug=<?= rawurlencode((string) $item['slug']) ?>"><?= e($item['name']) ?></a>
                                        <span class="cart-stock"><?= (int) $item['stock'] ?> in stock</span>
                                    </div>
                                </div>
                            </td>
                            <td class="cart-price"><?= e(money($item['price'])) ?></td>
                            <td>
                                <form id="<?= e($updateFormId) ?>" class="cart-update-form" method="post" action="cart.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                    <label class="visually-hidden" for="quantity-<?= (int) $item['product_id'] ?>">Quantity of <?= e($item['name']) ?></label>
                                    <input id="quantity-<?= (int) $item['product_id'] ?>" class="cart-quantity" type="number" name="quantity" min="1" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>" required>
                                    <button class="cart-update-button" type="submit">Update</button>
                                </form>
                            </td>
                            <td class="cart-line-total"><?= e(money($item['line_total'])) ?></td>
                            <td>
                                <form class="cart-remove-form" method="post" action="cart.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                    <button class="cart-remove-button" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <form class="clear-cart-form" method="post" action="cart.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="clear">
                    <button class="cart-remove-button" type="submit">Clear cart</button>
                </form>
            </div>

            <aside class="cart-summary">
                <h2>Order summary</h2>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <strong><?= e(money($total)) ?></strong>
                </div>
                <p class="summary-note">Shipping and any applicable taxes are calculated at checkout.</p>
                <a class="button button-dark checkout-button" href="checkout.php">Proceed to checkout <span aria-hidden="true">→</span></a>
                <a class="summary-continue" href="index.php">Continue shopping</a>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
