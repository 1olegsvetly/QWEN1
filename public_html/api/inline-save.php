<?php
/**
 * Account Store CMS - Inline Editor Save API
 * Сохраняет изменения из визуального редактора в JSON-файлы данных.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

function jsonResp($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Проверяем авторизацию администратора
if (!isAdminLoggedIn()) {
    jsonResp(['success' => false, 'error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResp(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['changes']) || !is_array($input['changes'])) {
    jsonResp(['success' => false, 'error' => 'Invalid input'], 400);
}

$page = $input['page'] ?? '';
$changes = $input['changes'];
$saved = [];
$errors = [];

foreach ($changes as $key => $value) {
    // Ключ формата: "type:id:field" или "type:field"
    // Примеры: "settings:seo:title", "article:my-slug:content", "page:faq:content"
    $parts = explode(':', $key, 3);

    if (count($parts) < 2) {
        $errors[] = "Invalid key format: $key";
        continue;
    }

    $type = $parts[0];
    $id = $parts[1];
    $field = $parts[2] ?? null;

    // Очищаем HTML (разрешаем базовые теги)
    $cleanValue = strip_tags($value, '<b><strong><i><em><u><a><br><p><ul><ol><li><h2><h3><h4><span>');

    switch ($type) {
        case 'settings':
            // settings:section:field -> settings.json
            $settings = getSettings();
            if ($field) {
                if (!isset($settings[$id])) $settings[$id] = [];
                $settings[$id][$field] = $cleanValue;
            } else {
                $settings[$id] = $cleanValue;
            }
            saveData('settings', $settings);
            $saved[] = $key;
            break;

        case 'page':
            // page:pagename:field -> pages.json
            $pages = getPages();
            if (!isset($pages[$id])) $pages[$id] = [];
            if ($field) {
                $pages[$id][$field] = $cleanValue;
            }
            saveData('pages', $pages);
            $saved[] = $key;
            break;

        case 'article':
            // article:slug:field -> pages.json (info.articles)
            $pages = getPages();
            $articles = $pages['info']['articles'] ?? [];
            $found = false;
            foreach ($articles as &$article) {
                if ($article['slug'] === $id) {
                    if ($field === 'content') {
                        // Контент статьи - разрешаем полный HTML
                        $article[$field] = $value;
                    } elseif ($field) {
                        $article[$field] = $cleanValue;
                    }
                    $found = true;
                    break;
                }
            }
            unset($article);
            if ($found) {
                $pages['info']['articles'] = $articles;
                saveData('pages', $pages);
                $saved[] = $key;
            } else {
                $errors[] = "Article not found: $id";
            }
            break;

        case 'product':
            // product:slug:field -> products.json
            $products = getProducts();
            $found = false;
            foreach ($products as &$product) {
                if ($product['slug'] === $id && $field) {
                    $product[$field] = $cleanValue;
                    $found = true;
                    break;
                }
            }
            unset($product);
            if ($found) {
                saveProductsWithAutoSplit($products);
                $saved[] = $key;
            } else {
                $errors[] = "Product not found: $id";
            }
            break;

        default:
            $errors[] = "Unknown type: $type";
    }
}

if (!empty($saved)) {
    jsonResp([
        'success' => true,
        'saved' => $saved,
        'errors' => $errors,
        'message' => count($saved) . ' изменений сохранено'
    ]);
} else {
    jsonResp([
        'success' => false,
        'error' => 'Ничего не сохранено',
        'errors' => $errors
    ], 400);
}
