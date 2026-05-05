<?php
require_once __DIR__ . '/includes/functions.php';
startAppOutputBuffer();

$settings = getSettings();

// Redirect to canonical domain only for real public hosts.
$canonicalDomain = trim($settings['site']['url'] ?? '');
if ($canonicalDomain !== '') {
    $parsedCanonical = parse_url($canonicalDomain);
    $canonicalHost = normalizeHostName($parsedCanonical['host'] ?? '');
    $currentHost = normalizeHostName($_SERVER['HTTP_HOST'] ?? '');

    $isCurrentHostLocal = isLocalHostName($currentHost) || preg_match('/\.manus\.computer$/', $currentHost);
    if ($canonicalHost !== '' && $currentHost !== '' && $currentHost !== $canonicalHost && PHP_SAPI !== 'cli' && !$isCurrentHostLocal) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . rtrim($canonicalDomain, '/') . $currentUri, true, 301);
        exit;
    }
}

$page = $_GET['page'] ?? 'home';

// Route to correct page
switch ($page) {
    case 'catalog': require __DIR__ . '/pages/catalog.php'; exit;
    case 'category': require __DIR__ . '/pages/category.php'; exit;
    case 'item': require __DIR__ . '/pages/item.php'; exit;
    case 'oplata': require __DIR__ . '/pages/oplata.php'; exit;
    case 'faq': require __DIR__ . '/pages/faq.php'; exit;
    case 'rules': require __DIR__ . '/pages/rules.php'; exit;
    case 'info': require __DIR__ . '/pages/info.php'; exit;
    case 'advertising': require __DIR__ . '/pages/advertising.php'; exit;
}

// If page param is a number (e.g. ?page=2 on /info/ URL), route to info
if (is_numeric($page) && $page > 0) {
    $_GET['page_num'] = (int)$page;
    unset($_GET['page']);
    require __DIR__ . '/pages/info.php';
    exit;
}

// Home page
$currentPage = 'home';
$categories = getCategories();
$pagesData = getPages();
$faqItems = array_slice($pagesData['faq']['items'] ?? [], 0, 4);
$totalAccounts = countTotalProducts();
$stats = $settings['stats'] ?? [];

// Search query
$searchQuery = sanitize($_GET['q'] ?? '');
$isSearchMode = !empty($searchQuery);

if ($isSearchMode) {
    $allProducts = getProducts();
    $searchResults = array_values(array_filter($allProducts, function($p) use ($searchQuery) {
        return $p['status'] === 'active' && (
            stripos($p['name'], $searchQuery) !== false ||
            stripos($p['short_description'], $searchQuery) !== false ||
            stripos($p['category'], $searchQuery) !== false ||
            stripos($p['full_description'] ?? '', $searchQuery) !== false
        );
    }));
    $popularProducts = array_slice($searchResults, 0, 40);
} else {
    $searchResults = [];
    $popularProducts = getPopularProducts(12); // Show up to 12 hit products
}

$siteUrl = getCurrentSiteUrl();
$siteName = trim($settings['site']['name'] ?? 'Магазин аккаунтов');
$homeDescription = $settings['seo']['description'] ?? 'Продажа аккаунтов соцсетей с гарантией.';
$searchDescription = 'Результаты поиска по запросу «' . $searchQuery . '» в каталоге аккаунтов. Если результатов нет, воспользуйтесь категориями и фильтрами магазина.';
$metaTags = metaTags(
    $isSearchMode ? ('Поиск: ' . htmlspecialchars($searchQuery) . ' | ' . $siteName) : ($settings['seo']['title'] ?? ('Купить аккаунты с гарантией | ' . $siteName)),
    $isSearchMode ? $searchDescription : $homeDescription,
    $settings['seo']['keywords'] ?? '',
    '/',
    '/images/ui/favicon.svg',
    $isSearchMode ? 'noindex, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
);

$schemaOrg = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "WebSite",
      "name": "' . htmlspecialchars($siteName) . '",
      "url": "' . $siteUrl . '",
      "description": "' . htmlspecialchars($homeDescription) . '",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "' . $siteUrl . '/?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    },
    {
      "@type": "Organization",
      "name": "' . htmlspecialchars($siteName) . '",
      "url": "' . $siteUrl . '",
      "logo": "' . $siteUrl . '/images/ui/favicon.svg"
    }
  ]
}
</script>';

require __DIR__ . '/includes/header.php';
?>

<?php if ($isSearchMode): ?>
<!-- Search Results Section -->
<div class="page-hero">
    <div class="container">
        <?php echo breadcrumbs([['name' => 'Главная', 'url' => '/'], ['name' => 'Поиск: ' . htmlspecialchars($searchQuery)]]); ?>
        <h1>Результаты поиска: «<?php echo htmlspecialchars($searchQuery); ?>»</h1>
        <p>Найдено <?php echo count($searchResults); ?> товаров</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <?php if (empty($searchResults)): ?>
        <div class="text-center" style="padding:60px 0;">
            <i class="fa-solid fa-magnifying-glass" style="font-size:3rem;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
            <h3>Ничего не найдено</h3>
            <p class="text-muted mt-8">По запросу «<?php echo htmlspecialchars($searchQuery); ?>» товары не найдены. Попробуйте другой запрос.</p>
            <a href="/" class="btn btn-primary mt-16">На главную</a>
        </div>
        <?php else: ?>
        <div class="filter-bar">
            <span class="filter-label">Результаты поиска:</span>
            <span class="filter-label" style="margin-left:auto;">
                Найдено: <strong id="productsCount"><?php echo count($searchResults); ?></strong> товаров
            </span>
        </div>
        <div class="products-grid" id="searchResultsGrid">
            <?php foreach (array_slice($searchResults, 0, 40) as $i => $product): ?>
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
        <?php if (count($searchResults) > 40): ?>
        <div class="text-center mt-32" id="loadMoreSearchWrap">
            <button class="btn btn-secondary btn-lg" id="loadMoreSearchBtn" onclick="loadMoreSearch()">
                <i class="fa-solid fa-rotate"></i> Загрузить ещё (<?php echo count($searchResults) - 40; ?> товаров)
            </button>
        </div>
        <script>
        var allSearchResultsExtra = <?php echo json_encode(array_slice($searchResults, 40), JSON_UNESCAPED_UNICODE); ?>;
        var searchLoadOffset = 0;
        function loadMoreSearch() {
            var batch = allSearchResultsExtra.slice(searchLoadOffset, searchLoadOffset + 40);
            searchLoadOffset += 40;
            var grid = document.getElementById('searchResultsGrid');
            batch.forEach(function(p) {
                var card = document.createElement('div');
                card.className = 'product-card animated';
                card.setAttribute('data-product-id', p.id);
                card.setAttribute('data-product-slug', p.slug);
                card.setAttribute('data-price', p.price);
                card.setAttribute('data-qty', p.quantity);
                card.onclick = function() { openProductModal(p.id); };
                var features = (p.features || []).slice(0,3).map(function(f){ return '<span class="product-feature-tag"><i class="fa-solid fa-check"></i> ' + escHtml(f) + '</span>'; }).join('');
                card.innerHTML = '<div class="product-card-header"><div class="product-icon"><img src="/images/icons/' + escHtml(p.icon) + '" alt="" onerror="this.src=\"/images/icons/default.svg\"" loading="lazy"></div><div class="product-meta"><div class="product-category">' + escHtml(p.category) + '</div><div class="product-name">' + escHtml(p.name) + '</div></div></div><p class="product-description">' + escHtml(p.short_description) + '</p><div class="product-features">' + features + '</div><div class="product-footer"><div class="product-stock ' + (p.quantity === 0 ? 'out-of-stock' : '') + '">В наличии: <span class="stock-count">' + p.quantity + ' шт.</span></div><div class="product-price"><span class="price-from">от</span> ' + formatPrice(p.price) + '</div></div><div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;"><a href="' + appUrl('/oplata/?item=' + encodeURIComponent(p.slug) + '&qty=1') + '" class="product-buy-btn" style="width:100%;" onclick="event.stopPropagation();"><i class="fa-solid fa-cart-shopping"></i> Купить</a><a href="/item/' + escHtml(p.slug) + '/" class="product-buy-btn" style="width:100%;background:var(--bg-hover);color:var(--text-secondary);text-align:center;" onclick="event.stopPropagation();" target="_blank"><i class="fa-solid fa-file-lines"></i> Полное описание</a></div>';
                grid.appendChild(card);
            });
            if (searchLoadOffset >= allSearchResultsExtra.length) {
                document.getElementById('loadMoreSearchWrap').style.display = 'none';
            } else {
                document.getElementById('loadMoreSearchBtn').innerHTML = '<i class="fa-solid fa-rotate"></i> Загрузить ещё (' + (allSearchResultsExtra.length - searchLoadOffset) + ' товаров)';
            }
        }
        </script>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php
$_adHomeShowcaseHtml = renderAdSpot('home_showcase_banner');
if (!empty($_adHomeShowcaseHtml)):
?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="ad-section ad-section--home-showcase">
            <?php echo $_adHomeShowcaseHtml; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Product Modal -->
<div class="modal" id="productModal">
    <div class="modal-overlay" id="productModalOverlay"></div>
    <div class="modal-dialog" id="productModalDialog">
        <div class="modal-header">
            <h3 id="productModalTitle">Товар</h3>
            <button class="modal-close" id="productModalClose"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="productModalBody"></div>
    </div>
</div>
<div class="toast-container" id="toastContainer"></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
<?php return; ?>
<?php endif; ?>

<!-- Hero Section -->
<section class="hero parallax-section" id="hero">
    <div class="hero-bg">
        <div class="hero-bg-gradient"></div>
        <div class="hero-particles" id="heroParticles"></div>
    </div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fa-solid fa-bolt"></i>
                <span>Моментальная выдача 24/7</span>
            </div>
            <h1 data-editable="settings:site:name" data-editable-type="text">
                <?php echo htmlspecialchars($siteName); ?> - <span class="highlight">Магазин Аккаунтов</span><br>
                Соцсетей и Сервисов
            </h1>
            <p class="hero-subtitle" data-editable="settings:site:tagline" data-editable-type="text">Моментальная Выдача &bull; Гарантия Качества &bull; Цены от 50 ₽</p>
            <div class="hero-features">
                <div class="hero-feature"><i class="fa-solid fa-check"></i> 15 000+ аккаунтов в наличии</div>
                <div class="hero-feature"><i class="fa-solid fa-check"></i> Автоматическая выдача 24/7</div>
                <div class="hero-feature"><i class="fa-solid fa-check"></i> Гарантия замены 72 часа</div>
                <div class="hero-feature"><i class="fa-solid fa-check"></i> Cookies + Прокси в комплекте</div>
            </div>
            <div class="hero-cta">
                <a href="#categories" class="btn btn-primary btn-lg btn-pulse">
                    <i class="fa-solid fa-fire"></i> Перейти в каталог
                </a>
                <button class="btn btn-secondary btn-lg" id="openContactModalHero">
                    <i class="fa-solid fa-comment"></i> Задать вопрос
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item animate-on-scroll">
                <span class="stat-number" data-count="<?php echo $stats['accounts_count'] ?? 15000; ?>">0</span>
                <span class="stat-label">аккаунтов в наличии</span>
            </div>
            <div class="stat-item animate-on-scroll delay-1">
                <span class="stat-number" data-count="<?php echo $stats['sales_count'] ?? 50000; ?>">0</span>
                <span class="stat-label">продаж выполнено</span>
            </div>
            <div class="stat-item animate-on-scroll delay-2">
                <span class="stat-number" data-count="<?php echo $stats['years_on_market'] ?? 3; ?>" data-suffix=" года">0</span>
                <span class="stat-label">на рынке</span>
            </div>
            <div class="stat-item animate-on-scroll delay-3">
                <span class="stat-number" data-count="<?php echo $stats['satisfaction_rate'] ?? 99; ?>" data-suffix="%">0</span>
                <span class="stat-label">довольных клиентов</span>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="section" id="categories">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Популярные Категории Аккаунтов</h2>
        <p class="section-subtitle animate-on-scroll delay-1">Выберите нужную соцсеть или сервис. Все аккаунты проходят предпродажную проверку на валидность и готовы к немедленному использованию.</p>

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

<!-- Popular Products Section - REMOVED: Catalog moved to separate page -->
<!-- 
<section class="section section-alt" id="popular">
    <div class="container">
        <h2 class="section-title animate-on-scroll">🔥 Аккаунты - Хиты Продаж</h2>
        <p class="section-subtitle animate-on-scroll delay-1">Самые востребованные позиции этого месяца. Количество ограничено - успейте купить по текущей цене.</p>

        <!-- Skeleton Loading -->
        <div class="products-grid" id="productsSkeletonGrid">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="product-card-skeleton">
                <div style="display:flex;gap:14px;align-items:center;">
                    <div class="skeleton skeleton-icon"></div>
                    <div style="flex:1;">
                        <div class="skeleton skeleton-title mb-8"></div>
                        <div class="skeleton skeleton-text-short"></div>
                    </div>
                </div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text-short"></div>
                <div class="skeleton skeleton-btn mt-8"></div>
            </div>
            <?php endfor; ?>
        </div>

        <?php if (empty($popularProducts)): ?>
        <div style="text-align:center;padding:40px 0;color:var(--text-muted);">
            <i class="fa-solid fa-fire" style="font-size:2.5rem;margin-bottom:12px;display:block;opacity:0.4;"></i>
            <p>Хиты продаж появятся здесь автоматически, как только вы отметите товары галочкой &laquo;Хит продаж&raquo; в админпанели.</p>
        </div>
        <?php else: ?>
        <div class="products-grid hidden" id="productsGrid">
            <?php foreach ($popularProducts as $i => $product): ?>
            <div class="product-card animate-on-scroll delay-<?php echo min($i, 5); ?>"
                 data-product-id="<?php echo $product['id']; ?>"
                 data-product-slug="<?php echo $product['slug']; ?>"
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
                    <span style="position:absolute;top:12px;right:12px;background:linear-gradient(135deg,#F59E0B,#EF4444);color:#fff;font-size:0.65rem;font-weight:700;padding:3px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px;"><i class="fa-solid fa-fire" style="margin-right:3px;"></i>ХИТ</span>
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
        <div style="text-align:center;margin-top:32px;">
            <a href="/catalog/" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-list"></i> Перейти в полный каталог
            </a>
        </div>
        <?php endif; ?>

        <div class="text-center mt-32">
            <a href="/#categories" class="btn btn-secondary">
                <i class="fa-solid fa-grid-2"></i> Смотреть все товары
            </a>
        </div>
    </div>
</section>
-->

<!-- Catalog Link Section -->
<section class="section" id="catalog-link">
    <div class="container">
        <h2 class="section-title animate-on-scroll">📦 Наш Каталог</h2>
        <p class="section-subtitle animate-on-scroll delay-1">Полный ассортимент товаров доступен в отдельном разделе каталога.</p>
        <div style="text-align:center;margin-top:32px;">
            <a href="/catalog/" class="btn btn-primary btn-lg animate-on-scroll delay-2">
                <i class="fa-solid fa-list"></i> Открыть каталог товаров
            </a>
        </div>
    </div>
</section>

<!-- Content Middle Ad Banner -->
<?php
$_adMiddleHtml = renderAdSpot('content_middle');
if (!empty($_adMiddleHtml)):
?>
<div class="ad-section ad-section--content">
    <div class="container">
        <?php echo $_adMiddleHtml; ?>
    </div>
</div>
<?php endif; ?>

<?php
// Логика отображения товаров в зависимости от темы
$current_theme = $config['template'] ?? 'dark-pro';
$display_mode = 'grid'; // grid, list, premium

if ($current_theme == 'accsmarket') {
    $display_mode = 'list'; // Строгий список как на accsmarket.com
} elseif ($current_theme == 'noves-shop') {
    $display_mode = 'grid-clean'; // Чистая сетка с тенью
} elseif ($current_theme == 'dark-shopping') {
    $display_mode = 'premium-grid'; // Премиум сетка с золотом
}
?>

<!-- Стили для разных режимов отображения -->
<style>
/* ACCSMARKET MODE: Строгий список */
.theme-accsmarket .product-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #333;
    background: rgba(255,255,255,0.02);
    transition: 0.2s;
}
.theme-accsmarket .product-list-item:hover {
    background: rgba(46, 204, 113, 0.08);
    padding-left: 20px;
}
.theme-accsmarket .product-title {
    font-size: 14px;
    color: #2ecc71;
    text-decoration: none;
    font-weight: 500;
}
.theme-accsmarket .product-price {
    font-weight: bold;
    color: #fff;
    background: #2ecc71;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 13px;
}

/* NOVES-SHOP MODE: Чистая сетка */
.theme-noves-shop .product-card-clean {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.theme-noves-shop .product-card-clean:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border-color: #3498db;
}
.theme-noves-shop .pc-img {
    height: 180px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.theme-noves-shop .pc-img img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
}
.theme-noves-shop .pc-body {
    padding: 15px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}
.theme-noves-shop .pc-title {
    font-size: 15px;
    color: #2c3e50;
    margin-bottom: 10px;
    line-height: 1.4;
    flex-grow: 1;
}
.theme-noves-shop .pc-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
}
.theme-noves-shop .pc-price {
    font-size: 18px;
    font-weight: 700;
    color: #3498db;
}
.theme-noves-shop .pc-btn {
    background: #3498db;
    color: #fff;
    padding: 6px 14px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    transition: 0.2s;
}
.theme-noves-shop .pc-btn:hover {
    background: #2980b9;
}

/* DARK-SHOPPING MODE: Премиум темный */
.theme-dark-shopping .product-card-premium {
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 6px;
    overflow: hidden;
    position: relative;
    transition: 0.3s;
    height: 100%;
}
.theme-dark-shopping .product-card-premium:hover {
    border-color: #d4af37;
    box-shadow: 0 0 20px rgba(212, 175, 55, 0.25);
    transform: translateY(-3px);
}
.theme-dark-shopping .pc-prem-img {
    height: 200px;
    background: #000;
    position: relative;
    overflow: hidden;
}
.theme-dark-shopping .pc-prem-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.85;
    transition: 0.3s;
}
.theme-dark-shopping .product-card-premium:hover .pc-prem-img img {
    opacity: 1;
    transform: scale(1.05);
}
.theme-dark-shopping .pc-prem-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: linear-gradient(135deg, #d4af37, #f1c40f);
    color: #000;
    font-size: 11px;
    font-weight: bold;
    padding: 4px 10px;
    text-transform: uppercase;
    border-radius: 3px;
    z-index: 2;
}
.theme-dark-shopping .pc-prem-body {
    padding: 18px;
    text-align: center;
}
.theme-dark-shopping .pc-prem-title {
    color: #eee;
    font-size: 14px;
    margin-bottom: 12px;
    font-family: 'Playfair Display', Georgia, serif;
    line-height: 1.4;
    min-height: 40px;
}
.theme-dark-shopping .pc-prem-price {
    color: #d4af37;
    font-size: 22px;
    font-weight: bold;
    letter-spacing: 1px;
}
</style>
<!-- Subcategory Sections -->
<?php foreach ($categories as $cat): ?>
    <?php foreach ($cat['subcategories'] ?? [] as $sub): ?>
        <?php 
        $subProducts = getProductsByCategory($cat['slug'], $sub['slug']);
        if (empty($subProducts)) continue;
        
        // Get up to 40 products
        shuffle($subProducts);
        $displayProducts = array_slice($subProducts, 0, 40);
        $totalProducts = count($subProducts);
        ?>
        <section class="section">
            <div class="container">
                <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:32px; gap:20px; flex-wrap:wrap;">
                    <div>
                        <h2 style="margin-bottom:8px;"><?php echo $cat['name']; ?> - <?php echo $sub['name']; ?></h2>
                        <p class="text-muted"><?php echo $sub['description']; ?></p>
                    </div>
                    <a href="/category/<?php echo $cat['slug']; ?>/<?php echo $sub['slug']; ?>/" class="btn btn-secondary btn-sm">
                        Смотреть все <i class="fa-solid fa-arrow-right" style="margin-left:8px;"></i>
                    </a>
                </div>

                <!-- ACCSMARKET STYLE: List -->
                <?php if ($display_mode == 'list'): ?>
                    <div class="products-list">
                        <?php foreach ($displayProducts as $product): ?>
                        <div class="product-list-item">
                            <a href="/item/<?php echo $product['slug']; ?>/" class="product-title">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                            <span class="product-price"><?php echo formatPrice($product['price']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                <!-- NOVES-SHOP STYLE: Clean Grid -->
                <?php elseif ($display_mode == 'grid-clean'): ?>
                    <div class="row">
                        <?php foreach ($displayProducts as $i => $product): ?>
                        <div class="col-md-3 col-sm-6 mb-4">
                            <div class="product-card-clean">
                                <div class="pc-img">
                                    <img src="<?php echo !empty($product['image']) ? $product['image'] : '/images/no-image.png'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </div>
                                <div class="pc-body">
                                    <div class="pc-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="pc-footer">
                                        <span class="pc-price"><?php echo formatPrice($product['price']); ?></span>
                                        <a href="/item/<?php echo $product['slug']; ?>/" class="pc-btn">Купить</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                <!-- DARK-SHOPPING STYLE: Premium Grid -->
                <?php elseif ($display_mode == 'premium-grid'): ?>
                    <div class="row">
                        <?php foreach ($displayProducts as $i => $product): ?>
                        <div class="col-md-3 col-sm-6 mb-4">
                            <div class="product-card-premium">
                                <div class="pc-prem-img">
                                    <span class="pc-prem-badge">HOT</span>
                                    <img src="<?php echo !empty($product['image']) ? $product['image'] : '/images/no-image.png'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </div>
                                <div class="pc-prem-body">
                                    <div class="pc-prem-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="pc-prem-price"><?php echo formatPrice($product['price']); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                
                <!-- DEFAULT GRID (для остальных тем) -->
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($displayProducts as $i => $product): ?>
                        <div class="product-card animate-on-scroll delay-<?php echo min($i % 6, 5); ?>"
                             data-product-id="<?php echo $product['id']; ?>"
                             data-product-slug="<?php echo $product['slug']; ?>"
                             onclick="openProductModal(<?php echo $product['id']; ?>)">
                            <div class="product-card-header">
                                <div class="product-icon">
                                    <img src="/images/icons/<?php echo $product['icon']; ?>"
                                         alt="<?php echo $product['category']; ?>"
                                         loading="lazy"
                                         onerror="this.src='/images/icons/default.svg'">
                                </div>
                                <div class="product-meta">
                                    <div class="product-category"><?php echo ucfirst($product['category']); ?> / <?php echo ucfirst($product['subcategory']); ?></div>
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
                <?php endif; ?>

                <?php if ($totalProducts > 40): ?>
                <div style="text-align:center;margin-top:32px;">
                    <a href="/category/<?php echo $cat['slug']; ?>/<?php echo $sub['slug']; ?>/" class="btn btn-primary btn-lg">
                        Смотреть все товары (<?php echo $totalProducts; ?>) <i class="fa-solid fa-arrow-right" style="margin-left:8px;"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endforeach; ?>

<!-- Advantages Section -->
<section class="section" id="advantages">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Почему Покупают Именно У Нас</h2>
        <p class="section-subtitle animate-on-scroll delay-1">Конкретика и цифры - без банальностей о «качестве» и «надёжности».</p>

        <div class="advantages-grid">
            <div class="advantage-card animate-on-scroll">
                <div class="advantage-icon">⚡</div>
                <div class="advantage-content">
                    <h3>Моментальная Выдача 24/7</h3>
                    <p>Автоматическая отправка данных сразу после оплаты. Не нужно ждать менеджера - получите аккаунт за 30 секунд.</p>
                </div>
            </div>
            <div class="advantage-card animate-on-scroll delay-1">
                <div class="advantage-icon">🛡️</div>
                <div class="advantage-content">
                    <h3>Гарантия Замены 72 часа</h3>
                    <p>Если аккаунт заблокирован в течение 3 суток - бесплатная замена или возврат средств. Работает без лишних вопросов.</p>
                </div>
            </div>
            <div class="advantage-card animate-on-scroll delay-2">
                <div class="advantage-icon">🔍</div>
                <div class="advantage-content">
                    <h3>Предпродажная Проверка</h3>
                    <p>Каждый аккаунт проверяем на валидность, активность cookies и корректность данных перед добавлением в каталог.</p>
                </div>
            </div>
            <div class="advantage-card animate-on-scroll delay-3">
                <div class="advantage-icon">💰</div>
                <div class="advantage-content">
                    <h3>Оптовые Цены от 50 ₽</h3>
                    <p>Прямые поставки без посредников. Скидки до 40% при заказе от 100 штук. Прайс-лист по запросу.</p>
                </div>
            </div>
            <div class="advantage-card animate-on-scroll delay-4">
                <div class="advantage-icon">📊</div>
                <div class="advantage-content">
                    <h3>15 000+ Аккаунтов в Наличии</h3>
                    <p>Постоянное пополнение складов. Не нужно ждать «завтра» - купите нужное количество прямо сейчас.</p>
                </div>
            </div>
            <div class="advantage-card animate-on-scroll delay-5">
                <div class="advantage-icon">🔒</div>
                <div class="advantage-content">
                    <h3>Безопасная Оплата</h3>
                    <p>Принимаем карты РФ, криптовалюту, электронные кошельки. SSL-шифрование всех транзакций.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How to Buy Section -->
<section class="section section-alt" id="how-to-buy">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Купить Аккаунт за 3 Минуты</h2>
        <p class="section-subtitle animate-on-scroll delay-1">Пошаговая инструкция. Регистрация не требуется.</p>

        <div class="steps-grid">
            <div class="step-card animate-on-scroll">
                <div class="step-number">1</div>
                <h3>Выберите Аккаунт</h3>
                <p>Найдите нужную категорию и подкатегорию. Используйте фильтры по цене, количеству и параметрам.</p>
            </div>
            <div class="step-card animate-on-scroll delay-2">
                <div class="step-number">2</div>
                <h3>Нажмите «Купить»</h3>
                <p>Укажите нужное количество и перейдите к оплате. Регистрация не требуется.</p>
            </div>
            <div class="step-card animate-on-scroll delay-4">
                <div class="step-number">3</div>
                <h3>Получите Данные</h3>
                <p>Сразу после оплаты получите логин, пароль, cookies и инструкцию по использованию на email.</p>
            </div>
        </div>
    </div>
</section>

<!-- Trust Signals Section -->
<section class="section" id="trust">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Гарантии и Безопасность Сделок</h2>

        <div class="trust-grid">
            <div>
                <h3 class="mb-16 animate-on-scroll">Что вы получаете при покупке:</h3>
                <div class="trust-list">
                    <div class="trust-item animate-on-scroll">
                        <i class="fa-solid fa-check trust-item-icon"></i>
                        <div class="trust-item-text">
                            <strong>Полные данные для входа</strong>
                            Логин, пароль, данные для восстановления
                        </div>
                    </div>
                    <div class="trust-item animate-on-scroll delay-1">
                        <i class="fa-solid fa-check trust-item-icon"></i>
                        <div class="trust-item-text">
                            <strong>Рабочие cookies</strong>
                            Для обхода дополнительных проверок соцсетей
                        </div>
                    </div>
                    <div class="trust-item animate-on-scroll delay-2">
                        <i class="fa-solid fa-check trust-item-icon"></i>
                        <div class="trust-item-text">
                            <strong>Инструкцию по использованию</strong>
                            Как зайти без риска блокировки
                        </div>
                    </div>
                    <div class="trust-item animate-on-scroll delay-3">
                        <i class="fa-solid fa-check trust-item-icon"></i>
                        <div class="trust-item-text">
                            <strong>Поддержку 24/7</strong>
                            Ответы на вопросы в течение 15 минут
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <h3 class="mb-16 animate-on-scroll">Наши обязательства:</h3>
                <div class="trust-list">
                    <div class="trust-item animate-on-scroll">
                        <i class="fa-solid fa-rotate trust-item-icon"></i>
                        <div class="trust-item-text">
                            <strong>Замена по гарантии</strong>
                            Если аккаунт неработоспособен сразу или в течение 72 часов
                        </div>
                    </div>
                    <div class="trust-item animate-on-scroll delay-1">
                        <i class="fa-solid fa-money-bill trust-item-icon"></i>
                        <div class="trust-item-text">
                            <strong>Возврат средств</strong>
                            Если замена невозможна, возвращаем 100% стоимости
                        </div>
                    </div>
                    <div class="trust-item animate-on-scroll delay-2">
                        <i class="fa-solid fa-ban trust-item-icon"></i>
                        <div class="trust-item-text">
                            <strong>Никаких скрытых платежей</strong>
                            Цена в карточке = итоговая цена
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="section section-alt" id="faq">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Ответы на Популярные Вопросы</h2>

        <div class="faq-list" style="max-width:800px;margin:0 auto;">
            <?php foreach ($faqItems as $i => $item): ?>
            <div class="faq-item animate-on-scroll delay-<?php echo min($i, 5); ?>">
                <button class="faq-question" onclick="toggleFaq(this)">
                    <?php echo htmlspecialchars($item['question']); ?>
                    <i class="fa-solid fa-chevron-down faq-question-icon"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-inner"><?php echo htmlspecialchars($item['answer']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-32">
            <a href="/faq/" class="btn btn-secondary">
                <i class="fa-solid fa-circle-question"></i> Все вопросы и ответы
            </a>
        </div>
    </div>
</section>

<!-- SEO Text Block -->
<section class="section">
    <div class="container">
        <div class="seo-text animate-on-scroll">
            <h2>Продажа Аккаунтов Соцсетей: Полное Руководство</h2>
            <p><?php echo htmlspecialchars($siteName); ?> — специализированный магазин цифровых аккаунтов для маркетологов, SMM-специалистов, арбитражников и предпринимателей. Мы предлагаем аккаунты Facebook для запуска рекламы, Instagram для продвижения бизнеса, VK для работы с русскоязычной аудиторией, Telegram для рассылок и мессенджер-маркетинга.</p>
            <p>В каталоге представлены:</p>
            <ul>
                <li>Автореги - бюджетный вариант для массовых задач</li>
                <li>Фарм-аккаунты - «прогретые» профили с историей</li>
                <li>БМ (Business Manager) - для запуска рекламных кампаний</li>
                <li>PVA-аккаунты - с привязанным телефоном</li>
                <li>Aged-аккаунты - зарегистрированные несколько лет назад</li>
            </ul>
            <p>Все позиции сопровождаются подробным описанием: год регистрации, география, пол, наличие cookies и прокси, статус верификации. Покупайте аккаунты для арбитража трафика, работы с рекламными кабинетами, массфолловинга и других задач digital-маркетинга.</p>
        </div>
    </div>
</section>

<!-- Product Modal -->
<div class="modal" id="productModal">
    <div class="modal-overlay" id="productModalOverlay"></div>
    <div class="modal-dialog" id="productModalDialog">
        <div class="modal-header">
            <h3 id="productModalTitle">Товар</h3>
            <button class="modal-close" id="productModalClose"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="productModalBody">
            <!-- Filled by JS -->
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<?php require __DIR__ . '/includes/footer.php'; ?>
