<?php $settings = getSettings(); ?>
</main>

<!-- Pre-Footer Ad Banner -->
<?php
$_adFooterHtml = renderAdSpot('footer_banner');
if (!empty($_adFooterHtml)):
?>
<div class="ad-section ad-section--footer">
    <div class="container">
        <?php echo $_adFooterHtml; ?>
    </div>
</div>
<?php endif; ?>

<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand -->
            <div class="footer-brand">
                <a href="/" class="logo footer-logo">
                    <span class="logo-icon">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="32" height="32" rx="8" fill="url(#footerLogoGrad)"/>
                            <path d="M8 22L13 10L16 17L19 13L24 22H8Z" fill="white" opacity="0.9"/>
                            <defs>
                                <linearGradient id="footerLogoGrad" x1="0" y1="0" x2="32" y2="32">
                                    <stop offset="0%" stop-color="#4F46E5"/>
                                    <stop offset="100%" stop-color="#7C3AED"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </span>
                    <span class="logo-text"><?php echo $settings['site']['name'] ?? 'Магазин аккаунтов'; ?></span>
                </a>
                <p class="footer-tagline"><?php echo $settings['site']['tagline'] ?? 'Магазин аккаунтов соцсетей'; ?></p>
                <div class="footer-social">
                    <a href="<?php echo $settings['contacts']['telegram_url'] ?? '#'; ?>" target="_blank" rel="noopener" aria-label="Telegram">
                        <i class="fa-brands fa-telegram"></i>
                    </a>
                </div>
            </div>

            <!-- Navigation -->
            <div class="footer-nav">
                <h4>Навигация</h4>
                <ul>
                    <li><a href="/">Магазин</a></li>
                    <li><a href="/info/">Полезная информация</a></li>
                    <li><a href="/rules/">Правила</a></li>
                    <li><a href="/faq/">FAQ</a></li>
                    <li><a href="/advertising/">Реклама на сайте</a></li>
                </ul>
            </div>

            <!-- Categories -->
            <div class="footer-nav">
                <h4>Популярные категории</h4>
                <ul>
                    <?php
                    $cats = getCategories();
                    foreach (array_slice($cats, 0, 6) as $cat):
                    ?>
                    <li><a href="/category/<?php echo $cat['slug']; ?>/"><?php echo $cat['name']; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Contacts -->
            <div class="footer-contacts">
                <h4>Контакты</h4>
                <ul>
                    <li>
                        <i class="fa-solid fa-envelope"></i>
                        <a href="mailto:<?php echo $settings['contacts']['email'] ?? 'support@example.com'; ?>"><?php echo $settings['contacts']['email'] ?? 'support@example.com'; ?></a>
                    </li>
                    <li>
                        <i class="fa-brands fa-telegram"></i>
                        <a href="<?php echo $settings['contacts']['telegram_url'] ?? '#'; ?>" target="_blank" rel="noopener"><?php echo $settings['contacts']['telegram'] ?? '@support'; ?></a>
                    </li>
                </ul>
                <div class="footer-guarantee">
                    <div class="guarantee-badge">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Гарантия 72 часа</span>
                    </div>
                    <div class="guarantee-badge">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Выдача 24/7</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $settings['site']['name'] ?? 'Магазин аккаунтов'; ?>. Все права защищены.</p>
            <div class="footer-bottom-links">
                <a href="/rules/">Правила</a>
                <a href="/faq/">FAQ</a>
                <a href="/sitemap.xml">Sitemap</a>
                <a href="/advertising/">Реклама</a>
            </div>
        </div>
    </div>
</footer>

<!-- Переключатель шаблонов доступен только администратору -->

<div class="cookie-consent" id="cookieConsent" hidden>
    <div class="cookie-consent__text">Мы используем файлы coockies для сбора обезличенной аналитики.</div>
    <button type="button" class="btn btn-primary cookie-consent__btn" id="cookieConsentAccept">Понятно</button>
</div>

<style>
.cookie-consent {
    position: fixed;
    left: 16px;
    right: 16px;
    bottom: 16px;
    z-index: 1200;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border-radius: 16px;
    border: 1px solid var(--border);
    background: rgba(15, 23, 42, 0.96);
    color: #fff;
    box-shadow: 0 20px 50px rgba(2, 6, 23, 0.35);
    backdrop-filter: blur(14px);
}
.cookie-consent[hidden] {
    display: none !important;
}
.cookie-consent__text {
    font-size: 0.95rem;
    line-height: 1.5;
    max-width: 820px;
}
.cookie-consent__btn {
    flex-shrink: 0;
    white-space: nowrap;
}
@media (max-width: 767px) {
    .cookie-consent {
        left: 12px;
        right: 12px;
        bottom: 12px;
        padding: 14px;
        border-radius: 14px;
        flex-direction: column;
        align-items: stretch;
    }
    .cookie-consent__btn {
        width: 100%;
    }
}
</style>

<script>
(function() {
    const storageKey = 'shillcms_cookie_notice_seen';
    const onReady = function() {
        const banner = document.getElementById('cookieConsent');
        const acceptBtn = document.getElementById('cookieConsentAccept');
        if (!banner || !acceptBtn) return;

        let alreadyAccepted = false;
        try {
            alreadyAccepted = localStorage.getItem(storageKey) === '1';
        } catch (e) {
            alreadyAccepted = false;
        }

        if (!alreadyAccepted) {
            banner.hidden = false;
        }

        acceptBtn.addEventListener('click', function() {
            try {
                localStorage.setItem(storageKey, '1');
            } catch (e) {}
            banner.hidden = true;
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady, { once: true });
    } else {
        onReady();
    }
})();
</script>

<!-- Scripts (дефер для быстрой загрузки) -->
<script src="<?php echo appUrl('/js/main.js'); ?>" defer></script>
<script src="<?php echo appUrl('/js/products.js'); ?>" defer></script>

<?php if (isAdminForEditor()): ?>
<!-- Visual Inline Editor (only for authorized admin) -->
<script>
    window.INLINE_EDITOR_ENABLED = true;
    window.APP_BASE_PATH = '<?php echo rtrim(getBasePath(), '/'); ?>';
</script>
<script src="<?php echo appUrl('/js/inline-editor.js'); ?>" defer></script>
<?php endif; ?>

<!-- Scroll to top -->
<button class="scroll-top" id="scrollTop" aria-label="Наверх">
    <i class="fa-solid fa-chevron-up"></i>
</button>

</body>
</html>
