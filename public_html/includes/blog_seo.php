<?php
/**
 * Blog SEO Optimization Module
 * Функции для поиска по статьям, похожих статей, и управления lastmod
 */

/**
 * Поиск статей по ключевому слову
 */
function searchArticles($query, $articles = []) {
    if (empty($query) || empty($articles)) {
        return [];
    }
    
    $query = mb_strtolower(trim($query));
    $keywords = array_filter(explode(' ', $query), fn($w) => mb_strlen($w) > 2);
    
    if (empty($keywords)) {
        return [];
    }
    
    $results = [];
    
    foreach ($articles as $article) {
        $title = mb_strtolower($article['title'] ?? '');
        $excerpt = mb_strtolower($article['excerpt'] ?? '');
        $content = mb_strtolower(strip_tags($article['content'] ?? ''));
        $category = mb_strtolower($article['category'] ?? '');
        
        $score = 0;
        
        foreach ($keywords as $keyword) {
            // Поиск в заголовке (наибольший вес)
            if (mb_strpos($title, $keyword) !== false) {
                $score += 10;
            }
            // Поиск в категории
            if (mb_strpos($category, $keyword) !== false) {
                $score += 5;
            }
            // Поиск в отрывке
            if (mb_strpos($excerpt, $keyword) !== false) {
                $score += 3;
            }
            // Поиск в контенте
            if (mb_strpos($content, $keyword) !== false) {
                $score += 1;
            }
        }
        
        if ($score > 0) {
            $results[] = [
                'article' => $article,
                'score' => $score
            ];
        }
    }
    
    // Сортируем по релевантности (убывание)
    usort($results, fn($a, $b) => $b['score'] - $a['score']);
    
    return array_map(fn($r) => $r['article'], $results);
}

/**
 * Получить похожие статьи на основе категории и тегов
 */
function getRelatedArticles($currentArticle, $allArticles, $limit = 3) {
    if (empty($currentArticle) || empty($allArticles)) {
        return [];
    }
    
    $currentSlug = $currentArticle['slug'] ?? '';
    $currentCategory = $currentArticle['category'] ?? '';
    $currentTags = $currentArticle['tags'] ?? [];
    
    if (!is_array($currentTags)) {
        $currentTags = [];
    }
    
    $related = [];
    
    foreach ($allArticles as $article) {
        // Исключаем текущую статью
        if (($article['slug'] ?? '') === $currentSlug) {
            continue;
        }
        
        $score = 0;
        
        // Совпадение по категории (наибольший вес)
        if (($article['category'] ?? '') === $currentCategory) {
            $score += 10;
        }
        
        // Совпадение по тегам
        $articleTags = $article['tags'] ?? [];
        if (!is_array($articleTags)) {
            $articleTags = [];
        }
        
        $commonTags = count(array_intersect($currentTags, $articleTags));
        $score += $commonTags * 5;
        
        // Совпадение ключевых слов в заголовке
        $currentTitle = mb_strtolower($currentArticle['title'] ?? '');
        $articleTitle = mb_strtolower($article['title'] ?? '');
        
        $currentWords = array_filter(explode(' ', $currentTitle), fn($w) => mb_strlen($w) > 3);
        foreach ($currentWords as $word) {
            if (mb_strpos($articleTitle, $word) !== false) {
                $score += 2;
            }
        }
        
        if ($score > 0) {
            $related[] = [
                'article' => $article,
                'score' => $score
            ];
        }
    }
    
    // Сортируем по релевантности (убывание)
    usort($related, fn($a, $b) => $b['score'] - $a['score']);
    
    // Возвращаем топ N статей
    return array_slice(
        array_map(fn($r) => $r['article'], $related),
        0,
        $limit
    );
}

/**
 * Обновить дату модификации статьи
 */
function updateArticleLastmod($articles, $articleSlug) {
    foreach ($articles as &$article) {
        if (($article['slug'] ?? '') === $articleSlug) {
            $article['lastmod'] = date('Y-m-d H:i:s');
            break;
        }
    }
    return $articles;
}

/**
 * Получить дату последней модификации статьи
 */
function getArticleLastmod($article) {
    // Приоритет: lastmod > date
    $lastmod = $article['lastmod'] ?? $article['date'] ?? date('Y-m-d');
    
    // Преобразуем в ISO 8601 формат для sitemap
    if (strpos($lastmod, ' ') !== false) {
        // Если есть время, преобразуем
        $timestamp = strtotime($lastmod);
        return date('Y-m-d\TH:i:s\Z', $timestamp);
    }
    
    return $lastmod . 'T00:00:00Z';
}

/**
 * Генерировать HTML блока "Похожие статьи"
 */
function renderRelatedArticles($relatedArticles) {
    if (empty($relatedArticles)) {
        return '';
    }
    
    $html = '<div class="related-articles" style="margin-top:48px;padding-top:32px;border-top:1px solid var(--border);">';
    $html .= '<h3 style="margin-bottom:24px;font-size:1.4rem;">Похожие статьи</h3>';
    $html .= '<div class="related-articles-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">';
    
    foreach ($relatedArticles as $article) {
        $html .= '<article class="related-article-card" style="border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:all 0.3s ease;background:var(--bg-card);">';
        
        if (!empty($article['image'])) {
            $html .= '<a href="/info/' . htmlspecialchars($article['slug']) . '/" class="related-article-image" style="display:block;height:180px;overflow:hidden;background:var(--bg-hover);">';
            $html .= '<img src="/images/blog/' . htmlspecialchars($article['image']) . '" alt="' . htmlspecialchars($article['title']) . '" style="width:100%;height:100%;object-fit:cover;transition:transform 0.3s ease;" loading="lazy" onerror="this.src=\'/images/icons/default.svg\'">';
            $html .= '</a>';
        }
        
        $html .= '<div style="padding:16px;">';
        
        if (!empty($article['category'])) {
            $html .= '<span style="display:inline-block;background:rgba(79,70,229,0.15);color:var(--primary);padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;margin-bottom:8px;">' . htmlspecialchars($article['category']) . '</span>';
        }
        
        $html .= '<h4 style="margin:8px 0;font-size:0.95rem;font-weight:600;line-height:1.3;">';
        $html .= '<a href="/info/' . htmlspecialchars($article['slug']) . '/" style="color:var(--text-primary);text-decoration:none;transition:color 0.2s;">' . htmlspecialchars($article['title']) . '</a>';
        $html .= '</h4>';
        
        if (!empty($article['excerpt'])) {
            $html .= '<p style="font-size:0.85rem;color:var(--text-secondary);margin:8px 0;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' . htmlspecialchars($article['excerpt']) . '</p>';
        }
        
        $html .= '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">';
        $html .= '<span style="font-size:0.8rem;color:var(--text-muted);"><i class="fa-regular fa-calendar"></i> ' . date('d.m.Y', strtotime($article['date'] ?? date('Y-m-d'))) . '</span>';
        $html .= '<a href="/info/' . htmlspecialchars($article['slug']) . '/" style="color:var(--primary);font-size:0.85rem;font-weight:600;text-decoration:none;">Читать →</a>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</article>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Генерировать HTML для отображения результатов поиска
 */
function renderSearchResults($results, $query) {
    if (empty($results)) {
        return '<div class="search-no-results" style="text-align:center;padding:40px 20px;color:var(--text-muted);">'
            . '<i class="fa-solid fa-search" style="font-size:2rem;margin-bottom:16px;display:block;"></i>'
            . '<p>По запросу «' . htmlspecialchars($query) . '» статей не найдено.</p>'
            . '</div>';
    }
    
    $html = '<div class="search-results-list">';
    
    foreach ($results as $article) {
        $html .= '<article class="search-result-item" style="padding:16px;border-bottom:1px solid var(--border);transition:background 0.2s;">';
        $html .= '<h3 style="margin:0 0 8px;font-size:1rem;">';
        $html .= '<a href="/info/' . htmlspecialchars($article['slug']) . '/" style="color:var(--primary);text-decoration:none;">' . htmlspecialchars($article['title']) . '</a>';
        $html .= '</h3>';
        
        if (!empty($article['excerpt'])) {
            $html .= '<p style="margin:0 0 8px;font-size:0.9rem;color:var(--text-secondary);line-height:1.5;">' . htmlspecialchars(substr($article['excerpt'], 0, 150)) . '...</p>';
        }
        
        $html .= '<div style="display:flex;gap:16px;font-size:0.8rem;color:var(--text-muted);">';
        if (!empty($article['category'])) {
            $html .= '<span><i class="fa-solid fa-tag"></i> ' . htmlspecialchars($article['category']) . '</span>';
        }
        $html .= '<span><i class="fa-regular fa-calendar"></i> ' . date('d.m.Y', strtotime($article['date'] ?? date('Y-m-d'))) . '</span>';
        $html .= '</div>';
        $html .= '</article>';
    }
    
    $html .= '</div>';
    
    return $html;
}

?>
