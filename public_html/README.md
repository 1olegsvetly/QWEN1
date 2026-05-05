# Account Store CMS — Магазин цифровых аккаунтов

## Описание проекта
Полноценный сайт-магазин по продаже аккаунтов социальных сетей и сервисов. Разработан на PHP, HTML, CSS, JS без использования базы данных (хранение данных в JSON-файлах).

---

## Технологии
- **Backend:** PHP 8.1+ (без фреймворков)
- **Frontend:** HTML5, CSS3, Vanilla JavaScript (ES6+)
- **Хранение данных:** JSON-файлы
- **Иконки:** Font Awesome 6.5
- **Шрифты:** Inter, JetBrains Mono (Google Fonts)
- **Веб-сервер:** Apache (с mod_rewrite)

---

## Структура проекта
```
shillcms/
├── .htaccess              — URL-роутинг и настройки Apache
├── index.php              — Главный роутер + главная страница
├── sitemap.php            — Генерация sitemap.xml
├── robots.php             — Генерация robots.txt
│
├── includes/
│   ├── functions.php      — PHP-хелперы (loadData, saveData, getProducts и др.)
│   ├── header.php         — Шапка сайта (HTML-шаблон)
│   └── footer.php         — Подвал сайта (HTML-шаблон)
│
├── pages/
│   ├── category.php       — Страница категории/подкатегории
│   ├── item.php           — Страница отдельного товара
│   ├── oplata.php         — Страница оформления заказа
│   ├── faq.php            — FAQ
│   ├── rules.php          — Правила магазина
│   └── info.php           — Полезные статьи
│
├── api/
│   └── index.php          — REST API (JSON-ответы)
│
├── admin/
│   └── index.php          — Полная админ-панель
│
├── css/
│   ├── style.css          — Основные стили + WOW-эффекты
│   └── responsive.css     — Адаптивные стили (mobile-first)
│
├── js/
│   ├── main.js            — Основной JavaScript
│   └── products.js        — Инициализация после загрузки товаров
│
├── data/
│   ├── categories.json    — Категории и подкатегории
│   ├── products.json      — Товары
│   ├── settings.json      — Настройки сайта
│   └── pages.json         — Контент страниц (FAQ, правила, статьи)
│
└── images/
    ├── icons/             — SVG-иконки категорий/товаров
    └── ui/                — UI-элементы (favicon)
```

---

## Установка на хостинг

### Требования
- PHP 8.0 или выше
- Apache с включённым mod_rewrite
- Права на запись в директорию `data/`

### Шаги установки
1. Распакуйте архив в корневую директорию сайта
2. Убедитесь, что `mod_rewrite` включён в Apache
3. Дайте права на запись директории `data/`:
   ```bash
   chmod 755 data/
   chmod 644 data/*.json
   ```
4. Откройте сайт в браузере

---

## Доступ в админ-панель

**URL:** `/admin/`

**Логин:** `admin`
**Пароль:** `admin`

> ⚠️ **Важно:** После первого входа обязательно смените пароль в разделе «Настройки»!

---

## API Endpoints

| Метод | Путь | Описание |
|-------|------|----------|
| GET | `/api/?path=categories` | Список категорий |
| GET | `/api/?path=products` | Список товаров |
| GET | `/api/?path=products/{slug}` | Товар по slug/id |
| GET | `/api/?path=search&q=...` | Поиск товаров |
| GET | `/api/?path=settings` | Настройки сайта |
| POST | `/api/?path=contact` | Отправка вопроса |
| POST | `/api/?path=admin/login` | Авторизация |
| POST | `/api/?path=admin/products` | Добавить товар |
| PUT | `/api/?path=admin/products/{id}` | Обновить товар |
| DELETE | `/api/?path=admin/products/{id}` | Удалить товар |
| PUT | `/api/?path=admin/settings` | Сохранить настройки |
| PUT | `/api/?path=admin/pages` | Сохранить FAQ/страницы |
| POST | `/api/?path=admin/import` | Импорт CSV |

---

## URL-структура сайта

| URL | Страница |
|-----|----------|
| `/` | Главная |
| `/category/{slug}/` | Категория |
| `/category/{slug}/{sub}/` | Подкатегория |
| `/item/{slug}/` | Товар |
| `/oplata/` | Оформление заказа |
| `/faq/` | FAQ |
| `/rules/` | Правила |
| `/info/` | Статьи |
| `/info/{slug}/` | Статья |
| `/admin/` | Админ-панель |
| `/sitemap.xml` | Карта сайта |
| `/robots.txt` | Robots |

---

## Функционал

### Публичная часть
- Главная страница с hero-секцией, статистикой, категориями, популярными товарами
- Каталог с фильтрацией, сортировкой и поиском
- Страница товара с подробными характеристиками
- Корзина (localStorage)
- Оформление заказа
- FAQ с аккордеоном
- Правила магазина
- Полезные статьи
- Поиск с автодополнением
- Адаптивный дизайн (mobile-first)
- Тёмная/светлая тема
- SEO: meta-теги, Schema.org, sitemap, robots.txt

### WOW-эффекты
- Sticky header с blur при скролле
- Parallax-эффект на hero
- 3D-наклон карточек при наведении
- Анимированные счётчики статистики
- Skeleton loading для товаров
- Scroll-анимации (Intersection Observer)
- Частицы в hero-секции
- Ripple-эффект на кнопках
- Toast-уведомления
- Плавные переходы и анимации

### Админ-панель
- Авторизация с сессиями
- Дашборд со статистикой
- CRUD для товаров
- CRUD для категорий
- Редактирование FAQ
- Настройки сайта (цвета, SEO, контакты)
- Смена пароля администратора
- Импорт товаров из CSV
- Поиск и фильтрация в таблицах

---

## Настройка цветовой схемы
Цвета настраиваются в админ-панели → Настройки → Цвета, либо напрямую в `data/settings.json`.

---

## Импорт товаров из CSV
Формат файла (заголовки обязательны):
```
name,slug,category,subcategory,short_description,full_description,price,quantity,icon,status,cookies,proxy,email_verified,country,sex,age
```

Шаблон можно скачать в админ-панели → Импорт.

---

## Лицензия
Проект создан как универсальная CMS для магазина цифровых аккаунтов. Все права защищены.
