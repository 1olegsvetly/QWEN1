# Account Store CMS — Changelog v6

## Что изменилось в версии 6

---

### 1. Скачивание товаров в админке (CSV)

**Файлы:** `admin/index.php`, `api/index.php`

- В разделе **Товары** появилась кнопка **«Скачать все товары»** (иконка `fa-file-arrow-down`)
- При нажатии скачивается файл `products_export_YYYY-MM-DD.csv` со всеми товарами
- CSV содержит поля: `name, slug, category, subcategory, short_description, full_description, price, quantity, icon, status, cookies, proxy, email_verified, country, sex, age, popular, features`
- Endpoint: `GET /api/?path=admin/export-products`

---

### 2. Система 5 шаблонов

**Файлы:** `css/themes.css`, `includes/footer.php`, `includes/header.php`, `js/main.js`, `admin/index.php`, `api/index.php`

#### Шаблоны:

| Ключ | Название | Описание |
|------|----------|----------|
| `dark-pro` | Тёмный Про | Фиолетово-синий, карточки-плитки (по умолчанию) |
| `cyber-neon` | Кибер-Неон | Тёмный фон, зелёный неон, угловые карточки |
| `accsmarket` | AccsMarket | Стиль accsmarket.com — строчный список товаров |
| `light-clean` | Светлый чистый | Белый фон, синие акценты, скруглённые карточки |
| `midnight-gold` | Полночь и Золото | Чёрный фон, золотые акценты |

#### Как работает:
- **Плавающая кнопка** с иконкой палитры (`fa-palette`) в правом нижнем углу открывает панель выбора шаблона
- Выбранный шаблон **сохраняется в `localStorage`** и применяется при следующем посещении
- В **Настройках админки** есть раздел «Шаблон сайта» — выбор шаблона по умолчанию для новых посетителей
- При переключении меняются: цвета, фон, шрифты, расположение карточек, стиль кнопок

#### Шаблон AccsMarket (строчный список):
- Товары отображаются строками (как на accsmarket.com)
- Иконка + название + описание + количество + цена + кнопка в одну строку
- Полное описание открывается во всплывающем окне (модалке) — как и в других шаблонах
- Адаптивен: на мобильных описание скрывается, остаётся только суть

---

### 3. Оптимизация производительности

**Файлы:** `includes/header.php`, `includes/footer.php`, `css/style.css`, `css/responsive.css`, `js/main.js`, `js/products.js`, `includes/functions.php`, `.htaccess`

#### Frontend:
- **Шрифты и Font Awesome** загружаются асинхронно (`preload` + `onload`) — не блокируют рендеринг
- **JS скрипты** переведены на `defer` — не блокируют парсинг HTML
- **DNS prefetch** для cdnjs.cloudflare.com
- **Preconnect** для Google Fonts
- **will-change: transform** на карточках товаров — GPU-ускорение анимаций
- **contain: layout style** на карточках — изоляция перерисовок
- **3D tilt** отключён на touch-устройствах и в шаблоне accsmarket
- **Параллакс** отключён на мобильных и при `prefers-reduced-motion`
- **Частицы** уменьшены на мобильных (4 вместо 20), отключены при `prefers-reduced-motion`
- **requestAnimationFrame** для параллакса и tilt-эффекта — плавность 60fps
- **Passive event listeners** для scroll/touch событий
- **DocumentFragment** для batch-вставки частиц
- **IntersectionObserver** для lazy loading изображений с `data-src`

#### Backend (PHP):
- **In-memory кэш** JSON-файлов — каждый файл читается с диска только 1 раз за запрос
- Инвалидация кэша при `saveData()`

#### Apache (.htaccess):
- **Gzip** для HTML, CSS, JS, JSON, SVG, шрифтов
- **Кэширование браузера:** изображения/шрифты — 1 год, CSS/JS — 1 месяц
- **Cache-Control: immutable** для статических ресурсов
- **Keep-Alive** соединения
- **Security headers:** X-Content-Type-Options, X-Frame-Options, X-XSS-Protection

#### Адаптивность:
- **Ultra-wide** (1920px+): 4 колонки товаров, 5 категорий
- **Touch-устройства:** увеличенные tap targets (44px), отключены hover-эффекты
- **Landscape mobile:** компактный hero, 4 колонки статистики
- **Safe area** для телефонов с вырезом (env(safe-area-inset-*))
- **Viewport-fit=cover** для iPhone с чёлкой

---

### Установка

1. Загрузите содержимое папки `shillcms_v6/` на хостинг
2. Убедитесь что `mod_rewrite`, `mod_deflate`, `mod_expires`, `mod_headers` включены в Apache
3. Папка `data/` должна быть доступна на запись PHP
4. Откройте сайт — шаблон `dark-pro` применится по умолчанию
5. Для смены шаблона — нажмите кнопку палитры в правом нижнем углу
6. Для установки шаблона по умолчанию — Админка → Настройки → Шаблон сайта
