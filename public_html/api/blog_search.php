<?php
/**
 * Blog Search API Endpoint
 * GET /api/blog_search.php?q=query
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blog_seo.php';

$query = sanitize($_GET['q'] ?? $_POST['q'] ?? '');

if (empty($query) || mb_strlen($query) < 2) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Query must be at least 2 characters long',
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pagesData = getPages();
    $articles = $pagesData['info']['articles'] ?? [];
    
    if (empty($articles)) {
        echo json_encode([
            'success' => true,
            'query' => $query,
            'results' => [],
            'count' => 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Поиск статей
    $results = searchArticles($query, $articles);
    
    // Ограничиваем результаты до 10 статей
    $results = array_slice($results, 0, 10);
    
    // Форматируем результаты для API
    $formattedResults = [];
    foreach ($results as $article) {
        $formattedResults[] = [
            'slug' => $article['slug'] ?? '',
            'title' => $article['title'] ?? '',
            'excerpt' => substr($article['excerpt'] ?? '', 0, 150),
            'category' => $article['category'] ?? '',
            'date' => $article['date'] ?? date('Y-m-d'),
            'image' => $article['image'] ?? 'blog-default.jpg',
            'url' => '/info/' . ($article['slug'] ?? '') . '/'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => $formattedResults,
        'count' => count($formattedResults)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search error: ' . $e->getMessage(),
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
}

?>
