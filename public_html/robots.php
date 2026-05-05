<?php
require_once __DIR__ . '/includes/functions.php';
// Используем текущий домен запроса, а не домен из settings.json.
// Это позволяет развернуть CMS на любом домене без риска дублей.
$siteUrl = getCurrentSiteUrl();
header('Content-Type: text/plain; charset=utf-8');
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /api/
Disallow: /data/
Disallow: /includes/

Sitemap: <?php echo $siteUrl; ?>/sitemap.xml
