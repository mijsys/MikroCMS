<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(cms_current_language()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $seoTitle = trim((string) ($page['meta_title'] ?? '')) !== '' ? (string) $page['meta_title'] : (string) $page['title']; ?>
    <?php $seoDescription = trim((string) ($page['meta_description'] ?? '')) !== '' ? (string) $page['meta_description'] : (string) ($page['excerpt'] ?: $siteTagline); ?>
    <title><?= htmlspecialchars($seoTitle) ?> | <?= htmlspecialchars($siteName) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars(cms_url_with_lang(['page' => (string) ($page['slug'] ?? '')])) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('themes/default/style.css')) ?>">
    <?= cms_get_theme_css() ?>
</head>
<body class="mode-<?= htmlspecialchars($themeVariant) ?>">
<header class="site-header">
    <div class="wrap header-inner">
        <div>
            <a class="brand" href="<?= htmlspecialchars(cms_url_with_lang()) ?>"><?= htmlspecialchars($siteName) ?></a>
            <div class="tagline"><?= htmlspecialchars($siteTagline) ?></div>
        </div>
        <nav class="nav">
            <?php if ($siteMode === 'onepage' && !empty($onepageSections)): ?>
                <?php foreach ($onepageSections as $navPage): ?>
                    <a href="#section-<?= htmlspecialchars($navPage['slug']) ?>"><?= htmlspecialchars($navPage['title']) ?></a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($navigationPages as $navPage): ?>
                    <a href="<?= htmlspecialchars(cms_url_with_lang(['page' => (string) $navPage['slug']])) ?>" class="<?= $navPage['slug'] === $page['slug'] ? 'active' : '' ?>"><?= htmlspecialchars($navPage['title']) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php foreach (cms_enabled_languages() as $langCode): ?>
                <a href="<?= htmlspecialchars(cms_url_with_lang(['page' => (string) ($page['slug'] ?? ''), 'lang' => $langCode])) ?>" class="<?= cms_current_language() === $langCode ? 'active' : '' ?>"><?= strtoupper(htmlspecialchars($langCode)) ?></a>
            <?php endforeach; ?>
            <a href="<?= htmlspecialchars(cms_url('admin/index.php')) ?>"><?= htmlspecialchars(cms_translate('nav.admin', 'Admin')) ?></a>
        </nav>
    </div>
</header>
<main class="<?= $siteMode === 'onepage' ? '' : 'wrap main-content' ?>">
    <?php if ($siteMode === 'onepage' && !empty($onepageSections)): ?>
        <?php foreach ($onepageSections as $section): ?>
            <section class="onepage-section" id="section-<?= htmlspecialchars($section['slug']) ?>">
                <div class="wrap">
                    <article class="page-card">
                        <p class="eyebrow"><?= htmlspecialchars(cms_translate('label.onepage_section', 'Onepage section')) ?></p>
                        <h1><?= htmlspecialchars($section['title']) ?></h1>
                        <?php if (!empty($section['excerpt'])): ?>
                            <p class="excerpt"><?= htmlspecialchars($section['excerpt']) ?></p>
                        <?php endif; ?>
                        <?= cms_render_page_plugins($section, 'before_content') ?>
                        <div class="content"><?= cms_render_builder_blocks($section) ?></div>
                        <?= cms_render_page_plugins($section, 'after_content') ?>
                        <?php $children = cms_child_pages((int) $section['id'], true); if ($children): ?>
                            <div class="child-links">
                                <strong><?= htmlspecialchars(cms_translate('label.related_subpages', 'Powiazane podstrony:')) ?></strong>
                                <?php foreach ($children as $child): ?>
                                    <a href="<?= htmlspecialchars(cms_url_with_lang(['page' => (string) $child['slug']])) ?>"><?= htmlspecialchars($child['title']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <article class="page-card">
            <p class="eyebrow"><?= !empty($page['parent_id']) ? htmlspecialchars(cms_translate('label.subpage', 'Subpage')) : htmlspecialchars(cms_translate('label.cms_page', 'CMS Page')) ?></p>
            <h1><?= htmlspecialchars($page['title']) ?></h1>
            <?php if (!empty($page['excerpt'])): ?>
                <p class="excerpt"><?= htmlspecialchars($page['excerpt']) ?></p>
            <?php endif; ?>
            <?= cms_render_page_plugins($page, 'before_content') ?>
            <div class="content"><?= cms_render_builder_blocks($page) ?></div>
            <?= cms_render_page_plugins($page, 'after_content') ?>
            <?php $children = !empty($page['id']) ? cms_child_pages((int) $page['id'], true) : []; if ($children): ?>
                <div class="child-links">
                    <strong><?= htmlspecialchars(cms_translate('label.subpages', 'Podstrony:')) ?></strong>
                    <?php foreach ($children as $child): ?>
                        <a href="<?= htmlspecialchars(cms_url_with_lang(['page' => (string) $child['slug']])) ?>"><?= htmlspecialchars($child['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
        <?php $afterContent = cms_collect_hook_output('theme_after_content', $page); if ($afterContent !== ''): ?>
            <section class="hook-zone"><?= $afterContent ?></section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<footer class="site-footer">
    <div class="wrap footer-inner">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?></span>
        <span><?= htmlspecialchars(cms_translate('footer.mode', 'Tryb:')) ?> <?= htmlspecialchars($siteMode) ?> | <?= htmlspecialchars(cms_translate('footer.plugins_ready', 'Pluginy i sklep GitHub gotowe')) ?></span>
    </div>
    <?php $footerHook = cms_collect_hook_output('theme_footer'); if ($footerHook !== ''): ?>
        <div class="wrap hook-zone footer-hook"><?= $footerHook ?></div>
    <?php endif; ?>
</footer>
</body>
</html>
