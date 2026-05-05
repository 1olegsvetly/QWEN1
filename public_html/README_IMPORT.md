# Инструкция по массовому импорту (CSV)

В админ-панели (раздел Импорт) вы можете массово загружать товары и статьи.

## 1. Импорт товаров (до 500 позиций)
**Файл:** `products.csv`
**Кодировка:** UTF-8
**Разделитель:** запятая `,`

### Заголовки (первая строка):
`name,slug,category,subcategory,short_description,full_description,price,quantity,icon,status,cookies,proxy,email_verified,country,sex,age,popular,features`

### Пример строки:
`Facebook Farm USA,fb-farm-usa,facebook,farm,Farm accounts USA with cookies,<h2>Описание</h2><p>Текст с HTML</p>,349,50,facebook.svg,active,yes,yes,yes,USA,any,2023,yes,Cookies|Proxy|72h Guarantee`

---

## 2. Импорт статей блога (до 5 позиций)
**Файл:** `articles.csv`
**Кодировка:** UTF-8
**Разделитель:** запятая `,`

### Заголовки (первая строка):
`title,slug,excerpt,content,image,date,seo_title,seo_description`

### Пример строки:
`Как выбрать аккаунт FB,fb-guide-2024,Краткий анонс статьи для плитки в блоге,<h2>Заголовок</h2><p>Весь текст статьи с HTML тегами для SEO</p>,fb-blog.jpg,2024-04-10,SEO Заголовок статьи,Описание для поисковиков`

---

## Важные примечания:
1. **Slug** должен быть уникальным и содержать только латиницу, цифры и дефисы.
2. **Category** и **Subcategory** должны соответствовать существующим slug из раздела "Категории".
3. **Features** для товаров разделяются вертикальной чертой `|`.
4. Поля `cookies`, `proxy`, `email_verified`, `popular` принимают значения `yes` или `no`.
5. Изображения для блога должны находиться в `/images/blog/` (создайте папку, если её нет).
