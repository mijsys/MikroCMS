<?php
declare(strict_types=1);

cms_add_hook('theme_footer', static function (): void {
    echo '<div class="plugin-note"><strong>Hello World Plugin</strong>Plugin jest aktywny. To miejsce moze byc wykorzystane np. do widgetow, CTA, SEO albo integracji.</div>';
});

cms_add_hook('admin_dashboard_cards', static function (): void {
    echo '<h2>Plugin demo</h2><p class="muted">Hello World Plugin dodal ten blok do dashboardu przez mechanizm hookow.</p>';
});

cms_add_hook('plugin_render', static function (string $slug, array $page, string $position): void {
    if ($slug !== 'hello-world') {
        return;
    }

    echo '<div class="plugin-note"><strong>Hello World Plugin</strong>Widok pluginu osadzony na stronie: ' . htmlspecialchars((string) ($page['title'] ?? 'strona'), ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($position, ENT_QUOTES, 'UTF-8') . ').</div>';
});
