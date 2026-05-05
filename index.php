<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

$theme = cms_get_setting('theme', 'default');
$themeFile = __DIR__ . '/themes/' . $theme . '/layout.php';
if (!is_file($themeFile)) {
    $themeFile = __DIR__ . '/themes/default/layout.php';
}

$siteName = cms_get_setting('site_name', 'My CMS');
$siteTagline = cms_get_setting('site_tagline', 'Lekki system CMS');
$siteMode = cms_site_mode();
$themeVariant = cms_theme_variant();
$slug = cms_slugify($_GET['page'] ?? '');
$onepageSections = [];

if ($siteMode === 'onepage' && ($slug === '' || $slug === 'strona')) {
    $onepageSections = cms_root_pages(true);
    $page = $onepageSections[0] ?? [
        'title' => $siteName,
        'slug' => 'start',
        'excerpt' => $siteTagline,
        'content' => '',
        'builder_data' => '[]',
        'status' => 'published',
        'is_homepage' => 1,
        'template' => 'default',
    ];
    $navigationPages = $onepageSections;
} else {
    $page = $slug !== '' && $slug !== 'strona' ? cms_find_page_by_slug($slug) : cms_homepage();
    if (!$page) {
        http_response_code(404);
        $page = [
            'title' => cms_translate('error.404_title', '404'),
            'slug' => '404',
            'excerpt' => cms_translate('error.404_excerpt', 'Nie znaleziono strony.'),
            'content' => '<h2>' . htmlspecialchars(cms_translate('error.404_heading', 'Nie znaleziono strony.'), ENT_QUOTES, 'UTF-8') . '</h2><p>' . htmlspecialchars(cms_translate('error.404_message', 'Sprawdz adres lub utworz nowa strone w panelu CMS.'), ENT_QUOTES, 'UTF-8') . '</p>',
            'builder_data' => '[]',
            'status' => 'published',
            'is_homepage' => 0,
            'template' => 'default',
        ];
    }
    $navigationPages = cms_root_pages(true);
}

require $themeFile;
