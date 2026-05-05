<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

cms_require_login();
$user = cms_current_user();
$db = cms_db();
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
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard CMS</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('admin/assets/dashboard.css')) ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">CMS</div>
        <div class="muted">Panel zarzadzania</div>
        <nav class="nav">
            <a href="<?= htmlspecialchars(cms_url('admin/dashboard.php')) ?>" class="active">Dashboard</a>
            <a href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>">Strony</a>
            <a href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>">Pluginy</a>
            <a href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>">Wyglad</a>
            <a href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>">Ustawienia</a>
            <a href="<?= htmlspecialchars(cms_url()) ?>" target="_blank">Podglad strony</a>
            <a href="<?= htmlspecialchars(cms_url('admin/logout.php')) ?>">Wyloguj</a>
        </nav>
        <div style="margin-top:24px" class="muted">Zalogowany: <?= htmlspecialchars($user['username'] ?? 'admin') ?></div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h1 style="margin:0 0 6px">Dashboard CMS</h1>
                <div class="muted">Wszystkie funkcje sa dostepne jako osobne podstrony panelu.</div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <section class="panel" style="margin-bottom:20px; border-color: <?= $hasAnyUpdates ? 'rgba(251,191,36,.55)' : 'rgba(22,163,74,.5)' ?>;">
            <h2 style="margin-top:0">Aktualizacje systemu</h2>
            <?php if ($hasAnyUpdates): ?>
                <p style="margin:0 0 10px; color:#fbbf24; font-weight:700">Masz nowe aktualizacje do zainstalowania.</p>
            <?php else: ?>
                <p style="margin:0 0 10px; color:#86efac; font-weight:700">System jest aktualny.</p>
            <?php endif; ?>

            <div class="split" style="margin-bottom:12px">
                <div class="card" style="padding:14px">
                    <div class="muted">Core CMS</div>
                    <div style="font-size:18px;font-weight:800;margin-top:6px">
                        <?= htmlspecialchars((string) $coreUpdate['current_version']) ?>
                        <?php if (!empty($coreUpdate['checked'])): ?>
                            <span class="muted" style="font-size:14px"> -> <?= htmlspecialchars((string) $coreUpdate['remote_version']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top:8px">
                        <?php if (!empty($coreUpdate['has_update'])): ?>
                            <span class="badge" style="background:rgba(251,191,36,.14);color:#fbbf24">Nowa wersja dostepna</span>
                        <?php elseif (!empty($coreUpdate['checked'])): ?>
                            <span class="badge ok">Aktualny</span>
                        <?php else: ?>
                            <span class="badge off">Brak polaczenia z manifestem</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card" style="padding:14px">
                    <div class="muted">Pluginy</div>
                    <div style="font-size:18px;font-weight:800;margin-top:6px">
                        <?= $pluginUpdateCount ?>
                        <span class="muted" style="font-size:14px"> aktualizacji / <?= count($plugins) ?> zainstalowanych</span>
                    </div>
                    <div style="margin-top:8px">
                        <?php if ($pluginUpdateCount > 0): ?>
                            <span class="badge" style="background:rgba(251,191,36,.14);color:#fbbf24">Wymagana aktualizacja pluginow</span>
                        <?php else: ?>
                            <span class="badge ok">Pluginy aktualne</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($pluginUpdateCount > 0): ?>
                <div class="muted" style="margin-bottom:10px">Do aktualizacji:</div>
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
                    <a class="btn" href="<?= htmlspecialchars($coreDownloadUrl) ?>" target="_blank" rel="noopener noreferrer">Aktualizuj CMS</a>
                <?php else: ?>
                    <button class="btn secondary" type="button" disabled style="opacity:.55;cursor:not-allowed">Aktualizuj CMS</button>
                <?php endif; ?>
                <a class="btn" href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>">Przejdz do aktualizacji pluginow</a>
                <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>">Ustawienia zrodel aktualizacji</a>
            </div>
        </section>

        <section class="cards">
            <div class="card"><div class="muted">Wszystkie strony</div><div style="font-size:32px;font-weight:800"><?= $pageCount ?></div></div>
            <div class="card"><div class="muted">Podstrony</div><div style="font-size:32px;font-weight:800"><?= $subpageCount ?></div></div>
            <div class="card"><div class="muted">Opublikowane</div><div style="font-size:32px;font-weight:800"><?= $publishedCount ?></div></div>
            <div class="card"><div class="muted">Aktywne pluginy</div><div style="font-size:32px;font-weight:800"><?= $enabledPlugins ?></div></div>
        </section>

        <div class="grid">
            <div class="stack">
                <section class="panel">
                    <h2>Praca z trescia</h2>
                    <p class="muted">Zarzadzaj stronami i podstronami oraz builderem blokow.</p>
                    <div class="actions">
                        <a class="btn" href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>">Przejdz do Stron</a>
                        <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>?edit=">Dodaj strone</a>
                    </div>
                </section>

                <section class="panel">
                    <h2>Pluginy i rozmieszczenie</h2>
                    <p class="muted">Instaluj i aktualizuj pluginy ze sklepu, a potem umieszczaj je na stronach (drag and drop + pozycja).</p>
                    <div class="actions">
                        <a class="btn" href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>">Przejdz do Pluginow</a>
                    </div>
                </section>
            </div>

            <div class="stack">
                <section class="panel">
                    <h2>Wyglad i konfiguracja</h2>
                    <p class="muted">Edytor wygladu calej witryny oraz ustawienia systemowe sa rozdzielone na podstrony.</p>
                    <div class="actions">
                        <a class="btn" href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>">Wyglad</a>
                        <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>">Ustawienia</a>
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
