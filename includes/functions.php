<?php
declare(strict_types=1);

function cms_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function cms_base_path(): string
{
    static $base = null;
    if (is_string($base)) {
        return $base;
    }

    $appRoot = realpath(__DIR__ . '/..');
    $docRootRaw = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $docRoot = is_string($docRootRaw) ? realpath($docRootRaw) : false;

    if (is_string($appRoot) && is_string($docRoot)) {
        $appRoot = str_replace('\\', '/', $appRoot);
        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');

        if ($appRoot === $docRoot) {
            $base = '/';
            return $base;
        }

        $prefix = $docRoot . '/';
        if (str_starts_with($appRoot, $prefix)) {
            $relative = trim(substr($appRoot, strlen($docRoot)), '/');
            $base = '/' . $relative;
            return $base;
        }
    }

    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = '/' . trim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($scriptDir === '/admin') {
        $scriptDir = '/';
    } elseif (str_ends_with($scriptDir, '/admin')) {
        $scriptDir = substr($scriptDir, 0, -6);
    }

    $base = $scriptDir === '' ? '/' : $scriptDir;
    return $base;
}

function cms_url(string $path = ''): string
{
    $base = cms_base_path();
    $path = trim($path);

    if ($path === '') {
        return $base;
    }

    if ($path[0] === '?') {
        return rtrim($base, '/') . '/' . $path;
    }

    if ($base === '/') {
        return '/' . ltrim($path, '/');
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function cms_flash(string $type, string $message): void
{
    cms_session_start();
    $_SESSION['cms_flash'] = ['type' => $type, 'message' => $message];
}

function cms_pull_flash(): ?array
{
    cms_session_start();
    if (empty($_SESSION['cms_flash'])) {
        return null;
    }

    $flash = $_SESSION['cms_flash'];
    unset($_SESSION['cms_flash']);

    return $flash;
}

function cms_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'strona';
}

function cms_normalize_lang_code(string $lang, string $fallback = 'pl'): string
{
    $lang = strtolower(trim($lang));
    if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $lang)) {
        return $lang;
    }
    return $fallback;
}

function cms_default_language(): string
{
    return cms_normalize_lang_code(cms_get_setting('site_default_language', 'pl'), 'pl');
}

function cms_enabled_languages(): array
{
    $raw = trim(cms_get_setting('site_enabled_languages', 'pl,en'));
    $parts = $raw === '' ? [] : preg_split('/\s*,\s*/', $raw);
    if (!is_array($parts)) {
        $parts = [];
    }

    $langs = [];
    foreach ($parts as $part) {
        $normalized = cms_normalize_lang_code((string) $part, '');
        if ($normalized !== '' && !in_array($normalized, $langs, true)) {
            $langs[] = $normalized;
        }
    }

    $default = cms_default_language();
    if (!in_array($default, $langs, true)) {
        array_unshift($langs, $default);
    }

    return $langs === [] ? [$default] : $langs;
}

function cms_current_language(): string
{
    static $current = null;
    if (is_string($current)) {
        return $current;
    }

    $default = cms_default_language();
    $allowed = cms_enabled_languages();

    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($scriptName, '/admin/')) {
        $current = $default;
        return $current;
    }

    cms_session_start();
    $queryLang = isset($_GET['lang']) ? cms_normalize_lang_code((string) $_GET['lang'], $default) : '';
    if ($queryLang !== '' && in_array($queryLang, $allowed, true)) {
        $_SESSION['cms_lang'] = $queryLang;
        $current = $queryLang;
        return $current;
    }

    $sessionLang = isset($_SESSION['cms_lang']) ? cms_normalize_lang_code((string) $_SESSION['cms_lang'], $default) : '';
    if ($sessionLang !== '' && in_array($sessionLang, $allowed, true)) {
        $current = $sessionLang;
        return $current;
    }

    $current = $default;
    return $current;
}

function cms_url_with_lang(array $params = []): string
{
    $lang = isset($params['lang'])
        ? cms_normalize_lang_code((string) $params['lang'], cms_default_language())
        : cms_current_language();

    if ($lang !== cms_default_language()) {
        $params['lang'] = $lang;
    } else {
        unset($params['lang']);
    }

    if ($params === []) {
        return cms_url();
    }

    return cms_url('?' . http_build_query($params));
}

function cms_page_translation(int $pageId, string $lang): ?array
{
    if ($pageId <= 0 || $lang === '' || $lang === cms_default_language()) {
        return null;
    }

    $stmt = cms_db()->prepare('SELECT * FROM cms_page_translations WHERE page_id = ? AND lang = ? LIMIT 1');
    $stmt->execute([$pageId, $lang]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function cms_save_page_translation(int $pageId, string $lang, array $data): void
{
    $lang = cms_normalize_lang_code($lang, cms_default_language());
    if ($pageId <= 0 || $lang === cms_default_language()) {
        return;
    }

    $title = trim((string) ($data['title'] ?? ''));
    $excerpt = trim((string) ($data['excerpt'] ?? ''));
    $content = trim((string) ($data['content'] ?? ''));
    $builderData = json_encode(cms_normalize_builder_blocks($data['builder_data'] ?? '[]'), JSON_UNESCAPED_UNICODE);

    if ($title === '') {
        return;
    }

    if (cms_db_driver() === 'mysql') {
        cms_db()->prepare('INSERT INTO cms_page_translations (page_id, lang, title, excerpt, content, builder_data) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), excerpt = VALUES(excerpt), content = VALUES(content), builder_data = VALUES(builder_data), updated_at = CURRENT_TIMESTAMP')
            ->execute([$pageId, $lang, $title, $excerpt, $content, $builderData]);
        return;
    }

    cms_db()->prepare('INSERT INTO cms_page_translations (page_id, lang, title, excerpt, content, builder_data) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(page_id, lang) DO UPDATE SET title = excluded.title, excerpt = excluded.excerpt, content = excluded.content, builder_data = excluded.builder_data, updated_at = CURRENT_TIMESTAMP')
        ->execute([$pageId, $lang, $title, $excerpt, $content, $builderData]);
}

function cms_apply_page_translation(array $page, ?string $lang = null): array
{
    $lang = cms_normalize_lang_code((string) ($lang ?? cms_current_language()), cms_default_language());
    if ($lang === cms_default_language()) {
        return $page;
    }

    $pageId = (int) ($page['id'] ?? 0);
    if ($pageId <= 0) {
        return $page;
    }

    $translation = cms_page_translation($pageId, $lang);
    if (!is_array($translation)) {
        return $page;
    }

    foreach (['title', 'excerpt', 'content', 'builder_data'] as $field) {
        if (isset($translation[$field]) && (string) $translation[$field] !== '') {
            $page[$field] = (string) $translation[$field];
        }
    }
    $page['_lang'] = $lang;
    return $page;
}

function cms_set_translation(string $lang, string $key, string $value): void
{
    $lang = cms_normalize_lang_code($lang, cms_default_language());
    $key = trim($key);
    if ($key === '') {
        return;
    }

    if (cms_db_driver() === 'mysql') {
        cms_db()->prepare('INSERT INTO cms_i18n_strings (lang, translation_key, translation_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE translation_value = VALUES(translation_value), updated_at = CURRENT_TIMESTAMP')->execute([$lang, $key, $value]);
        return;
    }

    cms_db()->prepare('INSERT INTO cms_i18n_strings (lang, translation_key, translation_value) VALUES (?, ?, ?) ON CONFLICT(lang, translation_key) DO UPDATE SET translation_value = excluded.translation_value, updated_at = CURRENT_TIMESTAMP')->execute([$lang, $key, $value]);
}

function cms_translations_for_lang(string $lang): array
{
    $lang = cms_normalize_lang_code($lang, cms_default_language());
    $stmt = cms_db()->prepare('SELECT translation_key, translation_value FROM cms_i18n_strings WHERE lang = ? ORDER BY translation_key ASC');
    $stmt->execute([$lang]);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $key = (string) ($row['translation_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $out[$key] = (string) ($row['translation_value'] ?? '');
    }

    return $out;
}

function cms_translate(string $key, string $fallback = ''): string
{
    $key = trim($key);
    if ($key === '') {
        return $fallback;
    }

    static $cache = [];
    $lang = cms_current_language();
    $cacheKey = $lang . '|' . $key;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey] !== '' ? $cache[$cacheKey] : $fallback;
    }

    $stmt = cms_db()->prepare('SELECT translation_value FROM cms_i18n_strings WHERE lang = ? AND translation_key = ? LIMIT 1');
    $stmt->execute([$lang, $key]);
    $value = $stmt->fetchColumn();
    $cache[$cacheKey] = $value !== false ? (string) $value : '';

    return $cache[$cacheKey] !== '' ? $cache[$cacheKey] : $fallback;
}

function cms_site_mode(): string
{
    return cms_get_setting('site_mode', 'multipage') === 'onepage' ? 'onepage' : 'multipage';
}

function cms_theme_variant(): string
{
    return cms_get_setting('theme_variant', cms_site_mode());
}

function cms_find_page_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $sql = 'SELECT * FROM cms_pages WHERE slug = ?';
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $sql .= ' LIMIT 1';

    $stmt = cms_db()->prepare($sql);
    $stmt->execute([$slug]);
    $page = $stmt->fetch();

    return $page ? cms_apply_page_translation($page) : null;
}

function cms_homepage(): ?array
{
    $stmt = cms_db()->query("SELECT * FROM cms_pages WHERE status = 'published' AND is_homepage = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
    $page = $stmt->fetch();

    if ($page) {
        return cms_apply_page_translation($page);
    }

    $stmt = cms_db()->query("SELECT * FROM cms_pages WHERE status = 'published' ORDER BY parent_id IS NOT NULL, sort_order ASC, id ASC LIMIT 1");
    $page = $stmt->fetch();

    return $page ? cms_apply_page_translation($page) : null;
}

function cms_all_pages(bool $publishedOnly = false): array
{
    $sql = 'SELECT * FROM cms_pages';
    if ($publishedOnly) {
        $sql .= " WHERE status = 'published'";
    }
    $sql .= ' ORDER BY parent_id IS NOT NULL, parent_id ASC, sort_order ASC, is_homepage DESC, title ASC';

    $rows = cms_db()->query($sql)->fetchAll();
    return array_map(static fn(array $row): array => cms_apply_page_translation($row), $rows);
}

function cms_root_pages(bool $publishedOnly = false): array
{
    $sql = 'SELECT * FROM cms_pages WHERE parent_id IS NULL';
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $sql .= ' ORDER BY sort_order ASC, is_homepage DESC, title ASC';

    $rows = cms_db()->query($sql)->fetchAll();
    return array_map(static fn(array $row): array => cms_apply_page_translation($row), $rows);
}

function cms_child_pages(int $parentId, bool $publishedOnly = false): array
{
    $sql = 'SELECT * FROM cms_pages WHERE parent_id = ?';
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $sql .= ' ORDER BY sort_order ASC, title ASC';
    $stmt = cms_db()->prepare($sql);
    $stmt->execute([$parentId]);

    $rows = $stmt->fetchAll();
    return array_map(static fn(array $row): array => cms_apply_page_translation($row), $rows);
}

function cms_page_by_id(int $id): ?array
{
    $stmt = cms_db()->prepare('SELECT * FROM cms_pages WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $page = $stmt->fetch();
    return $page ? cms_apply_page_translation($page) : null;
}

function cms_decode_builder_data(?string $builderData): array
{
    if (!is_string($builderData) || trim($builderData) === '') {
        return [];
    }
    $decoded = json_decode($builderData, true);
    return is_array($decoded) ? $decoded : [];
}

function cms_sanitize_hex(string $color, string $fallback = '#0f172a'): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : $fallback;
}

function cms_sanitize_url_value(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, '/')) {
        return $url;
    }
    return '';
}

function cms_font_families(): array
{
    return [
        'system' => "system-ui,-apple-system,sans-serif",
        'inter' => "'Inter',sans-serif",
        'lato' => "'Lato',sans-serif",
        'montserrat' => "'Montserrat',sans-serif",
        'poppins' => "'Poppins',sans-serif",
        'roboto' => "'Roboto',sans-serif",
        'playfair' => "'Playfair Display',serif",
        'merriweather' => "'Merriweather',serif",
    ];
}

function cms_get_theme_settings(): array
{
    return [
        'accent' => cms_sanitize_hex(cms_get_setting('theme_accent', '#2563eb'), '#2563eb'),
        'bg' => cms_sanitize_hex(cms_get_setting('theme_bg', '#f3f6fb'), '#f3f6fb'),
        'text' => cms_sanitize_hex(cms_get_setting('theme_text', '#0f172a'), '#0f172a'),
        'panel' => cms_sanitize_hex(cms_get_setting('theme_panel', '#ffffff'), '#ffffff'),
        'muted' => cms_sanitize_hex(cms_get_setting('theme_muted', '#475569'), '#475569'),
        'border' => cms_sanitize_hex(cms_get_setting('theme_border', '#dbe4f0'), '#dbe4f0'),
        'radius' => (string) max(0, min(48, (int) cms_get_setting('theme_radius', '20'))),
        'font_body' => cms_get_setting('theme_font_body', 'system'),
        'font_heading' => cms_get_setting('theme_font_heading', 'system'),
        'font_size' => (string) max(12, min(24, (int) cms_get_setting('theme_font_size', '16'))),
        'header_style' => cms_get_setting('theme_header_style', 'glass'),
        'header_bg' => cms_sanitize_hex(cms_get_setting('theme_header_bg', '#ffffff'), '#ffffff'),
        'bg_type' => cms_get_setting('theme_bg_type', 'gradient'),
        'bg_color' => cms_sanitize_hex(cms_get_setting('theme_bg_color', '#f3f6fb'), '#f3f6fb'),
        'bg_gradient_from' => cms_sanitize_hex(cms_get_setting('theme_bg_gradient_from', '#e0eaff'), '#e0eaff'),
        'bg_gradient_to' => cms_sanitize_hex(cms_get_setting('theme_bg_gradient_to', '#f3f6fb'), '#f3f6fb'),
        'bg_image' => cms_sanitize_url_value(cms_get_setting('theme_bg_image', '')),
        'bg_attachment' => cms_get_setting('theme_bg_attachment', 'scroll') === 'fixed' ? 'fixed' : 'scroll',
        'container_width' => (string) max(800, min(1920, (int) cms_get_setting('theme_container_width', '1120'))),
        'footer_bg' => cms_sanitize_hex(cms_get_setting('theme_footer_bg', '#ffffff'), '#ffffff'),
    ];
}

function cms_get_theme_css(): string
{
    $t = cms_get_theme_settings();
    $fonts = cms_font_families();
    $googleFontMap = [
        'inter' => 'Inter',
        'lato' => 'Lato',
        'montserrat' => 'Montserrat',
        'poppins' => 'Poppins',
        'roboto' => 'Roboto',
        'playfair' => 'Playfair+Display',
        'merriweather' => 'Merriweather',
    ];

    $toLoad = [];
    if (isset($googleFontMap[$t['font_body']])) {
        $toLoad[$t['font_body']] = $googleFontMap[$t['font_body']];
    }
    if (isset($googleFontMap[$t['font_heading']])) {
        $toLoad[$t['font_heading']] = $googleFontMap[$t['font_heading']];
    }

    $bodyFamily = $fonts[$t['font_body']] ?? $fonts['system'];
    $headingFamily = $fonts[$t['font_heading']] ?? $fonts['system'];

    $bgCss = match ($t['bg_type']) {
        'color' => $t['bg_color'],
        'image' => ($t['bg_image'] !== ''
            ? 'url(' . htmlspecialchars($t['bg_image'], ENT_QUOTES) . ') center/cover ' . $t['bg_attachment']
            : $t['bg_color']),
        default => 'radial-gradient(ellipse 800px 400px at 100% 0,rgba(37,99,235,.14),transparent 60%),'
            . 'radial-gradient(ellipse 700px 500px at 0 0,rgba(14,165,233,.12),transparent 55%),'
            . $t['bg_gradient_from'],
    };

    $headerCss = match ($t['header_style']) {
        'solid' => 'background:' . $t['header_bg'] . ';',
        'transparent' => 'background:transparent;backdrop-filter:none;',
        default => 'background:rgba(255,255,255,.72);backdrop-filter:blur(14px);',
    };

    $out = '';
    if ($toLoad) {
        $families = implode('&family=', array_map(
            fn($f) => $f . ':wght@300;400;500;600;700;800;900',
            array_values($toLoad)
        ));
        $out .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . $families . '&display=swap">' . "\n";
    }

    $out .= '<style id="cms-theme-vars">'
        . ':root{'
        . '--accent:' . $t['accent'] . ';'
        . '--bg:' . $t['bg'] . ';'
        . '--text:' . $t['text'] . ';'
        . '--panel:' . $t['panel'] . ';'
        . '--muted:' . $t['muted'] . ';'
        . '--border:' . $t['border'] . ';'
        . '--radius:' . $t['radius'] . 'px;'
        . '--font-body:' . $bodyFamily . ';'
        . '--font-heading:' . $headingFamily . ';'
        . '--font-size:' . $t['font_size'] . 'px;'
        . '--container-width:' . $t['container_width'] . 'px;'
        . '}'
        . 'body{background:' . $bgCss . ';font-family:var(--font-body);font-size:var(--font-size);color:var(--text)}'
        . 'h1,h2,h3,h4,h5,h6{font-family:var(--font-heading)}'
        . '.site-header{' . $headerCss . '}'
        . '.site-footer{background:' . $t['footer_bg'] . '}'
        . '.wrap{width:min(var(--container-width),92vw)}'
        . '.page-card,.hook-zone{background:var(--panel);border-color:var(--border);border-radius:var(--radius)}'
        . '.nav a.active,.nav a:hover{background:var(--accent);border-color:var(--accent)}'
        . '.nav a{border-color:var(--border)}'
        . '.brand{color:var(--text)}'
        . '</style>';

    return $out;
}

function cms_builder_block_defaults(string $type = 'text'): array
{
    return [
        'type' => $type,
        'title' => '',
        'text' => '',
        'background_color' => '#ffffff',
        'background_image' => '',
        'background_attachment' => 'scroll',
        'min_height' => '420',
        'align' => 'left',
        'button_text' => '',
        'button_url' => '',
        'image_url' => '',
        'image_alt' => '',
        'gallery_urls' => '',
        'container_columns' => '2',
        'container_items_json' => "[\n  {\"title\":\"Karta 1\",\"text\":\"Opis elementu\"},\n  {\"title\":\"Karta 2\",\"text\":\"Opis elementu\"}\n]",
        'plugin_slug' => '',
    ];
}

function cms_normalize_builder_blocks(mixed $input): array
{
    if (is_string($input)) {
        $input = json_decode($input, true);
    }
    if (!is_array($input)) {
        return [];
    }

    $blocks = [];
    foreach ($input as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = in_array(($item['type'] ?? 'text'), ['hero', 'text', 'image', 'container', 'gallery', 'plugin_slot'], true) ? $item['type'] : 'text';
        $block = cms_builder_block_defaults($type);
        $block['title'] = trim((string) ($item['title'] ?? ''));
        $block['text'] = trim((string) ($item['text'] ?? ''));
        $block['background_color'] = cms_sanitize_hex((string) ($item['background_color'] ?? '#ffffff'), '#ffffff');
        $block['background_image'] = cms_sanitize_url_value((string) ($item['background_image'] ?? ''));
        $block['background_attachment'] = (($item['background_attachment'] ?? 'scroll') === 'fixed') ? 'fixed' : 'scroll';
        $block['min_height'] = (string) max(200, min(1200, (int) ($item['min_height'] ?? 420)));
        $block['align'] = in_array(($item['align'] ?? 'left'), ['left', 'center', 'right'], true) ? $item['align'] : 'left';
        $block['button_text'] = trim((string) ($item['button_text'] ?? ''));
        $block['button_url'] = cms_sanitize_url_value((string) ($item['button_url'] ?? ''));
        $block['image_url'] = cms_sanitize_url_value((string) ($item['image_url'] ?? ''));
        $block['image_alt'] = trim((string) ($item['image_alt'] ?? ''));

        $galleryRaw = preg_split('/\r\n|\r|\n/', (string) ($item['gallery_urls'] ?? '')) ?: [];
        $gallerySanitized = [];
        foreach ($galleryRaw as $url) {
            $clean = cms_sanitize_url_value((string) $url);
            if ($clean !== '') {
                $gallerySanitized[] = $clean;
            }
        }
        $block['gallery_urls'] = implode("\n", $gallerySanitized);

        $columns = (int) ($item['container_columns'] ?? 2);
        $block['container_columns'] = (string) max(1, min(4, $columns));

        $containerItemsRaw = (string) ($item['container_items_json'] ?? '[]');
        $containerItems = json_decode($containerItemsRaw, true);
        if (!is_array($containerItems)) {
            $containerItems = [];
        }
        $normalizedItems = [];
        foreach ($containerItems as $containerItem) {
            if (!is_array($containerItem)) {
                continue;
            }
            $normalizedItems[] = [
                'title' => trim((string) ($containerItem['title'] ?? '')),
                'text' => trim((string) ($containerItem['text'] ?? '')),
                'image' => cms_sanitize_url_value((string) ($containerItem['image'] ?? '')),
                'button_text' => trim((string) ($containerItem['button_text'] ?? '')),
                'button_url' => cms_sanitize_url_value((string) ($containerItem['button_url'] ?? '')),
            ];
        }
        $block['container_items_json'] = json_encode($normalizedItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pluginSlug = trim((string) ($item['plugin_slug'] ?? ''));
        $block['plugin_slug'] = preg_match('/^[a-z0-9\-]+$/', $pluginSlug) ? $pluginSlug : '';
        $blocks[] = $block;
    }

    return $blocks;
}

function cms_render_builder_blocks(array $page): string
{
    $blocks = cms_normalize_builder_blocks($page['builder_data'] ?? '[]');
    if ($blocks === []) {
        return (string) ($page['content'] ?? '');
    }

    ob_start();
    foreach ($blocks as $block) {
        $styles = [
            'background:' . cms_sanitize_hex($block['background_color'], '#ffffff'),
            'min-height:' . (int) $block['min_height'] . 'px',
            'text-align:' . $block['align'],
        ];
        if ($block['background_image'] !== '') {
            $styles[] = 'background-image:url(' . htmlspecialchars($block['background_image'], ENT_QUOTES, 'UTF-8') . ')';
            $styles[] = 'background-size:cover';
            $styles[] = 'background-position:center';
            $styles[] = 'background-attachment:' . $block['background_attachment'];
        }
        $styleAttr = implode(';', $styles);
        ?>
        <section class="builder-block builder-block-<?= htmlspecialchars($block['type']) ?> align-<?= htmlspecialchars($block['align']) ?>" style="<?= $styleAttr ?>">
            <div class="builder-inner">
                <?php if ($block['type'] === 'image' && $block['image_url'] !== ''): ?>
                    <div class="builder-image-wrap">
                        <img src="<?= htmlspecialchars($block['image_url']) ?>" alt="<?= htmlspecialchars($block['image_alt']) ?>" class="builder-image">
                    </div>
                <?php endif; ?>

                <?php if ($block['type'] === 'gallery'): ?>
                    <?php $galleryItems = preg_split('/\r\n|\r|\n/', (string) ($block['gallery_urls'] ?? '')) ?: []; ?>
                    <?php $galleryItems = array_values(array_filter(array_map('trim', $galleryItems), static fn(string $u): bool => $u !== '')); ?>
                    <?php if ($galleryItems !== []): ?>
                        <div class="builder-gallery-grid">
                            <?php foreach ($galleryItems as $galleryUrl): ?>
                                <figure class="builder-gallery-item"><img src="<?= htmlspecialchars($galleryUrl) ?>" alt="<?= htmlspecialchars($block['title'] !== '' ? $block['title'] : 'Obraz galerii') ?>"></figure>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($block['type'] === 'container'): ?>
                    <?php $containerItems = json_decode((string) ($block['container_items_json'] ?? '[]'), true); ?>
                    <?php if (!is_array($containerItems)) { $containerItems = []; } ?>
                    <?php if ($containerItems !== []): ?>
                        <div class="builder-container-grid cols-<?= (int) ($block['container_columns'] ?? 2) ?>">
                            <?php foreach ($containerItems as $containerItem): ?>
                                <?php if (!is_array($containerItem)) { continue; } ?>
                                <article class="builder-container-item">
                                    <?php if (!empty($containerItem['image'])): ?><img class="builder-container-image" src="<?= htmlspecialchars((string) $containerItem['image']) ?>" alt=""><?php endif; ?>
                                    <?php if (!empty($containerItem['title'])): ?><h3><?= htmlspecialchars((string) $containerItem['title']) ?></h3><?php endif; ?>
                                    <?php if (!empty($containerItem['text'])): ?><p><?= nl2br(htmlspecialchars((string) $containerItem['text'])) ?></p><?php endif; ?>
                                    <?php if (!empty($containerItem['button_text']) && !empty($containerItem['button_url'])): ?><a href="<?= htmlspecialchars((string) $containerItem['button_url']) ?>" class="builder-button"><?= htmlspecialchars((string) $containerItem['button_text']) ?></a><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($block['type'] === 'plugin_slot'): ?>
                    <div class="builder-plugin-slot">
                        <?= cms_render_plugin_slot((string) ($block['plugin_slug'] ?? ''), $page) ?>
                    </div>
                <?php endif; ?>

                <?php if ($block['title'] !== ''): ?>
                    <h2><?= htmlspecialchars($block['title']) ?></h2>
                <?php endif; ?>
                <?php if ($block['text'] !== ''): ?>
                    <div class="builder-text"><?= nl2br(htmlspecialchars($block['text'])) ?></div>
                <?php endif; ?>
                <?php if ($block['button_text'] !== '' && $block['button_url'] !== ''): ?>
                    <a href="<?= htmlspecialchars($block['button_url']) ?>" class="builder-button"><?= htmlspecialchars($block['button_text']) ?></a>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    return trim((string) ob_get_clean());
}

function cms_save_page(array $data, ?int $id = null): int
{
    $title = trim($data['title'] ?? '');
    $slug = cms_slugify($data['slug'] ?? $title);
    $excerpt = trim($data['excerpt'] ?? '');
    $content = trim($data['content'] ?? '');
    $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
    $status = ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $isHomepage = !empty($data['is_homepage']) ? 1 : 0;
    $sortOrder = (int) ($data['sort_order'] ?? 0);
    $template = trim($data['template'] ?? 'default') ?: 'default';
    $builderData = json_encode(cms_normalize_builder_blocks($data['builder_data'] ?? '[]'), JSON_UNESCAPED_UNICODE);

    if ($title === '') {
        throw new InvalidArgumentException('Tytul strony jest wymagany.');
    }

    $db = cms_db();
    if ($isHomepage === 1) {
        $db->exec('UPDATE cms_pages SET is_homepage = 0');
    }

    if ($parentId !== null && $id !== null && $parentId === $id) {
        throw new InvalidArgumentException('Strona nie moze byc rodzicem samej siebie.');
    }

    if ($id !== null) {
        $stmt = $db->prepare('UPDATE cms_pages SET parent_id = ?, title = ?, slug = ?, excerpt = ?, content = ?, builder_data = ?, status = ?, is_homepage = ?, sort_order = ?, template = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$parentId, $title, $slug, $excerpt, $content, $builderData, $status, $isHomepage, $sortOrder, $template, $id]);
        return $id;
    }

    $stmt = $db->prepare('INSERT INTO cms_pages (parent_id, title, slug, excerpt, content, builder_data, status, is_homepage, sort_order, template) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$parentId, $title, $slug, $excerpt, $content, $builderData, $status, $isHomepage, $sortOrder, $template]);
    return (int) $db->lastInsertId();
}

function cms_delete_page(int $id): void
{
    cms_db()->prepare('UPDATE cms_pages SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);
    cms_db()->prepare('DELETE FROM cms_pages WHERE id = ?')->execute([$id]);
}

function cms_plugin_store_catalog(): array
{
    $sources = cms_update_sources();
    $catalogKey = (string) ($sources['plugin_store_catalog_key'] ?? 'plugins');

    // Kolejnosc: dedykowany manifest pluginow, a potem baza sklepu jako fallback.
    foreach ([$sources['plugin_store_manifest_url'] ?? '', $sources['store_db_manifest_url'] ?? ''] as $url) {
        $decoded = cms_fetch_remote_json((string) $url, 4);
        if (!is_array($decoded)) {
            continue;
        }

        if (isset($decoded[$catalogKey]) && is_array($decoded[$catalogKey])) {
            return $decoded[$catalogKey];
        }
        if (isset($decoded['plugins']) && is_array($decoded['plugins'])) {
            return $decoded['plugins'];
        }

        // Dopuszczamy takze format, gdzie root JSON to lista pluginow.
        if (array_is_list($decoded)) {
            return $decoded;
        }
    }

    return [];
}
