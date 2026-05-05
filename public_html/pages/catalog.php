<?php
require_once __DIR__ . '/../includes/functions.php';

$settings = getSettings();
$categories = getCategories();
$allProducts = getProducts();

// Фильтрация активных товаров
$activeProducts = array_filter($allProducts, function($p) {
    return $p['status'] === 'active';
});

$siteUrl = getCurrentSiteUrl();
$siteName = trim($settings['site']['name'] ?? 'Магазин аккаунтов');
$currentPage = 'catalog';

$metaTags = metaTags(
    'Каталог товаров | ' . $siteName,
    'Полный каталог товаров магазина. Все категории и продукты.',
    'каталог, товары, магазин',
    '/catalog/',
    '/images/ui/favicon.svg',
    'index, follow'
);

$schemaOrg = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "CollectionPage",
      "name": "Каталог товаров | ' . htmlspecialchars($siteName) . '",
      "description": "Полный каталог товаров магазина. Все категории и продукты.",
      "url": "' . $siteUrl . '/catalog/"
    }
  ]
}
</script>';

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container">
        <?php echo breadcrumbs([['name' => 'Главная', 'url' => '/'], ['name' => 'Каталог']]); ?>
        <h1>Каталог товаров</h1>
        <p>Все доступные товары по категориям</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <?php if (empty($categories)): ?>
        <div class="text-center" style="padding:60px 0;">
            <i class="fa-solid fa-box-open" style="font-size:3rem;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
            <h3>Категории не найдены</h3>
            <p class="text-muted mt-8">В каталоге пока нет категорий.</p>
        </div>
        <?php else: ?>
        <!-- Categories Grid -->
        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
            <a href="/category/<?php echo $category['slug']; ?>/" class="category-card animate-on-scroll">
                <div class="category-icon">
                    <img src="/images/icons/<?php echo $category['icon']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" loading="lazy" onerror="this.src='/images/icons/default.svg'">
                </div>
                <h3 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h3>
                <p class="category-count"><?php echo count(getProductsByCategory($category['slug'])); ?> товаров</p>
                <?php if (!empty($category['subcategories'])): ?>
                <div class="category-subcats">
                    <?php foreach (array_slice($category['subcategories'], 0, 3) as $sub): ?>
                    <span class="subcategory-tag"><?php echo htmlspecialchars($sub['name']); ?></span>
                    <?php endforeach; ?>
                    <?php if (count($category['subcategories']) > 3): ?>
                    <span class="subcategory-tag more">+<?php echo count($category['subcategories']) - 3; ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- All Products Section -->
        <div class="mt-48">
            <h2 class="mb-24">Все товары</h2>
            <?php if (empty($activeProducts)): ?>
            <div class="text-center" style="padding:40px 0;">
                <p class="text-muted">Товары временно отсутствуют</p>
            </div>
            <?php else: ?>
            <?php $firstBatch = array_slice($activeProducts, 0, 40); $restProducts = array_slice($activeProducts, 40); ?>
            <div class="products-grid" id="catalogProductsGrid">
                <?php foreach ($firstBatch as $i => $product): ?>
                <div class="product-card animate-on-scroll delay-<?php echo min($i % 6, 5); ?>"
                     data-product-id="<?php echo $product['id']; ?>"
                     data-product-slug="<?php echo $product['slug']; ?>"
                     data-price="<?php echo $product['price']; ?>"
                     data-qty="<?php echo $product['quantity']; ?>"
                     onclick="openProductModal(<?php echo $product['id']; ?>)">
                    <div class="product-card-header">
                        <div class="product-icon">
                            <img src="/images/icons/<?php echo $product['icon']; ?>"
                                 alt="<?php echo $product['category']; ?>"
                                 loading="lazy"
                                 onerror="this.src='/images/icons/default.svg'">
                        </div>
                        <div class="product-meta">
                            <div class="product-category"><?php echo ucfirst($product['category']); ?><?php if (!empty($product['subcategory'])): ?> / <?php echo ucfirst($product['subcategory']); ?><?php endif; ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        </div>
                    </div>
                    <p class="product-description"><?php echo htmlspecialchars($product['short_description']); ?></p>
                    <div class="product-features">
                        <?php foreach (array_slice($product['features'] ?? [], 0, 3) as $feature): ?>
                        <span class="product-feature-tag"><i class="fa-solid fa-check"></i> <?php echo htmlspecialchars($feature); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="product-footer">
                        <div class="product-stock <?php echo $product['quantity'] === 0 ? 'out-of-stock' : ''; ?>">
                            В наличии: <span class="stock-count"><?php echo $product['quantity']; ?> шт.</span>
                        </div>
                        <div class="product-price">
                            <span class="price-from">от</span>
                            <?php echo formatPrice($product['price']); ?>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;">
                        <a href="<?php echo appUrl('/oplata/?item=' . $product['slug'] . '&qty=1'); ?>" class="product-buy-btn" style="width:100%;" onclick="event.stopPropagation();">
                            <i class="fa-solid fa-cart-shopping"></i> Купить
                        </a>
                        <a href="/item/<?php echo $product['slug']; ?>/" class="product-buy-btn" style="width:100%;background:var(--bg-hover);color:var(--text-secondary);text-align:center;" onclick="event.stopPropagation();" target="_blank">
                            <i class="fa-solid fa-file-lines"></i> Полное описание
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($restProducts)): ?>
            <div class="text-center mt-32" id="loadMoreCatalogWrap">
                <button class="btn btn-secondary btn-lg" id="loadMoreCatalogBtn" onclick="loadMoreCatalog()">
                    <i class="fa-solid fa-rotate"></i> Загрузить ещё (<?php echo count($restProducts); ?> товаров)
                </button>
            </div>
            <script>
            var catalogProductsExtra = <?php echo json_encode($restProducts, JSON_UNESCAPED_UNICODE); ?>;
            var catalogLoadOffset = 0;
            function loadMoreCatalog() {
                var batch = catalogProductsExtra.slice(catalogLoadOffset, catalogLoadOffset + 40);
                catalogLoadOffset += 40;
                var grid = document.getElementById('catalogProductsGrid');
                batch.forEach(function(p) {
                    var card = document.createElement('div');
                    card.className = 'product-card animated';
                    card.setAttribute('data-product-id', p.id);
                    card.setAttribute('data-product-slug', p.slug);
                    card.setAttribute('data-price', p.price);
                    card.setAttribute('data-qty', p.quantity);
                    card.onclick = function() { openProductModal(p.id); };
                    var features = (p.features || []).slice(0,3).map(function(f){ return '<span class="product-feature-tag"><i class="fa-solid fa-check"></i> ' + escHtml(f) + '</span>'; }).join('');
                    card.innerHTML = '<div class="product-card-header"><div class="product-icon"><img src="/images/icons/' + escHtml(p.icon) + '" alt="" onerror="this.src=\'/images/icons/default.svg\'" loading="lazy"></div><div class="product-meta"><div class="product-category">' + escHtml(p.category) + (p.subcategory ? ' / ' + escHtml(p.subcategory) : '') + '</div><div class="product-name">' + escHtml(p.name) + '</div></div></div><p class="product-description">' + escHtml(p.short_description) + '</p><div class="product-features">' + features + '</div><div class="product-footer"><div class="product-stock ' + (p.quantity === 0 ? 'out-of-stock' : '') + '">В наличии: <span class="stock-count">' + p.quantity + ' шт.</span></div><div class="product-price"><span class="price-from">от</span> ' + formatPrice(p.price) + '</div></div><div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;"><a href="' + appUrl('/oplata/?item=' + encodeURIComponent(p.slug) + '&qty=1') + '" class="product-buy-btn" style="width:100%;" onclick="event.stopPropagation();"><i class="fa-solid fa-cart-shopping"></i> Купить</a><a href="/item/' + escHtml(p.slug) + '/" class="product-buy-btn" style="width:100%;background:var(--bg-hover);color:var(--text-secondary);text-align:center;" onclick="event.stopPropagation();" target="_blank"><i class="fa-solid fa-file-lines"></i> Полное описание</a></div>';
                    grid.appendChild(card);
                });
                if (catalogLoadOffset >= catalogProductsExtra.length) {
                    document.getElementById('loadMoreCatalogWrap').style.display = 'none';
                } else {
                    document.getElementById('loadMoreCatalogBtn').innerHTML = '<i class="fa-solid fa-rotate"></i> Загрузить ещё (' + (catalogProductsExtra.length - catalogLoadOffset) + ' товаров)';
                }
            }
            </script>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
