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
        cms_redirect(cms_url('admin/plugins.php'));
    }

    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'toggle_plugin':
                cms_set_plugin_enabled((string) ($_POST['slug'] ?? ''), !empty($_POST['enable']));
                cms_flash('success', 'Status pluginu zostal zmieniony.');
                break;
            case 'install_plugin':
            case 'update_plugin':
                $result = cms_install_or_update_plugin_from_store((string) ($_POST['slug'] ?? ''));
                cms_flash('success', $result['updated'] ? 'Plugin zostal zaktualizowany: ' . $result['name'] : 'Plugin zostal zainstalowany: ' . $result['name']);
                break;
            case 'upload_plugin_zip':
                if (empty($_FILES['plugin_zip']) || !is_array($_FILES['plugin_zip'])) {
                    throw new RuntimeException('Nie wybrano pliku ZIP pluginu.');
                }
                $file = $_FILES['plugin_zip'];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Blad uploadu pliku ZIP pluginu.');
                }
                $tmpPath = (string) ($file['tmp_name'] ?? '');
                $result = cms_install_or_update_plugin_from_zip_path($tmpPath);
                cms_flash('success', $result['updated'] ? 'Plugin z ZIP zostal zaktualizowany: ' . $result['name'] : 'Plugin z ZIP zostal zainstalowany: ' . $result['name']);
                break;
            case 'add_plugin_to_store':
                $slug = trim((string) ($_POST['store_slug'] ?? ''));
                $name = trim((string) ($_POST['store_name'] ?? ''));
                $version = trim((string) ($_POST['store_version'] ?? '0.0.0'));
                $description = trim((string) ($_POST['store_description'] ?? ''));
                $downloadUrl = trim((string) ($_POST['store_download_url'] ?? ''));
                $repository = trim((string) ($_POST['store_repository'] ?? ''));
                if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
                    throw new RuntimeException('Slug pluginu sklepu jest niepoprawny.');
                }
                if ($name === '') {
                    throw new RuntimeException('Nazwa pluginu sklepu jest wymagana.');
                }
                if (!preg_match('#^https?://#i', $downloadUrl)) {
                    throw new RuntimeException('Download URL musi byc poprawnym adresem HTTP/HTTPS.');
                }
                cms_local_store_add_plugin([
                    'slug' => $slug,
                    'name' => $name,
                    'version' => $version,
                    'description' => $description,
                    'download_url' => $downloadUrl,
                    'repository' => $repository,
                ]);
                cms_flash('success', 'Plugin zostal zapisany w lokalnym sklepie.');
                break;
            case 'save_facebook_plugin_settings':
                cms_set_setting('facebook_plugin_page_id', trim((string) ($_POST['facebook_plugin_page_id'] ?? '')));
                cms_set_setting('facebook_plugin_access_token', trim((string) ($_POST['facebook_plugin_access_token'] ?? '')));
                cms_set_setting('facebook_plugin_mode', in_array(($_POST['facebook_plugin_mode'] ?? 'posts'), ['posts', 'events'], true) ? (string) $_POST['facebook_plugin_mode'] : 'posts');
                cms_set_setting('facebook_plugin_limit', (string) max(1, min(20, (int) ($_POST['facebook_plugin_limit'] ?? 5))));
                cms_flash('success', 'Ustawienia pluginu Facebook zapisane.');
                break;
            case 'save_plugin_placements':
                $pageId = (int) ($_POST['placement_page_id'] ?? 0);
                $placements = json_decode((string) ($_POST['placements_json'] ?? '[]'), true);
                cms_save_page_plugin_placements($pageId, is_array($placements) ? $placements : []);
                cms_flash('success', 'Ustawienia rozmieszczenia pluginow zostaly zapisane.');
                break;
        }
    } catch (Throwable $e) {
        cms_flash('error', $e->getMessage());
    }

    $targetPageId = (int) ($_POST['placement_page_id'] ?? 0);
    $redirect = cms_url('admin/plugins.php');
    if ($targetPageId > 0) {
        $redirect .= '?page_id=' . $targetPageId;
    }
    cms_redirect($redirect);
}

$flash = cms_pull_flash();
$plugins = cms_all_plugins();
$catalog = cms_plugin_store_catalog();
$storeIndex = cms_plugin_store_index();
$pluginUpdates = cms_plugin_updates_map($plugins, $storeIndex);
$installedBySlug = [];
foreach ($plugins as $plugin) {
    $installedBySlug[(string) $plugin['slug']] = $plugin;
}

$allPages = cms_all_pages(false);
$selectedPageId = isset($_GET['page_id']) ? (int) $_GET['page_id'] : 0;
if ($selectedPageId <= 0 && $allPages !== []) {
    $selectedPageId = (int) $allPages[0]['id'];
}
$selectedPage = $selectedPageId > 0 ? cms_page_by_id($selectedPageId) : null;
$placements = $selectedPageId > 0 ? cms_page_plugin_placements($selectedPageId) : [];
$placementsBySlug = [];
foreach ($placements as $placement) {
    $placementsBySlug[(string) $placement['plugin_slug']] = $placement;
}
$enabledPlugins = cms_enabled_plugins();
$facebookMode = cms_get_setting('facebook_plugin_mode', 'posts');
$facebookLimit = cms_get_setting('facebook_plugin_limit', '5');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pluginy CMS</title>
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
            <a href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>" class="active">Pluginy</a>
            <a href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>">Wyglad</a>
            <a href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>">Ustawienia</a>
            <a href="<?= htmlspecialchars(cms_url()) ?>" target="_blank">Podglad strony</a>
            <a href="<?= htmlspecialchars(cms_url('admin/logout.php')) ?>">Wyloguj</a>
        </nav>
        <div style="margin-top:24px" class="muted">Zalogowany: <?= htmlspecialchars($user['username'] ?? 'admin') ?></div>
    </aside>
    <main class="main">
        <div class="topbar"><div><h1 style="margin:0 0 6px">Pluginy</h1><div class="muted">Instalacja, aktualizacje i umieszczanie pluginow na stronach.</div></div></div>

        <?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

        <div class="grid">
            <div class="stack">
                <section class="panel">
                    <h2>Pluginy lokalne</h2>
                    <div class="plugin-list">
                        <?php foreach ($plugins as $plugin): ?>
                            <?php $slug = (string) $plugin['slug']; $updateInfo = $pluginUpdates[$slug] ?? null; ?>
                            <div class="plugin-card">
                                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                                    <div>
                                        <strong><?= htmlspecialchars($plugin['name']) ?></strong>
                                        <div class="muted"><?= htmlspecialchars($plugin['description']) ?></div>
                                        <small class="muted">Slug: <?= htmlspecialchars($plugin['slug']) ?> | Wersja: <?= htmlspecialchars($plugin['version']) ?></small>
                                        <?php if ($updateInfo && !empty($updateInfo['has_update'])): ?><div><small style="color:#fbbf24">Dostepna aktualizacja: <?= htmlspecialchars((string) ($updateInfo['remote']['version'] ?? 'n/a')) ?></small></div><?php endif; ?>
                                    </div>
                                    <span class="badge <?= (int) $plugin['enabled'] === 1 ? 'ok' : 'off' ?>"><?= (int) $plugin['enabled'] === 1 ? 'Aktywny' : 'Wylaczony' ?></span>
                                </div>
                                <div class="table-actions" style="margin-top:12px">
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>"><input type="hidden" name="action" value="toggle_plugin"><input type="hidden" name="slug" value="<?= htmlspecialchars($plugin['slug']) ?>"><input type="hidden" name="enable" value="<?= (int) $plugin['enabled'] === 1 ? '0' : '1' ?>"><button class="btn secondary" type="submit"><?= (int) $plugin['enabled'] === 1 ? 'Wylacz plugin' : 'Wlacz plugin' ?></button></form>
                                    <?php if ($updateInfo && !empty($updateInfo['has_update'])): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>"><input type="hidden" name="action" value="update_plugin"><input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>"><button class="btn" type="submit">Aktualizuj z serwera</button></form><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="panel">
                    <h2>Sklep pluginow GitHub</h2>
                    <div class="field" style="margin-bottom:10px">
                        <label>Wyszukaj plugin w sklepie</label>
                        <input type="text" id="storeSearchInput" placeholder="np. comments, facebook...">
                    </div>
                    <?php if ($catalog): ?>
                        <div class="store-list" id="storeList">
                            <?php foreach ($catalog as $item): ?>
                                <?php
                                    $storeSlug = (string) ($item['slug'] ?? '');
                                    $installed = $storeSlug !== '' ? ($installedBySlug[$storeSlug] ?? null) : null;
                                    $hasUpdate = is_array($installed) && isset($pluginUpdates[$storeSlug]) && !empty($pluginUpdates[$storeSlug]['has_update']);
                                ?>
                                <div class="plugin-card" data-store-card data-search-text="<?= htmlspecialchars(strtolower((string) (($item['name'] ?? '') . ' ' . ($item['slug'] ?? '') . ' ' . ($item['description'] ?? '')))) ?>">
                                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                                        <strong><?= htmlspecialchars((string) ($item['name'] ?? 'Plugin')) ?></strong>
                                        <?php if ($installed): ?><span class="badge <?= $hasUpdate ? '' : 'ok' ?>" <?= $hasUpdate ? 'style="background:rgba(251,191,36,.14);color:#fbbf24"' : '' ?>><?= $hasUpdate ? 'Aktualizacja dostepna' : 'Zainstalowany' ?></span><?php else: ?><span class="badge off">Nie zainstalowany</span><?php endif; ?>
                                    </div>
                                    <div class="muted"><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></div>
                                    <small class="muted">Slug: <?= htmlspecialchars($storeSlug !== '' ? $storeSlug : 'brak') ?> | Wersja sklepu: <?= htmlspecialchars((string) ($item['version'] ?? 'n/a')) ?></small>
                                    <?php if ($storeSlug !== '' && !empty($item['download_url'])): ?>
                                        <form method="post" class="table-actions" style="margin-top:10px">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                                            <input type="hidden" name="action" value="<?= $installed ? ($hasUpdate ? 'update_plugin' : 'install_plugin') : 'install_plugin' ?>">
                                            <input type="hidden" name="slug" value="<?= htmlspecialchars($storeSlug) ?>">
                                            <button class="btn" type="submit" <?= $installed && !$hasUpdate ? 'disabled style="opacity:.55;cursor:not-allowed"' : '' ?>><?= $installed ? ($hasUpdate ? 'Aktualizuj' : 'Aktualny') : 'Instaluj' ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!empty($item['repository'])): ?><div style="margin-top:10px"><a class="btn secondary" href="<?= htmlspecialchars((string) $item['repository']) ?>" target="_blank" rel="noopener noreferrer">Repozytorium</a></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">Podaj URL manifestu sklepu w Ustawieniach, aby pobrac liste pluginow.</p>
                    <?php endif; ?>

                    <h3 style="margin-top:18px">Dodaj plugin do lokalnego sklepu</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_plugin_to_store">
                        <div class="split">
                            <div class="field"><label>Slug</label><input type="text" name="store_slug" required placeholder="np. comments"></div>
                            <div class="field"><label>Nazwa</label><input type="text" name="store_name" required placeholder="np. Comments Plugin"></div>
                        </div>
                        <div class="split">
                            <div class="field"><label>Wersja</label><input type="text" name="store_version" value="1.0.0"></div>
                            <div class="field"><label>Download URL (ZIP)</label><input type="url" name="store_download_url" required placeholder="https://..."></div>
                        </div>
                        <div class="field"><label>Repozytorium</label><input type="url" name="store_repository" placeholder="https://github.com/... "></div>
                        <div class="field"><label>Opis</label><textarea name="store_description"></textarea></div>
                        <button class="btn" type="submit">Zapisz w sklepie</button>
                    </form>
                </section>

                <section class="panel">
                    <h2>Upload wlasnego pluginu</h2>
                    <p class="muted">Wgraj paczke ZIP pluginu (musi zawierac plik plugin.json).</p>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="action" value="upload_plugin_zip">
                        <div class="field"><label>Plik ZIP pluginu</label><input type="file" name="plugin_zip" accept=".zip,application/zip" required></div>
                        <button class="btn" type="submit">Wgraj i zainstaluj</button>
                    </form>
                </section>
            </div>

            <div class="stack">
                <section class="panel">
                    <h2>Umieszczenie pluginow na stronie</h2>
                    <form method="get" style="margin-bottom:12px">
                        <div class="field">
                            <label>Wybierz strone</label>
                            <select name="page_id" onchange="this.form.submit()">
                                <?php foreach ($allPages as $page): ?>
                                    <option value="<?= (int) $page['id'] ?>" <?= (int) $page['id'] === $selectedPageId ? 'selected' : '' ?>><?= htmlspecialchars($page['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <?php if ($selectedPage): ?>
                        <p class="muted">Przeciagnij elementy, aby ustawic kolejnosc. Wybierz, czy plugin ma byc przed czy po tresci.</p>
                        <div style="margin-bottom:10px"><a class="btn secondary" target="_blank" href="<?= htmlspecialchars(cms_url('?page=' . urlencode((string) $selectedPage['slug']))) ?>">Podglad wybranej strony</a></div>
                        <form method="post" id="pluginPlacementForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                            <input type="hidden" name="action" value="save_plugin_placements">
                            <input type="hidden" name="placement_page_id" value="<?= (int) $selectedPageId ?>">
                            <input type="hidden" name="placements_json" id="placementsInput" value="[]">
                            <div id="placementList" class="placement-list">
                                <?php foreach ($enabledPlugins as $plugin): ?>
                                    <?php
                                        $slug = (string) $plugin['slug'];
                                        $placed = $placementsBySlug[$slug] ?? null;
                                        $enabled = $placed !== null;
                                        $position = $enabled ? (string) ($placed['position'] ?? 'after_content') : 'after_content';
                                    ?>
                                    <div class="placement-item" draggable="true" data-slug="<?= htmlspecialchars($slug) ?>">
                                        <div class="placement-handle">::</div>
                                        <div>
                                            <strong><?= htmlspecialchars($plugin['name']) ?></strong>
                                            <div class="muted"><?= htmlspecialchars($plugin['description']) ?></div>
                                        </div>
                                        <label class="inline"><input data-field="enabled" type="checkbox" <?= $enabled ? 'checked' : '' ?>> Na stronie</label>
                                        <select data-field="position"><option value="before_content" <?= $position === 'before_content' ? 'selected' : '' ?>>Przed trescia</option><option value="after_content" <?= $position === 'after_content' ? 'selected' : '' ?>>Po tresci</option></select>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="actions" style="margin-top:14px"><button class="btn" type="submit">Zapisz rozmieszczenie</button></div>
                        </form>
                    <?php else: ?>
                        <p class="muted">Brak stron. Najpierw utworz strone w zakladce Strony.</p>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <h2>Ustawienia pluginu Facebook</h2>
                    <p class="muted">Mozesz pobierac posty lub wydarzenia z Facebooka przez Page ID i Access Token.</p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_facebook_plugin_settings">
                        <div class="field"><label>Page ID</label><input type="text" name="facebook_plugin_page_id" value="<?= htmlspecialchars(cms_get_setting('facebook_plugin_page_id', '')) ?>"></div>
                        <div class="field"><label>Access Token</label><input type="text" name="facebook_plugin_access_token" value="<?= htmlspecialchars(cms_get_setting('facebook_plugin_access_token', '')) ?>"></div>
                        <div class="split">
                            <div class="field"><label>Tryb</label><select name="facebook_plugin_mode"><option value="posts" <?= $facebookMode === 'posts' ? 'selected' : '' ?>>Posty</option><option value="events" <?= $facebookMode === 'events' ? 'selected' : '' ?>>Wydarzenia</option></select></div>
                            <div class="field"><label>Limit</label><input type="number" min="1" max="20" name="facebook_plugin_limit" value="<?= htmlspecialchars($facebookLimit) ?>"></div>
                        </div>
                        <button class="btn" type="submit">Zapisz ustawienia Facebook</button>
                    </form>
                </section>
            </div>
        </div>
    </main>
</div>
<script src="<?= htmlspecialchars(cms_url('admin/assets/dashboard.js')) ?>"></script>
<script>
(function(){
    var input=document.getElementById('storeSearchInput');
    if(!input){return;}
    var cards=[].slice.call(document.querySelectorAll('[data-store-card]'));
    input.addEventListener('input',function(){
        var q=(input.value||'').toLowerCase().trim();
        cards.forEach(function(card){
            var txt=(card.getAttribute('data-search-text')||'').toLowerCase();
            card.style.display=(q===''||txt.indexOf(q)!==-1)?'':'none';
        });
    });
}());
</script>
</body>
</html>
