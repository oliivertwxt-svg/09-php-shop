<?php
declare(strict_types=1);

function cart_session(): array
{
    $storedCart = $_SESSION['cart'] ?? [];
    $cart = [];

    if (is_array($storedCart)) {
        foreach ($storedCart as $productId => $quantity) {
            $id = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $count = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false && $count !== false) {
                $cart[$id] = $count;
            }
        }
    }

    $_SESSION['cart'] = $cart;

    return $cart;
}

function cart_product(int $id): ?array
{
    if ($id < 1) {
        return null;
    }

    global $pdo;
    $statement = $pdo->prepare(
        'SELECT id, name, stock FROM products WHERE id = :id AND is_active = 1 LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();

    return $product ?: null;
}

function cart_add(int $id, int $qty): array
{
    $product = cart_product($id);
    if (!$product) {
        return ['ok' => false, 'adjusted' => false, 'quantity' => 0, 'notice' => 'This product is no longer available.'];
    }

    $stock = (int) $product['stock'];
    $cart = cart_session();
    $oldQuantity = $cart[$id] ?? 0;
    if ($stock < 1) {
        unset($cart[$id]);
        $_SESSION['cart'] = $cart;

        return ['ok' => false, 'adjusted' => false, 'quantity' => 0, 'notice' => 'This product is out of stock.'];
    }

    $quantity = min($oldQuantity + max(0, $qty), $stock);
    if ($quantity < 1) {
        return ['ok' => false, 'adjusted' => false, 'quantity' => 0, 'notice' => 'Choose a quantity of at least one.'];
    }

    $cart[$id] = $quantity;
    $_SESSION['cart'] = $cart;
    $adjusted = $quantity !== $oldQuantity + $qty;

    return [
        'ok' => true,
        'adjusted' => $adjusted,
        'quantity' => $quantity,
        'notice' => $adjusted
            ? 'Quantity adjusted to ' . $quantity . ' to match available stock.'
            : null,
    ];
}

function cart_update(int $id, int $qty): array
{
    $product = cart_product($id);
    if (!$product) {
        cart_remove($id);

        return ['ok' => false, 'adjusted' => false, 'quantity' => 0, 'notice' => 'This product is no longer available and was removed from your cart.'];
    }

    $stock = (int) $product['stock'];
    if ($stock < 1) {
        cart_remove($id);

        return ['ok' => false, 'adjusted' => false, 'quantity' => 0, 'notice' => 'This product is out of stock and was removed from your cart.'];
    }

    $quantity = min(max(1, $qty), $stock);
    $cart = cart_session();
    $cart[$id] = $quantity;
    $_SESSION['cart'] = $cart;
    $adjusted = $quantity !== $qty;

    return [
        'ok' => true,
        'adjusted' => $adjusted,
        'quantity' => $quantity,
        'notice' => $adjusted
            ? 'Quantity adjusted to ' . $quantity . ' to match available stock.'
            : 'Cart updated.',
    ];
}

function cart_remove(int $id): void
{
    $cart = cart_session();
    unset($cart[$id]);
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_queue_notice(string $notice): void
{
    $existing = $_SESSION['cart_notice'] ?? '';
    $_SESSION['cart_notice'] = $existing === ''
        ? $notice
        : $existing . "\n" . $notice;
}

function cart_take_notice(): string
{
    $notice = (string) ($_SESSION['cart_notice'] ?? '');
    unset($_SESSION['cart_notice']);

    return $notice;
}

function cart_items(): array
{
    $cart = cart_session();
    if ($cart === []) {
        return [];
    }

    global $pdo;
    $placeholders = implode(', ', array_fill(0, count($cart), '?'));
    $statement = $pdo->prepare(
        "SELECT id, name, slug, image_url, price, stock, is_active
         FROM products
         WHERE id IN ({$placeholders})"
    );
    $statement->execute(array_keys($cart));
    $products = [];
    foreach ($statement->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }

    $lines = [];
    foreach ($cart as $id => $quantity) {
        $product = $products[$id] ?? null;
        if (!$product || (int) $product['is_active'] !== 1 || (int) $product['stock'] < 1) {
            unset($cart[$id]);
            cart_queue_notice(($product['name'] ?? 'An item') . ' was removed from your cart because it is no longer available.');
            continue;
        }

        $stock = (int) $product['stock'];
        $adjusted = $quantity > $stock;
        if ($adjusted) {
            $quantity = $stock;
            $cart[$id] = $quantity;
            cart_queue_notice($product['name'] . ' quantity was reduced to ' . $quantity . ' to match available stock.');
        }

        $price = (float) $product['price'];
        $lines[] = [
            'product_id' => $id,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'image_url' => $product['image_url'],
            'quantity' => $quantity,
            'price' => $price,
            'line_total' => round($price * $quantity, 2),
            'stock' => $stock,
            'stock_ok' => $quantity <= $stock,
            'stock_adjusted' => $adjusted,
        ];
    }

    $_SESSION['cart'] = $cart;

    return $lines;
}

function cart_total(): float
{
    return round(array_sum(array_column(cart_items(), 'line_total')), 2);
}

function cart_count(): int
{
    return array_sum(cart_session());
}
