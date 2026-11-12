<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$search = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['category'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest');

$sortOptions = [
    'newest' => 'p.created_at DESC, p.id DESC',
    'price_asc' => 'p.price ASC, p.id DESC',
    'price_desc' => 'p.price DESC, p.id DESC',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}

$categories = $pdo->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();

$sql = 'SELECT p.id, p.name, p.slug, p.description, p.price, p.stock, p.image_url,
               c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1';
$params = [];

if ($categorySlug !== '') {
    $sql .= ' AND c.slug = :category_slug';
    $params['category_slug'] = $categorySlug;
}
if ($search !== '') {
    $sql .= ' AND (p.name LIKE :search_name OR p.description LIKE :search_description)';
    $params['search_name'] = '%' . $search . '%';
    $params['search_description'] = '%' . $search . '%';
}

$sql .= ' ORDER BY ' . $sortOptions[$sort];
$statement = $pdo->prepare($sql);
$statement->execute($params);
$products = $statement->fetchAll();

$pageTitle = 'Shop';
require __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Good things, thoughtfully chosen</p>
        <h1>Find your new<br><span>everyday favourite.</span></h1>
        <p class="hero-description">A considered collection of useful, lovely things for the way you live.</p>
        <a class="button button-dark" href="#products">Explore the collection <span aria-hidden="true">↓</span></a>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="hero-orbit orbit-one"></div>
        <div class="hero-orbit orbit-two"></div>
        <div class="hero-shape shape-orange"></div>
        <div class="hero-shape shape-green"></div>
        <div class="hero-shape shape-cream"></div>
        <span class="hero-spark">✳</span>
    </div>
</section>

<section class="collection-section" id="products">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Made for daily life</p>
            <h2>Our collection</h2>
        </div>
        <p class="product-total"><?= count($products) ?> thoughtfully picked <?= count($products) === 1 ? 'find' : 'finds' ?></p>
    </div>

    <form class="filters" method="get" action="index.php">
        <label class="search-field">
            <span class="visually-hidden">Search products</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search the collection">
        </label>
        <label class="select-field">
            <span class="visually-hidden">Category</span>
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="select-field">
            <span class="visually-hidden">Sort products</span>
            <select name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
            </select>
        </label>
        <button class="button button-filter" type="submit">Apply filters</button>
        <?php if ($search !== '' || $categorySlug !== '' || $sort !== 'newest'): ?>
            <a class="clear-filters" href="index.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($products === []): ?>
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true">⌕</span>
            <h3>No finds this time</h3>
            <p>Try a different search or browse the full collection.</p>
            <a class="text-link" href="index.php">See all products <span aria-hidden="true">→</span></a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <article class="product-card">
                    <a class="product-image-wrap" href="product.php?slug=<?= rawurlencode((string) $product['slug']) ?>">
                        <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php if ((int) $product['stock'] < 1): ?>
                            <span class="stock-badge">Out of stock</span>
                        <?php endif; ?>
                        <?php if ($product['category_name'] !== null): ?>
                            <span class="category-pill"><?= e($product['category_name']) ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <div>
                            <h3><a href="product.php?slug=<?= rawurlencode((string) $product['slug']) ?>"><?= e($product['name']) ?></a></h3>
                            <p><?= e(mb_strimwidth((string) $product['description'], 0, 76, '…')) ?></p>
                        </div>
                        <strong class="product-price"><?= e(money($product['price'])) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
