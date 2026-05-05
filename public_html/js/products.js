/* ============================================
   Account Store CMS — Products JS
   ============================================ */

'use strict';

// Re-init after dynamic content loads
document.addEventListener('DOMContentLoaded', function() {
    // Ожидаем загрузку скелетона (600ms) + небольшой запас
    setTimeout(() => {
        if (typeof init3DTiltCards === 'function') init3DTiltCards();
        if (typeof initScrollAnimations === 'function') initScrollAnimations();
        if (typeof initRippleEffect === 'function') initRippleEffect();
    }, 700);

    // Lazy load изображений через IntersectionObserver
    if ('IntersectionObserver' in window) {
        const imgObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    obs.unobserve(img);
                }
            });
        }, { rootMargin: '200px' });

        document.querySelectorAll('img[data-src]').forEach(img => imgObserver.observe(img));
    }
});
