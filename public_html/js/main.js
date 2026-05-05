/* ============================================
   Account Store CMS — Main JavaScript
   ============================================ */

'use strict';

const APP_BASE_PATH = (window.APP_BASE_PATH || '').replace(/\/$/, '');

function appUrl(path = '/') {
    if (!path) {
        return APP_BASE_PATH || '/';
    }

    if (/^https?:\/\//i.test(path)) {
        return path;
    }

    const normalizedPath = path.startsWith('/') ? path : `/${path}`;
    return `${APP_BASE_PATH}${normalizedPath}` || normalizedPath;
}

// ---- DOM Ready ----
document.addEventListener('DOMContentLoaded', function() {
    initHeader();
    initScrollAnimations();
    initCounters();
    initParticles();
    initFaq();
    initScrollTop();
    initMobileMenu();
    initSearch();
    initCart();
    initProductModal();
    initContactModal();
    initRippleEffect();
    init3DTiltCards();
    // initThemeToggle() — кнопка смены темы удалена из публичной части
    initParallax();
    // Show products grid after brief delay (simulating load)
    setTimeout(() => {
        const skeleton = document.getElementById('productsSkeletonGrid');
        const grid = document.getElementById('productsGrid');
        if (skeleton && grid) {
            skeleton.style.display = 'none';
            grid.classList.remove('hidden');
        }
    }, 600);
});

/* ============================================
   HEADER — Sticky + Blur on Scroll
   ============================================ */
function initHeader() {
    const header = document.querySelector('.site-header');
    if (!header) return;
    let lastScrollY = 0;
    window.addEventListener('scroll', () => {
        const scrollY = window.scrollY;
        if (scrollY > 60) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        lastScrollY = scrollY;
    }, { passive: true });
}

/* ============================================
   MOBILE MENU
   ============================================ */
function initMobileMenu() {
    const toggle = document.getElementById('mobileMenuToggle');
    const navList = document.querySelector('.nav-list');
    if (!toggle || !navList) return;
    toggle.addEventListener('click', () => {
        toggle.classList.toggle('active');
        navList.classList.toggle('mobile-open');
    });
    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!toggle.contains(e.target) && !navList.contains(e.target)) {
            toggle.classList.remove('active');
            navList.classList.remove('mobile-open');
        }
    });
}

/* ============================================
   SCROLL ANIMATIONS (Intersection Observer)
   ============================================ */
let scrollObserver = null;

function initScrollAnimations() {
    const elements = document.querySelectorAll('.animate-on-scroll');
    if (!elements.length) return;
    scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                scrollObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    elements.forEach(el => scrollObserver.observe(el));
}

// Observe newly added elements (for lazy-loaded products)
function observeNewElements(container) {
    const newElements = container.querySelectorAll('.animate-on-scroll:not(.animated)');
    if (!newElements.length) return;
    if (!scrollObserver) {
        // If observer not initialized, just make them visible
        newElements.forEach(el => el.classList.add('animated'));
        return;
    }
    newElements.forEach(el => scrollObserver.observe(el));
    // Also trigger immediately for elements already in viewport
    setTimeout(() => {
        newElements.forEach(el => {
            const rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                el.classList.add('animated');
            }
        });
    }, 50);
}

/* ============================================
   ANIMATED COUNTERS
   ============================================ */
function initCounters() {
    const counters = document.querySelectorAll('[data-count]');
    if (!counters.length) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    counters.forEach(counter => observer.observe(counter));
}

function animateCounter(el) {
    const target = parseInt(el.dataset.count);
    const suffix = el.dataset.suffix || '';
    const duration = 2000;
    const step = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        el.textContent = Math.floor(current).toLocaleString('ru-RU') + suffix;
    }, 16);
}

/* ============================================
   HERO PARTICLES
   ============================================ */
function initParticles() {
    const container = document.getElementById('heroParticles');
    if (!container) return;
    // Отключаем частицы при reduced motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    // На мобильных очень мало частиц, на десктопе умеренно
    const count = window.innerWidth < 480 ? 4 : window.innerWidth < 768 ? 8 : 15;
    const fragment = document.createDocumentFragment();
    for (let i = 0; i < count; i++) {
        const particle = document.createElement('div');
        particle.className = 'hero-particle';
        const size = Math.random() * 6 + 2;
        particle.style.cssText = `width:${size}px;height:${size}px;left:${Math.random() * 100}%;animation-duration:${Math.random() * 15 + 10}s;animation-delay:${Math.random() * 10}s;`;
        fragment.appendChild(particle);
    }
    container.appendChild(fragment);
}

/* ============================================
   PARALLAX
   ============================================ */
function initParallax() {
    // Отключаем на мобильных и при reduced motion
    if (window.innerWidth < 768) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const parallaxBgs = document.querySelectorAll('.parallax-bg');
    if (!parallaxBgs.length) return;
    let rafPending = false;
    window.addEventListener('scroll', () => {
        if (rafPending) return;
        rafPending = true;
        requestAnimationFrame(() => {
            const scrollY = window.scrollY;
            parallaxBgs.forEach(bg => {
                const section = bg.closest('.parallax-section');
                if (!section) return;
                const rect = section.getBoundingClientRect();
                if (rect.bottom < 0 || rect.top > window.innerHeight) return; // вне viewport
                const offset = rect.top + scrollY;
                const relativeScroll = scrollY - offset;
                bg.style.transform = `translateY(${relativeScroll * 0.3}px)`;
            });
            rafPending = false;
        });
    }, { passive: true });
}

/* ============================================
   FAQ ACCORDION
   ============================================ */
function initFaq() {
    document.querySelectorAll('.faq-question').forEach(btn => {
        btn.addEventListener('click', () => toggleFaq(btn));
    });
}

function toggleFaq(btn) {
    const item = btn.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    // Close all
    document.querySelectorAll('.faq-item.open').forEach(openItem => {
        openItem.classList.remove('open');
    });
    // Open clicked if it was closed
    if (!isOpen) {
        item.classList.add('open');
    }
}

/* ============================================
   SCROLL TO TOP
   ============================================ */
function initScrollTop() {
    const btn = document.getElementById('scrollTop');
    if (!btn) return;
    window.addEventListener('scroll', () => {
        btn.classList.toggle('visible', window.scrollY > 400);
    }, { passive: true });
    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

/* ============================================
   SEARCH
   ============================================ */
function initSearch() {
    const form = document.getElementById('searchForm');
    const input = document.getElementById('searchInput');
    if (!form || !input) return;
    let searchTimeout;
    input.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        const q = input.value.trim();
        if (q.length < 2) {
            hideSearchResults();
            return;
        }
        searchTimeout = setTimeout(() => performSearch(q), 300);
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const q = input.value.trim();
        if (q) {
            window.location.href = appUrl('/') + '?q=' + encodeURIComponent(q);
        }
    });
    document.addEventListener('click', (e) => {
        if (!form.contains(e.target)) hideSearchResults();
    });
}

async function performSearch(q) {
    try {
        const res = await fetch(appUrl('/api/?path=search&q=' + encodeURIComponent(q)));
        const data = await res.json();
        if (data.success && data.data.length > 0) {
            showSearchResults(data.data);
        } else {
            showSearchResults([]);
        }
    } catch (e) {
        console.error('Search error:', e);
    }
}

function showSearchResults(results) {
    let dropdown = document.getElementById('searchDropdown');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.id = 'searchDropdown';
        dropdown.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 12px 12px;
            z-index: 500;
            max-height: 320px;
            overflow-y: auto;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        `;
        const wrapper = document.querySelector('.search-wrapper');
        if (wrapper) {
            wrapper.style.position = 'relative';
            wrapper.appendChild(dropdown);
        }
    }
    if (results.length === 0) {
        dropdown.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-muted);font-size:0.875rem;">Ничего не найдено</div>';
    } else {
        dropdown.innerHTML = results.map(p => `
            <div onclick="openProductModal(${p.id})" style="display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--border);transition:background 0.2s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                <div style="width:36px;height:36px;border-radius:8px;background:var(--bg-hover);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                    <img src="${appUrl('/images/icons/' + p.icon)}" alt="" style="width:22px;height:22px;" onerror="this.src='${appUrl('/images/icons/default.svg')}'">
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:0.875rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(p.name)}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">${p.quantity} шт. в наличии</div>
                </div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:0.875rem;font-weight:700;color:var(--primary);flex-shrink:0;">${formatPrice(p.price)}</div>
            </div>
        `).join('');
    }
    dropdown.style.display = 'block';
}

function hideSearchResults() {
    const dropdown = document.getElementById('searchDropdown');
    if (dropdown) dropdown.style.display = 'none';
}

/* ============================================
   CART
   ============================================ */
let cart = [];
const CART_STORAGE_KEY = 'shillcms_cart';
const CHECKOUT_CART_SNAPSHOT_KEY = 'shillcms_checkout_cart';
const CART_SESSION_KEY = 'shillcms_cart_session';

function persistCheckoutCartSnapshot(items = cart) {
    try {
        localStorage.setItem(CHECKOUT_CART_SNAPSHOT_KEY, JSON.stringify(Array.isArray(items) ? items : []));
    } catch (e) {}
}

function clearCheckoutCartSnapshot() {
    try {
        localStorage.removeItem(CHECKOUT_CART_SNAPSHOT_KEY);
    } catch (e) {}
    try {
        sessionStorage.removeItem(CART_SESSION_KEY);
    } catch (e) {}
}

function prepareCartForCheckout() {
    saveCart();
    persistCheckoutCartSnapshot(cart);
    // Also save to sessionStorage as a reliable cross-page backup
    try {
        sessionStorage.setItem(CART_SESSION_KEY, JSON.stringify(Array.isArray(cart) ? cart : []));
    } catch (e) {}
}

function initCart() {
    // Read cart from localStorage; fall back to sessionStorage if empty
    try {
        const stored = localStorage.getItem(CART_STORAGE_KEY);
        const parsed = JSON.parse(stored || '[]');
        if (Array.isArray(parsed) && parsed.length > 0) {
            cart = parsed;
        } else {
            // Try sessionStorage backup
            const sessionStored = sessionStorage.getItem(CART_SESSION_KEY);
            const sessionParsed = JSON.parse(sessionStored || '[]');
            cart = Array.isArray(sessionParsed) ? sessionParsed : [];
            // Restore to localStorage if we got data from session
            if (cart.length > 0) {
                localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
            }
        }
    } catch (e) {
        cart = [];
    }
    updateCartUI();

    const cartBtn = document.getElementById('cartBtn');
    const cartSidebar = document.getElementById('cartSidebar');
    const cartClose = document.getElementById('cartClose');
    const cartOverlay = document.getElementById('cartSidebarOverlay');

    if (cartBtn) cartBtn.addEventListener('click', openCart);
    if (cartClose) cartClose.addEventListener('click', closeCart);
    if (cartOverlay) cartOverlay.addEventListener('click', closeCart);

    // Checkout button — both possible IDs
    const checkoutBtn = document.getElementById('cartCheckoutBtn') || document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (cart.length === 0) {
                showToast('Корзина пуста', 'error');
                return;
            }
            prepareCartForCheckout();
            window.location.href = appUrl('/oplata/');
        });
    }
}

function openCart() {
    const sidebar = document.getElementById('cartSidebar');
    if (sidebar) sidebar.classList.add('open');
    renderCartItems();
}

function closeCart() {
    const sidebar = document.getElementById('cartSidebar');
    if (sidebar) sidebar.classList.remove('open');
}

function addToCart(productId) {
    // Fetch product data
    fetch(appUrl('/api/?path=products/' + productId))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const product = data.data;
            const existing = cart.find(i => i.id === productId);
            if (existing) {
                existing.qty = Math.min(existing.qty + 1, product.quantity);
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    icon: product.icon,
                    slug: product.slug,
                    qty: 1,
                    maxQty: product.quantity
                });
            }
            saveCart();
            updateCartUI();
            renderCartItems();
            showToast(`«${product.name}» добавлен в корзину`, 'success');
            // Bump animation
            const countEl = document.getElementById('cartCount');
            if (countEl) {
                countEl.classList.remove('bump');
                void countEl.offsetWidth;
                countEl.classList.add('bump');
            }
        })
        .catch(() => showToast('Ошибка загрузки товара', 'error'));
}

function removeFromCart(productId) {
    cart = cart.filter(i => i.id !== productId);
    saveCart();
    updateCartUI();
    renderCartItems();
}

function clearCart() {
    if (!confirm('Удалить все товары из корзины?')) return;
    cart = [];
    saveCart();
    clearCheckoutCartSnapshot();
    updateCartUI();
    renderCartItems();
    showToast('Корзина очищена', 'success');
}

function saveCart() {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
}

function updateCartUI() {
    const count = cart.reduce((sum, i) => sum + i.qty, 0);
    const countEl = document.getElementById('cartCount');
    if (countEl) {
        countEl.textContent = count;
        countEl.style.display = count > 0 ? 'flex' : 'none';
    }
    // Show/hide cart footer
    const footer = document.getElementById('cartFooter');
    if (footer) {
        footer.style.display = cart.length > 0 ? 'block' : 'none';
    }
}

function renderCartItems() {
    const container = document.getElementById('cartItems');
    if (!container) return;
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="cart-empty">
                <i class="fa-solid fa-cart-shopping"></i>
                <p>Корзина пуста</p>
            </div>`;
        updateCartTotal();
        updateCartUI();
        return;
    }
    container.innerHTML = cart.map(item => `
        <div class="cart-item">
            <div class="cart-item-icon">
                <img src="${appUrl('/images/icons/' + item.icon)}" alt="" onerror="this.src='${appUrl('/images/icons/default.svg')}'">
            </div>
            <div class="cart-item-info">
                <div class="cart-item-name">${escHtml(item.name)}</div>
                <div class="cart-item-price">${formatPrice(item.price)}</div>
                <div class="cart-item-qty">× ${item.qty} шт.</div>
            </div>
            <button class="cart-item-remove" onclick="removeFromCart('${item.id}')" title="Удалить">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    `).join('');
    updateCartTotal();
    updateCartUI();
}

function updateCartTotal() {
    const total = cart.reduce((sum, i) => sum + i.price * i.qty, 0);
    const totalEl = document.getElementById('cartTotal');
    if (totalEl) totalEl.textContent = formatPrice(total);
}

/* ============================================
   PRODUCT MODAL
   ============================================ */
let productsCache = {};

function initProductModal() {
    const modal = document.getElementById('productModal');
    const overlay = document.getElementById('productModalOverlay');
    const closeBtn = document.getElementById('productModalClose');
    if (!modal) return;
    if (overlay) overlay.addEventListener('click', closeProductModal);
    if (closeBtn) closeBtn.addEventListener('click', closeProductModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeProductModal();
    });
}

async function openProductModal(productId) {
    const modal = document.getElementById('productModal');
    if (!modal) return;
    const body = document.getElementById('productModalBody');
    const title = document.getElementById('productModalTitle');
    if (!body || !title) return;

    // Show loading
    modal.classList.add('open');
    body.innerHTML = `<div style="text-align:center;padding:40px;">
        <div style="width:40px;height:40px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;"></div>
        <p style="color:var(--text-muted);">Загрузка...</p>
    </div>`;

    try {
        let product = productsCache[productId];
        if (!product) {
            const res = await fetch(appUrl('/api/?path=products/' + productId));
            const data = await res.json();
            if (!data.success) throw new Error('Not found');
            product = data.data;
            productsCache[productId] = product;
        }

        title.textContent = product.name;
        body.innerHTML = renderProductModalContent(product);

        // Init qty controls
        const qtyInput = document.getElementById('modalQty');
        const qtyMinus = document.getElementById('modalQtyMinus');
        const qtyPlus = document.getElementById('modalQtyPlus');
        const totalEl = document.getElementById('modalTotal');
        const buyBtn = document.getElementById('modalBuyBtn');

        function updateModalTotal() {
            const qty = parseInt(qtyInput.value) || 1;
            const total = qty * product.price;
            if (totalEl) totalEl.textContent = formatPrice(total);
            if (buyBtn) buyBtn.href = appUrl('/oplata/?item=' + product.slug + '&qty=' + qty);
        }

        if (qtyMinus) qtyMinus.addEventListener('click', () => {
            qtyInput.value = Math.max(1, parseInt(qtyInput.value) - 1);
            updateModalTotal();
        });
        if (qtyPlus) qtyPlus.addEventListener('click', () => {
            qtyInput.value = Math.min(product.quantity, parseInt(qtyInput.value) + 1);
            updateModalTotal();
        });
        if (qtyInput) qtyInput.addEventListener('input', updateModalTotal);

        const addCartBtn = document.getElementById('modalAddCartBtn');
        if (addCartBtn) {
            addCartBtn.innerHTML = '<i class="fa-solid fa-bolt"></i> Купить сейчас';
            addCartBtn.className = 'btn btn-primary btn-full mt-8';
            addCartBtn.addEventListener('click', () => {
                const qty = parseInt(document.getElementById('modalQty')?.value || '1') || 1;
                window.location.href = appUrl('/oplata/?item=' + encodeURIComponent(product.slug) + '&qty=' + qty);
            });
        }

    } catch (e) {
        body.innerHTML = `<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Ошибка загрузки товара</div>`;
    }
}

function renderProductModalContent(p) {
    const sexLabel = p.sex === 'female' ? 'Женский' : p.sex === 'male' ? 'Мужской' : 'Любой';
    const features = (p.features || []).map(f => `<span class="product-feature-tag"><i class="fa-solid fa-check"></i> ${escHtml(f)}</span>`).join('');
    return `
        <div style="text-align:center;margin-bottom:20px;">
            <div class="product-modal-icon">
                <img src="${appUrl('/images/icons/' + p.icon)}" alt="${escHtml(p.category)}" onerror="this.src='${appUrl('/images/icons/default.svg')}'">
            </div>
            <div class="product-modal-category">${escHtml(p.category)} / ${escHtml(p.subcategory)}</div>
        </div>
        <div class="product-modal-specs">
            <div class="spec-item">
                <div class="spec-label">Цена</div>
                <div class="spec-value">${formatPrice(p.price)}</div>
            </div>
            <div class="spec-item">
                <div class="spec-label">В наличии</div>
                <div class="spec-value ${p.quantity > 0 ? 'yes' : 'no'}">${p.quantity} шт.</div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Cookies</div>
                <div class="spec-value ${p.cookies ? 'yes' : 'no'}">${p.cookies ? 'Да' : 'Нет'}</div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Прокси</div>
                <div class="spec-value ${p.proxy ? 'yes' : 'no'}">${p.proxy ? 'Да' : 'Нет'}</div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Email верифицирован</div>
                <div class="spec-value ${p.email_verified ? 'yes' : 'no'}">${p.email_verified ? 'Да' : 'Нет'}</div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Страна</div>
                <div class="spec-value">${escHtml(p.country)}</div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Пол</div>
                <div class="spec-value">${sexLabel}</div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Год регистрации</div>
                <div class="spec-value">${p.age}</div>
            </div>
        </div>
        ${p.short_description ? `<div class="product-modal-description">${escHtml(p.short_description)}</div>` : ''}
        ${p.full_description ? `<div class="product-modal-full-desc" style="margin:12px 0;padding:12px 16px;background:var(--bg);border-radius:10px;border:1px solid var(--border);font-size:0.875rem;color:var(--text-secondary);line-height:1.6;">${p.full_description}</div>` : ''}
        ${features ? `<div class="product-features mb-16">${features}</div>` : ''}
        <div class="product-modal-buy">
            <div class="quantity-selector">
                <button class="qty-btn" id="modalQtyMinus" type="button">−</button>
                <input type="number" class="qty-input" id="modalQty" value="1" min="1" max="${p.quantity}">
                <button class="qty-btn" id="modalQtyPlus" type="button">+</button>
            </div>
            <a href="${appUrl('/oplata/?item=' + p.slug + '&qty=1')}" id="modalBuyBtn" class="btn btn-primary" style="flex:1;" target="_blank">
                <i class="fa-solid fa-bolt"></i> Купить — <span id="modalTotal">${formatPrice(p.price)}</span>
            </a>
        </div>
        <button class="btn btn-secondary btn-full mt-8" id="modalAddCartBtn">
            <i class="fa-solid fa-cart-shopping"></i> Добавить в корзину
        </button>
        <div class="modal-footer-links" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
            <a href="${appUrl('/item/' + p.slug + '/')}" style="font-weight:600;color:var(--primary);" target="_blank"><i class="fa-solid fa-file-lines"></i> Полное описание</a>
            <a href="${appUrl('/rules/')}"><i class="fa-solid fa-shield-halved"></i> Гарантии</a>
        </div>
    `;
}

function closeProductModal() {
    const modal = document.getElementById('productModal');
    if (modal) modal.classList.remove('open');
}

/* ============================================
   CONTACT MODAL — с Telegram полем и капчей
   ============================================ */
function generateCaptcha() {
    const a = Math.floor(Math.random() * 10) + 1;
    const b = Math.floor(Math.random() * 10) + 1;
    return { a, b, answer: a + b };
}

let captchaData = generateCaptcha();

function initContactModal() {
    // Create modal HTML
    const modalHtml = `
        <div class="modal" id="contactModal">
            <div class="modal-overlay" id="contactModalOverlay"></div>
            <div class="modal-dialog" style="max-width:480px;">
                <div class="modal-header">
                    <h3>Задать вопрос</h3>
                    <button class="modal-close" id="contactModalClose"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="modal-body">
                    <p class="modal-subtitle">Ответим в Telegram в течение 15 минут</p>
                    <form id="contactForm" onsubmit="submitContact(event)">
                        <div class="form-group">
                            <label for="contactEmail">Ваш Email *</label>
                            <input type="email" id="contactEmail" placeholder="your@email.com" required>
                        </div>
                        <div class="form-group">
                            <label for="contactSocial">Ваш Telegram или другая соцсеть</label>
                            <input type="text" id="contactSocial" placeholder="@username или ссылка на профиль">
                        </div>
                        <div class="form-group">
                            <label for="contactMessage">Сообщение *</label>
                            <textarea id="contactMessage" placeholder="Опишите ваш вопрос..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="contactCaptcha" id="captchaLabel">Сколько будет <span id="captchaQuestion"></span>? *</label>
                            <input type="number" id="contactCaptcha" placeholder="Введите ответ" required style="width:120px;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">
                            <i class="fa-solid fa-paper-plane"></i> Отправить
                        </button>
                    </form>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    document.getElementById('contactModalOverlay')?.addEventListener('click', closeContactModal);
    document.getElementById('contactModalClose')?.addEventListener('click', closeContactModal);

    // Bind open buttons
    document.querySelectorAll('[id*="openContactModal"], [data-open-contact]').forEach(btn => {
        btn.addEventListener('click', () => {
            captchaData = generateCaptcha();
            const qEl = document.getElementById('captchaQuestion');
            if (qEl) qEl.textContent = captchaData.a + ' + ' + captchaData.b;
            document.getElementById('contactModal').classList.add('open');
        });
    });

    // Set initial captcha
    const qEl = document.getElementById('captchaQuestion');
    if (qEl) qEl.textContent = captchaData.a + ' + ' + captchaData.b;
}

function closeContactModal() {
    document.getElementById('contactModal')?.classList.remove('open');
}

async function submitContact(e) {
    e.preventDefault();
    const email = document.getElementById('contactEmail').value;
    const social = document.getElementById('contactSocial')?.value || '';
    const message = document.getElementById('contactMessage').value;
    const captchaInput = parseInt(document.getElementById('contactCaptcha')?.value || '0');

    // Validate captcha
    if (captchaInput !== captchaData.answer) {
        showToast('Неверный ответ на проверочный вопрос', 'error');
        // Regenerate captcha
        captchaData = generateCaptcha();
        const qEl = document.getElementById('captchaQuestion');
        if (qEl) qEl.textContent = captchaData.a + ' + ' + captchaData.b;
        document.getElementById('contactCaptcha').value = '';
        return;
    }

    try {
        const res = await fetch(appUrl('/api/?path=contact'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, social, message })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeContactModal();
            document.getElementById('contactForm').reset();
            // Regenerate captcha for next use
            captchaData = generateCaptcha();
        } else {
            showToast(data.error || 'Ошибка отправки', 'error');
        }
    } catch (e) {
        showToast('Ошибка соединения', 'error');
    }
}

/* ============================================
   RIPPLE EFFECT
   ============================================ */
function initRippleEffect() {
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.cssText = `
                width: ${size}px;
                height: ${size}px;
                left: ${e.clientX - rect.left - size/2}px;
                top: ${e.clientY - rect.top - size/2}px;
            `;
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

/* ============================================
   3D TILT EFFECT ON PRODUCT CARDS
   ============================================ */
function init3DTiltCards() {
    // Отключаем на touch-устройствах для производительности
    if (window.matchMedia('(hover: none)').matches) return;
    // Отключаем в шаблоне accsmarket (строчный список)
    if (document.body.classList.contains('theme-accsmarket')) return;
    const cards = document.querySelectorAll('.product-card, .category-card');
    cards.forEach(card => {
        let tiltRAF = null;
        card.addEventListener('mousemove', (e) => {
            if (tiltRAF) cancelAnimationFrame(tiltRAF);
            tiltRAF = requestAnimationFrame(() => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = ((y - centerY) / centerY) * -6;
                const rotateY = ((x - centerX) / centerX) * 6;
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(4px)`;
            });
        }, { passive: true });
        card.addEventListener('mouseleave', () => {
            if (tiltRAF) cancelAnimationFrame(tiltRAF);
            card.style.transform = '';
        });
    });
}

/* ============================================
   THEME TOGGLE
   ============================================ */
function initThemeToggle() {
    // Кнопка темы (шестерёнка) переключает между светлым и тёмным режимом
    const btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const isDark = document.body.classList.contains('theme-dark-pro') || 
                       document.body.classList.contains('theme-cyber-neon') || 
                       document.body.classList.contains('theme-accsmarket') || 
                       document.body.classList.contains('theme-midnight-gold');
        
        if (isDark) {
            // Если сейчас любая тёмная тема — переключаем на светлую
            switchTheme('light-clean');
        } else {
            // Если светлая — возвращаем на последнюю тёмную или dark-pro
            const lastDark = localStorage.getItem('shillcms_last_dark') || 'dark-pro';
            switchTheme(lastDark);
        }
    });
}

/* ============================================
   TOAST NOTIFICATIONS
   ============================================ */
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';
    toast.innerHTML = `<i class="fa-solid ${icon}"></i><span>${escHtml(message)}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

/* ============================================
   UTILITIES
   ============================================ */
function formatPrice(price) {
    return Math.floor(price).toLocaleString('ru-RU') + ' ₽';
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// CSS spin animation
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);

// Global exports
window.appUrl = appUrl;
window.openProductModal = openProductModal;
window.closeProductModal = closeProductModal;
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.clearCart = clearCart;
window.saveCart = saveCart;
window.updateCartUI = updateCartUI;
window.renderCartItems = renderCartItems;
// Expose cart array reference (item.php and other inline scripts may need it)
Object.defineProperty(window, 'cart', {
    get: function() { return cart; },
    set: function(v) { cart = v; },
    configurable: true
});
window.toggleFaq = toggleFaq;
window.showToast = showToast;
window.formatPrice = formatPrice;
window.escHtml = escHtml;
window.observeNewElements = observeNewElements;

/* ============================================
   THEME — Тема управляется только админом
   ============================================ */

const THEMES = ['dark-pro', 'cyber-neon', 'accsmarket', 'light-clean', 'midnight-gold'];

// Тема задаётся сервером через class body.
// Пользователь не может её менять — это привилегия админа.
// localStorage для темы не используется в публичной части сайта.

function initThemeSwitcher() {
    // Для публичной части — панель шаблонов отсутствует, ничего не делаем.
    const toggle = document.getElementById('themeSwitcherToggle');
    if (!toggle) return; // В публичной части элемента нет
    const panel = document.getElementById('themeSwitcherPanel');
    if (!panel) return;
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        panel.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
        if (!document.getElementById('themeSwitcher')?.contains(e.target)) {
            panel.classList.remove('open');
        }
    });
}

function applyTheme(theme) {
    // Только для админпанели — не вызывается в публичной части
    if (!THEMES.includes(theme)) return;
    THEMES.forEach(t => document.body.classList.remove('theme-' + t));
    document.body.classList.add('theme-' + theme);
    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.theme === theme);
    });
}

function switchTheme(theme) {
    // Только для админпанели — отправляет PUT /api/?path=admin/settings
    if (!THEMES.includes(theme)) return;
    applyTheme(theme);
    document.getElementById('themeSwitcherPanel')?.classList.remove('open');
    const names = {
        'dark-pro': 'Тёмный Про',
        'cyber-neon': 'Кибер-Неон',
        'accsmarket': 'AccsMarket',
        'light-clean': 'Светлый чистый',
        'midnight-gold': 'Полночь и Золото'
    };
    if (typeof showToast === 'function') showToast('Шаблон: ' + (names[theme] || theme), 'success');
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    initThemeSwitcher();
});

// Экспорт
window.switchTheme = switchTheme;
window.applyTheme = applyTheme;
