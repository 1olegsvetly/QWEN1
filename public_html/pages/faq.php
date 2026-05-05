<?php
$currentPage = 'faq';
$pagesData = getPages();
$faqData = $pagesData['faq'] ?? [];
$faqItems = $faqData['items'] ?? [];

$faqSeoTitle = !empty($faqData['title']) ? $faqData['title'] : ('FAQ - Частые вопросы | ' . ($settings['site']['name'] ?? 'Магазин аккаунтов'));
$faqSeoDesc = !empty($faqData['description']) ? $faqData['description'] : 'Ответы на популярные вопросы о покупке аккаунтов: гарантии, оплата, доставка, возврат.';
$metaTags = metaTags($faqSeoTitle, $faqSeoDesc, '', '/faq/');

$schemaOrg = '<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [' . implode(',', array_map(function($item) {
    return '{"@type":"Question","name":"' . htmlspecialchars($item['question']) . '","acceptedAnswer":{"@type":"Answer","text":"' . htmlspecialchars($item['answer']) . '"}}';
}, $faqItems)) . ']
}
</script>';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
    <div class="container">
        <?php echo breadcrumbs([['name' => 'Главная', 'url' => '/'], ['name' => 'FAQ']]); ?>
        <h1>Часто Задаваемые Вопросы</h1>
        <p>Ответы на популярные вопросы о покупке аккаунтов, гарантиях, оплате и доставке.</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="max-width:800px;margin:0 auto;">
            <?php if (!empty($faqItems)): ?>
            <div class="faq-list">
                <?php foreach ($faqItems as $i => $item): ?>
                <div class="faq-item animate-on-scroll delay-<?php echo min($i, 5); ?>">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <?php echo htmlspecialchars($item['question']); ?>
                        <i class="fa-solid fa-chevron-down faq-question-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner"><?php echo htmlspecialchars($item['answer']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center" style="padding:60px 0;">
                <p class="text-muted">FAQ пока не заполнен. Задайте вопрос в поддержку.</p>
            </div>
            <?php endif; ?>

            <?php
            $_adFaqHelpHtml = renderAdSpot('faq_help_banner');
            if (!empty($_adFaqHelpHtml)):
            ?>
            <div class="ad-section ad-section--faq-help" style="margin-top:32px;">
                <?php echo $_adFaqHelpHtml; ?>
            </div>
            <?php endif; ?>

            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--border-radius);padding:32px;margin-top:40px;text-align:center;">
                <h3 style="margin-bottom:12px;">Не нашли ответ?</h3>
                <p style="color:var(--text-secondary);margin-bottom:20px;">Напишите нам в Telegram - ответим в течение 15 минут.</p>
                <a href="<?php echo $settings['contacts']['telegram_url'] ?? '#'; ?>" target="_blank" rel="noopener" class="btn btn-primary">
                    <i class="fa-brands fa-telegram"></i> Написать в Telegram
                </a>
            </div>
        </div>
    </div>
</section>

<div class="toast-container" id="toastContainer"></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
