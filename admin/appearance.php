<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

cms_require_login();
$user = cms_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        cms_flash('error', 'Nieprawidlowy token bezpieczenstwa.');
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
        cms_flash('success', 'Styl witryny zostal zapisany.');
    } catch (Throwable $e) {
        cms_flash('error', $e->getMessage());
    }

    cms_redirect(cms_url('admin/appearance.php'));
}

$flash = cms_pull_flash();
$themeSettings = cms_get_theme_settings();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wyglad CMS</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('admin/assets/dashboard.css')) ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">CMS</div>
        <div class="muted">Panel zarzadzania</div>
        <nav class="nav">
            <a href="<?= htmlspecialchars(cms_url('admin/dashboard.php')) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>">Strony</a>
            <a href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>">Pluginy</a>
            <a href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>" class="active">Wyglad</a>
            <a href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>">Ustawienia</a>
            <a href="<?= htmlspecialchars(cms_url()) ?>" target="_blank">Podglad strony</a>
            <a href="<?= htmlspecialchars(cms_url('admin/logout.php')) ?>">Wyloguj</a>
        </nav>
        <div style="margin-top:24px" class="muted">Zalogowany: <?= htmlspecialchars($user['username'] ?? 'admin') ?></div>
    </aside>
    <main class="main">
        <div class="topbar"><div><h1 style="margin:0 0 6px">Wyglad witryny</h1><div class="muted">Wizualny edytor calego stylu strony.</div></div></div>
        <?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

        <section class="panel customizer-panel">
            <h2>Styl witryny &mdash; wizualny edytor wygladu</h2>
            <p class="muted" style="margin:0 0 20px">Zmien kolory, czcionki, tlo i uklad calej strony. Podglad odswiezany na zywo.</p>
            <div class="customizer-layout">
                <div class="customizer-controls">
                    <form method="post" id="themeForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <div class="cust-group"><div class="cust-group-title">Kolory</div>
                            <div class="cust-row"><label>Kolor wiodacy</label><input type="color" name="theme_accent" value="<?= htmlspecialchars($themeSettings['accent']) ?>"></div>
                            <div class="cust-row"><label>Kolor tekstu</label><input type="color" name="theme_text" value="<?= htmlspecialchars($themeSettings['text']) ?>"></div>
                            <div class="cust-row"><label>Tlo kart</label><input type="color" name="theme_panel" value="<?= htmlspecialchars($themeSettings['panel']) ?>"></div>
                            <div class="cust-row"><label>Kolor drugoplanowy</label><input type="color" name="theme_muted" value="<?= htmlspecialchars($themeSettings['muted']) ?>"></div>
                            <div class="cust-row"><label>Kolor obramowania</label><input type="color" name="theme_border" value="<?= htmlspecialchars($themeSettings['border']) ?>"></div>
                        </div>
                        <div class="cust-group"><div class="cust-group-title">Tlo strony</div>
                            <div class="cust-row"><label>Typ tla</label><select name="theme_bg_type" id="bgTypeSelect"><option value="gradient" <?= $themeSettings['bg_type'] === 'gradient' ? 'selected' : '' ?>>Gradient</option><option value="color" <?= $themeSettings['bg_type'] === 'color' ? 'selected' : '' ?>>Kolor</option><option value="image" <?= $themeSettings['bg_type'] === 'image' ? 'selected' : '' ?>>Obraz</option></select></div>
                            <div class="cust-row bg-opt-color" <?= $themeSettings['bg_type'] !== 'color' ? 'style="display:none"' : '' ?>><label>Kolor tla</label><input type="color" name="theme_bg_color" value="<?= htmlspecialchars($themeSettings['bg_color']) ?>"></div>
                            <div class="cust-row bg-opt-gradient" <?= $themeSettings['bg_type'] !== 'gradient' ? 'style="display:none"' : '' ?>><label>Gradient od</label><input type="color" name="theme_bg_gradient_from" value="<?= htmlspecialchars($themeSettings['bg_gradient_from']) ?>"></div>
                            <div class="cust-row bg-opt-gradient" <?= $themeSettings['bg_type'] !== 'gradient' ? 'style="display:none"' : '' ?>><label>Gradient do</label><input type="color" name="theme_bg_gradient_to" value="<?= htmlspecialchars($themeSettings['bg_gradient_to']) ?>"></div>
                            <div class="cust-row bg-opt-image" <?= $themeSettings['bg_type'] !== 'image' ? 'style="display:none"' : '' ?>><label>URL obrazu tla</label><input type="url" name="theme_bg_image" value="<?= htmlspecialchars($themeSettings['bg_image']) ?>"></div>
                            <div class="cust-row bg-opt-image" <?= $themeSettings['bg_type'] !== 'image' ? 'style="display:none"' : '' ?>><label>Zachowanie tla</label><select name="theme_bg_attachment"><option value="scroll" <?= $themeSettings['bg_attachment'] === 'scroll' ? 'selected' : '' ?>>Ruchome</option><option value="fixed" <?= $themeSettings['bg_attachment'] === 'fixed' ? 'selected' : '' ?>>Nieruchome</option></select></div>
                        </div>
                        <div class="cust-group"><div class="cust-group-title">Czcionki i uklad</div>
                            <div class="cust-row"><label>Czcionka tresci</label><select name="theme_font_body"><?php foreach (['system','inter','lato','montserrat','poppins','roboto','merriweather'] as $f): ?><option value="<?= $f ?>" <?= $themeSettings['font_body'] === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option><?php endforeach; ?></select></div>
                            <div class="cust-row"><label>Czcionka naglowkow</label><select name="theme_font_heading"><?php foreach (['system','inter','lato','montserrat','poppins','roboto','playfair','merriweather'] as $f): ?><option value="<?= $f ?>" <?= $themeSettings['font_heading'] === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option><?php endforeach; ?></select></div>
                            <div class="cust-row"><label>Rozmiar bazowy</label><input type="range" name="theme_font_size" min="12" max="22" value="<?= (int) $themeSettings['font_size'] ?>"></div>
                            <div class="cust-row"><label>Zaokraglenie</label><input type="range" name="theme_radius" min="0" max="48" value="<?= (int) $themeSettings['radius'] ?>"></div>
                            <div class="cust-row"><label>Szerokosc kontenera</label><input type="range" name="theme_container_width" min="800" max="1600" step="40" value="<?= (int) $themeSettings['container_width'] ?>"></div>
                            <div class="cust-row"><label>Styl naglowka</label><select name="theme_header_style"><option value="glass" <?= $themeSettings['header_style'] === 'glass' ? 'selected' : '' ?>>Szklany</option><option value="solid" <?= $themeSettings['header_style'] === 'solid' ? 'selected' : '' ?>>Pelny</option><option value="transparent" <?= $themeSettings['header_style'] === 'transparent' ? 'selected' : '' ?>>Przezroczysty</option></select></div>
                            <div class="cust-row" id="headerBgRow" <?= $themeSettings['header_style'] !== 'solid' ? 'style="display:none"' : '' ?>><label>Kolor naglowka</label><input type="color" name="theme_header_bg" value="<?= htmlspecialchars($themeSettings['header_bg']) ?>"></div>
                            <div class="cust-row"><label>Kolor stopki</label><input type="color" name="theme_footer_bg" value="<?= htmlspecialchars($themeSettings['footer_bg']) ?>"></div>
                        </div>
                        <button class="btn" type="submit" style="margin-top:16px;width:100%">Zapisz styl witryny</button>
                    </form>
                </div>
                <div class="customizer-preview" id="custPreview">
                    <div class="prev-header"><span class="prev-brand">Nazwa strony</span><div class="prev-nav"><span>Start</span><span class="active">Oferta</span><span>Kontakt</span></div></div>
                    <div class="prev-body"><div class="prev-card"><div class="prev-eyebrow">CMS Page</div><h2 class="prev-h1">Tytul strony</h2><p class="prev-lead">Podglad wygladu strony na zywo.</p><a href="#" class="prev-btn" onclick="return false">Przycisk</a></div></div>
                    <div class="prev-footer">&copy; 2026 Nazwa strony</div>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="<?= htmlspecialchars(cms_url('admin/assets/dashboard.js')) ?>"></script>
</body>
</html>
