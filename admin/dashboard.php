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
        cms_flash('error', cms_t('admin.flash.csrf_invalid', 'Nieprawidlowy token bezpieczenstwa.'));
        cms_redirect(cms_url('admin/dashboard.php'));
    }

    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update_core') {
            $result = cms_install_or_update_core_from_manifest();
            cms_flash('success', cms_t('admin.dashboard.flash.core_updated', 'CMS zostal zaktualizowany z wersji ') . $result['from'] . cms_t('admin.dashboard.flash.core_updated_to', ' do ') . $result['to'] . '.');
        }
    } catch (Throwable $e) {
        cms_flash('error', $e->getMessage());
    }

    cms_redirect(cms_url('admin/dashboard.php'));
}

$flash = cms_pull_flash();

$pageCount = (int) $db->query('SELECT COUNT(*) FROM cms_pages')->fetchColumn();
$publishedCount = (int) $db->query("SELECT COUNT(*) FROM cms_pages WHERE status = 'published'")->fetchColumn();
$enabledPlugins = (int) $db->query('SELECT COUNT(*) FROM cms_plugins WHERE enabled = 1')->fetchColumn();
$subpageCount = (int) $db->query('SELECT COUNT(*) FROM cms_pages WHERE parent_id IS NOT NULL')->fetchColumn();

$coreUpdate = cms_core_update_info();
$plugins = cms_all_plugins();
$storeIndex = cms_plugin_store_index();
$pluginUpdates = cms_plugin_updates_map($plugins, $storeIndex);
$pluginsWithUpdate = array_values(array_filter(
    $pluginUpdates,
    static fn(array $item): bool => !empty($item['has_update'])
));
$pluginUpdateCount = count($pluginsWithUpdate);
$hasAnyUpdates = !empty($coreUpdate['has_update']) || $pluginUpdateCount > 0;
$coreDownloadUrl = cms_core_update_download_url($coreUpdate);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(cms_admin_language()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(cms_t('admin.dashboard.title', 'Dashboard CMS')) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('admin/assets/dashboard.css')) ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">CMS</div>
        <div class="muted"><?= htmlspecialchars(cms_t('admin.nav.panel', 'Panel zarzadzania')) ?></div>
        <nav class="nav">
            <a href="<?= htmlspecialchars(cms_url('admin/dashboard.php')) ?>" class="active"><?= htmlspecialchars(cms_t('admin.nav.dashboard', 'Dashboard')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.pages', 'Strony')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.plugins', 'Pluginy')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.appearance', 'Wyglad')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.settings', 'Ustawienia')) ?></a>
            <a href="<?= htmlspecialchars(cms_url()) ?>" target="_blank"><?= htmlspecialchars(cms_t('admin.nav.preview', 'Podglad strony')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/logout.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.logout', 'Wyloguj')) ?></a>
        </nav>
        <div style="margin-top:24px" class="muted"><?= htmlspecialchars(cms_t('admin.nav.logged_as', 'Zalogowany:')) ?> <?= htmlspecialchars($user['username'] ?? 'admin') ?></div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div style="display:flex;align-items:flex-start;gap:12px">
                <button id="sidebarToggleBtn" class="btn ghost" title="Ukryj panel boczny" style="padding:8px 13px;font-size:18px;line-height:1;flex-shrink:0;margin-top:3px">&#8249;</button>
                <div>
                    <h1 style="margin:0 0 6px"><?= htmlspecialchars(cms_t('admin.dashboard.heading', 'Dashboard CMS')) ?></h1>
                    <div class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.subheading', 'Wszystkie funkcje sa dostepne jako osobne podstrony panelu.')) ?></div>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <section class="panel" style="margin-bottom:20px; border-color: <?= $hasAnyUpdates ? 'rgba(251,191,36,.55)' : 'rgba(22,163,74,.5)' ?>;">
            <h2 style="margin-top:0"><?= htmlspecialchars(cms_t('admin.dashboard.updates.heading', 'Aktualizacje systemu')) ?></h2>
            <?php if ($hasAnyUpdates): ?>
                <p style="margin:0 0 10px; color:#fbbf24; font-weight:700"><?= htmlspecialchars(cms_t('admin.dashboard.updates.available', 'Masz nowe aktualizacje do zainstalowania.')) ?></p>
            <?php else: ?>
                <p style="margin:0 0 10px; color:#86efac; font-weight:700"><?= htmlspecialchars(cms_t('admin.dashboard.updates.current', 'System jest aktualny.')) ?></p>
            <?php endif; ?>

            <div class="split" style="margin-bottom:12px">
                <div class="card" style="padding:14px">
                    <div class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.core', 'Core CMS')) ?></div>
                    <div style="font-size:18px;font-weight:800;margin-top:6px">
                        <?= htmlspecialchars((string) $coreUpdate['current_version']) ?>
                        <?php if (!empty($coreUpdate['checked'])): ?>
                            <span class="muted" style="font-size:14px"> -> <?= htmlspecialchars((string) $coreUpdate['remote_version']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="muted" style="font-size:12px;margin-top:4px"><?= htmlspecialchars(cms_t('admin.dashboard.code', 'Kod:')) ?> <?= htmlspecialchars(CMS_CODE_VERSION) ?></div>
                    <div style="margin-top:8px">
                        <?php if (!empty($coreUpdate['has_update'])): ?>
                            <span class="badge" style="background:rgba(251,191,36,.14);color:#fbbf24"><?= htmlspecialchars(cms_t('admin.dashboard.core.new_version', 'Nowa wersja dostepna')) ?></span>
                        <?php elseif (!empty($coreUpdate['checked'])): ?>
                            <span class="badge ok"><?= htmlspecialchars(cms_t('admin.dashboard.core.current', 'Aktualny')) ?></span>
                        <?php else: ?>
                            <span class="badge off"><?= htmlspecialchars(cms_t('admin.dashboard.core.no_manifest', 'Brak polaczenia z manifestem')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card" style="padding:14px">
                    <div class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.plugins', 'Pluginy')) ?></div>
                    <div style="font-size:18px;font-weight:800;margin-top:6px">
                        <?= $pluginUpdateCount ?>
                        <span class="muted" style="font-size:14px"> <?= htmlspecialchars(cms_t('admin.dashboard.plugins.updates', 'aktualizacji /')) ?> <?= count($plugins) ?> <?= htmlspecialchars(cms_t('admin.dashboard.plugins.installed', 'zainstalowanych')) ?></span>
                    </div>
                    <div style="margin-top:8px">
                        <?php if ($pluginUpdateCount > 0): ?>
                            <span class="badge" style="background:rgba(251,191,36,.14);color:#fbbf24"><?= htmlspecialchars(cms_t('admin.dashboard.plugins.need_update', 'Wymagana aktualizacja pluginow')) ?></span>
                        <?php else: ?>
                            <span class="badge ok"><?= htmlspecialchars(cms_t('admin.dashboard.plugins.current', 'Pluginy aktualne')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($pluginUpdateCount > 0): ?>
                <div class="muted" style="margin-bottom:10px"><?= htmlspecialchars(cms_t('admin.dashboard.plugins.to_update', 'Do aktualizacji:')) ?></div>
                <div class="actions" style="margin-bottom:14px">
                    <?php foreach ($pluginsWithUpdate as $info): ?>
                        <?php $remote = (array) ($info['remote'] ?? []); ?>
                        <span class="badge" style="background:rgba(251,191,36,.14);color:#fbbf24">
                            <?= htmlspecialchars((string) ($remote['name'] ?? $remote['slug'] ?? 'plugin')) ?>
                            <?php if (!empty($remote['version'])): ?>
                                (<?= htmlspecialchars((string) $remote['version']) ?>)
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="actions">
                <?php if (!empty($coreUpdate['has_update']) && $coreDownloadUrl !== ''): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_core">
                        <button class="btn" type="submit"><?= htmlspecialchars(cms_t('admin.dashboard.btn.update_core', 'Aktualizuj CMS')) ?></button>
                    </form>
                <?php else: ?>
                    <button class="btn secondary" type="button" disabled style="opacity:.55;cursor:not-allowed"><?= htmlspecialchars(cms_t('admin.dashboard.btn.update_core', 'Aktualizuj CMS')) ?></button>
                <?php endif; ?>
                <a class="btn" href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>"><?= htmlspecialchars(cms_t('admin.dashboard.btn.plugin_updates', 'Przejdz do aktualizacji pluginow')) ?></a>
                <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>"><?= htmlspecialchars(cms_t('admin.dashboard.btn.update_sources', 'Ustawienia zrodel aktualizacji')) ?></a>
            </div>
        </section>

        <section class="cards">
            <div class="card"><div class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.stats.pages', 'Wszystkie strony')) ?></div><div style="font-size:32px;font-weight:800"><?= $pageCount ?></div></div>
            <div class="card"><div class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.stats.subpages', 'Podstrony')) ?></div><div style="font-size:32px;font-weight:800"><?= $subpageCount ?></div></div>
            <div class="card"><div class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.stats.published', 'Opublikowane')) ?></div><div style="font-size:32px;font-weight:800"><?= $publishedCount ?></div></div>
            <div class="card"><div class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.stats.active_plugins', 'Aktywne pluginy')) ?></div><div style="font-size:32px;font-weight:800"><?= $enabledPlugins ?></div></div>
        </section>

        <div class="grid">
            <div class="stack">
                <section class="panel">
                    <h2><?= htmlspecialchars(cms_t('admin.dashboard.content.heading', 'Praca z trescia')) ?></h2>
                    <p class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.content.desc', 'Zarzadzaj stronami i podstronami oraz builderem blokow.')) ?></p>
                    <div class="actions">
                        <a class="btn" href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>"><?= htmlspecialchars(cms_t('admin.dashboard.btn.goto_pages', 'Przejdz do Stron')) ?></a>
                        <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>?edit="><?= htmlspecialchars(cms_t('admin.dashboard.btn.add_page', 'Dodaj strone')) ?></a>
                    </div>
                </section>

                <section class="panel">
                    <h2><?= htmlspecialchars(cms_t('admin.dashboard.plugins_layout.heading', 'Pluginy i rozmieszczenie')) ?></h2>
                    <p class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.plugins_layout.desc', 'Instaluj i aktualizuj pluginy ze sklepu, a potem umieszczaj je na stronach (drag and drop + pozycja).')) ?></p>
                    <div class="actions">
                        <a class="btn" href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>"><?= htmlspecialchars(cms_t('admin.dashboard.btn.goto_plugins', 'Przejdz do Pluginow')) ?></a>
                    </div>
                </section>
            </div>

            <div class="stack">
                <section class="panel">
                    <h2><?= htmlspecialchars(cms_t('admin.dashboard.appearance.heading', 'Wyglad i konfiguracja')) ?></h2>
                    <p class="muted"><?= htmlspecialchars(cms_t('admin.dashboard.appearance.desc', 'Edytor wygladu calej witryny oraz ustawienia systemowe sa rozdzielone na podstrony.')) ?></p>
                    <div class="actions">
                        <a class="btn" href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.appearance', 'Wyglad')) ?></a>
                        <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.settings', 'Ustawienia')) ?></a>
                    </div>
                </section>

                <?php $dashboardHook = cms_collect_hook_output('admin_dashboard_cards'); if ($dashboardHook !== ''): ?>
                    <section class="panel"><?= $dashboardHook ?></section>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
