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

function cms_admin_language(): string
{
    $default = cms_default_language();
    $configured = cms_normalize_lang_code(cms_get_setting('admin_language', $default), $default);
    $allowed = cms_enabled_languages();
    return in_array($configured, $allowed, true) ? $configured : $default;
}

function cms_admin_theme(?array $user = null): string
{
    $allowed = ['dark', 'light', 'oldschool', 'sunset'];
    $candidate = '';
    if (is_array($user) && isset($user['admin_theme'])) {
        $candidate = strtolower(trim((string) $user['admin_theme']));
    }
    if ($candidate === '') {
        $candidate = strtolower(trim((string) cms_get_setting('admin_theme', 'dark')));
    }
    return in_array($candidate, $allowed, true) ? $candidate : 'dark';
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
        $current = cms_admin_language();
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

function cms_t(string $key, string $fallback = ''): string
{
    return cms_translate($key, $fallback);
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

function cms_save_page_revision_snapshot(int $pageId, ?int $createdBy = null): void
{
    if ($pageId <= 0) {
        return;
    }

    $db = cms_db();
    $stmt = $db->prepare('SELECT * FROM cms_pages WHERE id = ? LIMIT 1');
    $stmt->execute([$pageId]);
    $page = $stmt->fetch();
    if (!is_array($page)) {
        return;
    }

    $insert = $db->prepare('INSERT INTO cms_page_revisions (page_id, title, slug, excerpt, content, meta_title, meta_description, builder_data, status, is_homepage, sort_order, template, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([
        $pageId,
        (string) ($page['title'] ?? ''),
        (string) ($page['slug'] ?? ''),
        (string) ($page['excerpt'] ?? ''),
        (string) ($page['content'] ?? ''),
        (string) ($page['meta_title'] ?? ''),
        (string) ($page['meta_description'] ?? ''),
        (string) ($page['builder_data'] ?? '[]'),
        (string) ($page['status'] ?? 'draft'),
        (int) ($page['is_homepage'] ?? 0),
        (int) ($page['sort_order'] ?? 0),
        (string) ($page['template'] ?? 'default'),
        $createdBy,
    ]);
}

function cms_page_revisions(int $pageId, int $limit = 20): array
{
    if ($pageId <= 0) {
        return [];
    }
    $limit = max(1, min(100, $limit));

        $sql = "SELECT r.*, u.username AS created_by_username
            FROM cms_page_revisions r
            LEFT JOIN cms_users u ON u.id = r.created_by
            WHERE r.page_id = ?
            ORDER BY r.id DESC
            LIMIT " . (int) $limit;
    $stmt = cms_db()->prepare($sql);
    $stmt->execute([$pageId]);
    return $stmt->fetchAll() ?: [];
}

function cms_restore_page_revision(int $revisionId, ?int $actorId = null): int
{
    if ($revisionId <= 0) {
        throw new InvalidArgumentException('Nieprawidlowe ID rewizji.');
    }

    $db = cms_db();
    $stmt = $db->prepare('SELECT * FROM cms_page_revisions WHERE id = ? LIMIT 1');
    $stmt->execute([$revisionId]);
    $revision = $stmt->fetch();
    if (!is_array($revision)) {
        throw new RuntimeException('Nie znaleziono wskazanej rewizji strony.');
    }

    $pageId = (int) ($revision['page_id'] ?? 0);
    if ($pageId <= 0) {
        throw new RuntimeException('Rewizja nie zawiera poprawnego ID strony.');
    }

    $db->beginTransaction();
    try {
        cms_save_page_revision_snapshot($pageId, $actorId);

        if ((int) ($revision['is_homepage'] ?? 0) === 1) {
            $db->exec('UPDATE cms_pages SET is_homepage = 0');
        }

        $update = $db->prepare('UPDATE cms_pages SET title = ?, slug = ?, excerpt = ?, content = ?, meta_title = ?, meta_description = ?, builder_data = ?, status = ?, is_homepage = ?, sort_order = ?, template = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([
            (string) ($revision['title'] ?? ''),
            (string) ($revision['slug'] ?? ''),
            (string) ($revision['excerpt'] ?? ''),
            (string) ($revision['content'] ?? ''),
            (string) ($revision['meta_title'] ?? ''),
            (string) ($revision['meta_description'] ?? ''),
            (string) ($revision['builder_data'] ?? '[]'),
            (string) ($revision['status'] ?? 'draft'),
            (int) ($revision['is_homepage'] ?? 0),
            (int) ($revision['sort_order'] ?? 0),
            (string) ($revision['template'] ?? 'default'),
            $pageId,
        ]);

        $db->commit();
        return $pageId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
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
    $common = [
        'type'         => $type,
        'display'      => 'block',
        'order'        => 0,
        'link_url'     => '',
        'link_target'  => '_self',
        'padding_y'    => '20',
        'padding_x'    => '0',
        'bg_color'     => '',
        'width'        => '100',
        'align'        => 'left',
        'float_x'      => '0',
        'float_y'      => '0',
    ];
    $byType = [
        'heading' => ['heading_text' => 'Nagłówek', 'heading_level' => 'h2', 'heading_color' => '#111111', 'heading_size' => ''],
        'text'    => ['text_content' => 'Wpisz tekst...', 'text_size' => '16', 'text_color' => '#333333', 'text_bold' => '0', 'text_italic' => '0'],
        'image'   => ['image_src' => '', 'image_alt' => '', 'image_width' => '100', 'image_border_radius' => '0', 'image_link' => ''],
        'button'  => ['btn_label' => 'Przycisk', 'btn_url' => '#', 'btn_target' => '_self', 'btn_style' => 'primary', 'btn_size' => 'md', 'btn_full_width' => '0'],
        'hero'    => ['hero_title' => 'Nagłówek hero', 'hero_subtitle' => '', 'hero_bg_color' => '#1a2942', 'hero_bg_image' => '', 'hero_text_color' => '#ffffff', 'hero_btn_text' => '', 'hero_btn_url' => '#', 'hero_min_height' => '400', 'hero_text_align' => 'center'],
        'divider' => ['div_color' => '#e2e8f0', 'div_thickness' => '1', 'div_style' => 'solid', 'div_width' => '100'],
        'spacer'  => ['spacer_height' => '40'],
        'html'    => ['html_content' => ''],
    ];
    return array_merge($common, $byType[$type] ?? []);
}

function cms_normalize_builder_blocks(mixed $input): array
{
    if (is_string($input)) {
        $input = json_decode($input, true);
    }
    if (!is_array($input)) {
        return [];
    }
    $validTypes = ['heading', 'text', 'image', 'button', 'hero', 'divider', 'spacer', 'html'];
    $legacyMap  = ['container' => 'text', 'gallery' => 'text', 'plugin_slot' => 'text'];

    $blocks = [];
    foreach ($input as $idx => $item) {
        if (!is_array($item)) { continue; }
        $type = (string) ($item['type'] ?? 'text');
        if (!in_array($type, $validTypes, true)) {
            $type = $legacyMap[$type] ?? 'text';
        }
        $defaults = cms_builder_block_defaults($type);
        $block = $defaults;
        foreach ($defaults as $k => $v) {
            if (array_key_exists($k, $item) && $item[$k] !== null) {
                $block[$k] = $item[$k];
            }
        }
        // Legacy field migrations
        if ($type === 'image'   && $block['image_src']    === '' && !empty($item['image_url']))  { $block['image_src']    = (string) $item['image_url']; }
        if ($type === 'heading' && $block['heading_text'] === '' && !empty($item['title']))       { $block['heading_text'] = trim((string) $item['title']); }
        if ($type === 'hero'    && $block['hero_title']   === '' && !empty($item['title']))       { $block['hero_title']   = trim((string) $item['title']); }

        $block['type']         = $type;
        $block['display']      = in_array($block['display'], ['block', 'float'], true) ? $block['display'] : 'block';
        $block['order']        = (int) ($item['order'] ?? $idx);
        $block['link_target']  = in_array($block['link_target'], ['_self', '_blank'], true) ? $block['link_target'] : '_self';
        $block['padding_y']    = (string) max(0, min(200, (int) $block['padding_y']));
        $block['padding_x']    = (string) max(0, min(200, (int) $block['padding_x']));
        $block['width']        = (string) max(10, min(100, (int) $block['width']));
        $block['float_x']      = (string) max(0, min(90, (int) $block['float_x']));
        $block['float_y']      = (string) max(0, (int) $block['float_y']);
        $block['link_url']     = cms_sanitize_url_value((string) $block['link_url']);
        $block['bg_color']     = $block['bg_color'] !== '' ? cms_sanitize_hex((string) $block['bg_color'], '') : '';
        $block['align']        = in_array($block['align'], ['left', 'center', 'right'], true) ? $block['align'] : 'left';

        if ($type === 'heading') {
            $block['heading_level'] = in_array($block['heading_level'], ['h1','h2','h3','h4','h5','h6'], true) ? $block['heading_level'] : 'h2';
            $block['heading_color'] = cms_sanitize_hex((string) $block['heading_color'], '#111111');
        } elseif ($type === 'text') {
            $block['text_size']   = (string) max(10, min(120, (int) $block['text_size']));
            $block['text_color']  = cms_sanitize_hex((string) $block['text_color'], '#333333');
            $block['text_bold']   = $block['text_bold']   === '1' ? '1' : '0';
            $block['text_italic'] = $block['text_italic'] === '1' ? '1' : '0';
        } elseif ($type === 'image') {
            $block['image_src']           = cms_sanitize_url_value((string) $block['image_src']);
            $block['image_width']         = (string) max(10, min(100, (int) $block['image_width']));
            $block['image_border_radius'] = (string) max(0, min(200, (int) $block['image_border_radius']));
            $block['image_link']          = cms_sanitize_url_value((string) $block['image_link']);
        } elseif ($type === 'button') {
            $block['btn_url']        = cms_sanitize_url_value((string) $block['btn_url']);
            $block['btn_target']     = in_array($block['btn_target'], ['_self', '_blank'], true) ? $block['btn_target'] : '_self';
            $block['btn_style']      = in_array($block['btn_style'], ['primary','secondary','outline','danger','success'], true) ? $block['btn_style'] : 'primary';
            $block['btn_size']       = in_array($block['btn_size'], ['sm','md','lg'], true) ? $block['btn_size'] : 'md';
            $block['btn_full_width'] = $block['btn_full_width'] === '1' ? '1' : '0';
        } elseif ($type === 'hero') {
            $block['hero_bg_color']   = cms_sanitize_hex((string) $block['hero_bg_color'], '#1a2942');
            $block['hero_text_color'] = cms_sanitize_hex((string) $block['hero_text_color'], '#ffffff');
            $block['hero_min_height'] = (string) max(100, min(1200, (int) $block['hero_min_height']));
            $block['hero_text_align'] = in_array($block['hero_text_align'], ['left','center','right'], true) ? $block['hero_text_align'] : 'center';
            $block['hero_bg_image']   = cms_sanitize_url_value((string) $block['hero_bg_image']);
            $block['hero_btn_url']    = cms_sanitize_url_value((string) $block['hero_btn_url']);
        } elseif ($type === 'divider') {
            $block['div_color']     = cms_sanitize_hex((string) $block['div_color'], '#e2e8f0');
            $block['div_thickness'] = (string) max(1, min(20, (int) $block['div_thickness']));
            $block['div_style']     = in_array($block['div_style'], ['solid','dashed','dotted'], true) ? $block['div_style'] : 'solid';
            $block['div_width']     = (string) max(10, min(100, (int) $block['div_width']));
        } elseif ($type === 'spacer') {
            $block['spacer_height'] = (string) max(4, min(400, (int) $block['spacer_height']));
        }
        $blocks[] = $block;
    }
    usort($blocks, static fn(array $a, array $b): int => (int) $a['order'] - (int) $b['order']);
    return $blocks;
}

function cms_render_single_block(array $block, bool $isFloat = false): string
{
    $type    = (string) ($block['type'] ?? 'text');
    $padY    = max(0, (int) ($block['padding_y'] ?? 20));
    $padX    = max(0, (int) ($block['padding_x'] ?? 0));
    $bgColor = (string) ($block['bg_color'] ?? '');
    $align   = in_array($block['align'] ?? 'left', ['left','center','right'], true) ? $block['align'] : 'left';
    $width   = max(10, min(100, (int) ($block['width'] ?? 100)));
    $linkUrl = trim((string) ($block['link_url'] ?? ''));
    $linkTgt = ($block['link_target'] ?? '_self') === '_blank' ? '_blank' : '_self';

    $wrapStyle = 'padding:' . $padY . 'px ' . $padX . 'px';
    if ($bgColor !== '') { $wrapStyle .= ';background:' . htmlspecialchars($bgColor, ENT_QUOTES); }
    if ($isFloat) {
        $fx = max(0, min(90, (int) ($block['float_x'] ?? 0)));
        $fy = max(0, (int) ($block['float_y'] ?? 0));
        $wrapStyle .= ';position:absolute;left:' . $fx . '%;top:' . $fy . 'px;z-index:10';
    } elseif ($width < 100) {
        $wrapStyle .= ';width:' . $width . '%';
    }

    $inner = '';
    if ($type === 'heading') {
        $lvl   = in_array($block['heading_level'] ?? 'h2', ['h1','h2','h3','h4','h5','h6'], true) ? $block['heading_level'] : 'h2';
        $szMap = ['h1' => '2.5rem', 'h2' => '2rem', 'h3' => '1.5rem', 'h4' => '1.25rem', 'h5' => '1rem', 'h6' => '0.875rem'];
        $sz    = !empty($block['heading_size']) ? ((int) $block['heading_size'] . 'px') : ($szMap[$lvl] ?? '2rem');
        $clr   = htmlspecialchars((string) ($block['heading_color'] ?? '#111111'), ENT_QUOTES);
        $txt   = htmlspecialchars((string) ($block['heading_text'] ?? 'Nagłówek'));
        $inner = '<' . $lvl . ' class="cms-block-heading" style="font-size:' . $sz . ';color:' . $clr . ';text-align:' . $align . ';margin:0;line-height:1.25;font-weight:800">' . $txt . '</' . $lvl . '>';
    } elseif ($type === 'text') {
        $sz  = max(10, (int) ($block['text_size'] ?? 16));
        $clr = htmlspecialchars((string) ($block['text_color'] ?? '#333'), ENT_QUOTES);
        $css = 'font-size:' . $sz . 'px;color:' . $clr . ';text-align:' . $align . ';margin:0;line-height:1.6';
        if (($block['text_bold'] ?? '0') === '1')   { $css .= ';font-weight:700'; }
        if (($block['text_italic'] ?? '0') === '1') { $css .= ';font-style:italic'; }
        $inner = '<p class="cms-block-text" style="' . $css . '">' . nl2br(htmlspecialchars((string) ($block['text_content'] ?? ''))) . '</p>';
    } elseif ($type === 'image') {
        $src = (string) ($block['image_src'] ?? '');
        if ($src !== '') {
            $iw  = max(10, min(100, (int) ($block['image_width'] ?? 100)));
            $rad = max(0, (int) ($block['image_border_radius'] ?? 0));
            $ims = 'width:' . $iw . '%;max-width:100%;border-radius:' . $rad . 'px;display:block';
            if ($align === 'center') { $ims .= ';margin:0 auto'; }
            elseif ($align === 'right') { $ims .= ';margin-left:auto;margin-right:0'; }
            $img   = '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="' . htmlspecialchars((string) ($block['image_alt'] ?? ''), ENT_QUOTES) . '" class="cms-block-image" style="' . $ims . '">';
            $ilink = (string) ($block['image_link'] ?? '');
            $inner = $ilink !== '' ? '<a href="' . htmlspecialchars($ilink, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . $img . '</a>' : $img;
        }
    } elseif ($type === 'button') {
        $bStyle = (string) ($block['btn_style'] ?? 'primary');
        $bSize  = (string) ($block['btn_size']  ?? 'md');
        $bgM    = ['primary' => '#2563eb', 'secondary' => '#475569', 'outline' => 'transparent', 'danger' => '#dc2626', 'success' => '#16a34a'];
        $txM    = ['primary' => '#fff', 'secondary' => '#fff', 'outline' => '#2563eb', 'danger' => '#fff', 'success' => '#fff'];
        $bdM    = ['primary' => '#2563eb', 'secondary' => '#475569', 'outline' => '#2563eb', 'danger' => '#dc2626', 'success' => '#16a34a'];
        $szM    = ['sm' => '8px 18px', 'md' => '11px 28px', 'lg' => '15px 40px'];
        $szFM   = ['sm' => '13px', 'md' => '15px', 'lg' => '18px'];
        $css    = 'padding:' . ($szM[$bSize] ?? $szM['md']) . ';font-size:' . ($szFM[$bSize] ?? '15px') . ';background:' . ($bgM[$bStyle] ?? '#2563eb') . ';color:' . ($txM[$bStyle] ?? '#fff') . ';border:2px solid ' . ($bdM[$bStyle] ?? '#2563eb') . ';border-radius:8px;font-weight:700;text-decoration:none;display:inline-block';
        if (($block['btn_full_width'] ?? '0') === '1') { $css .= ';display:block;text-align:center;width:100%;box-sizing:border-box'; }
        $aw    = match($align) { 'center' => ';text-align:center', 'right' => ';text-align:right', default => '' };
        $tgt   = ($block['btn_target'] ?? '_self') === '_blank' ? '_blank' : '_self';
        $rel   = $tgt === '_blank' ? ' rel="noopener noreferrer"' : '';
        $label = htmlspecialchars((string) ($block['btn_label'] ?? 'Przycisk'));
        $url   = htmlspecialchars((string) ($block['btn_url']   ?? '#'), ENT_QUOTES);
        $inner = '<div style="line-height:1' . $aw . '"><a href="' . $url . '" target="' . $tgt . '"' . $rel . ' class="cms-btn" style="' . $css . '">' . $label . '</a></div>';
    } elseif ($type === 'hero') {
        $ha  = in_array($block['hero_text_align'] ?? 'center', ['left','center','right'], true) ? $block['hero_text_align'] : 'center';
        $bg  = htmlspecialchars((string) ($block['hero_bg_color'] ?? '#1a2942'), ENT_QUOTES);
        $tc  = htmlspecialchars((string) ($block['hero_text_color'] ?? '#ffffff'), ENT_QUOTES);
        $mh  = max(100, min(1200, (int) ($block['hero_min_height'] ?? 400)));
        $hs  = 'min-height:' . $mh . 'px;background-color:' . $bg . ';text-align:' . $ha . ';display:flex;align-items:center;justify-content:center;flex-direction:column;padding:60px 40px;box-sizing:border-box';
        $bgi = (string) ($block['hero_bg_image'] ?? '');
        if ($bgi !== '') { $hs .= ';background-image:url(' . htmlspecialchars($bgi, ENT_QUOTES) . ');background-size:cover;background-position:center'; }
        $htitle = htmlspecialchars((string) ($block['hero_title'] ?? ''));
        $hsub   = htmlspecialchars((string) ($block['hero_subtitle'] ?? ''));
        $hbtn   = htmlspecialchars((string) ($block['hero_btn_text'] ?? ''));
        $hurl   = htmlspecialchars((string) ($block['hero_btn_url'] ?? '#'), ENT_QUOTES);
        $inner  = '<section class="cms-hero" style="' . $hs . '">'
            . '<h2 class="cms-hero-title" style="color:' . $tc . ';font-size:clamp(2rem,5vw,3.5rem);font-weight:900;margin:0 0 1rem;line-height:1.15;max-width:800px">' . $htitle . '</h2>'
            . ($hsub !== '' ? '<p class="cms-hero-subtitle" style="color:' . $tc . ';opacity:0.85;font-size:1.25rem;margin:0 0 2rem;max-width:600px;line-height:1.6">' . $hsub . '</p>' : '')
            . ($hbtn !== '' ? '<a class="cms-btn" href="' . $hurl . '" style="display:inline-block;padding:12px 30px;background:#2563eb;color:#fff;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none">' . $hbtn . '</a>' : '')
            . '</section>';
        $wrapStyle = '';
    } elseif ($type === 'divider') {
        $dc  = htmlspecialchars((string) ($block['div_color'] ?? '#e2e8f0'), ENT_QUOTES);
        $dth = max(1, min(20, (int) ($block['div_thickness'] ?? 1)));
        $dst = in_array($block['div_style'] ?? 'solid', ['solid','dashed','dotted'], true) ? $block['div_style'] : 'solid';
        $dw  = max(10, min(100, (int) ($block['div_width'] ?? 100)));
        $inner = '<hr style="border:none;border-top:' . $dth . 'px ' . $dst . ' ' . $dc . ';width:' . $dw . '%;margin:0 auto">';
    } elseif ($type === 'spacer') {
        $sh = max(4, min(400, (int) ($block['spacer_height'] ?? 40)));
        $inner = '<div style="height:' . $sh . 'px"></div>';
    } elseif ($type === 'html') {
        $inner = '<div class="cms-block-html">' . (string) ($block['html_content'] ?? '') . '</div>';
    }

    if ($inner === '') { return ''; }

    $wrapped = '<div class="cms-block cms-block-' . htmlspecialchars($type, ENT_QUOTES) . '"'
        . ($wrapStyle !== '' ? ' style="' . $wrapStyle . '"' : '') . '>' . $inner . '</div>';
    if ($linkUrl !== '') {
        $wrapped = '<a href="' . htmlspecialchars($linkUrl, ENT_QUOTES) . '" target="' . $linkTgt . '"'
            . ($linkTgt === '_blank' ? ' rel="noopener noreferrer"' : '')
            . ' style="display:block;color:inherit;text-decoration:none">' . $wrapped . '</a>';
    }
    return $wrapped;
}

function cms_render_builder_blocks(array $page): string
{
    $blocks = cms_normalize_builder_blocks($page['builder_data'] ?? '[]');
    if ($blocks === []) {
        return (string) ($page['content'] ?? '');
    }
    $flow   = array_filter($blocks, static fn(array $b): bool => ($b['display'] ?? 'block') !== 'float');
    $floats = array_filter($blocks, static fn(array $b): bool => ($b['display'] ?? 'block') === 'float');

    $html = '<div class="cms-builder-page">';
    foreach ($flow as $block) { $html .= cms_render_single_block($block); }
    if ($floats !== []) {
        $html .= '<div class="cms-builder-floats" style="position:relative;pointer-events:none">';
        foreach ($floats as $block) { $html .= cms_render_single_block($block, true); }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
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
