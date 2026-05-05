<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page['title']) ?> | <?= htmlspecialchars($siteName) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page['excerpt'] ?: $siteTagline) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('themes/default/style.css')) ?>">
    <?= cms_get_theme_css() ?>
</head>
<body class="mode-<?= htmlspecialchars($themeVariant) ?>">
<header class="site-header">
    <div class="wrap header-inner">
        <div>
            <a class="brand" href="<?= htmlspecialchars(cms_url()) ?>"><?= htmlspecialchars($siteName) ?></a>
            <div class="tagline"><?= htmlspecialchars($siteTagline) ?></div>
        </div>
        <nav class="nav">
            <?php if ($siteMode === 'onepage' && !empty($onepageSections)): ?>
                <?php foreach ($onepageSections as $navPage): ?>
                    <a href="#section-<?= htmlspecialchars($navPage['slug']) ?>"><?= htmlspecialchars($navPage['title']) ?></a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($navigationPages as $navPage): ?>
                    <a href="<?= htmlspecialchars(cms_url('?page=' . urlencode((string) $navPage['slug']))) ?>" class="<?= $navPage['slug'] === $page['slug'] ? 'active' : '' ?>"><?= htmlspecialchars($navPage['title']) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="<?= htmlspecialchars(cms_url('admin/index.php')) ?>">Admin</a>
        </nav>
    </div>
</header>
<main class="<?= $siteMode === 'onepage' ? '' : 'wrap main-content' ?>">
    <?php if ($siteMode === 'onepage' && !empty($onepageSections)): ?>
        <?php foreach ($onepageSections as $section): ?>
            <section class="onepage-section" id="section-<?= htmlspecialchars($section['slug']) ?>">
                <div class="wrap">
                    <article class="page-card">
                        <p class="eyebrow">Onepage section</p>
                        <h1><?= htmlspecialchars($section['title']) ?></h1>
                        <?php if (!empty($section['excerpt'])): ?>
                            <p class="excerpt"><?= htmlspecialchars($section['excerpt']) ?></p>
                        <?php endif; ?>
                        <?= cms_render_page_plugins($section, 'before_content') ?>
                        <div class="content"><?= cms_render_builder_blocks($section) ?></div>
                        <?= cms_render_page_plugins($section, 'after_content') ?>
                        <?php $children = cms_child_pages((int) $section['id'], true); if ($children): ?>
                            <div class="child-links">
                                <strong>Powiazane podstrony:</strong>
                                <?php foreach ($children as $child): ?>
                                    <a href="<?= htmlspecialchars(cms_url('?page=' . urlencode((string) $child['slug']))) ?>"><?= htmlspecialchars($child['title']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <article class="page-card">
            <p class="eyebrow"><?= !empty($page['parent_id']) ? 'Subpage' : 'CMS Page' ?></p>
            <h1><?= htmlspecialchars($page['title']) ?></h1>
            <?php if (!empty($page['excerpt'])): ?>
                <p class="excerpt"><?= htmlspecialchars($page['excerpt']) ?></p>
            <?php endif; ?>
            <?= cms_render_page_plugins($page, 'before_content') ?>
            <div class="content"><?= cms_render_builder_blocks($page) ?></div>
            <?= cms_render_page_plugins($page, 'after_content') ?>
            <?php $children = !empty($page['id']) ? cms_child_pages((int) $page['id'], true) : []; if ($children): ?>
                <div class="child-links">
                    <strong>Podstrony:</strong>
                    <?php foreach ($children as $child): ?>
                        <a href="<?= htmlspecialchars(cms_url('?page=' . urlencode((string) $child['slug']))) ?>"><?= htmlspecialchars($child['title']) ?></a>
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
        <span>Tryb: <?= htmlspecialchars($siteMode) ?> | Pluginy i sklep GitHub gotowe</span>
    </div>
    <?php $footerHook = cms_collect_hook_output('theme_footer'); if ($footerHook !== ''): ?>
        <div class="wrap hook-zone footer-hook"><?= $footerHook ?></div>
    <?php endif; ?>
</footer>
</body>
</html>
