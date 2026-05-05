<?php
require_once __DIR__ . '/../includes/functions.php';

$settings = getSettings();
$currentPage = 'advertising';
$adSpots = getAdSpots();

$siteUrl = getCurrentSiteUrl();
$siteName = $settings['site']['name'] ?? 'Магазин аккаунтов';

$metaTags = metaTags(
    'Реклама на сайте | ' . $siteName,
    'Разместите рекламу на ' . $siteName . '. Выгодные условия, широкая аудитория покупателей аккаунтов. Баннеры, ротация, гибкие тарифы.',
    'реклама на сайте, разместить рекламу, баннерная реклама, купить рекламу',
    '/advertising/',
    '/images/ui/favicon.svg'
);

$schemaOrg = '';

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container">
        <?php echo breadcrumbs([['name' => 'Главная', 'url' => '/'], ['name' => 'Реклама на сайте']]); ?>
        <h1><i class="fa-solid fa-rectangle-ad" style="color:var(--primary);margin-right:12px;"></i>Реклама на сайте</h1>
        <p>Разместите рекламу на <?php echo htmlspecialchars($siteName); ?> и привлеките целевую аудиторию покупателей аккаунтов</p>
    </div>
</div>

<section class="section">
    <div class="container">

        <!-- Преимущества -->
        <div class="ad-page-advantages">
            <div class="ad-advantage-card animate-on-scroll">
                <div class="ad-advantage-icon"><i class="fa-solid fa-users"></i></div>
                <div class="ad-advantage-title">Целевая аудитория</div>
                <div class="ad-advantage-desc">Покупатели аккаунтов соцсетей - активные пользователи интернета, маркетологи и арбитражники</div>
            </div>
            <div class="ad-advantage-card animate-on-scroll delay-1">
                <div class="ad-advantage-icon"><i class="fa-solid fa-rotate"></i></div>
                <div class="ad-advantage-title">Ротация баннеров</div>
                <div class="ad-advantage-desc">На одно место можно разместить до 10 баннеров с равномерной ротацией при каждой загрузке страницы</div>
            </div>
            <div class="ad-advantage-card animate-on-scroll delay-2">
                <div class="ad-advantage-icon"><i class="fa-solid fa-bolt"></i></div>
                <div class="ad-advantage-title">Быстрый старт</div>
                <div class="ad-advantage-desc">Размещение в течение 24 часов после оплаты. Никаких сложных согласований</div>
            </div>
            <div class="ad-advantage-card animate-on-scroll delay-3">
                <div class="ad-advantage-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="ad-advantage-title">Гибкие тарифы</div>
                <div class="ad-advantage-desc">Оплата за неделю или месяц. Скидки при долгосрочном размещении</div>
            </div>
        </div>

        <!-- Схема расположения баннеров -->
        <div class="ad-layout-map animate-on-scroll">
            <h2 class="section-title" style="text-align:left;margin-bottom:8px;">Карта расположения рекламных мест</h2>
            <p style="color:var(--text-secondary);margin-bottom:28px;">Визуальная схема размещения баннеров в реальных рабочих зонах сайта: внутри контента, на карточке товара и на checkout.</p>

            <div class="ad-site-mockup">
                <!-- Header -->
                <div class="ad-mockup-header">
                    <div class="ad-mockup-logo"></div>
                    <div class="ad-mockup-nav"></div>
                </div>
                <!-- Header Banner -->
                <div class="ad-mockup-spot ad-mockup-spot--full" data-spot="header_banner">
                    <i class="fa-solid fa-rectangle-ad"></i>
                    <span>Header Banner · 970×90</span>
                    <span class="ad-mockup-badge">Все страницы</span>
                </div>
                <!-- Content area -->
                <div class="ad-mockup-content">
                    <div class="ad-mockup-main" style="width:100%;">
                        <div class="ad-mockup-content-block"></div>
                        <div class="ad-mockup-spot" data-spot="content_middle">
                            <i class="fa-solid fa-rectangle-ad"></i>
                            <span>Content Middle · 728×90</span>
                            <span class="ad-mockup-badge">Главная</span>
                        </div>
                        <div style="display:grid;gap:12px;margin:12px 0;">
                            <div class="ad-mockup-spot" data-spot="home_showcase_banner">
                                <i class="fa-solid fa-rectangle-ad"></i>
                                <span>Home Showcase · 728×90</span>
                                <span class="ad-mockup-badge">Главная, после первого экрана</span>
                            </div>
                            <div class="ad-mockup-spot" data-spot="blog_feed_banner">
                                <i class="fa-solid fa-rectangle-ad"></i>
                                <span>Blog Feed · 728×90</span>
                                <span class="ad-mockup-badge">Блог, между статьями и пагинацией</span>
                            </div>
                            <div class="ad-mockup-spot" data-spot="faq_help_banner">
                                <i class="fa-solid fa-rectangle-ad"></i>
                                <span>FAQ Assist · 728×90</span>
                                <span class="ad-mockup-badge">FAQ, перед блоком поддержки</span>
                            </div>
                        </div>
                        <div class="ad-mockup-content-block"></div>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
                            <div class="ad-mockup-spot ad-mockup-spot--square" data-spot="sidebar_top">
                                <i class="fa-solid fa-rectangle-ad"></i>
                                <span>Inline Promo</span>
                                <span>300×250</span>
                                <span class="ad-mockup-badge">Каталог / товар / checkout</span>
                            </div>
                            <div class="ad-mockup-spot ad-mockup-spot--square" data-spot="checkout_banner">
                                <i class="fa-solid fa-rectangle-ad"></i>
                                <span>Checkout Banner</span>
                                <span>300×250</span>
                                <span class="ad-mockup-badge">Оформление заказа</span>
                            </div>
                            <div class="ad-mockup-spot ad-mockup-spot--tall" data-spot="sidebar_bottom">
                                <i class="fa-solid fa-rectangle-ad"></i>
                                <span>Inline Extended</span>
                                <span>300×600</span>
                                <span class="ad-mockup-badge">Каталог / товар</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Pre-Footer Banner -->
                <div class="ad-mockup-spot ad-mockup-spot--full" data-spot="footer_banner">
                    <i class="fa-solid fa-rectangle-ad"></i>
                    <span>Pre-Footer Banner · 970×90</span>
                    <span class="ad-mockup-badge">Все страницы</span>
                </div>
                <!-- Footer -->
                <div class="ad-mockup-footer"></div>
            </div>
        </div>

        <!-- Таблица рекламных мест -->
        <h2 class="section-title animate-on-scroll" style="text-align:left;margin-top:48px;margin-bottom:8px;">Рекламные места и цены</h2>
        <p style="color:var(--text-secondary);margin-bottom:28px;" class="animate-on-scroll delay-1">Выберите подходящее место для размещения вашей рекламы</p>

        <div class="ad-spots-grid">
            <?php foreach ($adSpots as $i => $spot): ?>
            <?php if (!$spot['enabled']) continue; ?>
            <div class="ad-spot-card animate-on-scroll delay-<?php echo min($i, 5); ?>">
                <div class="ad-spot-card-header">
                    <div class="ad-spot-card-icon">
                        <i class="fa-solid fa-rectangle-ad"></i>
                    </div>
                    <div class="ad-spot-card-meta">
                        <div class="ad-spot-card-name"><?php echo htmlspecialchars($spot['name']); ?></div>
                        <div class="ad-spot-card-size"><?php echo htmlspecialchars($spot['size']); ?> px</div>
                    </div>
                    <div class="ad-spot-card-badge">
                        <span class="badge badge-active">Доступно</span>
                    </div>
                </div>
                <p class="ad-spot-card-desc"><?php echo htmlspecialchars($spot['description']); ?></p>
                <div class="ad-spot-card-details">
                    <div class="ad-spot-detail">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?php echo htmlspecialchars($spot['location']); ?></span>
                    </div>
                    <div class="ad-spot-detail">
                        <i class="fa-solid fa-rotate"></i>
                        <span>До <?php echo $spot['max_banners']; ?> баннеров в ротации</span>
                    </div>
                </div>
                <div class="ad-spot-card-prices">
                    <div class="ad-spot-price">
                        <span class="ad-spot-price-period">Неделя</span>
                        <span class="ad-spot-price-value"><?php echo number_format($spot['price_week'], 0, '.', ' '); ?> ₽</span>
                    </div>
                    <div class="ad-spot-price ad-spot-price--featured">
                        <span class="ad-spot-price-period">Месяц</span>
                        <span class="ad-spot-price-value"><?php echo number_format($spot['price_month'], 0, '.', ' '); ?> ₽</span>
                    </div>
                </div>
                <a href="#contact-form" class="btn btn-primary" style="width:100%;margin-top:16px;text-align:center;" onclick="scrollToContactForm()">
                    <i class="fa-solid fa-paper-plane"></i> Заказать размещение
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Сравнительная таблица -->
        <div class="admin-table-wrap animate-on-scroll" style="margin-top:48px;">
            <div class="admin-table-header" style="background:var(--bg-card);padding:20px 24px;">
                <h3 style="font-size:1.1rem;">Сравнение рекламных мест</h3>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:var(--bg);">
                            <th style="padding:12px 16px;text-align:left;font-size:0.75rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border);">Место</th>
                            <th style="padding:12px 16px;text-align:left;font-size:0.75rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border);">Размер</th>
                            <th style="padding:12px 16px;text-align:left;font-size:0.75rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border);">Расположение</th>
                            <th style="padding:12px 16px;text-align:left;font-size:0.75rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border);">Ротация</th>
                            <th style="padding:12px 16px;text-align:right;font-size:0.75rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border);">Неделя</th>
                            <th style="padding:12px 16px;text-align:right;font-size:0.75rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border);">Месяц</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adSpots as $spot): ?>
                        <tr style="<?php echo !$spot['enabled'] ? 'opacity:0.5;' : ''; ?>">
                            <td style="padding:14px 16px;border-bottom:1px solid var(--border);">
                                <div style="font-weight:600;"><?php echo htmlspecialchars($spot['name']); ?></div>
                                <?php if (!$spot['enabled']): ?>
                                <span class="badge badge-inactive" style="margin-top:4px;">Отключено</span>
                                <?php else: ?>
                                <span class="badge badge-active" style="margin-top:4px;">Доступно</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:14px 16px;border-bottom:1px solid var(--border);font-family:'JetBrains Mono',monospace;color:var(--primary);"><?php echo htmlspecialchars($spot['size']); ?></td>
                            <td style="padding:14px 16px;border-bottom:1px solid var(--border);color:var(--text-secondary);font-size:0.875rem;"><?php echo htmlspecialchars($spot['location']); ?></td>
                            <td style="padding:14px 16px;border-bottom:1px solid var(--border);color:var(--text-secondary);">до <?php echo $spot['max_banners']; ?> баннеров</td>
                            <td style="padding:14px 16px;border-bottom:1px solid var(--border);text-align:right;font-weight:600;"><?php echo number_format($spot['price_week'], 0, '.', ' '); ?> ₽</td>
                            <td style="padding:14px 16px;border-bottom:1px solid var(--border);text-align:right;font-weight:700;color:var(--primary);"><?php echo number_format($spot['price_month'], 0, '.', ' '); ?> ₽</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Форма заявки -->
        <div class="ad-contact-section animate-on-scroll" id="contact-form" style="margin-top:48px;">
            <div class="ad-contact-card">
                <div class="ad-contact-header">
                    <i class="fa-solid fa-paper-plane"></i>
                    <h2>Оставить заявку на размещение</h2>
                    <p>Напишите нам в Telegram или на email - ответим в течение нескольких часов</p>
                </div>
                <div class="ad-contact-methods">
                    <a href="<?php echo htmlspecialchars($settings['contacts']['telegram_url'] ?? '#'); ?>" target="_blank" rel="noopener" class="ad-contact-method">
                        <i class="fa-brands fa-telegram"></i>
                        <div>
                            <div class="ad-contact-method-title">Telegram</div>
                            <div class="ad-contact-method-value"><?php echo htmlspecialchars($settings['contacts']['telegram'] ?? '@support'); ?></div>
                        </div>
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($settings['contacts']['email'] ?? 'support@example.com'); ?>" class="ad-contact-method">
                        <i class="fa-solid fa-envelope"></i>
                        <div>
                            <div class="ad-contact-method-title">Email</div>
                            <div class="ad-contact-method-value"><?php echo htmlspecialchars($settings['contacts']['email'] ?? 'support@example.com'); ?></div>
                        </div>
                    </a>
                </div>
                <div class="ad-contact-info">
                    <h4>Что указать в заявке:</h4>
                    <ul>
                        <li><i class="fa-solid fa-check"></i> Выбранное рекламное место и период размещения</li>
                        <li><i class="fa-solid fa-check"></i> Ссылку на сайт или продукт для рекламы</li>
                        <li><i class="fa-solid fa-check"></i> Готовый баннер в нужном размере (или мы поможем с созданием)</li>
                        <li><i class="fa-solid fa-check"></i> Желаемые даты начала и окончания</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- FAQ по рекламе -->
        <div class="ad-faq animate-on-scroll" style="margin-top:48px;">
            <h2 class="section-title" style="text-align:left;margin-bottom:24px;">Частые вопросы</h2>
            <div class="ad-faq-list">
                <div class="ad-faq-item">
                    <div class="ad-faq-q"><i class="fa-solid fa-circle-question"></i> Как работает ротация баннеров?</div>
                    <div class="ad-faq-a">На одно рекламное место можно разместить несколько баннеров (до 10). При каждой загрузке страницы система случайным образом выбирает один из активных баннеров. Это позволяет нескольким рекламодателям делить одно место и снижает стоимость размещения.</div>
                </div>
                <div class="ad-faq-item">
                    <div class="ad-faq-q"><i class="fa-solid fa-circle-question"></i> Какой формат баннеров принимается?</div>
                    <div class="ad-faq-a">Принимаются изображения в форматах JPG, PNG, GIF, WebP. Также поддерживаются текстовые баннеры без изображения. Размеры указаны для каждого места. Анимированные GIF разрешены.</div>
                </div>
                <div class="ad-faq-item">
                    <div class="ad-faq-q"><i class="fa-solid fa-circle-question"></i> Когда начнётся показ рекламы?</div>
                    <div class="ad-faq-a">После согласования и оплаты баннер будет размещён в течение 24 часов. Обычно - значительно быстрее.</div>
                </div>
                <div class="ad-faq-item">
                    <div class="ad-faq-q"><i class="fa-solid fa-circle-question"></i> Есть ли скидки при долгосрочном размещении?</div>
                    <div class="ad-faq-a">Да, при размещении на 3 месяца и более предоставляется скидка 15%, на 6 месяцев - 25%. Уточняйте актуальные условия при оформлении заявки.</div>
                </div>
            </div>
        </div>

    </div>
</section>

<style>
/* ===== ADVERTISING PAGE STYLES ===== */
.ad-page-advantages {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 48px;
}
@media (max-width: 900px) { .ad-page-advantages { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .ad-page-advantages { grid-template-columns: 1fr; } }

.ad-advantage-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
}
.ad-advantage-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.2); }
.ad-advantage-icon {
    width: 56px; height: 56px;
    background: rgba(79,70,229,0.15);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: var(--primary);
    margin: 0 auto 16px;
}
.ad-advantage-title { font-size: 1rem; font-weight: 700; margin-bottom: 8px; }
.ad-advantage-desc { font-size: 0.875rem; color: var(--text-secondary); line-height: 1.5; }

/* Site Mockup */
.ad-layout-map { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; }
.ad-site-mockup { border: 2px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--bg); }
.ad-mockup-header {
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
    padding: 12px 16px;
    display: flex; align-items: center; gap: 12px;
}
.ad-mockup-logo { width: 80px; height: 20px; background: var(--bg-hover); border-radius: 4px; }
.ad-mockup-nav { flex: 1; height: 16px; background: var(--bg-hover); border-radius: 4px; max-width: 300px; }
.ad-mockup-spot {
    background: rgba(79,70,229,0.08);
    border: 2px dashed rgba(79,70,229,0.4);
    border-radius: 8px;
    margin: 8px;
    padding: 14px 20px;
    display: flex; align-items: center; gap: 10px;
    font-size: 0.85rem; font-weight: 600; color: var(--primary);
    transition: background 0.2s;
}
.ad-mockup-spot:hover { background: rgba(79,70,229,0.15); }
.ad-mockup-spot--full { margin: 8px; }
.ad-mockup-spot--square { height: 100px; }
.ad-mockup-spot--tall { height: 160px; flex: 1; }
.ad-mockup-badge {
    margin-left: auto;
    background: rgba(79,70,229,0.2);
    color: var(--primary);
    font-size: 0.7rem;
    padding: 3px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.ad-mockup-content { display: flex; gap: 0; }
.ad-mockup-main { flex: 1; padding: 8px; display: flex; flex-direction: column; gap: 8px; }
.ad-mockup-sidebar { width: 200px; padding: 8px; display: flex; flex-direction: column; gap: 8px; border-left: 1px solid var(--border); }
.ad-mockup-content-block { height: 60px; background: var(--bg-hover); border-radius: 8px; opacity: 0.5; }
.ad-mockup-footer { height: 40px; background: var(--bg-card); border-top: 1px solid var(--border); }

/* Spots Grid */
.ad-spots-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}
@media (max-width: 1000px) { .ad-spots-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .ad-spots-grid { grid-template-columns: 1fr; } }

.ad-spot-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}
.ad-spot-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.2); border-color: var(--primary); }
.ad-spot-card-header { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px; }
.ad-spot-card-icon {
    width: 44px; height: 44px;
    background: rgba(79,70,229,0.15);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; color: var(--primary);
    flex-shrink: 0;
}
.ad-spot-card-meta { flex: 1; }
.ad-spot-card-name { font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; }
.ad-spot-card-size { font-size: 0.8rem; color: var(--primary); font-family: 'JetBrains Mono', monospace; }
.ad-spot-card-desc { font-size: 0.875rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 16px; }
.ad-spot-card-details { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.ad-spot-detail { display: flex; align-items: flex-start; gap: 8px; font-size: 0.8rem; color: var(--text-secondary); }
.ad-spot-detail i { color: var(--primary); margin-top: 2px; flex-shrink: 0; }
.ad-spot-card-prices { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ad-spot-price {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px;
    text-align: center;
}
.ad-spot-price--featured { border-color: var(--primary); background: rgba(79,70,229,0.08); }
.ad-spot-price-period { display: block; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 4px; }
.ad-spot-price-value { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.ad-spot-price--featured .ad-spot-price-value { color: var(--primary); }

/* Contact Section */
.ad-contact-section { }
.ad-contact-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
}
.ad-contact-header { margin-bottom: 32px; }
.ad-contact-header i { font-size: 2.5rem; color: var(--primary); margin-bottom: 16px; display: block; }
.ad-contact-header h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; }
.ad-contact-header p { color: var(--text-secondary); }
.ad-contact-methods { display: flex; justify-content: center; gap: 20px; margin-bottom: 32px; flex-wrap: wrap; }
.ad-contact-method {
    display: flex; align-items: center; gap: 14px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px 24px;
    transition: all 0.2s;
    min-width: 220px;
}
.ad-contact-method:hover { border-color: var(--primary); background: rgba(79,70,229,0.08); }
.ad-contact-method i { font-size: 1.8rem; color: var(--primary); }
.ad-contact-method-title { font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; }
.ad-contact-method-value { font-size: 1rem; font-weight: 600; }
.ad-contact-info { text-align: left; max-width: 500px; margin: 0 auto; }
.ad-contact-info h4 { font-size: 0.95rem; font-weight: 700; margin-bottom: 12px; }
.ad-contact-info ul { list-style: none; display: flex; flex-direction: column; gap: 8px; }
.ad-contact-info li { display: flex; align-items: flex-start; gap: 10px; font-size: 0.875rem; color: var(--text-secondary); }
.ad-contact-info li i { color: var(--secondary); margin-top: 2px; flex-shrink: 0; }

/* FAQ */
.ad-faq-list { display: flex; flex-direction: column; gap: 12px; }
.ad-faq-item {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}
.ad-faq-q {
    padding: 16px 20px;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex; align-items: center; gap: 10px;
    cursor: pointer;
    transition: background 0.2s;
}
.ad-faq-q:hover { background: var(--bg-hover); }
.ad-faq-q i { color: var(--primary); }
.ad-faq-a {
    padding: 0 20px 16px 44px;
    font-size: 0.875rem;
    color: var(--text-secondary);
    line-height: 1.6;
}
</style>

<script>
function scrollToContactForm() {
    document.getElementById('contact-form').scrollIntoView({behavior: 'smooth', block: 'center'});
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
