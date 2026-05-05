<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

cms_require_login();
$user = cms_current_user();
$db = cms_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        cms_flash('error', 'Nieprawidlowy token bezpieczenstwa.');
        cms_redirect(cms_url('admin/settings.php'));
    }

    try {
        cms_set_setting('site_name', trim($_POST['site_name'] ?? 'My CMS'));
        cms_set_setting('site_tagline', trim($_POST['site_tagline'] ?? ''));
        cms_set_setting('site_mode', ($_POST['site_mode'] ?? 'multipage') === 'onepage' ? 'onepage' : 'multipage');
        cms_set_setting('theme_variant', ($_POST['theme_variant'] ?? 'multipage') === 'onepage' ? 'onepage' : 'multipage');
        cms_set_setting('cms_update_manifest_url', trim($_POST['cms_update_manifest_url'] ?? ''));
        cms_set_setting('store_db_manifest_url', trim($_POST['store_db_manifest_url'] ?? ''));
        cms_set_setting('plugin_store_manifest_url', trim($_POST['plugin_store_manifest_url'] ?? ''));
        cms_set_setting('plugin_store_catalog_key', trim($_POST['plugin_store_catalog_key'] ?? 'plugins'));
        cms_set_setting('plugin_store_directory', trim($_POST['plugin_store_directory'] ?? 'plugins'));
        cms_flash('success', 'Ustawienia CMS zostaly zapisane.');
    } catch (Throwable $e) {
        cms_flash('error', $e->getMessage());
    }

    cms_redirect(cms_url('admin/settings.php'));
}

$flash = cms_pull_flash();
$pageCount = (int) $db->query('SELECT COUNT(*) FROM cms_pages')->fetchColumn();
$pluginCount = (int) $db->query('SELECT COUNT(*) FROM cms_plugins')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia CMS</title>
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
            <a href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>">Wyglad</a>
            <a href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>" class="active">Ustawienia</a>
            <a href="<?= htmlspecialchars(cms_url()) ?>" target="_blank">Podglad strony</a>
            <a href="<?= htmlspecialchars(cms_url('admin/logout.php')) ?>">Wyloguj</a>
        </nav>
        <div style="margin-top:24px" class="muted">Zalogowany: <?= htmlspecialchars($user['username'] ?? 'admin') ?></div>
    </aside>
    <main class="main">
        <div class="topbar"><div><h1 style="margin:0 0 6px">Ustawienia CMS</h1><div class="muted">Konfiguracja ogolna i warstwa danych.</div></div></div>

        <?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

        <div class="grid">
            <div class="stack">
                <section class="panel">
                    <h2>Ustawienia ogolne</h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <div class="field"><label>Nazwa strony</label><input type="text" name="site_name" value="<?= htmlspecialchars(cms_get_setting('site_name', 'My CMS')) ?>"></div>
                        <div class="field"><label>Tagline</label><input type="text" name="site_tagline" value="<?= htmlspecialchars(cms_get_setting('site_tagline', '')) ?>"></div>
                        <div class="field"><label>Tryb strony</label><select name="site_mode"><option value="multipage" <?= cms_site_mode() === 'multipage' ? 'selected' : '' ?>>Wiele stron</option><option value="onepage" <?= cms_site_mode() === 'onepage' ? 'selected' : '' ?>>Onepage</option></select></div>
                        <div class="field"><label>Wariant motywu</label><select name="theme_variant"><option value="multipage" <?= cms_theme_variant() === 'multipage' ? 'selected' : '' ?>>Motyw wielostronicowy</option><option value="onepage" <?= cms_theme_variant() === 'onepage' ? 'selected' : '' ?>>Motyw onepage</option></select></div>
                        <div class="field"><label>Manifest aktualizacji CMS (GitHub Raw URL)</label><input type="url" name="cms_update_manifest_url" placeholder="https://raw.githubusercontent.com/.../cms-update.json" value="<?= htmlspecialchars(cms_get_setting('cms_update_manifest_url', '')) ?>"></div>
                        <div class="field"><label>Manifest bazy sklepu (GitHub Raw URL)</label><input type="url" name="store_db_manifest_url" placeholder="https://raw.githubusercontent.com/.../store-db.json" value="<?= htmlspecialchars(cms_get_setting('store_db_manifest_url', '')) ?>"></div>
                        <div class="field"><label>Manifest pluginow (GitHub Raw URL)</label><input type="url" name="plugin_store_manifest_url" placeholder="https://raw.githubusercontent.com/.../plugins.json" value="<?= htmlspecialchars(cms_get_setting('plugin_store_manifest_url', '')) ?>"></div>
                        <div class="field"><label>Klucz katalogu pluginow w JSON</label><input type="text" name="plugin_store_catalog_key" placeholder="plugins" value="<?= htmlspecialchars(cms_get_setting('plugin_store_catalog_key', 'plugins')) ?>"></div>
                        <div class="field"><label>Katalog pluginow lokalnie</label><input type="text" name="plugin_store_directory" placeholder="plugins" value="<?= htmlspecialchars(cms_get_setting('plugin_store_directory', 'plugins')) ?>"></div>
                        <button class="btn" type="submit">Zapisz ustawienia</button>
                    </form>
                </section>
            </div>
            <div class="stack">
                <section class="panel">
                    <h2>Warstwa danych</h2>
                    <div class="db-meta">
                        <div><strong>Driver:</strong> <?= htmlspecialchars(cms_db_driver()) ?></div>
                        <div><strong>Tryb strony:</strong> <?= htmlspecialchars(cms_site_mode()) ?></div>
                        <div><strong>Wariant motywu:</strong> <?= htmlspecialchars(cms_theme_variant()) ?></div>
                        <div><strong>Config:</strong> <?= htmlspecialchars(cms_config_path()) ?></div>
                        <div><strong>Liczba stron:</strong> <?= $pageCount ?></div>
                        <div><strong>Liczba pluginow:</strong> <?= $pluginCount ?></div>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
</body>
</html>
