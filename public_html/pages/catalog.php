<?php
// Каталог - все категории и товары
$categories = getCategories();
$allProducts = getProducts();
$currentPage = 'catalog';
$settings = getSettings();
$siteName = $settings['site']['name'] ?? 'Магазин аккаунтов';

$pageTitle = "Каталог товаров | {$siteName}";
$pageDesc = "Полный каталог товаров магазина. Все категории и аккаунты в наличии.";
$canonical = '/catalog/';

$siteUrl = getCurrentSiteUrl();
$metaTags = metaTags($pageTitle, $pageDesc, '', $canonical, '/images/ui/favicon.svg', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1', 'website');

$schemaOrg = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "CollectionPage",
      "name": "' . htmlspecialchars($pageTitle) . '",
      "description": "' . htmlspecialchars($pageDesc) . '",
      "url": "' . $siteUrl . $canonical . '"
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
        <h1>Каталог Товаров</h1>
        <p>Все категории и товары магазина. Выберите нужную категорию или просмотрите все товары.</p>
    </div>
</div>

<!-- Categories Grid -->
<section class="section">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Категории</h2>
        <div class="categories-grid" id="categoriesGrid">
            <?php foreach ($categories as $i => $cat): ?>
            <?php
            $catProducts = getProductsByCategory($cat['slug']);
            $catCount = count($catProducts);
            $catQty = array_sum(array_column($catProducts, 'quantity'));
            $minPrice = $catProducts ? min(array_column($catProducts, 'price')) : 0;
            ?>
            <a href="/category/<?php echo $cat['slug']; ?>/"
               class="category-card animate-on-scroll delay-<?php echo min($i, 5); ?>"
               aria-label="Купить аккаунты <?php echo $cat['name']; ?>">
                <div class="category-card-icon">
                    <img src="/images/icons/<?php echo $cat['icon']; ?>"
                         alt="<?php echo $cat['name']; ?>"
                         loading="lazy"
                         onerror="this.src='/images/icons/default.svg'">
                </div>
                <div class="category-card-name"><?php echo $cat['name']; ?></div>
                <div class="category-card-count"><?php echo $catQty; ?> шт. в наличии</div>
                <div class="category-card-desc"><?php echo $cat['description']; ?></div>
                <?php if ($minPrice > 0): ?>
                <div class="category-card-count" style="color:var(--text-muted);font-size:0.75rem;margin-top:4px;">от <?php echo formatPrice($minPrice); ?></div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- All Products Section -->
<section class="section section-alt">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Все Товары</h2>
        <p class="section-subtitle animate-on-scroll delay-1">Полный список всех товаров магазина</p>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <span class="filter-label">Сортировка:</span>
            <select class="filter-select" id="sortSelect" onchange="sortProducts(this.value)">
                <option value="default">По умолчанию</option>
                <option value="price_asc">Цена: по возрастанию</option>
                <option value="price_desc">Цена: по убыванию</option>
                <option value="qty_desc">Количество: больше</option>
            </select>
            <span class="filter-label" style="margin-left:auto;">
                Найдено: <strong id="productsCount"><?php echo count($allProducts); ?></strong> товаров
            </span>
        </div>

        <?php if (empty($allProducts)): ?>
        <div class="text-center" style="padding:60px 0;">
            <i class="fa-solid fa-box-open" style="font-size:3rem;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
            <h3>Товары не найдены</h3>
            <p class="text-muted mt-8">В каталоге пока нет товаров.</p>
        </div>
        <?php else: ?>
        <?php $firstBatch = array_slice($allProducts, 0, 40); $restProducts = array_slice($allProducts, 40); ?>
        <div class="products-grid" id="catalogProductsGrid">
            <?php foreach ($firstBatch as $i => $product): ?>
            <div class="product-card animate-on-scroll delay-<?php echo min($i % 6, 5); ?>"
                 data-product-id="<?php echo $product['id']; ?>"
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
                        <div class="product-category"><?php echo ucfirst($product['category']); ?></div>
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
        <div class="text-center mt-32" id="loadMoreCatWrap">
            <button class="btn btn-secondary btn-lg" id="loadMoreCatBtn" onclick="loadMoreCatalog()">
                <i class="fa-solid fa-rotate"></i> Загрузить ещё (<?php echo count($restProducts); ?> товаров)
            </button>
        </div>
        <script>
        var catProductsExtra = <?php echo json_encode($restProducts, JSON_UNESCAPED_UNICODE); ?>;
        var catLoadOffset = 0;
        function loadMoreCatalog() {
            var batch = catProductsExtra.slice(catLoadOffset, catLoadOffset + 40);
            catLoadOffset += 40;
            var grid = document.getElementById('catalogProductsGrid');
            batch.forEach(function(p) {
                var card = document.createElement('div');
                card.className = 'product-card animated';
                card.setAttribute('data-product-id', p.id);
                card.setAttribute('data-price', p.price);
                card.setAttribute('data-qty', p.quantity);
                card.onclick = function() { openProductModal(p.id); };
                var features = (p.features || []).slice(0,3).map(function(f){ return '<span class="product-feature-tag"><i class="fa-solid fa-check"></i> ' + escHtml(f) + '</span>'; }).join('');
                card.innerHTML = '<div class="product-card-header"><div class="product-icon"><img src="/images/icons/' + escHtml(p.icon) + '" alt="" onerror="this.src=\'/images/icons/default.svg\'" loading="lazy"></div><div class="product-meta"><div class="product-category">' + escHtml(p.category) + '</div><div class="product-name">' + escHtml(p.name) + '</div></div></div><p class="product-description">' + escHtml(p.short_description) + '</p><div class="product-features">' + features + '</div><div class="product-footer"><div class="product-stock ' + (p.quantity === 0 ? 'out-of-stock' : '') + '">В наличии: <span class="stock-count">' + p.quantity + ' шт.</span></div><div class="product-price"><span class="price-from">от</span> ' + formatPrice(p.price) + '</div></div><div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;"><a href="' + appUrl('/oplata/?item=' + encodeURIComponent(p.slug) + '&qty=1') + '" class="product-buy-btn" style="width:100%;" onclick="event.stopPropagation();"><i class="fa-solid fa-cart-shopping"></i> Купить</a><a href="/item/' + escHtml(p.slug) + '/" class="product-buy-btn" style="width:100%;background:var(--bg-hover);color:var(--text-secondary);text-align:center;" onclick="event.stopPropagation();" target="_blank"><i class="fa-solid fa-file-lines"></i> Полное описание</a></div>';
                grid.appendChild(card);
            });
            if (catLoadOffset >= catProductsExtra.length) {
                document.getElementById('loadMoreCatWrap').style.display = 'none';
            } else {
                document.getElementById('loadMoreCatBtn').innerHTML = '<i class="fa-solid fa-rotate"></i> Загрузить ещё (' + (catProductsExtra.length - catLoadOffset) + ' товаров)';
            }
        }
        function sortProducts(value) {
            var grid = document.getElementById('catalogProductsGrid');
            var cards = Array.from(grid.querySelectorAll('.product-card'));
            cards.sort(function(a, b) {
                var aPrice = parseFloat(a.getAttribute('data-price'));
                var bPrice = parseFloat(b.getAttribute('data-price'));
                var aQty = parseInt(a.getAttribute('data-qty'));
                var bQty = parseInt(b.getAttribute('data-qty'));
                if (value === 'price_asc') return aPrice - bPrice;
                if (value === 'price_desc') return bPrice - aPrice;
                if (value === 'qty_desc') return bQty - aQty;
                return 0;
            });
            cards.forEach(function(card) { grid.appendChild(card); });
        }
        </script>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
