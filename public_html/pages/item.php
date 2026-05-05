<?php
$slug = sanitize($_GET['slug'] ?? '');
$product = getProductBySlug($slug);

if (!$product) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>404 - Товар не найден</h1><a href="/">На главную</a></body></html>';
    exit;
}

$category = getCategoryBySlug($product['category']);
$currentPage = 'shop';

$siteUrl = getCurrentSiteUrl();
$canonical = '/item/' . $slug . '/';
$metaTags = metaTags(
    $product['name'] . ' | ' . ($settings['site']['name'] ?? 'Магазин аккаунтов'),
    $product['short_description'],
    '',
    $canonical,
    '/images/icons/' . ($product['icon'] ?? 'default.svg'),
    'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
    'product'
);

$schemaOrg = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Product",
      "name": "' . htmlspecialchars($product['name']) . '",
      "description": "' . htmlspecialchars($product['short_description']) . '",
      "image": "' . $siteUrl . '/images/icons/' . ($product['icon'] ?? 'default.svg') . '",
      "sku": "' . (int)($product['id'] ?? 0) . '",
      "category": "' . htmlspecialchars(($category['name'] ?? ucfirst($product['category'] ?? '')) . (!empty($product['subcategory']) ? ' / ' . $product['subcategory'] : '')) . '",
      "offers": {
        "@type": "Offer",
        "url": "' . $siteUrl . $canonical . '",
        "price": "' . $product['price'] . '",
        "priceCurrency": "RUB",
        "availability": "' . ($product['quantity'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock') . '"
      }
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
          "name": "' . htmlspecialchars($category ? $category['name'] : ucfirst($product['category'])) . '",
          "item": "' . $siteUrl . '/category/' . $product['category'] . '/"
        },
        {
          "@type": "ListItem",
          "position": 3,
          "name": "' . htmlspecialchars($product['name']) . '",
          "item": "' . $siteUrl . $canonical . '"
        }
      ]
    }
  ]
}
</script>';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
    <div class="container">
        <?php
        $breadcrumbItems = [
            ['name' => 'Главная', 'url' => '/'],
            ['name' => $category ? $category['name'] : ucfirst($product['category']), 'url' => '/category/' . $product['category'] . '/'],
            ['name' => $product['name']]
        ];
        echo breadcrumbs($breadcrumbItems);
        ?>
        <h1><?php echo htmlspecialchars($product['name']); ?></h1>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="item-layout" style="display:grid;grid-template-columns:1fr 380px;gap:40px;align-items:start;">
            <!-- Product Details -->
            <div class="item-layout-main">
                <div class="item-layout-card" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--border-radius);padding:32px;">
                    <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;">
                        <div class="product-icon" style="width:72px;height:72px;border-radius:16px;">
                            <img src="/images/icons/<?php echo $product['icon']; ?>"
                                 alt="<?php echo $product['category']; ?>"
                                 style="width:44px;height:44px;"
                                 onerror="this.src='/images/icons/default.svg'">
                        </div>
                        <div>
                            <div class="product-category" style="font-size:0.9rem;"><?php echo ucfirst($product['category']); ?> / <?php echo ucfirst($product['subcategory']); ?></div>
                            <h2 style="font-size:1.3rem;"><?php echo htmlspecialchars($product['name']); ?></h2>
                        </div>
                    </div>

                    <div class="article-body-text" style="color:var(--text-secondary);line-height:1.8;margin-bottom:24px;"><?php echo $product['full_description']; // HTML allowed - renders tags ?></div>

                    <div class="product-modal-specs">
                        <div class="spec-item">
                            <div class="spec-label">Цена</div>
                            <div class="spec-value"><?php echo formatPrice($product['price']); ?></div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-label">В наличии</div>
                            <div class="spec-value <?php echo $product['quantity'] > 0 ? 'yes' : 'no'; ?>"><?php echo $product['quantity']; ?> шт.</div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-label">Cookies</div>
                            <div class="spec-value <?php echo $product['cookies'] ? 'yes' : 'no'; ?>"><?php echo $product['cookies'] ? 'Да' : 'Нет'; ?></div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-label">Прокси</div>
                            <div class="spec-value <?php echo $product['proxy'] ? 'yes' : 'no'; ?>"><?php echo $product['proxy'] ? 'Да' : 'Нет'; ?></div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-label">Email верифицирован</div>
                            <div class="spec-value <?php echo $product['email_verified'] ? 'yes' : 'no'; ?>"><?php echo $product['email_verified'] ? 'Да' : 'Нет'; ?></div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-label">Страна</div>
                            <div class="spec-value"><?php echo $product['country']; ?></div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-label">Пол</div>
                            <div class="spec-value"><?php echo $product['sex'] === 'female' ? 'Женский' : ($product['sex'] === 'male' ? 'Мужской' : 'Любой'); ?></div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-label">Год регистрации</div>
                            <div class="spec-value"><?php echo $product['age']; ?></div>
                        </div>
                    </div>

                    <?php if (!empty($product['features'])): ?>
                    <div class="product-features mt-16">
                        <?php foreach ($product['features'] as $feature): ?>
                        <span class="product-feature-tag"><i class="fa-solid fa-check"></i> <?php echo htmlspecialchars($feature); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Buy Panel -->
            <div class="item-layout-sidebar" style="position:sticky;top:100px;">
                <div class="item-layout-card item-layout-buy-card" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--border-radius);padding:28px;">
                    <div style="text-align:center;margin-bottom:20px;">
                        <div style="font-size:2rem;font-weight:800;font-family:'JetBrains Mono',monospace;color:var(--primary);"><?php echo formatPrice($product['price']); ?></div>
                        <div style="font-size:0.85rem;color:var(--text-muted);">за 1 аккаунт</div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:8px;">Количество:</label>
                        <div class="quantity-selector" style="width:100%;">
                            <button class="qty-btn" onclick="changeQty(-1)">−</button>
                            <input type="number" class="qty-input" id="itemQty" value="1" min="1" max="<?php echo $product['quantity']; ?>" style="flex:1;">
                            <button class="qty-btn" onclick="changeQty(1)">+</button>
                        </div>
                    </div>

                    <div style="background:var(--bg-dark);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:20px;">
                        <div style="display:flex;justify-content:space-between;font-size:0.875rem;color:var(--text-secondary);margin-bottom:8px;">
                            <span>Цена за шт.:</span>
                            <span><?php echo formatPrice($product['price']); ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:700;">
                            <span>Итого:</span>
                            <span id="itemTotal" style="font-family:'JetBrains Mono',monospace;color:var(--primary);"><?php echo formatPrice($product['price']); ?></span>
                        </div>
                    </div>

                    <a href="<?php echo htmlspecialchars(appUrl('/oplata/?item=' . $product['slug'] . '&qty=1')); ?>" id="buyNowBtn" class="btn btn-primary btn-full btn-lg">
	                        <i class="fa-solid fa-bolt"></i> Купить сейчас
	                    </a>
	                    <!-- <button class="btn btn-secondary btn-full mt-8" onclick="addToCartItem(<?php echo $product['id']; ?>)">
	                        <i class="fa-solid fa-cart-shopping"></i> В корзину
	                    </button> -->

                    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
                        <div class="trust-item" style="margin-bottom:8px;padding:10px 12px;">
                            <i class="fa-solid fa-shield-halved trust-item-icon"></i>
                            <div class="trust-item-text"><strong>Гарантия 72 часа</strong></div>
                        </div>
                        <div class="trust-item" style="padding:10px 12px;">
                            <i class="fa-solid fa-bolt trust-item-icon"></i>
                            <div class="trust-item-text"><strong>Выдача за 30 секунд</strong></div>
                        </div>
                    </div>
                </div>
                <!-- Sidebar Ad Banners -->
                <?php
                $_adSidebarTop = renderAdSpot('sidebar_top');
                if (!empty($_adSidebarTop)) echo '<div style="margin-top:16px;">' . $_adSidebarTop . '</div>';
                $_adSidebarBot = renderAdSpot('sidebar_bottom');
                if (!empty($_adSidebarBot)) echo '<div style="margin-top:12px;">' . $_adSidebarBot . '</div>';
                ?>
            </div>
        </div>
    </div>
</section>

<div class="toast-container" id="toastContainer"></div>

<script>
const itemPrice = <?php echo $product['price']; ?>;
const itemMaxQty = <?php echo $product['quantity']; ?>;
const itemSlug = '<?php echo $product['slug']; ?>';

function changeQty(delta) {
    const input = document.getElementById('itemQty');
    let val = parseInt(input.value) + delta;
    val = Math.max(1, Math.min(itemMaxQty, val));
    input.value = val;
    updateTotal();
}

document.getElementById('itemQty').addEventListener('input', updateTotal);

function updateTotal() {
    const qty = parseInt(document.getElementById('itemQty').value) || 1;
    const total = qty * itemPrice;
    document.getElementById('itemTotal').textContent = total.toLocaleString('ru-RU') + ' ₽';
    const baseOplataUrl = window.appUrl ? window.appUrl('/oplata/') : '/oplata/';
    document.getElementById('buyNowBtn').href = baseOplataUrl + '?item=' + encodeURIComponent(itemSlug) + '&qty=' + qty;
}

function addToCartItem(id) {
    const qty = parseInt(document.getElementById('itemQty').value) || 1;
    // Fix: use a single addToCart call and pass qty directly to avoid race conditions
    // with multiple simultaneous fetch requests
    addToCartWithQty(id, qty);
}

function addToCartWithQty(productId, qty) {
    const apiUrl = window.appUrl ? window.appUrl('/api/?path=products/' + productId) : '/api/?path=products/' + productId;
    fetch(apiUrl)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const product = data.data;
            const existing = window.cart ? window.cart.find(i => i.id === productId) : null;
            if (existing) {
                existing.qty = Math.min(existing.qty + qty, product.quantity);
            } else {
                if (!window.cart) window.cart = [];
                window.cart.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    icon: product.icon,
                    slug: product.slug,
                    qty: Math.min(qty, product.quantity),
                    maxQty: product.quantity
                });
            }
            if (typeof saveCart === 'function') saveCart();
            if (typeof updateCartUI === 'function') updateCartUI();
            if (typeof renderCartItems === 'function') renderCartItems();
            if (typeof showToast === 'function') showToast(`«${product.name}» добавлен в корзину`, 'success');
        })
        .catch(() => { if (typeof showToast === 'function') showToast('Ошибка загрузки товара', 'error'); });
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
