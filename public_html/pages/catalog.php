<?php
require_once __DIR__ . '/../includes/functions.php';

$settings = getSettings();
$categories = getCategories();
$allProducts = getProducts();

// Фильтрация активных товаров
$activeProducts = array_filter($allProducts, function($p) {
    return $p['status'] === 'active';
});

// Группировка товаров по категориям
$productsByCategory = [];
foreach ($activeProducts as $product) {
    $catName = $product['category'];
    if (!isset($productsByCategory[$catName])) {
        $productsByCategory[$catName] = [];
    }
    $productsByCategory[$catName][] = $product;
}

$siteUrl = getCurrentSiteUrl();
$siteName = trim($settings['site']['name'] ?? 'Магазин аккаунтов');
$metaTags = metaTags(
    'Каталог товаров | ' . $siteName,
    'Полный каталог товаров магазина. Все категории и продукты.',
    'каталог, товары, магазин',
    '/catalog/',
    '/images/ui/favicon.svg',
    'index, follow'
);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $metaTags ?>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .catalog-page { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .catalog-header { margin-bottom: 30px; }
        .catalog-header h1 { font-size: 2em; margin-bottom: 10px; }
        .category-section { margin-bottom: 40px; }
        .category-title { font-size: 1.5em; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .product-card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; transition: transform 0.2s, box-shadow 0.2s; background: #fff; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .product-image { width: 100%; height: 180px; object-fit: cover; border-radius: 6px; margin-bottom: 10px; }
        .product-name { font-size: 1.1em; margin-bottom: 8px; color: #333; }
        .product-price { font-size: 1.2em; font-weight: bold; color: #27ae60; margin-bottom: 10px; }
        .product-link { display: inline-block; padding: 8px 16px; background: #3498db; color: #fff; text-decoration: none; border-radius: 4px; transition: background 0.2s; }
        .product-link:hover { background: #2980b9; }
        .no-products { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <main class="catalog-page">
        <div class="catalog-header">
            <h1>Каталог товаров</h1>
            <p>Все доступные товары по категориям</p>
        </div>

        <?php if (empty($productsByCategory)): ?>
            <div class="no-products">
                <p>Товары временно отсутствуют</p>
            </div>
        <?php else: ?>
            <?php foreach ($productsByCategory as $categoryName => $products): ?>
                <section class="category-section">
                    <h2 class="category-title"><?= htmlspecialchars($categoryName) ?></h2>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <img src="<?= htmlspecialchars($product['image'] ?? '/images/ui/placeholder.png') ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                                <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                                <div class="product-price"><?= number_format($product['price'], 2) ?> ₽</div>
                                <a href="/item/<?= htmlspecialchars($product['slug']) ?>" class="product-link">Подробнее</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
