<?php
$slug = sanitize($_GET['slug'] ?? '');
$sub = sanitize($_GET['sub'] ?? '');

$category = getCategoryBySlug($slug);
if (!$category) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>404 - Категория не найдена</h1><a href="/">На главную</a></body></html>';
    exit;
}

$subcategory = null;
if ($sub) {
    foreach ($category['subcategories'] ?? [] as $s) {
        if ($s['slug'] === $sub) { $subcategory = $s; break; }
    }
}

$products = getProductsByCategory($slug, $sub ?: null);
$currentPage = 'category';
$siteName = $settings['site']['name'] ?? 'Магазин аккаунтов';

$pageTitle = $subcategory
    ? "Купить {$subcategory['name']} {$category['name']} - от " . ($products ? min(array_column($products, 'price')) : 0) . " ₽ | {$siteName}"
    : ($category['seo_title'] ?? "Купить аккаунты {$category['name']} | {$siteName}");

$pageDesc = $subcategory
    ? "Аккаунты {$category['name']} - {$subcategory['name']}. Моментальная выдача. Гарантия 72 часа."
    : ($category['seo_description'] ?? "Продажа аккаунтов {$category['name']} с гарантией. Моментальная выдача.");

$canonical = '/category/' . $slug . '/' . ($sub ? $sub . '/' : '');

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
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Главная",
          "item": "' . $siteUrl . '/"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "' . htmlspecialchars($category['name']) . '",
          "item": "' . $siteUrl . '/category/' . $slug . '/"
        }' . ($subcategory ? ',
        {
          "@type": "ListItem",
          "position": 3,
          "name": "' . htmlspecialchars($subcategory['name']) . '",
          "item": "' . $siteUrl . $canonical . '"
        }' : '') . '
      ]
    }
  ]
}
</script>';

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container">
        <?php
        $breadcrumbItems = [['name' => 'Главная', 'url' => '/'], ['name' => $category['name'], 'url' => '/category/' . $slug . '/']];
        if ($subcategory) $breadcrumbItems[] = ['name' => $subcategory['name']];
        echo breadcrumbs($breadcrumbItems);
        ?>
        <h1>
            Купить Аккаунты <?php echo $category['name']; ?>
            <?php if ($subcategory): ?> - <?php echo $subcategory['name']; ?><?php endif; ?>
            <?php if ($products): ?> - от <?php echo formatPrice(min(array_column($products, 'price'))); ?><?php endif; ?>
        </h1>
        <p>Аккаунты <?php echo $category['name']; ?> в наличии. Моментальная выдача после оплаты. Гарантия замены 72 часа. Оптовые скидки от 100 шт.</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <!-- Subcategory Tabs -->
        <?php if (!empty($category['subcategories'])): ?>
        <div class="subcategory-tabs">
            <a href="/category/<?php echo $slug; ?>/"
               class="subcategory-tab <?php echo !$sub ? 'active' : ''; ?>">
                Все
            </a>
            <?php foreach ($category['subcategories'] as $s): ?>
            <a href="/category/<?php echo $slug; ?>/<?php echo $s['slug']; ?>/"
               class="subcategory-tab <?php echo $sub === $s['slug'] ? 'active' : ''; ?>">
                <?php echo $s['name']; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

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
                Найдено: <strong id="productsCount"><?php echo count($products); ?></strong> товаров
            </span>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
        <div class="text-center" style="padding:60px 0;">
            <i class="fa-solid fa-box-open" style="font-size:3rem;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
            <h3>Товары не найдены</h3>
            <p class="text-muted mt-8">В данной категории пока нет товаров. Попробуйте другую категорию.</p>
            <a href="/" class="btn btn-primary mt-16">На главную</a>
        </div>
        <?php else: ?>
        <?php $firstBatch = array_slice($products, 0, 40); $restProducts = array_slice($products, 40); ?>
        <div class="products-grid" id="categoryProductsGrid">
            <?php foreach ($firstBatch as $i => $product): ?>
            <div class="product-card animate-on-scroll delay-<?php echo min($i % 6, 5); ?> <?php echo (!empty($product['items']) ? '' : (($product['quantity'] > 0 && ($product['is_demo'] ?? false)) ? 'demo-product' : '')); ?>"
                 data-product-id="<?php echo $product['id']; ?>"
                 data-price="<?php echo $product['price']; ?>"
                 data-qty="<?php echo $product['quantity']; ?>"
                 data-is-demo="<?php echo ($product['is_demo'] ?? false) ? 'true' : 'false'; ?>"
                 onclick="openProductModal(<?php echo $product['id']; ?>)">
                <div class="product-card-header">
                    <div class="product-icon">
                        <img src="/images/icons/<?php echo $product['icon']; ?>"
                             alt="<?php echo $product['category']; ?>"
                             loading="lazy"
                             onerror="this.src='/images/icons/default.svg'">
                    </div>
                    <div class="product-meta">
                        <div class="product-category"><?php echo ucfirst($product['subcategory']); ?></div>
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
            <button class="btn btn-secondary btn-lg" id="loadMoreCatBtn" onclick="loadMoreCategory()">
                <i class="fa-solid fa-rotate"></i> Загрузить ещё (<?php echo count($restProducts); ?> товаров)
            </button>
        </div>
        <script>
        var catProductsExtra = <?php echo json_encode($restProducts, JSON_UNESCAPED_UNICODE); ?>;
        var catLoadOffset = 0;
        function loadMoreCategory() {
            var batch = catProductsExtra.slice(catLoadOffset, catLoadOffset + 40);
            catLoadOffset += 40;
            var grid = document.getElementById('categoryProductsGrid');
            batch.forEach(function(p) {
                var card = document.createElement('div');
                card.className = 'product-card animated'; // сразу видимые
                card.setAttribute('data-product-id', p.id);
                card.setAttribute('data-price', p.price);
                card.setAttribute('data-qty', p.quantity);
                card.onclick = function() { openProductModal(p.id); };
                var features = (p.features || []).slice(0,3).map(function(f){ return '<span class="product-feature-tag"><i class="fa-solid fa-check"></i> ' + escHtml(f) + '</span>'; }).join('');
                card.innerHTML = '<div class="product-card-header"><div class="product-icon"><img src="/images/icons/' + escHtml(p.icon) + '" alt="" onerror="this.src=\'/images/icons/default.svg\'" loading="lazy"></div><div class="product-meta"><div class="product-category">' + escHtml(p.subcategory) + '</div><div class="product-name">' + escHtml(p.name) + '</div></div></div><p class="product-description">' + escHtml(p.short_description) + '</p><div class="product-features">' + features + '</div><div class="product-footer"><div class="product-stock ' + (p.quantity === 0 ? 'out-of-stock' : '') + '">В наличии: <span class="stock-count">' + p.quantity + ' шт.</span></div><div class="product-price"><span class="price-from">от</span> ' + formatPrice(p.price) + '</div></div><div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;"><a href="' + appUrl('/oplata/?item=' + encodeURIComponent(p.slug) + '&qty=1') + '" class="product-buy-btn" style="width:100%;" onclick="event.stopPropagation();"><i class="fa-solid fa-cart-shopping"></i> Купить</a><a href="/item/' + escHtml(p.slug) + '/" class="product-buy-btn" style="width:100%;background:var(--bg-hover);color:var(--text-secondary);text-align:center;" onclick="event.stopPropagation();" target="_blank"><i class="fa-solid fa-file-lines"></i> Полное описание</a></div>';
                grid.appendChild(card);
            });
            if (catLoadOffset >= catProductsExtra.length) {
                document.getElementById('loadMoreCatWrap').style.display = 'none';
            } else {
                document.getElementById('loadMoreCatBtn').innerHTML = '<i class="fa-solid fa-rotate"></i> Загрузить ещё (' + (catProductsExtra.length - catLoadOffset) + ' товаров)';
            }
        }
        </script>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Comparison Table -->
        <?php if (!$sub && !empty($category['subcategories'])): ?>
        <div class="mt-32 animate-on-scroll">
            <h3 class="mb-16">Сравнение Типов Аккаунтов <?php echo $category['name']; ?></h3>
            <div style="overflow-x:auto;">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Параметр</th>
                            <th>Автореги</th>
                            <th>Фарм</th>
                            <th>Aged</th>
                            <th>БМ/Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Цена</td>
                            <td class="price-cell">от 50 ₽</td>
                            <td class="price-cell">от 149 ₽</td>
                            <td class="price-cell">от 299 ₽</td>
                            <td class="price-cell">от 499 ₽</td>
                        </tr>
                        <tr>
                            <td>Возраст</td>
                            <td>1-7 дней</td>
                            <td>1-3 месяца</td>
                            <td>1-4 года</td>
                            <td>1-3 месяца</td>
                        </tr>
                        <tr>
                            <td>Живучесть</td>
                            <td>Средняя</td>
                            <td>Высокая</td>
                            <td>Максимальная</td>
                            <td>Высокая</td>
                        </tr>
                        <tr>
                            <td>Для чего</td>
                            <td>Тесты, массовка</td>
                            <td>Реклама, работа</td>
                            <td>Серьёзные проекты</td>
                            <td>Запуск РК</td>
                        </tr>
                        <tr>
                            <td>Cookies</td>
                            <td><span class="badge badge-success">Да</span></td>
                            <td><span class="badge badge-success">Да</span></td>
                            <td><span class="badge badge-success">Да</span></td>
                            <td><span class="badge badge-success">Да</span></td>
                        </tr>
                        <tr>
                            <td>Гарантия</td>
                            <td>24 часа</td>
                            <td>72 часа</td>
                            <td>72 часа</td>
                            <td>72 часа</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-16">
                <button class="btn btn-secondary" id="openContactModalCat">
                    <i class="fa-solid fa-comment"></i> Не знаете что выбрать? Напишите нам →
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sidebar Ad Banners (shown after products) -->
        <?php
        $_adSidebarTopCat = renderAdSpot('sidebar_top');
        $_adSidebarBotCat = renderAdSpot('sidebar_bottom');
        if (!empty($_adSidebarTopCat) || !empty($_adSidebarBotCat)):
        ?>
        <div class="ad-section-inline" style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center;margin-top:32px;margin-bottom:16px;">
            <?php if (!empty($_adSidebarTopCat)) echo $_adSidebarTopCat; ?>
            <?php if (!empty($_adSidebarBotCat)) echo $_adSidebarBotCat; ?>
        </div>
        <?php endif; ?>

        <!-- SEO Text -->
        <div class="seo-text animate-on-scroll">
            <h2><?php echo $category['name']; ?> Аккаунты для Бизнеса и Маркетинга</h2>
            <p>Аккаунты <?php echo $category['name']; ?> - необходимый инструмент для SMM-продвижения, таргетированной рекламы, арбитража трафика и автоматизации бизнес-процессов.</p>
            <p>В отличие от самостоятельной регистрации, покупные аккаунты дают:</p>
            <ul>
                <li>Экономию времени - не нужно ждать «прогрева»</li>
                <li>Готовый траст - обход антифрод-систем</li>
                <li>Масштабируемость - запуск сотни аккаунтов одновременно</li>
                <li>Техническую поддержку - помощь при возникновении вопросов</li>
            </ul>
            <p>Мы предлагаем только проверенные аккаунты с рабочими cookies и корректными данными. Каждая партия проходит валидацию перед добавлением в каталог. Оптовым покупателям - специальные условия и персональный менеджер.</p>
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
        <div class="modal-body" id="productModalBody"></div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
document.getElementById('openContactModalCat')?.addEventListener('click', function() {
    document.getElementById('contactModal').classList.add('open');
});
function sortProducts(val) {
    const grid = document.getElementById('categoryProductsGrid');
    const cards = Array.from(grid.querySelectorAll('.product-card'));
    cards.sort((a, b) => {
        const pa = parseFloat(a.dataset.price), pb = parseFloat(b.dataset.price);
        const qa = parseInt(a.dataset.qty), qb = parseInt(b.dataset.qty);
        if (val === 'price_asc') return pa - pb;
        if (val === 'price_desc') return pb - pa;
        if (val === 'qty_desc') return qb - qa;
        return 0;
    });
    cards.forEach(c => grid.appendChild(c));
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
