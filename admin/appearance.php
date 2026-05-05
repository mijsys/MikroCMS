<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

cms_require_login();
$user = cms_current_user();
$previewPartsAllowed = ['header', 'hero', 'content', 'cta', 'footer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        cms_flash('error', cms_t('admin.flash.csrf_invalid', 'Nieprawidlowy token bezpieczenstwa.'));
        cms_redirect(cms_url('admin/appearance.php'));
    }

    try {
        $hexKeys = ['theme_accent','theme_bg','theme_text','theme_panel','theme_muted','theme_border','theme_header_bg','theme_bg_color','theme_bg_gradient_from','theme_bg_gradient_to','theme_footer_bg'];
        foreach ($hexKeys as $k) {
            $v = trim($_POST[$k] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                cms_set_setting($k, strtolower($v));
            }
        }
        cms_set_setting('theme_radius', (string) max(0, min(48, (int) ($_POST['theme_radius'] ?? 20))));
        cms_set_setting('theme_font_size', (string) max(12, min(24, (int) ($_POST['theme_font_size'] ?? 16))));
        cms_set_setting('theme_container_width', (string) max(800, min(1920, (int) ($_POST['theme_container_width'] ?? 1120))));
        $allowedFonts = ['system','inter','lato','montserrat','poppins','roboto','playfair','merriweather'];
        cms_set_setting('theme_font_body', in_array($_POST['theme_font_body'] ?? '', $allowedFonts, true) ? (string) $_POST['theme_font_body'] : 'system');
        cms_set_setting('theme_font_heading', in_array($_POST['theme_font_heading'] ?? '', $allowedFonts, true) ? (string) $_POST['theme_font_heading'] : 'system');
        cms_set_setting('theme_header_style', in_array($_POST['theme_header_style'] ?? '', ['glass','solid','transparent'], true) ? (string) $_POST['theme_header_style'] : 'glass');
        cms_set_setting('theme_bg_type', in_array($_POST['theme_bg_type'] ?? '', ['gradient','color','image'], true) ? (string) $_POST['theme_bg_type'] : 'gradient');
        cms_set_setting('theme_bg_image', trim($_POST['theme_bg_image'] ?? ''));
        cms_set_setting('theme_bg_attachment', ($_POST['theme_bg_attachment'] ?? 'scroll') === 'fixed' ? 'fixed' : 'scroll');

        $previewLayoutRaw = (string) ($_POST['theme_preview_layout'] ?? '[]');
        $previewLayoutDecoded = json_decode($previewLayoutRaw, true);
        $previewLayout = [];
        if (is_array($previewLayoutDecoded)) {
            foreach ($previewLayoutDecoded as $item) {
                $part = (string) $item;
                if (in_array($part, $previewPartsAllowed, true) && !in_array($part, $previewLayout, true)) {
                    $previewLayout[] = $part;
                }
            }
        }
        foreach ($previewPartsAllowed as $requiredPart) {
            if (!in_array($requiredPart, $previewLayout, true)) {
                $previewLayout[] = $requiredPart;
            }
        }
        cms_set_setting('theme_preview_layout', json_encode($previewLayout, JSON_UNESCAPED_UNICODE));

        cms_flash('success', cms_t('admin.appearance.flash.saved', 'Styl witryny zostal zapisany.'));
    } catch (Throwable $e) {
        cms_flash('error', $e->getMessage());
    }

    cms_redirect(cms_url('admin/appearance.php'));
}

$flash = cms_pull_flash();
$themeSettings = cms_get_theme_settings();
$previewLayoutStored = json_decode((string) cms_get_setting('theme_preview_layout', '[]'), true);
$previewLayout = [];
if (is_array($previewLayoutStored)) {
    foreach ($previewLayoutStored as $part) {
        $partKey = (string) $part;
        if (in_array($partKey, $previewPartsAllowed, true) && !in_array($partKey, $previewLayout, true)) {
            $previewLayout[] = $partKey;
        }
    }
}
foreach ($previewPartsAllowed as $requiredPart) {
    if (!in_array($requiredPart, $previewLayout, true)) {
        $previewLayout[] = $requiredPart;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(cms_admin_language()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(cms_t('admin.appearance.title', 'Wyglad CMS')) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('admin/assets/dashboard.css')) ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">CMS</div>
        <div class="muted"><?= htmlspecialchars(cms_t('admin.nav.panel', 'Panel zarzadzania')) ?></div>
        <nav class="nav">
            <a href="<?= htmlspecialchars(cms_url('admin/dashboard.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.dashboard', 'Dashboard')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.pages', 'Strony')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.plugins', 'Pluginy')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>" class="active"><?= htmlspecialchars(cms_t('admin.nav.appearance', 'Wyglad')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.settings', 'Ustawienia')) ?></a>
            <a href="<?= htmlspecialchars(cms_url()) ?>" target="_blank"><?= htmlspecialchars(cms_t('admin.nav.preview', 'Podglad strony')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/logout.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.logout', 'Wyloguj')) ?></a>
        </nav>
        <div style="margin-top:24px" class="muted"><?= htmlspecialchars(cms_t('admin.nav.logged_as', 'Zalogowany:')) ?> <?= htmlspecialchars($user['username'] ?? 'admin') ?></div>
    </aside>
    <main class="main">
        <div class="topbar"><div style="display:flex;align-items:flex-start;gap:12px"><button id="sidebarToggleBtn" class="btn ghost" title="Ukryj panel boczny" style="padding:8px 13px;font-size:18px;line-height:1;flex-shrink:0;margin-top:3px">&#8249;</button><div><h1 style="margin:0 0 6px"><?= htmlspecialchars(cms_t('admin.appearance.heading', 'Wyglad witryny')) ?></h1><div class="muted"><?= htmlspecialchars(cms_t('admin.appearance.subheading', 'Wizualny edytor calego stylu strony.')) ?></div></div></div></div>
        <?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

        <section class="panel customizer-panel">
            <h2><?= htmlspecialchars(cms_t('admin.appearance.editor.heading', 'Styl witryny - wizualny edytor wygladu')) ?></h2>
            <p class="muted" style="margin:0 0 20px"><?= htmlspecialchars(cms_t('admin.appearance.editor.desc', 'Zmien kolory, czcionki, tlo i uklad calej strony. Podglad odswiezany na zywo.')) ?></p>
            <div class="customizer-layout">
                <div class="customizer-controls">
                    <form method="post" id="themeForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="theme_preview_layout" id="themePreviewLayoutInput" value="<?= htmlspecialchars(json_encode($previewLayout, JSON_UNESCAPED_UNICODE)) ?>">
                        <div class="cust-group"><div class="cust-group-title"><?= htmlspecialchars(cms_t('admin.appearance.colors', 'Kolory')) ?></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.color.accent', 'Kolor wiodacy')) ?></label><input type="color" name="theme_accent" value="<?= htmlspecialchars($themeSettings['accent']) ?>"></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.color.text', 'Kolor tekstu')) ?></label><input type="color" name="theme_text" value="<?= htmlspecialchars($themeSettings['text']) ?>"></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.color.panel', 'Tlo kart')) ?></label><input type="color" name="theme_panel" value="<?= htmlspecialchars($themeSettings['panel']) ?>"></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.color.muted', 'Kolor drugoplanowy')) ?></label><input type="color" name="theme_muted" value="<?= htmlspecialchars($themeSettings['muted']) ?>"></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.color.border', 'Kolor obramowania')) ?></label><input type="color" name="theme_border" value="<?= htmlspecialchars($themeSettings['border']) ?>"></div>
                        </div>
                        <div class="cust-group"><div class="cust-group-title"><?= htmlspecialchars(cms_t('admin.appearance.background', 'Tlo strony')) ?></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.bg.type', 'Typ tla')) ?></label><select name="theme_bg_type" id="bgTypeSelect"><option value="gradient" <?= $themeSettings['bg_type'] === 'gradient' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.appearance.bg.gradient', 'Gradient')) ?></option><option value="color" <?= $themeSettings['bg_type'] === 'color' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.appearance.bg.color', 'Kolor')) ?></option><option value="image" <?= $themeSettings['bg_type'] === 'image' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.appearance.bg.image', 'Obraz')) ?></option></select></div>
                            <div class="cust-row bg-opt-color" <?= $themeSettings['bg_type'] !== 'color' ? 'style="display:none"' : '' ?>><label><?= htmlspecialchars(cms_t('admin.appearance.bg.color_single', 'Kolor tla')) ?></label><input type="color" name="theme_bg_color" value="<?= htmlspecialchars($themeSettings['bg_color']) ?>"></div>
                            <div class="cust-row bg-opt-gradient" <?= $themeSettings['bg_type'] !== 'gradient' ? 'style="display:none"' : '' ?>><label><?= htmlspecialchars(cms_t('admin.appearance.bg.gradient_from', 'Gradient od')) ?></label><input type="color" name="theme_bg_gradient_from" value="<?= htmlspecialchars($themeSettings['bg_gradient_from']) ?>"></div>
                            <div class="cust-row bg-opt-gradient" <?= $themeSettings['bg_type'] !== 'gradient' ? 'style="display:none"' : '' ?>><label><?= htmlspecialchars(cms_t('admin.appearance.bg.gradient_to', 'Gradient do')) ?></label><input type="color" name="theme_bg_gradient_to" value="<?= htmlspecialchars($themeSettings['bg_gradient_to']) ?>"></div>
                            <div class="cust-row bg-opt-image" <?= $themeSettings['bg_type'] !== 'image' ? 'style="display:none"' : '' ?>><label><?= htmlspecialchars(cms_t('admin.appearance.bg.image_url', 'URL obrazu tla')) ?></label><input type="url" name="theme_bg_image" value="<?= htmlspecialchars($themeSettings['bg_image']) ?>"></div>
                            <div class="cust-row bg-opt-image" <?= $themeSettings['bg_type'] !== 'image' ? 'style="display:none"' : '' ?>><label><?= htmlspecialchars(cms_t('admin.appearance.bg.attachment', 'Zachowanie tla')) ?></label><select name="theme_bg_attachment"><option value="scroll" <?= $themeSettings['bg_attachment'] === 'scroll' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.appearance.bg.scroll', 'Ruchome')) ?></option><option value="fixed" <?= $themeSettings['bg_attachment'] === 'fixed' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.appearance.bg.fixed', 'Nieruchome')) ?></option></select></div>
                        </div>
                        <div class="cust-group"><div class="cust-group-title"><?= htmlspecialchars(cms_t('admin.appearance.typography', 'Czcionki i uklad')) ?></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.font.body', 'Czcionka tresci')) ?></label><select name="theme_font_body"><?php foreach (['system','inter','lato','montserrat','poppins','roboto','merriweather'] as $f): ?><option value="<?= $f ?>" <?= $themeSettings['font_body'] === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option><?php endforeach; ?></select></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.font.heading', 'Czcionka naglowkow')) ?></label><select name="theme_font_heading"><?php foreach (['system','inter','lato','montserrat','poppins','roboto','playfair','merriweather'] as $f): ?><option value="<?= $f ?>" <?= $themeSettings['font_heading'] === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option><?php endforeach; ?></select></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.font.size', 'Rozmiar bazowy')) ?></label><input type="range" name="theme_font_size" min="12" max="22" value="<?= (int) $themeSettings['font_size'] ?>"></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.radius', 'Zaokraglenie')) ?></label><input type="range" name="theme_radius" min="0" max="48" value="<?= (int) $themeSettings['radius'] ?>"></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.container_width', 'Szerokosc kontenera')) ?></label><input type="range" name="theme_container_width" min="800" max="1600" step="40" value="<?= (int) $themeSettings['container_width'] ?>"></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.header_style', 'Styl naglowka')) ?></label><select name="theme_header_style"><option value="glass" <?= $themeSettings['header_style'] === 'glass' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.appearance.header.glass', 'Szklany')) ?></option><option value="solid" <?= $themeSettings['header_style'] === 'solid' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.appearance.header.solid', 'Pelny')) ?></option><option value="transparent" <?= $themeSettings['header_style'] === 'transparent' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.appearance.header.transparent', 'Przezroczysty')) ?></option></select></div>
                            <div class="cust-row" id="headerBgRow" <?= $themeSettings['header_style'] !== 'solid' ? 'style="display:none"' : '' ?>><label><?= htmlspecialchars(cms_t('admin.appearance.header_color', 'Kolor naglowka')) ?></label><input type="color" name="theme_header_bg" value="<?= htmlspecialchars($themeSettings['header_bg']) ?>"></div>
                            <div class="cust-row"><label><?= htmlspecialchars(cms_t('admin.appearance.footer_color', 'Kolor stopki')) ?></label><input type="color" name="theme_footer_bg" value="<?= htmlspecialchars($themeSettings['footer_bg']) ?>"></div>
                        </div>
                        <button class="btn" type="submit" style="margin-top:16px;width:100%"><?= htmlspecialchars(cms_t('admin.appearance.btn.save', 'Zapisz styl witryny')) ?></button>
                    </form>
                </div>
                <div class="customizer-preview" id="custPreview">
                    <div class="muted tiny" style="padding:14px 18px 0"><?= htmlspecialchars(cms_t('admin.appearance.preview.drag_help', 'Przeciagnij sekcje podgladu, aby ustawic kolejnosc ukladu calej strony.')) ?></div>
                    <div id="previewSortable" class="preview-sortable">
                        <div class="preview-part" data-preview-part="header" draggable="true">
                            <div class="preview-part-head"><span class="preview-handle">Przeciagnij</span><strong><?= htmlspecialchars(cms_t('admin.appearance.preview.part.header', 'Naglowek')) ?></strong></div>
                            <div class="prev-header"><span class="prev-brand"><?= htmlspecialchars(cms_t('admin.appearance.preview.site_name', 'Nazwa strony')) ?></span><div class="prev-nav"><span><?= htmlspecialchars(cms_t('admin.appearance.preview.nav.home', 'Start')) ?></span><span class="active"><?= htmlspecialchars(cms_t('admin.appearance.preview.nav.offer', 'Oferta')) ?></span><span><?= htmlspecialchars(cms_t('admin.appearance.preview.nav.contact', 'Kontakt')) ?></span></div></div>
                        </div>
                        <div class="preview-part" data-preview-part="hero" draggable="true">
                            <div class="preview-part-head"><span class="preview-handle">Przeciagnij</span><strong><?= htmlspecialchars(cms_t('admin.appearance.preview.part.hero', 'Hero')) ?></strong></div>
                            <div class="prev-body"><div class="prev-card"><div class="prev-eyebrow"><?= htmlspecialchars(cms_t('label.cms_page', 'CMS Page')) ?></div><h2 class="prev-h1"><?= htmlspecialchars(cms_t('admin.appearance.preview.page_title', 'Tytul strony')) ?></h2><p class="prev-lead"><?= htmlspecialchars(cms_t('admin.appearance.preview.desc', 'Podglad wygladu strony na zywo.')) ?></p><a href="#" class="prev-btn" onclick="return false"><?= htmlspecialchars(cms_t('admin.appearance.preview.button', 'Przycisk')) ?></a></div></div>
                        </div>
                        <div class="preview-part" data-preview-part="content" draggable="true">
                            <div class="preview-part-head"><span class="preview-handle">Przeciagnij</span><strong><?= htmlspecialchars(cms_t('admin.appearance.preview.part.content', 'Sekcja tresci')) ?></strong></div>
                            <div class="prev-body"><div class="prev-card"><p class="prev-lead"><?= htmlspecialchars(cms_t('admin.appearance.preview.content_block', 'Tutaj pojawia sie tresc strony i bloki buildera.')) ?></p></div></div>
                        </div>
                        <div class="preview-part" data-preview-part="cta" draggable="true">
                            <div class="preview-part-head"><span class="preview-handle">Przeciagnij</span><strong><?= htmlspecialchars(cms_t('admin.appearance.preview.part.cta', 'Sekcja CTA')) ?></strong></div>
                            <div class="prev-body"><div class="prev-card"><h3 class="prev-h1" style="font-size:18px"><?= htmlspecialchars(cms_t('admin.appearance.preview.cta_title', 'Call to action')) ?></h3><a href="#" class="prev-btn" onclick="return false"><?= htmlspecialchars(cms_t('admin.appearance.preview.cta_button', 'Dzialaj teraz')) ?></a></div></div>
                        </div>
                        <div class="preview-part" data-preview-part="footer" draggable="true">
                            <div class="preview-part-head"><span class="preview-handle">Przeciagnij</span><strong><?= htmlspecialchars(cms_t('admin.appearance.preview.part.footer', 'Stopka')) ?></strong></div>
                            <div class="prev-footer">&copy; 2026 <?= htmlspecialchars(cms_t('admin.appearance.preview.site_name', 'Nazwa strony')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
window.CMS_THEME_PREVIEW_LAYOUT = <?= json_encode($previewLayout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars(cms_url('admin/assets/dashboard.js?v=' . rawurlencode(CMS_CODE_VERSION))) ?>"></script>
</body>
</html>
