<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$statement = $pdo->prepare(
    'SELECT p.id, p.name, p.slug, p.description, p.price, p.stock, p.image_url,
            c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.slug = :slug AND p.is_active = 1
     LIMIT 1'
);
$statement->execute(['slug' => $slug]);
$product = $statement->fetch();

if (!$product) {
    http_response_code(404);
}

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $message = 'Your session could not be verified. Refresh the page and try again.';
        $messageType = 'error';
    } else {
        $quantityValue = $_POST['quantity'] ?? null;
        $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($quantity === false) {
            $message = 'Choose a valid quantity.';
            $messageType = 'error';
        } else {
            $result = cart_add((int) $product['id'], $quantity);
            if ($result['ok']) {
                cart_queue_notice($result['notice'] ?? ($product['name'] . ' added to your cart.'));
                header('Location: product.php?slug=' . rawurlencode((string) $product['slug']));
                exit;
            }
            $message = $result['notice'];
            $messageType = 'error';
        }
    }
}

if ($message === '') {
    $message = cart_take_notice();
}

$pageTitle = $product ? (string) $product['name'] : 'Product not found';
require __DIR__ . '/partials/header.php';
?>
<?php if (!$product): ?>
    <section class="not-found">
        <p class="eyebrow">A little detour</p>
        <h1>We couldn't find that product.</h1>
        <p>It may have moved on. Take a look at the rest of the collection.</p>
        <a class="button button-dark" href="index.php">Back to the shop</a>
    </section>
<?php else: ?>
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="index.php">Shop</a><span aria-hidden="true">/</span>
        <?php if ($product['category_name'] !== null): ?><span><?= e($product['category_name']) ?></span><span aria-hidden="true">/</span><?php endif; ?>
        <span><?= e($product['name']) ?></span>
    </nav>
    <section class="product-detail">
        <div class="detail-image-wrap">
            <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>">
            <?php if ((int) $product['stock'] < 1): ?>
                <span class="stock-badge">Out of stock</span>
            <?php endif; ?>
        </div>
        <div class="detail-copy">
            <?php if ($product['category_name'] !== null): ?>
                <p class="eyebrow"><?= e($product['category_name']) ?></p>
            <?php endif; ?>
            <h1><?= e($product['name']) ?></h1>
            <p class="detail-price"><?= e(money($product['price'])) ?></p>
            <div class="detail-description"><?= nl2br(e($product['description'])) ?></div>
            <?php if ($message !== ''): ?>
                <p class="notice notice-<?= e($messageType) ?>" role="status"><?= e($message) ?></p>
            <?php endif; ?>
            <?php if ((int) $product['stock'] > 0): ?>
                <form class="add-to-cart-form" method="post" action="product.php?slug=<?= rawurlencode((string) $product['slug']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label class="quantity-label" for="quantity">Quantity</label>
                    <div class="purchase-row">
                        <input id="quantity" name="quantity" type="number" min="1" max="<?= (int) $product['stock'] ?>" value="1" required>
                        <button class="button button-dark add-button" type="submit">Add to cart <span aria-hidden="true">→</span></button>
                    </div>
                    <p class="stock-note"><?= (int) $product['stock'] ?> available</p>
                </form>
            <?php else: ?>
                <button class="button button-disabled" type="button" disabled>Currently unavailable</button>
            <?php endif; ?>
            <a class="back-link" href="index.php"><span aria-hidden="true">←</span> Back to the collection</a>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
