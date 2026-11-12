<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require __DIR__ . '/app/bootstrap.php';

$categoryRows = [
    ['Home & Living', 'home-living'],
    ['Accessories', 'accessories'],
    ['Kitchen', 'kitchen'],
    ['Wellness', 'wellness'],
    ['Stationery', 'stationery'],
];

$pdo->beginTransaction();
try {
    $categoryInsert = $pdo->prepare(
        'INSERT INTO categories (name, slug) VALUES (:name, :slug)
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    foreach ($categoryRows as [$name, $slug]) {
        $categoryInsert->execute(['name' => $name, 'slug' => $slug]);
    }

    $categoryLookup = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
    $categoryIds = [];
    foreach ($categoryRows as [, $slug]) {
        $categoryLookup->execute(['slug' => $slug]);
        $id = $categoryLookup->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Unable to find seeded category: ' . $slug);
        }
        $categoryIds[$slug] = (int) $id;
    }

    $products = [
        ['home-living', 'Sculpted Ceramic Vase', 'A softly sculpted stoneware vase with a matte finish, made to bring a little calm to any shelf.', 18500, 12, 'Sculpted+Ceramic+Vase', 1],
        ['home-living', 'Woven Market Basket', 'A roomy, handwoven basket for market mornings, beach days, and all the bits in between.', 22000, 8, 'Woven+Market+Basket', 1],
        ['home-living', 'Linen Cushion Cover', 'A breathable linen cushion cover in a warm natural shade, finished with a hidden zip.', 12500, 15, 'Linen+Cushion+Cover', 1],
        ['accessories', 'Everyday Canvas Tote', 'A sturdy cotton-canvas carryall with comfortable straps and space for your daily essentials.', 9500, 24, 'Everyday+Canvas+Tote', 1],
        ['accessories', 'Minimal Leather Wallet', 'A slim, thoughtfully organised wallet crafted from supple genuine leather.', 28500, 6, 'Minimal+Leather+Wallet', 1],
        ['accessories', 'Classic Round Sunglasses', 'Lightweight frames with UV-protective lenses for bright days and easy weekends.', 19500, 0, 'Classic+Round+Sunglasses', 1],
        ['kitchen', 'Pour-Over Coffee Set', 'A ceramic dripper and matching cup for a slower, better morning coffee ritual.', 24000, 9, 'Pour-Over+Coffee+Set', 1],
        ['kitchen', 'Insulated Travel Flask', 'A double-wall stainless steel flask that keeps drinks hot or cold while you are on the move.', 16500, 18, 'Insulated+Travel+Flask', 1],
        ['kitchen', 'Acacia Serving Board', 'A richly grained acacia board for serving snacks, slicing fruit, or styling your kitchen.', 14500, 7, 'Acacia+Serving+Board', 1],
        ['wellness', 'Botanical Soy Candle', 'A clean-burning soy candle with a gentle botanical scent and a reusable glass vessel.', 11000, 21, 'Botanical+Soy+Candle', 1],
        ['wellness', 'Daily Ritual Journal', 'An undated journal with guided prompts for reflection, intention, and everyday notes.', 8500, 30, 'Daily+Ritual+Journal', 1],
        ['wellness', 'Cotton Bath Towel Set', 'A soft, absorbent pair of generously sized cotton towels in a timeless neutral tone.', 26000, 5, 'Cotton+Bath+Towel+Set', 1],
        ['stationery', 'Pocket Notebook Trio', 'Three pocket-sized notebooks with smooth, fountain-pen-friendly recycled paper.', 6500, 40, 'Pocket+Notebook+Trio', 1],
        ['stationery', 'Brass Desk Pen', 'A satisfying-weight brass pen with a fine black refill for your desk or notebook.', 13000, 11, 'Brass+Desk+Pen', 1],
        ['stationery', 'Weekly Planner Pad', 'A tear-off weekly planner pad with space for priorities, plans, and small reminders.', 7500, 0, 'Weekly+Planner+Pad', 0],
    ];

    $productInsert = $pdo->prepare(
        'INSERT INTO products
            (category_id, name, slug, description, price, stock, image_url, is_active, created_at)
         VALUES
            (:category_id, :name, :slug, :description, :price, :stock, :image_url, :is_active, :created_at)
         ON DUPLICATE KEY UPDATE
            category_id = VALUES(category_id),
            name = VALUES(name),
            description = VALUES(description),
            price = VALUES(price),
            stock = VALUES(stock),
            image_url = VALUES(image_url),
            is_active = VALUES(is_active)'
    );

    foreach ($products as $index => [$categorySlug, $name, $description, $price, $stock, $imageText, $isActive]) {
        $slug = strtolower(str_replace(' ', '-', $name));
        $productInsert->execute([
            'category_id' => $categoryIds[$categorySlug],
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'price' => $price,
            'stock' => $stock,
            'image_url' => 'https://placehold.co/600x400?text=' . $imageText,
            'is_active' => $isActive,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . (15 - $index) . ' days')),
        ]);
    }

    $pdo->commit();
    fwrite(STDOUT, "Seeded 5 categories and 15 products into {$dbName}.\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seeding failed: ' . $error->getMessage() . "\n");
    exit(1);
}
