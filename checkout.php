<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

function checkout_amount_to_cents(string $amount): int
{
    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
        throw new RuntimeException('The current product price is invalid.');
    }

    $whole = (int) $matches[1];
    $fraction = (int) str_pad($matches[2] ?? '', 2, '0');
    if ($whole > intdiv(PHP_INT_MAX - $fraction, 100)) {
        throw new RuntimeException('The order total is too large.');
    }

    return $whole * 100 + $fraction;
}

function checkout_format_cents(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function checkout_reference(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $suffix = '';
    for ($index = 0; $index < 6; $index++) {
        $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return 'ORD-' . date('Ymd') . '-' . $suffix;
}

$postedString = static fn (string $key): string =>
    is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
$values = [
    'name' => $postedString('name'),
    'email' => $postedString('email'),
    'phone' => $postedString('phone'),
    'address' => $postedString('address'),
];
$errors = [];
$checkoutError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $checkoutError = 'Your session could not be verified. Refresh the page and try again.';
    } else {
        if ($values['name'] === '' || strlen($values['name']) > 120) {
            $errors['name'] = 'Enter your name (up to 120 characters).';
        }
        if (
            $values['email'] === ''
            || strlen($values['email']) > 160
            || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Enter a valid email address.';
        }
        $phoneDigits = preg_replace('/\D+/', '', $values['phone']);
        if (
            $values['phone'] === ''
            || strlen($values['phone']) > 20
            || !preg_match('/^\+?[0-9().\-\s]+$/', $values['phone'])
            || strlen((string) $phoneDigits) < 7
            || strlen((string) $phoneDigits) > 15
        ) {
            $errors['phone'] = 'Enter a valid phone number, including its country code if needed.';
        }
        if ($values['address'] === '' || strlen($values['address']) > 2000) {
            $errors['address'] = 'Enter a delivery address (up to 2,000 characters).';
        }
    }
}

$items = cart_items();
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $checkoutError === ''
    && array_filter($items, static fn (array $item): bool => $item['stock_adjusted']) !== []
) {
    $checkoutError = 'Stock changed while you were checking out. Review the updated quantities and try again.';
}
if ($items === []) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $checkoutError === '' && $errors === []) {
        cart_queue_notice('Your cart is empty or the items are no longer available.');
        header('Location: cart.php');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        cart_queue_notice('Add an available item before proceeding to checkout.');
        header('Location: cart.php');
        exit;
    }
    $checkoutError = $checkoutError !== ''
        ? $checkoutError
        : 'Your cart is empty or the items are no longer available.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $checkoutError === '' && $errors === [] && $items !== []) {
    $orderedItems = $items;
    usort($orderedItems, static fn (array $left, array $right): int => $left['product_id'] <=> $right['product_id']);

    try {
        $pdo->beginTransaction();

        $lockProduct = $pdo->prepare(
            'SELECT id, name, price, stock, is_active FROM products WHERE id = ? FOR UPDATE'
        );
        $lockedProducts = [];
        $totalCents = 0;
        foreach ($orderedItems as $item) {
            $lockProduct->execute([$item['product_id']]);
            $product = $lockProduct->fetch();
            if (
                !$product
                || (int) $product['is_active'] !== 1
                || (int) $product['stock'] < (int) $item['quantity']
            ) {
                throw new DomainException(
                    $item['name'] . ' no longer has enough stock. Please review your cart and try again.'
                );
            }

            $unitPriceCents = checkout_amount_to_cents((string) $product['price']);
            $quantity = (int) $item['quantity'];
            $remainingCents = 999999999999 - $totalCents;
            if ($quantity < 1 || $unitPriceCents > intdiv($remainingCents, $quantity)) {
                throw new DomainException('The order total exceeds the supported limit.');
            }
            $lineCents = $unitPriceCents * $quantity;
            $totalCents += $lineCents;
            $lockedProducts[(int) $product['id']] = [
                'name' => $product['name'],
                'price' => checkout_format_cents($unitPriceCents),
            ];
        }

        $reference = checkout_reference();
        $insertOrder = $pdo->prepare(
            'INSERT INTO orders (reference, customer_name, email, phone, address, total)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertOrder->execute([
            $reference,
            $values['name'],
            $values['email'],
            $values['phone'],
            $values['address'],
            checkout_format_cents($totalCents),
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
             VALUES (?, ?, ?, ?, ?)'
        );
        $updateStock = $pdo->prepare(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
        );

        foreach ($orderedItems as $item) {
            $product = $lockedProducts[$item['product_id']];
            $quantity = (int) $item['quantity'];
            $insertItem->execute([
                $orderId,
                $item['product_id'],
                $product['name'],
                $product['price'],
                $quantity,
            ]);
            $updateStock->execute([$quantity, $item['product_id'], $quantity]);
            if ($updateStock->rowCount() !== 1) {
                throw new DomainException(
                    $product['name'] . ' just sold out. Please review your cart and try again.'
                );
            }
        }

        $pdo->commit();
        cart_clear();
        header('Location: order-success.php?ref=' . rawurlencode($reference));
        exit;
    } catch (DomainException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $checkoutError = $error->getMessage();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Checkout failed: ' . $error->getMessage());
        $checkoutError = 'We could not place your order right now. Your cart is safe; please try again.';
    }
}

$notice = cart_take_notice();
$subtotal = round(array_sum(array_column($items, 'line_total')), 2);
$pageTitle = 'Checkout';
require __DIR__ . '/partials/header.php';
?>
<section class="checkout-page">
    <div class="section-heading checkout-heading">
        <div>
            <p class="eyebrow">Just a few details</p>
            <h1>Checkout</h1>
        </div>
        <a class="back-link" href="cart.php"><span aria-hidden="true">←</span> Back to cart</a>
    </div>

    <?php if ($checkoutError !== ''): ?>
        <p class="notice notice-error" role="alert"><?= e($checkoutError) ?></p>
    <?php endif; ?>
    <?php if ($notice !== ''): ?>
        <p class="notice notice-success" role="status"><?= nl2br(e($notice)) ?></p>
    <?php endif; ?>

    <?php if ($items !== []): ?>
        <div class="checkout-layout">
            <form class="checkout-form" method="post" action="checkout.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <h2>Delivery details</h2>
                <div class="checkout-field">
                    <label for="checkout-name">Full name</label>
                    <input id="checkout-name" name="name" type="text" maxlength="120" autocomplete="name" value="<?= e($values['name']) ?>" required aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>">
                    <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
                </div>
                <div class="checkout-field">
                    <label for="checkout-email">Email address</label>
                    <input id="checkout-email" name="email" type="email" maxlength="160" autocomplete="email" value="<?= e($values['email']) ?>" required aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>">
                    <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
                </div>
                <div class="checkout-field">
                    <label for="checkout-phone">Phone number</label>
                    <input id="checkout-phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= e($values['phone']) ?>" required aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>">
                    <?php if (isset($errors['phone'])): ?><span class="field-error"><?= e($errors['phone']) ?></span><?php endif; ?>
                </div>
                <div class="checkout-field">
                    <label for="checkout-address">Delivery address</label>
                    <textarea id="checkout-address" name="address" rows="4" maxlength="2000" autocomplete="street-address" required aria-invalid="<?= isset($errors['address']) ? 'true' : 'false' ?>"><?= e($values['address']) ?></textarea>
                    <?php if (isset($errors['address'])): ?><span class="field-error"><?= e($errors['address']) ?></span><?php endif; ?>
                </div>
                <button class="button button-dark checkout-submit" type="submit">Place order <span aria-hidden="true">→</span></button>
                <p class="checkout-safe-note">Your order total is calculated from current product prices.</p>
            </form>

            <aside class="checkout-summary">
                <h2>Order summary <span><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></span></h2>
                <ul class="checkout-items">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <span class="checkout-item-quantity"><?= (int) $item['quantity'] ?></span>
                            <span class="checkout-item-name"><?= e($item['name']) ?></span>
                            <strong><?= e(money($item['line_total'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="summary-row checkout-subtotal">
                    <span>Subtotal</span>
                    <strong><?= e(money($subtotal)) ?></strong>
                </div>
                <p class="summary-note">Shipping and any applicable taxes will be confirmed separately.</p>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
