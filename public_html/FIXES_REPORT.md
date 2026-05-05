# Отчёт об исправлениях — accsme_v29

## Исправленные файлы

| Файл | Проблема | Статус |
|------|----------|--------|
| `pages/oplata.php` | Скачивание товара + обработка demo-paid | ✅ Исправлено |
| `admin/index.php` | Белый экран после авторизации | ✅ Исправлено |
| `includes/footer.php` | Визуальный редактор не отображался | ✅ Исправлено |

---

## Баг 1: Скачивание товара после оплаты (pages/oplata.php)

### Причина проблемы

Функция `downloadOrderItems` была объявлена **внутри IIFE** (Immediately Invoked Function Expression):
```js
(function(){
    // ...
    function downloadOrderItems() { ... }
    // ...
})();
```

Кнопка скачивания использует `onclick="downloadOrderItems()"` — это обращение к **глобальному** контексту `window`. Поскольку функция находилась внутри IIFE, она не была доступна глобально, и при нажатии кнопки возникала ошибка `downloadOrderItems is not defined`.

### Исправление

Добавлен экспорт функции в глобальный контекст вместе с остальными экспортируемыми функциями:

```js
// pages/oplata.php — строки 1010-1015
window.selectPaymentMethod = selectPaymentMethod;
window.selectCryptoToken = selectCryptoToken;
window.changePayQty = changePayQty;
window.changeCartItemQty = changeCartItemQty;
window.proceedToPayment = proceedToPayment;
window.downloadOrderItems = downloadOrderItems;  // ← ДОБАВЛЕНО
```

### Дополнительное исправление: обработка demo-paid при перезагрузке страницы

При переходе по URL с параметром `?order=ACCME-...` функция `refreshOrderStatus()` опрашивала API, получала заказ со статусом `demo-paid`, но не вызывала `showDemoStep()` — кнопка скачивания не появлялась.

Добавлена обработка `status === 'demo-paid'` в функцию `refreshOrderStatus`:

```js
// pages/oplata.php — функция refreshOrderStatus
if(order.status==='demo-paid'||order.payment_method==='demo'){
    // Демо-заказ: показываем шаг демо-оплаты с выдачей товара
    document.getElementById('checkoutStep1')&&(document.getElementById('checkoutStep1').hidden=true);
    showDemoStep(order);
    if(orderStatusPoller){clearInterval(orderStatusPoller);orderStatusPoller=null}
} else if(order.payment_status==='paid'||order.status==='paid'){
    // ... обычная оплата
}
```

---

## Баг 2: Белый экран в админке (admin/index.php)

### Причина проблемы

В начале `admin/index.php` вызывается `startAppOutputBuffer()`, которая запускает `ob_start('rewriteAppUrls')`. Это означает, что **весь вывод** буферизуется и обрабатывается функцией `rewriteAppUrls` при сбросе буфера.

Проблема возникала при POST-запросе авторизации:
1. Буфер открывается через `ob_start('rewriteAppUrls')`
2. Проверяется логин/пароль
3. При успешной авторизации вызывается `header('Location: /admin/')` + `exit`
4. PHP завершает скрипт и пытается сбросить буфер через callback `rewriteAppUrls`
5. Функция `rewriteAppUrls` вызывает `getBasePath()` и `strtr()` на пустом или частичном буфере
6. В зависимости от конфигурации PHP это могло вызывать ошибку или возвращать пустую строку
7. Браузер получал пустой ответ (белый экран) вместо редиректа

### Исправление

Добавлен явный `ob_end_clean()` перед каждым `header()` + `exit` в блоке авторизации:

```php
// admin/index.php — блок обработки POST-запроса авторизации
if ($loginOk) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = $inputUser;
    ob_end_clean();  // ← ДОБАВЛЕНО: очищаем буфер перед редиректом
    header('Location: ' . appUrl('/admin/'));
    exit;
} else {
    ob_end_clean();  // ← ДОБАВЛЕНО: очищаем буфер перед редиректом
    header('Location: ' . appUrl('/admin/?error=1'));
    exit;
}
```

---

## Баг 3: Визуальный редактор не отображался (includes/footer.php)

### Причина проблемы

В `footer.php` скрипты подключались через **жёсткие абсолютные пути**:

```php
// БЫЛО (неправильно):
<script src="/js/inline-editor.js" defer></script>
<script src="/js/main.js" defer></script>
<script src="/js/products.js" defer></script>
```

При установке проекта в **подпапку** (например, `/shop/`) пути `/js/inline-editor.js` указывали на несуществующие файлы. Браузер не мог загрузить скрипт, и редактор не инициализировался.

Дополнительно: функция `rewriteAppUrls` в `functions.php` обрабатывает `href`, `src`, `action` атрибуты через regex, но **не обрабатывает** `<script src="...">` теги с относительными путями внутри PHP-генерируемого HTML.

### Исправление

Все пути к JS-файлам заменены на динамические через функцию `appUrl()`:

```php
// includes/footer.php — ИСПРАВЛЕНО:
<script src="<?= htmlspecialchars(appUrl('/js/main.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(appUrl('/js/products.js')) ?>" defer></script>
<?php if (isAdminForEditor()): ?>
<script>window.INLINE_EDITOR_ENABLED = true;</script>
<script src="<?= htmlspecialchars(appUrl('/js/inline-editor.js')) ?>" defer></script>
<?php endif; ?>
```

Теперь при установке в корень (`basePath=''`) пути остаются `/js/...`, а при установке в подпапку (`basePath='/shop'`) становятся `/shop/js/...`.

---

## Итоговая проверка

| Функция | Результат теста |
|---------|----------------|
| Авторизация в админке | ✅ Редирект работает, белый экран устранён |
| Дашборд админки | ✅ Отображается корректно |
| Визуальный редактор на главной | ✅ Тулбар "Жирный / Курсив / Ссылка / Сохранить" отображается |
| Демо-оплата товара | ✅ Заказ создаётся, `delivered_items` заполняются |
| Кнопка "Скачать товар (.txt)" | ✅ Создаёт Blob-файл и инициирует скачивание |
| Перезагрузка страницы с `?order=` | ✅ Статус demo-paid восстанавливается, кнопка скачивания появляется |
