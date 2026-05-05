<?php
/**
 * Account Store CMS - Helper Functions
 */

// In-memory cache for JSON data (per-request)
$_dataCache = [];

// Load JSON data with in-memory caching
function loadData($file) {
    global $_dataCache;
    if (isset($_dataCache[$file])) return $_dataCache[$file];
    $path = __DIR__ . '/../data/' . $file . '.json';
    if (!file_exists($path)) return [];
    $content = file_get_contents($path);
    $data = json_decode($content, true) ?? [];
    $_dataCache[$file] = $data;
    return $data;
}

// Save JSON data (also invalidates in-memory cache)
function saveData($file, $data) {
    global $_dataCache;
    $path = __DIR__ . '/../data/' . $file . '.json';
    $result = file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    // Invalidate cache
    if ($result !== false) unset($_dataCache[$file]);
    return $result;
}

// Get settings
function getSettings() {
    return loadData('settings');
}

function getCryptoTokenCatalog() {
    $catalog = loadData('crypto_tokens');
    return is_array($catalog) ? $catalog : [];
}

function getCryptoTokens() {
    $catalog = getCryptoTokenCatalog();
    $tokens = $catalog['tokens'] ?? [];
    return is_array($tokens) ? $tokens : [];
}

function saveCryptoTokens($tokens) {
    $catalog = getCryptoTokenCatalog();
    $catalog['tokens'] = array_values($tokens);
    return saveData('crypto_tokens', $catalog);
}

function findCryptoTokenByCode($code) {
    $code = strtoupper(trim((string)$code));
    foreach (getCryptoTokens() as $token) {
        if (strtoupper((string)($token['code'] ?? '')) === $code) {
            return $token;
        }
    }
    return null;
}

function httpRequestJson($url, $method = 'GET', $headers = [], $body = null, $timeout = 20) {
    $method = strtoupper(trim((string)$method));
    if ($method === '') {
        $method = 'GET';
    }

    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => 'AccountStoreCMS/crypto-payment',
            CURLOPT_CUSTOMREQUEST => $method
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response === false || $statusCode >= 400) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    $contextOptions = [
        'http' => [
            'method' => $method,
            'timeout' => $timeout,
            'header' => implode("\r\n", $headerLines),
            'user_agent' => 'AccountStoreCMS/crypto-payment'
        ]
    ];
    if ($body !== null) {
        $contextOptions['http']['content'] = $body;
    }
    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function httpGetJson($url, $headers = [], $timeout = 20) {
    return httpRequestJson($url, 'GET', $headers, null, $timeout);
}

function httpPostJson($url, $payload = [], $headers = [], $timeout = 20) {
    $headers = array_merge(['Content-Type' => 'application/json'], $headers);
    $body = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
    return httpRequestJson($url, 'POST', $headers, $body, $timeout);
}

function isCryptoRatesCacheFresh($cache, $ttlSeconds = 43200) {
    if (!is_array($cache) || empty($cache['updated_at_unix'])) {
        return false;
    }
    return (time() - (int)$cache['updated_at_unix']) < $ttlSeconds;
}

function refreshCryptoRatesCache($force = false) {
    $cache = loadData('crypto_rates');
    if (!$force && isCryptoRatesCacheFresh($cache)) {
        return $cache;
    }

    $tokens = getCryptoTokens();
    $coinIds = [];
    foreach ($tokens as $token) {
        $coinId = trim((string)($token['coingecko_id'] ?? ''));
        if ($coinId !== '') {
            $coinIds[$coinId] = true;
        }
    }

    if (empty($coinIds)) {
        return is_array($cache) ? $cache : [];
    }

    $coinGeckoUrl = 'https://api.coingecko.com/api/v3/simple/price?ids=' . rawurlencode(implode(',', array_keys($coinIds))) . '&vs_currencies=usd&include_last_updated_at=true';
    $coinGeckoResponse = httpGetJson($coinGeckoUrl);
    $fxResponse = httpGetJson('https://www.cbr-xml-daily.ru/daily_json.js');

    if (!is_array($coinGeckoResponse) || empty($coinGeckoResponse) || !is_array($fxResponse)) {
        return is_array($cache) ? $cache : [];
    }

    $usdValute = $fxResponse['Valute']['USD'] ?? null;
    $rubPerUsd = 0;
    if (is_array($usdValute) && !empty($usdValute['Value']) && !empty($usdValute['Nominal'])) {
        $rubPerUsd = ((float)$usdValute['Value']) / max(1, (float)$usdValute['Nominal']);
    }
    $usdPerRub = $rubPerUsd > 0 ? (1 / $rubPerUsd) : 0;
    if ($usdPerRub <= 0 || $rubPerUsd <= 0) {
        return is_array($cache) ? $cache : [];
    }

    $rates = [];
    foreach ($tokens as $token) {
        $coinId = trim((string)($token['coingecko_id'] ?? ''));
        $code = strtoupper((string)($token['code'] ?? ''));
        $usdSymbol = strtoupper((string)($token['usd_symbol'] ?? $code));
        if ($coinId === '' || $code === '' || empty($coinGeckoResponse[$coinId]['usd'])) {
            continue;
        }
        $usdRate = (float)$coinGeckoResponse[$coinId]['usd'];
        $rates[$code] = [
            'code' => $code,
            'symbol' => $usdSymbol,
            'coingecko_id' => $coinId,
            'usd' => $usdRate,
            'rub' => $usdRate * $rubPerUsd,
            'last_updated_at' => (int)($coinGeckoResponse[$coinId]['last_updated_at'] ?? time())
        ];
    }

    $payload = [
        'provider' => 'coingecko_simple_price',
        'fx_provider' => 'cbr_xml_daily',
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_at_unix' => time(),
        'ttl_seconds' => 43200,
        'usd_per_rub' => $usdPerRub,
        'rub_per_usd' => $rubPerUsd,
        'fx_timestamp' => !empty($fxResponse['Timestamp']) ? strtotime((string)$fxResponse['Timestamp']) : time(),
        'rates' => $rates
    ];
    saveData('crypto_rates', $payload);
    return $payload;
}

function getCryptoUsdRates($forceRefresh = false) {
    return refreshCryptoRatesCache($forceRefresh);
}

// Get all categories
function getCategories() {
    return loadData('categories');
}

// Get category by slug
function getCategoryBySlug($slug) {
    $categories = getCategories();
    foreach ($categories as $cat) {
        if ($cat['slug'] === $slug) return $cat;
    }
    return null;
}

// Включаем модуль разбивки товаров
require_once __DIR__ . '/functions_products_split.php';

// Get all products (с поддержкой разбивки)
function getProducts() {
    return getProductsWithSplitSupport();
}

// Get products by category
function getProductsByCategory($categorySlug, $subcategorySlug = null) {
    $products = getProducts();
    $filtered = array_values(array_filter($products, function($p) use ($categorySlug, $subcategorySlug) {
        if ($p['category'] !== $categorySlug) return false;
        if ($subcategorySlug && $p['subcategory'] !== $subcategorySlug) return false;
        return $p['status'] === 'active';
    }));
    return filterDemoProducts($filtered);
}

// Filter demo products based on settings
function filterDemoProducts($products) {
    $settings = getSettings();
    $showDemo = $settings['shop']['show_demo_products'] ?? true;
    if ($showDemo) return $products;
    // Hide demo products (those with quantity but no items)
    return array_values(array_filter($products, function($p) {
        $hasItems = !empty($p['items']) && count($p['items']) > 0;
        $isDemo = $p['is_demo'] ?? false;
        return $hasItems || !$isDemo || $p['quantity'] === 0;
    }));
}

// Get product by slug
function getProductBySlug($slug) {
    $products = getProducts();
    foreach ($products as $p) {
        if ($p['slug'] === $slug) return $p;
    }
    return null;
}

// Determine whether product should be shown as a sales hit
function isProductHit($product) {
    if (!is_array($product)) {
        return false;
    }

    foreach (['popular', 'hit', 'is_hit', 'featured'] as $flag) {
        if (!empty($product[$flag])) {
            return true;
        }
    }

    return false;
}

// Get popular products / sales hits
function getPopularProducts($limit = 6) {
    $products = getProducts();
    $popular = array_filter($products, fn($p) => isProductHit($p) && ($p['status'] ?? '') === 'active');
    $popular = array_values($popular);
    $popular = filterDemoProducts($popular);
    return array_slice($popular, 0, $limit);
}

// Get pages data
function getPages() {
    return loadData('pages');
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Generate breadcrumbs
function breadcrumbs($items) {
    $html = '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol itemscope itemtype="https://schema.org/BreadcrumbList">';
    foreach ($items as $i => $item) {
        $pos = $i + 1;
        if (isset($item['url'])) {
            $html .= "<li itemprop=\"itemListElement\" itemscope itemtype=\"https://schema.org/ListItem\">
                <a itemprop=\"item\" href=\"{$item['url']}\"><span itemprop=\"name\">{$item['name']}</span></a>
                <meta itemprop=\"position\" content=\"{$pos}\" />
            </li>";
        } else {
            $html .= "<li itemprop=\"itemListElement\" itemscope itemtype=\"https://schema.org/ListItem\">
                <span itemprop=\"name\">{$item['name']}</span>
                <meta itemprop=\"position\" content=\"{$pos}\" />
            </li>";
        }
    }
    $html .= '</ol></nav>';
    return $html;
}

// Format price
function formatPrice($price) {
    return number_format($price, 0, '.', ' ') . ' ₽';
}

// Get icon URL
function getIconUrl($icon) {
    return '/images/icons/' . $icon;
}

// Count total products
function countTotalProducts() {
    $products = getProducts();
    return array_sum(array_column(array_filter($products, fn($p) => $p['status'] === 'active'), 'quantity'));
}

/**
 * Возвращает базовый путь установки приложения.
 * Поддерживает запуск как из корня домена, так и из подпапки.
 */
function getBasePath() {
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName));

    if ($dir === '.' || $dir === '/' || $dir === '\\') {
        $dir = '';
    }

    $dir = rtrim($dir, '/');
    $dir = preg_replace('#/(admin|api)$#', '', $dir);
    $dir = $dir === '/' ? '' : $dir;

    $basePath = $dir;
    return $basePath;
}

/**
 * Строит URL внутри текущей установки приложения.
 */
function appUrl($path = '/') {
    if (preg_match('#^https?://#i', (string)$path)) {
        return $path;
    }

    $basePath = getBasePath();
    $normalizedPath = '/' . ltrim((string)$path, '/');

    if ($normalizedPath === '//') {
        $normalizedPath = '/';
    }

    if ($basePath === '') {
        return $normalizedPath;
    }

    return $normalizedPath === '/' ? $basePath . '/' : $basePath . $normalizedPath;
}

/**
 * Переписывает root-relative ссылки в HTML/inline JS с учётом подпапки установки.
 */
function rewriteAppUrls($content) {
    $basePath = getBasePath();
    if ($basePath === '' || $basePath === '/') {
        return $content;
    }

    $content = preg_replace_callback(
        '#\b(href|src|action|poster|content)=(["\'])/(?!/|https?:|mailto:|tel:|#)#i',
        function ($matches) use ($basePath) {
            return $matches[1] . '=' . $matches[2] . $basePath . '/';
        },
        $content
    );

    $replacements = [
        "fetch('/" => "fetch('" . $basePath . "/",
        'fetch("/' => 'fetch("' . $basePath . '/',
        "this.src='/" => "this.src='" . $basePath . "/",
        'this.src="/' => 'this.src="' . $basePath . '/',
        "this.href='/" => "this.href='" . $basePath . "/",
        'this.href="/' => 'this.href="' . $basePath . '/',
        "window.location.href='/" => "window.location.href='" . $basePath . "/",
        'window.location.href="/' => 'window.location.href="' . $basePath . '/',
        'url(/' => 'url(' . $basePath . '/',
        "='/images/" => "='" . $basePath . "/images/",
        '="/images/' => '="' . $basePath . '/images/',
        "'/api/" => "'" . $basePath . "/api/",
        '"/api/' => '"' . $basePath . '/api/',
        '`/api/' => '`' . $basePath . '/api/',
        "'/item/" => "'" . $basePath . "/item/",
        '"/item/' => '"' . $basePath . '/item/',
        '`/item/' => '`' . $basePath . '/item/',
        "'/oplata/" => "'" . $basePath . "/oplata/",
        '"/oplata/' => '"' . $basePath . '/oplata/',
        '`/oplata/' => '`' . $basePath . '/oplata/',
        "'/rules/" => "'" . $basePath . "/rules/",
        '"/rules/' => '"' . $basePath . '/rules/'
    ];

    return strtr($content, $replacements);
}

function startAppOutputBuffer() {
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (!headers_sent()) {
        ob_start('rewriteAppUrls');
    }
}

/**
 * Определяет базовый URL сайта динамически по HTTP_HOST.
 * При развёртывании на новом домене canonical и sitemap автоматически
 * указывают на текущий домен и путь установки, а не на старое значение из settings.json.
 */
function normalizeHostName($host) {
    $host = trim((string)$host);
    if ($host === '') {
        return '';
    }

    $host = preg_replace('/:\d+$/', '', $host);
    return trim($host, '[]');
}

function isLocalHostName($host) {
    $host = strtolower(normalizeHostName($host));
    if ($host === '') {
        return false;
    }

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || preg_match('/^127(?:\.\d{1,3}){3}$/', $host)
        || preg_match('/^192\.168(?:\.\d{1,3}){2}$/', $host)
        || preg_match('/^10(?:\.\d{1,3}){3}$/', $host)
        || preg_match('/^172\.(1[6-9]|2\d|3[01])(?:\.\d{1,3}){2}$/', $host);
}

function getCurrentSiteUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        // Фоллбэк: если нет HTTP_HOST (например, CLI), берём из settings
        $settings = getSettings();
        return rtrim($settings['site']['url'] ?? '', '/');
    }
    return $protocol . '://' . $host . getBasePath();
}

// Generate meta tags - Enhanced SEO version
function metaTags(
    $title,
    $description,
    $keywords = '',
    $canonical = '',
    $image = '',
    $robots = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
    $type = 'website'
) {
    $siteUrl = getCurrentSiteUrl();
    $settings = getSettings();
    $siteName = trim($settings['site']['name'] ?? 'Магазин аккаунтов');
    $canonicalUrl = $canonical ? $siteUrl . $canonical : '';
    $defaultImage = '/images/ui/favicon.svg';
    $imagePath = $image ?: $defaultImage;
    $absoluteImage = preg_match('#^https?://#i', $imagePath) ? $imagePath : $siteUrl . '/' . ltrim($imagePath, '/');

    // Auto-fill keywords from global settings if not provided
    if (empty($keywords)) {
        $keywords = $settings['seo']['keywords'] ?? '';
    }

    $html = "<title>" . htmlspecialchars($title) . "</title>\n";
    $html .= "<meta name=\"description\" content=\"" . htmlspecialchars($description) . "\">\n";
    $html .= "<meta name=\"robots\" content=\"" . htmlspecialchars($robots) . "\">\n";
    $html .= "<meta name=\"author\" content=\"" . htmlspecialchars($siteName) . "\">\n";
    $html .= "<meta name=\"application-name\" content=\"" . htmlspecialchars($siteName) . "\">\n";
    $html .= "<meta name=\"generator\" content=\"Account Store CMS\">\n";
    if ($keywords) {
        $html .= "<meta name=\"keywords\" content=\"" . htmlspecialchars($keywords) . "\">\n";
    }
    if ($canonicalUrl) {
        $html .= "<link rel=\"canonical\" href=\"" . htmlspecialchars($canonicalUrl) . "\">\n";
    }
    // Verification codes from analytics settings
    $googleVerify = trim($settings['analytics']['google_verify'] ?? '');
    if ($googleVerify) {
        $html .= "<meta name=\"google-site-verification\" content=\"" . htmlspecialchars($googleVerify) . "\">\n";
    }
    $yandexVerify = trim($settings['analytics']['yandex_verify'] ?? '');
    if ($yandexVerify) {
        $html .= "<meta name=\"yandex-verification\" content=\"" . htmlspecialchars($yandexVerify) . "\">\n";
    }
    // Open Graph
    $html .= "<meta property=\"og:locale\" content=\"ru_RU\">\n";
    $html .= "<meta property=\"og:site_name\" content=\"" . htmlspecialchars($siteName) . "\">\n";
    $html .= "<meta property=\"og:title\" content=\"" . htmlspecialchars($title) . "\">\n";
    $html .= "<meta property=\"og:description\" content=\"" . htmlspecialchars($description) . "\">\n";
    if ($canonicalUrl) {
        $html .= "<meta property=\"og:url\" content=\"" . htmlspecialchars($canonicalUrl) . "\">\n";
    }
    $html .= "<meta property=\"og:type\" content=\"" . htmlspecialchars($type) . "\">\n";
    $html .= "<meta property=\"og:image\" content=\"" . htmlspecialchars($absoluteImage) . "\">\n";
    $html .= "<meta property=\"og:image:alt\" content=\"" . htmlspecialchars($siteName) . "\">\n";
    $html .= "<meta property=\"og:image:width\" content=\"1200\">\n";
    $html .= "<meta property=\"og:image:height\" content=\"630\">\n";
    // Twitter Cards
    $html .= "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    $html .= "<meta name=\"twitter:title\" content=\"" . htmlspecialchars($title) . "\">\n";
    $html .= "<meta name=\"twitter:description\" content=\"" . htmlspecialchars($description) . "\">\n";
    $html .= "<meta name=\"twitter:image\" content=\"" . htmlspecialchars($absoluteImage) . "\">\n";
    if ($canonicalUrl) {
        $html .= "<meta name=\"twitter:url\" content=\"" . htmlspecialchars($canonicalUrl) . "\">\n";
    }
    return $html;
}

/**
 * Генерирует ключевые слова для страницы на основе контекста.
 * Используется для автоматического SEO будущих статей и страниц.
 */
function generateKeywords($context = [], $type = 'page') {
    $settings = getSettings();
    $siteName = $settings['site']['name'] ?? 'Магазин аккаунтов';
    $baseKeywords = $settings['seo']['keywords'] ?? 'купить аккаунты, аккаунты соцсетей, цифровые товары';

    $keywords = [];
    switch ($type) {
        case 'article':
            $title = $context['title'] ?? '';
            $category = $context['category'] ?? '';
            if ($title) {
                $words = preg_split('/[\s,]+/', mb_strtolower($title));
                foreach ($words as $word) {
                    if (mb_strlen($word) > 3) $keywords[] = $word;
                }
            }
            if ($category) $keywords[] = mb_strtolower($category);
            $keywords[] = 'блог';
            $keywords[] = $siteName;
            break;
        case 'category':
            $catName = $context['name'] ?? '';
            if ($catName) {
                $keywords[] = 'купить ' . mb_strtolower($catName);
                $keywords[] = mb_strtolower($catName) . ' аккаунты';
                $keywords[] = mb_strtolower($catName);
            }
            $keywords[] = 'купить аккаунты';
            $keywords[] = $siteName;
            break;
        case 'product':
            $productName = $context['name'] ?? '';
            $catName = $context['category'] ?? '';
            if ($productName) {
                $keywords[] = 'купить ' . mb_strtolower($productName);
                $keywords[] = mb_strtolower($productName);
            }
            if ($catName) {
                $keywords[] = 'купить ' . mb_strtolower($catName);
                $keywords[] = mb_strtolower($catName);
            }
            $keywords[] = 'аккаунты';
            $keywords[] = $siteName;
            break;
        default:
            return $baseKeywords;
    }
    $baseArr = array_map('trim', explode(',', $baseKeywords));
    $merged = array_unique(array_merge($keywords, array_slice($baseArr, 0, 5)));
    return implode(', ', array_filter($merged));
}

/**
 * Генерирует HTML-код аналитики для вставки в <head>.
 * Поддерживает Google Analytics 4, Яндекс.Метрику и Google Tag Manager.
 */
function getAnalyticsHeadCode() {
    $settings = getSettings();
    $html = '';

    // Google Tag Manager (head)
    $gtmId = trim($settings['analytics']['gtm_id'] ?? '');
    if ($gtmId) {
        $html .= "<!-- Google Tag Manager -->\n";
        $html .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
        $html .= "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
        $html .= "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
        $html .= "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
        $html .= "})(window,document,'script','dataLayer','" . htmlspecialchars($gtmId) . "');</script>\n";
        $html .= "<!-- End Google Tag Manager -->\n";
    }

    // Google Analytics 4
    $gaId = trim($settings['analytics']['ga4_id'] ?? '');
    if ($gaId) {
        $html .= "<!-- Google Analytics 4 -->\n";
        $html .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . htmlspecialchars($gaId) . "\"></script>\n";
        $html .= "<script>\n";
        $html .= "  window.dataLayer = window.dataLayer || [];\n";
        $html .= "  function gtag(){dataLayer.push(arguments);}\n";
        $html .= "  gtag('js', new Date());\n";
        $html .= "  gtag('config', '" . htmlspecialchars($gaId) . "', { 'anonymize_ip': true });\n";
        $html .= "</script>\n";
        $html .= "<!-- End Google Analytics 4 -->\n";
    }

    // Яндекс.Метрика
    $ymId = trim($settings['analytics']['ym_id'] ?? '');
    if ($ymId) {
        $html .= "<!-- Yandex.Metrika counter -->\n";
        $html .= "<script type=\"text/javascript\">\n";
        $html .= "   (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};\n";
        $html .= "   m[i].l=1*new Date();\n";
        $html .= "   for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}\n";
        $html .= "   k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})\n";
        $html .= "   (window, document, \"script\", \"https://mc.yandex.ru/metrika/tag.js\", \"ym\");\n";
        $html .= "   ym(" . htmlspecialchars($ymId) . ", \"init\", {\n";
        $html .= "        clickmap:true,\n";
        $html .= "        trackLinks:true,\n";
        $html .= "        accurateTrackBounce:true,\n";
        $html .= "        webvisor:true\n";
        $html .= "   });\n";
        $html .= "</script>\n";
        $html .= "<!-- /Yandex.Metrika counter -->\n";
    }

    // Произвольный код для <head>
    $customHead = trim($settings['analytics']['custom_head'] ?? '');
    if ($customHead) {
        $html .= "<!-- Custom Analytics Head Code -->\n" . $customHead . "\n";
    }

    return $html;
}

/**
 * Генерирует HTML-код аналитики для вставки сразу после <body>.
 */
function getAnalyticsBodyCode() {
    $settings = getSettings();
    $html = '';

    // Google Tag Manager (body noscript)
    $gtmId = trim($settings['analytics']['gtm_id'] ?? '');
    if ($gtmId) {
        $html .= "<!-- Google Tag Manager (noscript) -->\n";
        $html .= "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=" . htmlspecialchars($gtmId) . "\"\n";
        $html .= "height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
        $html .= "<!-- End Google Tag Manager (noscript) -->\n";
    }

    // Яндекс.Метрика noscript
    $ymId = trim($settings['analytics']['ym_id'] ?? '');
    if ($ymId) {
        $html .= "<!-- Yandex.Metrika noscript -->\n";
        $html .= "<noscript><div><img src=\"https://mc.yandex.ru/watch/" . htmlspecialchars($ymId) . "\" style=\"position:absolute; left:-9999px;\" alt=\"\" /></div></noscript>\n";
        $html .= "<!-- /Yandex.Metrika noscript -->\n";
    }

    // Произвольный код для <body>
    $customBody = trim($settings['analytics']['custom_body'] ?? '');
    if ($customBody) {
        $html .= "<!-- Custom Analytics Body Code -->\n" . $customBody . "\n";
    }

    return $html;
}

/**
 * Проверяет, авторизован ли пользователь как администратор (для визуального редактора).
 */
function isAdminForEditor() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Admin auth check
function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Require admin auth
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . appUrl('/admin/'));
        exit;
    }
}

// Generate sitemap - Enhanced with image sitemap and proper priorities
function generateSitemap() {
    $siteUrl = getCurrentSiteUrl();
    $categories = getCategories();
    $products = getProducts();
    $pages = getPages();
    $articles = $pages['info']['articles'] ?? [];

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    // Main pages with proper priorities
    $staticPages = [
        '' => ['priority' => '1.0', 'changefreq' => 'daily'],
        'faq' => ['priority' => '0.8', 'changefreq' => 'weekly'],
        'rules' => ['priority' => '0.7', 'changefreq' => 'monthly'],
        'info' => ['priority' => '0.9', 'changefreq' => 'daily'],
        'oplata' => ['priority' => '0.6', 'changefreq' => 'monthly'],
        'advertising' => ['priority' => '0.7', 'changefreq' => 'monthly'],
    ];
    foreach ($staticPages as $page => $meta) {
        $url = $siteUrl . '/' . ($page ? $page . '/' : '');
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
        $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        $xml .= "    <changefreq>" . $meta['changefreq'] . "</changefreq>\n";
        $xml .= "    <priority>" . $meta['priority'] . "</priority>\n";
        $xml .= "  </url>\n";
    }

    // Category pages
    foreach ($categories as $cat) {
        $url = $siteUrl . '/category/' . $cat['slug'] . '/';
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
        $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        $xml .= "    <changefreq>daily</changefreq>\n";
        $xml .= "    <priority>0.9</priority>\n";
        $xml .= "  </url>\n";
        foreach ($cat['subcategories'] ?? [] as $sub) {
            $url = $siteUrl . '/category/' . $cat['slug'] . '/' . $sub['slug'] . '/';
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
            $xml .= "    <changefreq>daily</changefreq>\n";
            $xml .= "    <priority>0.8</priority>\n";
            $xml .= "  </url>\n";
        }
    }

    // Product pages
    foreach ($products as $product) {
        if ($product['status'] === 'active') {
            $url = $siteUrl . '/item/' . $product['slug'] . '/';
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
            $xml .= "    <changefreq>daily</changefreq>\n";
            $xml .= "    <priority>0.7</priority>\n";
            $xml .= "  </url>\n";
        }
    }

    // Article pages (blog) - автоматически включает будущие статьи с lastmod
    foreach ($articles as $article) {
        $url = $siteUrl . '/info/' . $article['slug'] . '/';
        // Приоритет: lastmod > date
        $lastmod = $article['lastmod'] ?? $article['date'] ?? date('Y-m-d');
        // Преобразуем в ISO 8601 для sitemap
        if (strpos($lastmod, ' ') !== false) {
            $lastmod = date('Y-m-d', strtotime($lastmod));
        }
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
        $xml .= "    <lastmod>" . htmlspecialchars($lastmod, ENT_XML1) . "</lastmod>\n";
        $xml .= "    <changefreq>weekly</changefreq>\n";
        $xml .= "    <priority>0.6</priority>\n";
        if (!empty($article['image'])) {
            $imgUrl = $siteUrl . '/images/blog/' . $article['image'];
            $xml .= "    <image:image>\n";
            $xml .= "      <image:loc>" . htmlspecialchars($imgUrl, ENT_XML1) . "</image:loc>\n";
            $xml .= "      <image:title>" . htmlspecialchars($article['title'] ?? '', ENT_XML1) . "</image:title>\n";
            $xml .= "    </image:image>\n";
        }
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';
    return $xml;
}
?>

<?php
// ============================================================
// ADVERTISING SYSTEM FUNCTIONS
// ============================================================

/**
 * Получить все данные рекламной системы
 */
function getAdvertising() {
    $data = loadData('advertising');
    if (!is_array($data)) $data = [];
    if (!isset($data['spots'])) $data['spots'] = [];
    if (!isset($data['banners'])) $data['banners'] = [];
    return $data;
}

/**
 * Сохранить данные рекламной системы
 */
function saveAdvertising($data) {
    return saveData('advertising', $data);
}

/**
 * Получить все рекламные места
 */
function getAdSpots() {
    $adv = getAdvertising();
    return $adv['spots'] ?? [];
}

/**
 * Получить рекламное место по ID
 */
function getAdSpotById($id) {
    foreach (getAdSpots() as $spot) {
        if ($spot['id'] === $id) return $spot;
    }
    return null;
}

/**
 * Получить все баннеры
 */
function getAdBanners() {
    $adv = getAdvertising();
    return $adv['banners'] ?? [];
}

/**
 * Получить активные баннеры для конкретного рекламного места
 */
function getActiveBannersForSpot($spotId) {
    $banners = getAdBanners();
    $now = time();
    return array_values(array_filter($banners, function($b) use ($spotId, $now) {
        if ($b['spot_id'] !== $spotId) return false;
        // Проверяем флаг active (в JSON он active, а не status)
        if (empty($b['active'])) return false;
        // Проверка дат активности (в JSON поля date_start и date_end)
        if (!empty($b['date_start']) && strtotime($b['date_start']) > $now) return false;
        if (!empty($b['date_end']) && strtotime($b['date_end']) < $now) return false;
        return true;
    }));
}

/**
 * Отрендерить рекламный блок для конкретного места
 * Возвращает HTML баннера или заглушки
 */
function renderAdSpot($spotId) {
    $spot = getAdSpotById($spotId);
    if (!$spot) return '';
    
    // Если место отключено - ничего не показываем
    if (empty($spot['enabled'])) return '';
    
    $activeBanners = getActiveBannersForSpot($spotId);
    $width = $spot['width'] ?? 728;
    $height = $spot['height'] ?? 90;
    
    // Если нет активных баннеров - показываем заглушку
    if (empty($activeBanners)) {
        return renderAdPlaceholder($spot);
    }
    
    // Ротация: выбираем баннер на основе сессии/рандома
    $bannerIndex = 0;
    $sessionKey = 'ad_rotation_' . $spotId;
    if (!isset($_SESSION)) @session_start();
    if (isset($_SESSION[$sessionKey])) {
        $bannerIndex = ($_SESSION[$sessionKey] + 1) % count($activeBanners);
    } else {
        $bannerIndex = rand(0, count($activeBanners) - 1);
    }
    $_SESSION[$sessionKey] = $bannerIndex;
    
    $banner = $activeBanners[$bannerIndex];
    return renderAdBanner($banner, $spot);
}

/**
 * Отрендерить баннер
 */
function renderAdBanner($banner, $spot) {
    $width = $spot['width'] ?? 728;
    $height = $spot['height'] ?? 90;
    // В JSON поле называется url, а не link_url
    $link = htmlspecialchars($banner['url'] ?? '#');
    $title = htmlspecialchars($banner['title'] ?? 'Реклама');
    // Все ссылки открываются в новых вкладках по требованию пользователя
    $target = ' target="_blank" rel="noopener"';
    
    $html = '<div class="ad-spot ad-spot--active" data-spot="' . htmlspecialchars($spot['id']) . '" style="max-width:' . $width . 'px;margin:0 auto;">';
    $html .= '<div class="ad-label">Реклама</div>';
    $html .= '<a href="' . $link . '"' . $target . ' class="ad-banner-link" title="' . $title . '">';
    
    if (!empty($banner['image_url'])) {
        $imgSrc = htmlspecialchars($banner['image_url']);
        $html .= '<img src="' . $imgSrc . '" alt="' . $title . '" width="' . $width . '" height="' . $height . '" loading="lazy" style="display:block;width:100%;height:auto;border-radius:8px;">';
    } else {
        // Текстовый баннер
        $html .= '<div class="ad-text-banner" style="width:100%;height:' . $height . 'px;">';
        $html .= '<div class="ad-text-banner-inner">';
        $html .= '<div class="ad-text-title">' . $title . '</div>';
        if (!empty($banner['description'])) {
            $html .= '<div class="ad-text-desc">' . htmlspecialchars($banner['description']) . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</a>';
    $html .= '</div>';
    return $html;
}

/**
 * Отрендерить заглушку рекламного места
 */
function renderAdPlaceholder($spot) {
    $width = $spot['width'] ?? 728;
    $height = $spot['height'] ?? 90;
    $name = htmlspecialchars($spot['name'] ?? 'Рекламное место');
    $size = htmlspecialchars($spot['size'] ?? ($width . 'x' . $height));
    $price = isset($spot['price_month']) ? number_format($spot['price_month'], 0, '.', ' ') . ' ₽/мес.' : '';
    
    $html = '<div class="ad-spot ad-spot--placeholder" data-spot="' . htmlspecialchars($spot['id']) . '" style="max-width:' . $width . 'px;margin:0 auto;">';
    $html .= '<div class="ad-placeholder" style="height:' . max(60, $height) . 'px;">';
    $html .= '<div class="ad-placeholder-inner">';
    $html .= '<i class="fa-solid fa-rectangle-ad"></i>';
    $html .= '<div class="ad-placeholder-text">';
    $html .= '<span class="ad-placeholder-name">' . $name . '</span>';
    $html .= '<span class="ad-placeholder-size">' . $size . ($price ? ' · ' . $price : '') . '</span>';
    $html .= '</div>';
    $html .= '<a href="' . appUrl('/advertising/') . '" class="ad-placeholder-btn">Разместить рекламу</a>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

/**
 * Алиасы для API-роутера
 */
function getAdvertisingData() {
    return getAdvertising();
}

function saveAdvertisingData($data) {
    return saveAdvertising($data);
}
