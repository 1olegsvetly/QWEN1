<?php
require_once __DIR__ . '/../includes/blog_seo.php';

$currentPage = 'info';
$pagesData = getPages();
$articles = $pagesData['info']['articles'] ?? [];
$articleSlug = sanitize($_GET['article'] ?? '');
$siteName = $settings['site']['name'] ?? 'Магазин аккаунтов';

// Single article view
if ($articleSlug) {
    $article = null;
    foreach ($articles as $a) {
        if ($a['slug'] === $articleSlug) { $article = $a; break; }
    }
    if ($article) {
        $siteUrl = getCurrentSiteUrl();
        $articleCanonical = '/info/' . $articleSlug . '/';
        $articleTitle = ($article['seo_title'] ?: $article['title']) . ' | ' . $siteName;
        $articleDescription = ($article['seo_description'] ?: $article['excerpt']);
        // Auto-generate keywords for article (applies to all future articles too)
        $articleKeywords = !empty($article['seo_keywords'])
            ? $article['seo_keywords']
            : generateKeywords(['title' => $article['title'], 'category' => $article['category'] ?? ''], 'article');
        $metaTags = metaTags(
            $articleTitle,
            $articleDescription,
            $articleKeywords,
            $articleCanonical,
            '/images/blog/' . ($article['image'] ?? 'blog-default.jpg'),
            'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'article'
        );
        $schemaOrg = '<script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@graph": [
            {
              "@type": "Article",
              "headline": "' . htmlspecialchars($article['title']) . '",
              "description": "' . htmlspecialchars($articleDescription) . '",
              "datePublished": "' . htmlspecialchars($article['date'] ?? date('Y-m-d')) . '",
              "dateModified": "' . htmlspecialchars($article['date'] ?? date('Y-m-d')) . '",
              "author": {
                "@type": "Organization",
                "name": "' . htmlspecialchars($siteName) . '"
              },
              "publisher": {
                "@type": "Organization",
                "name": "' . htmlspecialchars($siteName) . '",
                "logo": {
                  "@type": "ImageObject",
                  "url": "' . $siteUrl . '/images/ui/favicon.svg"
                }
              },
              "image": "' . $siteUrl . '/images/blog/' . ($article['image'] ?? 'blog-default.jpg') . '",
              "mainEntityOfPage": "' . $siteUrl . $articleCanonical . '"
            },
            {
              "@type": "BreadcrumbList",
              "itemListElement": [
                {
                  "@type": "ListItem",
                  "position": 1,
                  "name": "Главная",
                  "item": "' . $siteUrl . '/"
                },
                {
                  "@type": "ListItem",
                  "position": 2,
                  "name": "Блог",
                  "item": "' . $siteUrl . '/info/"
                },
                {
                  "@type": "ListItem",
                  "position": 3,
                  "name": "' . htmlspecialchars($article['title']) . '",
                  "item": "' . $siteUrl . $articleCanonical . '"
                }
              ]
            }
          ]
        }
        </script>';
        require __DIR__ . '/../includes/header.php';
        ?>
        <div class="page-hero article-hero">
            <div class="container">
                <?php echo breadcrumbs([['name' => 'Главная', 'url' => '/'], ['name' => 'Блог', 'url' => '/info/'], ['name' => $article['title']]]); ?>
                <div class="article-meta-top">
                    <span class="article-category"><?php echo htmlspecialchars($article['category'] ?? 'Блог'); ?></span>
                    <span class="article-date-top"><i class="fa-regular fa-calendar"></i> <?php echo date('d.m.Y', strtotime($article['date'])); ?></span>
                </div>
                <h1><?php echo htmlspecialchars($article['title']); ?></h1>
            </div>
        </div>

        <section class="section article-section">
            <div class="container">
                <div class="article-layout">
                    <article class="article-main-content">
                        <?php if (!empty($article['image'])): ?>
                        <div class="article-featured-image">
                            <img src="/images/blog/<?php echo $article['image']; ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" onerror="this.src='/images/icons/default.svg'">
                        </div>
                        <?php endif; ?>
                        
                        <div class="article-body-text" data-editable="article:<?php echo htmlspecialchars($article['slug']); ?>:content">
                            <?php echo $article['content']; // Allow HTML for SEO articles ?>
                        </div>
                        
                        <div class="article-footer-nav">
                            <a href="/info/" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Назад в блог</a>
                            <div class="article-share">
                                <span>Поделиться:</span>
                                <?php $shareUrl = rtrim(getCurrentSiteUrl(), '/') . '/info/' . $articleSlug . '/'; ?>
                                <a href="https://t.me/share/url?url=<?php echo urlencode($shareUrl); ?>&text=<?php echo urlencode($article['title']); ?>" target="_blank"><i class="fa-brands fa-telegram"></i></a>
                                <a href="https://vk.com/share.php?url=<?php echo urlencode($shareUrl); ?>" target="_blank"><i class="fa-brands fa-vk"></i></a>
                            </div>
                        </div>
                        
                        <!-- Похожие статьи -->
                        <?php
                        $relatedArticles = getRelatedArticles($article, $articles, 3);
                        if (!empty($relatedArticles)) {
                            echo renderRelatedArticles($relatedArticles);
                        }
                        ?>
                    </article>
                    
                    <aside class="article-sidebar">
                        <?php
                        // Collect unique article categories for sidebar
                        $artCats = [];
                        foreach ($articles as $a) {
                            $c = $a['category'] ?? 'Без категории';
                            if (!in_array($c, $artCats)) $artCats[] = $c;
                        }
                        ?>
                        <?php if (!empty($artCats)): ?>
                        <div class="sidebar-widget">
                            <h3>Категории блога</h3>
                            <ul class="sidebar-cats">
                                <?php foreach ($artCats as $ac): ?>
                                <li><a href="/info/?cat=<?php echo urlencode($ac); ?>"><?php echo htmlspecialchars($ac); ?> <i class="fa-solid fa-chevron-right"></i></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <div class="sidebar-widget">
                            <h3>Популярные категории</h3>
                            <ul class="sidebar-cats">
                                <?php foreach (array_slice(getCategories(), 0, 5) as $c): ?>
                                <li><a href="/category/<?php echo $c['slug']; ?>/"><?php echo $c['name']; ?> <i class="fa-solid fa-chevron-right"></i></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="sidebar-widget promo-widget">
                            <h3>Нужны аккаунты?</h3>
                            <p>У нас более 15,000 качественных аккаунтов в наличии с моментальной выдачей.</p>
                            <a href="/#categories" class="btn btn-primary btn-full">В каталог</a>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <div class="toast-container" id="toastContainer"></div>
        <?php require __DIR__ . '/../includes/footer.php';
        exit;
    }
}

// Articles list with categories and pagination
$filterCat = sanitize($_GET['cat'] ?? '');
$perPage = 9;
// Support both ?page=N (from /info/ URL) and direct GET
// If page is numeric, treat it as page number (pagination)
$rawPage = $_GET['page_num'] ?? $_GET['p'] ?? null;
if ($rawPage === null) {
    $rawPage2 = $_GET['page'] ?? null;
    if ($rawPage2 !== null && is_numeric($rawPage2)) {
        $rawPage = $rawPage2;
    } else {
        $rawPage = 1;
    }
}
$currentPageNum = max(1, (int)$rawPage);

// Collect all unique categories
$allArticleCategories = [];
foreach ($articles as $a) {
    $c = $a['category'] ?? 'Без категории';
    if (!in_array($c, $allArticleCategories)) $allArticleCategories[] = $c;
}

// Filter by category if needed
$filteredArticles = array_reverse($articles);
if ($filterCat) {
    $filteredArticles = array_values(array_filter($filteredArticles, fn($a) => ($a['category'] ?? 'Без категории') === $filterCat));
}

$totalArticles = count($filteredArticles);
$totalPages = max(1, (int)ceil($totalArticles / $perPage));
$currentPageNum = min($currentPageNum, $totalPages);
$offset = ($currentPageNum - 1) * $perPage;
$pageArticles = array_slice($filteredArticles, $offset, $perPage);

$siteUrl = getCurrentSiteUrl();
$infoPageSeo = $pagesData['info'] ?? [];
$blogTitle = $filterCat
    ? ('Блог: ' . $filterCat . ' | ' . $siteName)
    : (!empty($infoPageSeo['title']) ? $infoPageSeo['title'] : ('Блог - Полезные статьи и гайды по аккаунтам | ' . $siteName));
$blogDescription = $filterCat
    ? ('Статьи категории «' . $filterCat . '»: руководства, инструкции и практические советы по работе с аккаунтами.')
    : (!empty($infoPageSeo['description']) ? $infoPageSeo['description'] : 'Читайте наш блог: руководства по выбору аккаунтов, секреты фарма, работа с прокси и антидетект-браузерами.');
$queryParts = [];
if ($filterCat) $queryParts[] = 'cat=' . urlencode($filterCat);
if ($currentPageNum > 1) $queryParts[] = 'page=' . $currentPageNum;
$canonical = '/info/' . (!empty($queryParts) ? ('?' . implode('&', $queryParts)) : '');
$metaTags = metaTags(
    $blogTitle,
    $blogDescription,
    '',
    $canonical,
    '/images/blog/blog-default.jpg',
    'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
    'website'
);
$schemaOrg = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Blog",
      "name": "' . htmlspecialchars($blogTitle) . '",
      "description": "' . htmlspecialchars($blogDescription) . '",
      "url": "' . $siteUrl . $canonical . '"
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Главная",
          "item": "' . $siteUrl . '/"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "Блог",
          "item": "' . $siteUrl . '/info/"
        }' . ($filterCat ? ',
        {
          "@type": "ListItem",
          "position": 3,
          "name": "' . htmlspecialchars($filterCat) . '",
          "item": "' . $siteUrl . $canonical . '"
        }' : '') . '
      ]
    }
  ]
}
</script>';
if ($currentPageNum > 1) {
    $prevQueryParts = [];
    if ($filterCat) $prevQueryParts[] = 'cat=' . urlencode($filterCat);
    if ($currentPageNum > 2) $prevQueryParts[] = 'page=' . ($currentPageNum - 1);
    $prevHref = '/info/' . (!empty($prevQueryParts) ? ('?' . implode('&', $prevQueryParts)) : '');
    $metaTags .= '<link rel="prev" href="' . htmlspecialchars($siteUrl . $prevHref) . '">' . "\n";
}
if ($currentPageNum < $totalPages) {
    $nextQueryParts = [];
    if ($filterCat) $nextQueryParts[] = 'cat=' . urlencode($filterCat);
    $nextQueryParts[] = 'page=' . ($currentPageNum + 1);
    $nextHref = '/info/' . '?' . implode('&', $nextQueryParts);
    $metaTags .= '<link rel="next" href="' . htmlspecialchars($siteUrl . $nextHref) . '">' . "\n";
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
    <div class="container">
        <?php echo breadcrumbs([['name' => 'Главная', 'url' => '/'], ['name' => 'Блог']]); ?>
        <h1>Блог и Полезные Материалы</h1>
        <p>Актуальные гайды, новости и советы по работе с социальными сетями.</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <!-- Category Filter Tabs -->
        <?php if (!empty($allArticleCategories)): ?>
        <div class="blog-category-tabs" style="margin-bottom:32px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <a href="/info/" class="blog-cat-tab <?php echo !$filterCat ? 'active' : ''; ?>" style="padding:8px 18px;border-radius:20px;font-size:0.875rem;font-weight:600;border:2px solid <?php echo !$filterCat ? 'var(--primary)' : 'var(--border)'; ?>;background:<?php echo !$filterCat ? 'var(--primary)' : 'transparent'; ?>;color:<?php echo !$filterCat ? '#fff' : 'var(--text-muted)'; ?>;text-decoration:none;transition:all 0.2s;">
                Все статьи <span style="opacity:0.7;">(<?php echo count($articles); ?>)</span>
            </a>
            <?php foreach ($allArticleCategories as $ac):
                $acCount = count(array_filter($articles, fn($a) => ($a['category'] ?? 'Без категории') === $ac));
                $isActive = $filterCat === $ac;
            ?>
            <a href="/info/?cat=<?php echo urlencode($ac); ?>" class="blog-cat-tab <?php echo $isActive ? 'active' : ''; ?>" style="padding:8px 18px;border-radius:20px;font-size:0.875rem;font-weight:600;border:2px solid <?php echo $isActive ? 'var(--primary)' : 'var(--border)'; ?>;background:<?php echo $isActive ? 'var(--primary)' : 'transparent'; ?>;color:<?php echo $isActive ? '#fff' : 'var(--text-muted)'; ?>;text-decoration:none;transition:all 0.2s;">
                <?php echo htmlspecialchars($ac); ?> <span style="opacity:0.7;">(<?php echo $acCount; ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($pageArticles)): ?>
        <div class="blog-grid">
            <?php foreach ($pageArticles as $i => $article): ?>
            <article class="blog-card animate-on-scroll delay-<?php echo min($i, 5); ?>">
                <a href="/info/<?php echo $article['slug']; ?>/" class="blog-card-image">
                    <img src="/images/blog/<?php echo $article['image'] ?? 'blog-default.jpg'; ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" loading="lazy" onerror="this.src='/images/icons/default.svg'">
                </a>
                <div class="blog-card-content">
                    <div class="blog-card-meta">
                        <?php if (!empty($article['category'])): ?>
                        <span class="blog-card-cat" style="background:rgba(79,70,229,0.15);color:var(--primary);padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;"><?php echo htmlspecialchars($article['category']); ?></span>
                        <?php endif; ?>
                        <span class="blog-card-date"><?php echo date('d.m.Y', strtotime($article['date'])); ?></span>
                    </div>
                    <h3 class="blog-card-title">
                        <a href="/info/<?php echo $article['slug']; ?>/"><?php echo htmlspecialchars($article['title']); ?></a>
                    </h3>
                    <p class="blog-card-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                    <a href="/info/<?php echo $article['slug']; ?>/" class="blog-card-more">Читать статью <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php
        $_adBlogFeedHtml = renderAdSpot('blog_feed_banner');
        if (!empty($_adBlogFeedHtml)):
        ?>
        <div class="ad-section ad-section--blog-feed" style="margin-top:32px;">
            <?php echo $_adBlogFeedHtml; ?>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="blog-pagination" style="margin-top:40px;display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;">
            <?php if ($currentPageNum > 1): ?>
            <a href="/info/?<?php echo $filterCat ? 'cat='.urlencode($filterCat).'&' : ''; ?>page=<?php echo $currentPageNum - 1; ?>" class="pagination-btn" style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);color:var(--text);text-decoration:none;background:var(--bg-card);">
                <i class="fa-solid fa-chevron-left"></i> Назад
            </a>
            <?php endif; ?>
            
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="/info/?<?php echo $filterCat ? 'cat='.urlencode($filterCat).'&' : ''; ?>page=<?php echo $p; ?>" 
               style="padding:8px 14px;border-radius:8px;border:1px solid <?php echo $p === $currentPageNum ? 'var(--primary)' : 'var(--border)'; ?>;background:<?php echo $p === $currentPageNum ? 'var(--primary)' : 'var(--bg-card)'; ?>;color:<?php echo $p === $currentPageNum ? '#fff' : 'var(--text)'; ?>;text-decoration:none;font-weight:<?php echo $p === $currentPageNum ? '600' : '400'; ?>;">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($currentPageNum < $totalPages): ?>
            <a href="/info/?<?php echo $filterCat ? 'cat='.urlencode($filterCat).'&' : ''; ?>page=<?php echo $currentPageNum + 1; ?>" class="pagination-btn" style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);color:var(--text);text-decoration:none;background:var(--bg-card);">
                Вперёд <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <div style="text-align:center;margin-top:12px;color:var(--text-muted);font-size:0.875rem;">
            Страница <?php echo $currentPageNum; ?> из <?php echo $totalPages; ?> · Всего статей: <?php echo $totalArticles; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center" style="padding:60px 0;">
            <i class="fa-solid fa-newspaper" style="font-size:3rem;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
            <h3><?php echo $filterCat ? 'В категории «'.htmlspecialchars($filterCat).'» нет статей' : 'Статьи скоро появятся'; ?></h3>
            <p class="text-muted mt-8">
                <?php if ($filterCat): ?>
                <a href="/info/" style="color:var(--primary);">Посмотреть все статьи</a>
                <?php else: ?>
                Раздел наполняется контентом.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="toast-container" id="toastContainer"></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
