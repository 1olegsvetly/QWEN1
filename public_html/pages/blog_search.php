<?php
/**
 * Blog Search Page with HTML Filters
 * Поиск по статьям блога с фильтрами по категориям, индексируемыми ссылками и SEO
 */

require_once __DIR__ . '/../includes/blog_seo.php';

$currentPage = 'blog_search';
$pagesData = getPages();
$articles = $pagesData['info']['articles'] ?? [];
$siteName = $settings['site']['name'] ?? 'Магазин аккаунтов';

// Параметры поиска и фильтрации
$searchQuery = sanitize($_GET['q'] ?? '');
$filterCategory = sanitize($_GET['cat'] ?? '');
$perPage = 12;
$currentPageNum = max(1, (int)($_GET['page'] ?? 1));

// Сбор всех категорий
$allCategories = [];
foreach ($articles as $article) {
    $cat = $article['category'] ?? 'Без категории';
    if (!in_array($cat, $allCategories)) {
        $allCategories[] = $cat;
    }
}
sort($allCategories);

// Фильтрация статей
$filteredArticles = $articles;

// Поиск по ключевому слову
if (!empty($searchQuery)) {
    $filteredArticles = searchArticles($searchQuery, $filteredArticles);
}

// Фильтр по категории
if (!empty($filterCategory)) {
    $filteredArticles = array_values(array_filter(
        $filteredArticles,
        fn($a) => ($a['category'] ?? 'Без категории') === $filterCategory
    ));
}

// Сортировка по дате (новые первыми)
usort($filteredArticles, fn($a, $b) => 
    strtotime($b['date'] ?? '0') - strtotime($a['date'] ?? '0')
);

// Пагинация
$totalArticles = count($filteredArticles);
$totalPages = max(1, (int)ceil($totalArticles / $perPage));
$currentPageNum = min($currentPageNum, $totalPages);
$offset = ($currentPageNum - 1) * $perPage;
$pageArticles = array_slice($filteredArticles, $offset, $perPage);

// SEO
$siteUrl = getCurrentSiteUrl();
$pageTitle = !empty($searchQuery)
    ? ('Поиск: ' . htmlspecialchars($searchQuery) . ' | ' . $siteName)
    : (!empty($filterCategory)
        ? ('Статьи: ' . htmlspecialchars($filterCategory) . ' | ' . $siteName)
        : ('Поиск по блогу | ' . $siteName));

$pageDescription = !empty($searchQuery)
    ? ('Результаты поиска по запросу «' . htmlspecialchars($searchQuery) . '» в блоге ' . $siteName)
    : (!empty($filterCategory)
        ? ('Статьи категории «' . htmlspecialchars($filterCategory) . '» в блоге ' . $siteName)
        : ('Поиск статей в блоге ' . $siteName . '. Фильтры по категориям и полнотекстовый поиск.'));

$canonical = '/blog/search/';
if (!empty($searchQuery)) {
    $canonical .= '?q=' . urlencode($searchQuery);
    if (!empty($filterCategory)) $canonical .= '&cat=' . urlencode($filterCategory);
    if ($currentPageNum > 1) $canonical .= '&page=' . $currentPageNum;
} elseif (!empty($filterCategory)) {
    $canonical .= '?cat=' . urlencode($filterCategory);
    if ($currentPageNum > 1) $canonical .= '&page=' . $currentPageNum;
} elseif ($currentPageNum > 1) {
    $canonical .= '?page=' . $currentPageNum;
}

$metaTags = metaTags(
    $pageTitle,
    $pageDescription,
    '',
    $canonical,
    '/images/blog/blog-default.jpg',
    'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
    'website'
);

// Schema.org для поиска
$schemaOrg = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SearchResultsPage",
  "name": "' . htmlspecialchars($pageTitle) . '",
  "description": "' . htmlspecialchars($pageDescription) . '",
  "url": "' . $siteUrl . $canonical . '",
  "potentialAction": {
    "@type": "SearchAction",
    "target": {
      "@type": "EntryPoint",
      "urlTemplate": "' . $siteUrl . '/blog/search/?q={search_term_string}"
    }
  }
}
</script>';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
    <div class="container">
        <?php echo breadcrumbs([
            ['name' => 'Главная', 'url' => '/'],
            ['name' => 'Блог', 'url' => '/info/'],
            ['name' => 'Поиск']
        ]); ?>
        <h1>Поиск по блогу</h1>
        <p>Найдите интересующие вас статьи по ключевым словам или категориям.</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <!-- Search Form -->
        <div class="blog-search-form" style="margin-bottom:32px;background:var(--bg-card);padding:24px;border-radius:16px;border:1px solid var(--border);">
            <form method="GET" action="/blog/search/" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div style="flex:1;min-width:200px;">
                    <label for="searchInput" style="display:block;font-size:0.875rem;font-weight:600;color:var(--text-muted);margin-bottom:6px;">Поиск по статьям</label>
                    <input type="text" id="searchInput" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Введите ключевое слово..." style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text-primary);font-size:0.9rem;">
                </div>
                
                <div style="min-width:180px;">
                    <label for="categorySelect" style="display:block;font-size:0.875rem;font-weight:600;color:var(--text-muted);margin-bottom:6px;">Категория</label>
                    <select id="categorySelect" name="cat" style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text-primary);font-size:0.9rem;">
                        <option value="">Все категории</option>
                        <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" style="padding:10px 24px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:all 0.2s;font-size:0.9rem;">
                    <i class="fa-solid fa-search"></i> Найти
                </button>
                
                <?php if (!empty($searchQuery) || !empty($filterCategory)): ?>
                <a href="/blog/search/" style="padding:10px 24px;background:var(--bg-hover);color:var(--text-primary);border:1px solid var(--border);border-radius:8px;font-weight:600;cursor:pointer;transition:all 0.2s;font-size:0.9rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-times"></i> Очистить
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Category Filter Buttons (HTML) -->
        <?php if (!empty($allCategories)): ?>
        <div class="blog-category-filter" style="margin-bottom:32px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <span style="font-size:0.875rem;font-weight:600;color:var(--text-muted);">Фильтры:</span>
            <a href="/blog/search/<?php echo !empty($searchQuery) ? '?q=' . urlencode($searchQuery) : ''; ?>" class="filter-btn <?php echo empty($filterCategory) ? 'active' : ''; ?>" style="padding:8px 16px;border-radius:20px;font-size:0.875rem;font-weight:600;border:2px solid <?php echo empty($filterCategory) ? 'var(--primary)' : 'var(--border)'; ?>;background:<?php echo empty($filterCategory) ? 'var(--primary)' : 'transparent'; ?>;color:<?php echo empty($filterCategory) ? '#fff' : 'var(--text-muted)'; ?>;text-decoration:none;transition:all 0.2s;">
                Все
            </a>
            <?php foreach ($allCategories as $cat): ?>
            <a href="/blog/search/?cat=<?php echo urlencode($cat); ?><?php echo !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : ''; ?>" class="filter-btn <?php echo $filterCategory === $cat ? 'active' : ''; ?>" style="padding:8px 16px;border-radius:20px;font-size:0.875rem;font-weight:600;border:2px solid <?php echo $filterCategory === $cat ? 'var(--primary)' : 'var(--border)'; ?>;background:<?php echo $filterCategory === $cat ? 'var(--primary)' : 'transparent'; ?>;color:<?php echo $filterCategory === $cat ? '#fff' : 'var(--text-muted)'; ?>;text-decoration:none;transition:all 0.2s;">
                <?php echo htmlspecialchars($cat); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Results Info -->
        <div style="margin-bottom:24px;padding:12px;background:rgba(79,70,229,0.1);border-radius:8px;border-left:4px solid var(--primary);">
            <p style="margin:0;font-size:0.9rem;color:var(--text-secondary);">
                <?php if (!empty($searchQuery)): ?>
                    Найдено <strong><?php echo $totalArticles; ?></strong> статей по запросу «<strong><?php echo htmlspecialchars($searchQuery); ?></strong>»
                    <?php if (!empty($filterCategory)): ?>
                        в категории «<strong><?php echo htmlspecialchars($filterCategory); ?></strong>»
                    <?php endif; ?>
                <?php elseif (!empty($filterCategory)): ?>
                    Всего <strong><?php echo $totalArticles; ?></strong> статей в категории «<strong><?php echo htmlspecialchars($filterCategory); ?></strong>»
                <?php else: ?>
                    Всего <strong><?php echo $totalArticles; ?></strong> статей в блоге
                <?php endif; ?>
            </p>
        </div>

        <!-- Search Results Grid -->
        <?php if (!empty($pageArticles)): ?>
        <div class="blog-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-bottom:40px;">
            <?php foreach ($pageArticles as $i => $article): ?>
            <article class="blog-card" style="border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--bg-card);transition:all 0.3s ease;display:flex;flex-direction:column;">
                <?php if (!empty($article['image'])): ?>
                <a href="/info/<?php echo htmlspecialchars($article['slug']); ?>/" class="blog-card-image" style="display:block;height:180px;overflow:hidden;background:var(--bg-hover);">
                    <img src="/images/blog/<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;" onerror="this.src='/images/icons/default.svg'">
                </a>
                <?php endif; ?>
                
                <div class="blog-card-content" style="padding:16px;flex:1;display:flex;flex-direction:column;">
                    <div class="blog-card-meta" style="display:flex;gap:12px;margin-bottom:8px;flex-wrap:wrap;">
                        <?php if (!empty($article['category'])): ?>
                        <span class="blog-card-cat" style="background:rgba(79,70,229,0.15);color:var(--primary);padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;">
                            <?php echo htmlspecialchars($article['category']); ?>
                        </span>
                        <?php endif; ?>
                        <span class="blog-card-date" style="font-size:0.75rem;color:var(--text-muted);">
                            <i class="fa-regular fa-calendar"></i> <?php echo date('d.m.Y', strtotime($article['date'] ?? date('Y-m-d'))); ?>
                        </span>
                    </div>
                    
                    <h3 class="blog-card-title" style="margin:0 0 8px;font-size:1rem;font-weight:600;line-height:1.3;flex:1;">
                        <a href="/info/<?php echo htmlspecialchars($article['slug']); ?>/" style="color:var(--text-primary);text-decoration:none;transition:color 0.2s;">
                            <?php echo htmlspecialchars($article['title']); ?>
                        </a>
                    </h3>
                    
                    <?php if (!empty($article['excerpt'])): ?>
                    <p class="blog-card-excerpt" style="margin:0 0 12px;font-size:0.85rem;color:var(--text-secondary);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                        <?php echo htmlspecialchars($article['excerpt']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <a href="/info/<?php echo htmlspecialchars($article['slug']); ?>/" class="blog-card-more" style="color:var(--primary);font-size:0.85rem;font-weight:600;text-decoration:none;transition:color 0.2s;display:inline-flex;align-items:center;gap:6px;margin-top:auto;">
                        Читать статью <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="blog-pagination" style="margin-top:40px;display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;">
            <?php if ($currentPageNum > 1): ?>
            <a href="/blog/search/?<?php echo http_build_query(array_filter(['q' => $searchQuery, 'cat' => $filterCategory, 'page' => $currentPageNum - 1])); ?>" class="pagination-btn" style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);color:var(--text);text-decoration:none;background:var(--bg-card);transition:all 0.2s;">
                <i class="fa-solid fa-chevron-left"></i> Назад
            </a>
            <?php endif; ?>
            
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="/blog/search/?<?php echo http_build_query(array_filter(['q' => $searchQuery, 'cat' => $filterCategory, 'page' => $p])); ?>" 
               style="padding:8px 14px;border-radius:8px;border:1px solid <?php echo $p === $currentPageNum ? 'var(--primary)' : 'var(--border)'; ?>;background:<?php echo $p === $currentPageNum ? 'var(--primary)' : 'var(--bg-card)'; ?>;color:<?php echo $p === $currentPageNum ? '#fff' : 'var(--text)'; ?>;text-decoration:none;font-weight:<?php echo $p === $currentPageNum ? '600' : '400'; ?>;transition:all 0.2s;">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($currentPageNum < $totalPages): ?>
            <a href="/blog/search/?<?php echo http_build_query(array_filter(['q' => $searchQuery, 'cat' => $filterCategory, 'page' => $currentPageNum + 1])); ?>" class="pagination-btn" style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);color:var(--text);text-decoration:none;background:var(--bg-card);transition:all 0.2s;">
                Вперёд <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <div style="text-align:center;margin-top:12px;color:var(--text-muted);font-size:0.875rem;">
            Страница <?php echo $currentPageNum; ?> из <?php echo $totalPages; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center" style="padding:60px 20px;">
            <i class="fa-solid fa-search" style="font-size:3rem;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
            <h3>Статьи не найдены</h3>
            <p class="text-muted" style="margin-top:8px;">
                <?php if (!empty($searchQuery)): ?>
                    По запросу «<?php echo htmlspecialchars($searchQuery); ?>» статей не найдено. Попробуйте другие ключевые слова.
                <?php else: ?>
                    В выбранной категории нет статей.
                <?php endif; ?>
            </p>
            <a href="/blog/search/" style="color:var(--primary);margin-top:16px;display:inline-block;text-decoration:none;font-weight:600;">Вернуться к поиску</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="toast-container" id="toastContainer"></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
