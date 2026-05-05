<?php
$settings = getSettings();
$siteName = $settings['site']['name'] ?? 'Магазин аккаунтов';
$colors = $settings['colors'] ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Language" content="ru">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="<?php echo htmlspecialchars($colors['bg_dark'] ?? '#0F172A'); ?>">
    <link rel="apple-touch-icon" href="/images/ui/favicon.svg">
    <?php echo $metaTags ?? ''; ?>
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <!-- Critical CSS first -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/responsive.css">
    <link rel="stylesheet" href="/css/themes.css">
    <!-- Non-critical CSS loaded async -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
    <link rel="icon" type="image/svg+xml" href="/images/ui/favicon.svg">
    <!-- Fallback для иконок до загрузки FA -->
    <style>
        /* Critical: fallback для FA иконок */
        .fa-solid, .fa-brands, .fa-regular { font-family: 'Font Awesome 6 Free', sans-serif; }
        /* Prevent layout shift */
        body { font-family: Inter, system-ui, -apple-system, sans-serif; }
    </style>
    <style>
        :root {
            --primary: <?php echo $colors['primary'] ?? '#4F46E5'; ?>;
            --primary-hover: <?php echo $colors['primary_hover'] ?? '#4338CA'; ?>;
            --secondary: <?php echo $colors['secondary'] ?? '#10B981'; ?>;
            --danger: <?php echo $colors['danger'] ?? '#EF4444'; ?>;
            --warning: <?php echo $colors['warning'] ?? '#F59E0B'; ?>;
            --bg-dark: <?php echo $colors['bg_dark'] ?? '#0F172A'; ?>;
            --bg-card: <?php echo $colors['bg_card'] ?? '#1E293B'; ?>;
            --bg-hover: <?php echo $colors['bg_hover'] ?? '#334155'; ?>;
            --text-primary: <?php echo $colors['text_primary'] ?? '#F8FAFC'; ?>;
            --text-secondary: <?php echo $colors['text_secondary'] ?? '#94A3B8'; ?>;
            --text-muted: <?php echo $colors['text_muted'] ?? '#64748B'; ?>;
            --border: <?php echo $colors['border'] ?? '#334155'; ?>;
            --border-radius: <?php echo ($colors['border_radius'] ?? '12') . 'px'; ?>;
            --gradient-primary: linear-gradient(135deg, <?php echo $colors['primary'] ?? '#4F46E5'; ?> 0%, #7C3AED 100%);
            --gradient-success: linear-gradient(135deg, <?php echo $colors['secondary'] ?? '#10B981'; ?> 0%, #059669 100%);
        }
    </style>
    <script>
        window.APP_BASE_PATH = <?php echo json_encode(rtrim(getBasePath(), '/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || '';
    </script>
    <?php echo $schemaOrg ?? ''; ?>
    <!-- Analytics (Google Tag Manager, GA4, Yandex.Metrika) -->
    <?php echo getAnalyticsHeadCode(); ?>
    <!-- Предотвращение FOUC: тема задаётся сервером, не меняется пользователем -->
    <script>
        (function(){
            // Тема управляется только админом через админпанель.
            // Для публичной части localStorage игнорируется.
            // Тема уже задана через class body сервером.
            // Удаляем любой каш темы из localStorage, чтобы не перезаписывать серверную тему.
            try { localStorage.removeItem('shillcms_template'); } catch(e) {}
        })();
    </script>
</head>
<?php
$currentTheme = $settings['site']['template'] ?? 'dark-pro';
// Совместимость со старым форматом
if ($currentTheme === 'dark') $currentTheme = 'dark-pro';
if ($currentTheme === 'light') $currentTheme = 'light-clean';
?>
<body class="theme-<?php echo htmlspecialchars($currentTheme); ?>">
<?php echo getAnalyticsBodyCode(); ?>

<!-- Sticky Header -->
<header class="site-header" id="siteHeader">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="top-bar-inner">
                <!-- Logo -->
                <a href="/" class="logo" aria-label="<?php echo htmlspecialchars($siteName); ?> - Главная">
                    <span class="logo-icon">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="32" height="32" rx="8" fill="url(#logoGrad)"/>
                            <path d="M8 22L13 10L16 17L19 13L24 22H8Z" fill="white" opacity="0.9"/>
                            <defs>
                                <linearGradient id="logoGrad" x1="0" y1="0" x2="32" y2="32">
                                    <stop offset="0%" stop-color="#4F46E5"/>
                                    <stop offset="100%" stop-color="#7C3AED"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </span>
                    <span class="logo-text"><?php echo $siteName; ?></span>
                </a>

                <!-- Ask Question -->
                <a href="#contact-modal" class="ask-question-btn" id="openContactModal">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>Задать вопрос</span>
                </a>

                <!-- Cart (Hidden as per request) -->
	                <button class="cart-btn" id="cartBtn" aria-label="Корзина" style="display:none;">
	                    <i class="fa-solid fa-cart-shopping"></i>
	                    <span class="cart-count" id="cartCount">0</span>
	                </button>
            </div>
        </div>
    </div>

    <!-- Main Navigation -->
    <nav class="main-nav" role="navigation" aria-label="Основная навигация">
        <div class="container">
            <div class="nav-inner">
                <ul class="nav-list">
                    <li><a href="/#categories" class="nav-link <?php echo ($currentPage ?? '') === 'home' ? 'active' : ''; ?>"><i class="fa-solid fa-store"></i> Магазин</a></li>
                    <li><a href="/info/" class="nav-link <?php echo ($currentPage ?? '') === 'info' ? 'active' : ''; ?>"><i class="fa-solid fa-newspaper"></i> Блог</a></li>
                    <li><a href="/faq/" class="nav-link <?php echo ($currentPage ?? '') === 'faq' ? 'active' : ''; ?>"><i class="fa-solid fa-circle-question"></i> FAQ</a></li>
                    <li><a href="/rules/" class="nav-link <?php echo ($currentPage ?? '') === 'rules' ? 'active' : ''; ?>"><i class="fa-solid fa-file-lines"></i> Правила</a></li>
                    <li><a href="/advertising/" class="nav-link <?php echo ($currentPage ?? '') === 'advertising' ? 'active' : ''; ?>"><i class="fa-solid fa-rectangle-ad"></i> Реклама на сайте</a></li>
                </ul>

                <!-- Search -->
                <div class="search-wrapper">
                    <form class="search-form" id="searchForm" action="/" method="GET" role="search">
                        <input type="search" name="q" id="searchInput" class="search-input" placeholder="Поиск аккаунтов..." value="<?php echo sanitize($_GET['q'] ?? ''); ?>" aria-label="Поиск" autocomplete="off">
                        <button type="submit" class="search-btn" aria-label="Найти">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </form>
                </div>

                <!-- Mobile menu toggle -->
                <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Меню">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>
</header>

<!-- Cart Sidebar -->
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-sidebar-overlay" id="cartSidebarOverlay"></div>
    <div class="cart-sidebar-panel">
        <div class="cart-header">
            <h3>Корзина</h3>
            <button class="cart-close" id="cartClose"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="cart-items" id="cartItems">
            <div class="cart-empty">
                <i class="fa-solid fa-cart-shopping"></i>
                <p>Корзина пуста</p>
            </div>
        </div>
        <div class="cart-footer" id="cartFooter" style="display:none;">
            <div class="cart-total">
                <span>Итого:</span>
                <strong id="cartTotal">0 ₽</strong>
            </div>
            <button type="button" class="btn btn-primary btn-full" id="cartCheckoutBtn">
                <i class="fa-solid fa-credit-card"></i> Оформить заказ
            </button>
            <button class="btn btn-secondary btn-full" id="clearCartBtn" onclick="clearCart()" style="margin-top:8px;">
                <i class="fa-solid fa-trash"></i> Очистить корзину
            </button>
        </div>
    </div>
</div>

<main class="main-content">

<!-- Header Ad Banner -->
<?php
$_adHeaderHtml = renderAdSpot('header_banner');
if (!empty($_adHeaderHtml)):
?>
<div class="ad-section ad-section--header">
    <div class="container">
        <?php echo $_adHeaderHtml; ?>
    </div>
</div>
<?php endif; ?>
