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
        cms_redirect(cms_url('admin/pages.php'));
    }

    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'save_page':
                $id = isset($_POST['page_id']) && $_POST['page_id'] !== '' ? (int) $_POST['page_id'] : null;
                cms_save_page($_POST, $id);
                cms_flash('success', $id ? 'Strona zostala zaktualizowana.' : 'Strona zostala dodana.');
                break;
            case 'delete_page':
                cms_delete_page((int) ($_POST['page_id'] ?? 0));
                cms_flash('success', 'Strona zostala usunieta.');
                break;
        }
    } catch (Throwable $e) {
        cms_flash('error', $e->getMessage());
    }

    cms_redirect(cms_url('admin/pages.php'));
}

$flash = cms_pull_flash();
$pages = cms_all_pages(false);
$rootPages = cms_root_pages(false);
$editPage = null;
if (isset($_GET['edit'])) {
    $editPage = cms_page_by_id((int) $_GET['edit']);
}

$builderBlocks = cms_normalize_builder_blocks($editPage['builder_data'] ?? '[]');
$parentMap = [];
foreach ($pages as $pageItem) {
    $parentMap[(int) $pageItem['id']] = $pageItem['title'];
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strony CMS</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('admin/assets/dashboard.css')) ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">CMS</div>
        <div class="muted">Panel zarzadzania</div>
        <nav class="nav">
            <a href="<?= htmlspecialchars(cms_url('admin/dashboard.php')) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>" class="active">Strony</a>
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
                <h1 style="margin:0 0 6px">Strony i podstrony</h1>
                <div class="muted">Tworzenie stron, podstron i builder drag and drop.</div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="stack">
                <section class="panel">
                    <h2><?= $editPage ? 'Edytuj strone / podstrone' : 'Dodaj nowa strone / podstrone' ?></h2>
                    <form method="post" id="pageEditorForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_page">
                        <input type="hidden" name="page_id" value="<?= htmlspecialchars((string) ($editPage['id'] ?? '')) ?>">
                        <input type="hidden" name="builder_data" id="builderDataInput" value="<?= htmlspecialchars(json_encode($builderBlocks, JSON_UNESCAPED_UNICODE)) ?>">

                        <div class="split">
                            <div class="field"><label>Tytul</label><input type="text" name="title" required value="<?= htmlspecialchars($editPage['title'] ?? '') ?>"></div>
                            <div class="field"><label>Slug</label><input type="text" name="slug" value="<?= htmlspecialchars($editPage['slug'] ?? '') ?>"></div>
                        </div>

                        <div class="split">
                            <div class="field">
                                <label>Rodzic strony</label>
                                <select name="parent_id">
                                    <option value="">Brak rodzica</option>
                                    <?php foreach ($rootPages as $rootPage): ?>
                                        <?php if ($editPage && (int) $editPage['id'] === (int) $rootPage['id']) { continue; } ?>
                                        <option value="<?= (int) $rootPage['id'] ?>" <?= (($editPage['parent_id'] ?? null) == $rootPage['id']) ? 'selected' : '' ?>><?= htmlspecialchars($rootPage['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label>Kolejnosc</label><input type="number" name="sort_order" value="<?= htmlspecialchars((string) ($editPage['sort_order'] ?? '0')) ?>"></div>
                        </div>

                        <div class="field"><label>Lead / zajawka</label><textarea name="excerpt"><?= htmlspecialchars($editPage['excerpt'] ?? '') ?></textarea></div>
                        <div class="field"><label>Tresc dodatkowa (fallback)</label><textarea name="content"><?= htmlspecialchars($editPage['content'] ?? '') ?></textarea></div>

                        <div class="split">
                            <div class="field"><label>Status</label><select name="status"><option value="draft" <?= (($editPage['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Szkic</option><option value="published" <?= (($editPage['status'] ?? '') === 'published') ? 'selected' : '' ?>>Opublikowana</option></select></div>
                            <div class="field inline"><label><input type="checkbox" name="is_homepage" value="1" <?= !empty($editPage['is_homepage']) ? 'checked' : '' ?>> Ustaw jako strone glowna</label></div>
                        </div>

                        <div class="field">
                            <label>Builder wygladu strony</label>
                            <div class="tiny">Dodawaj bloki i przeciagnij elementy aby zmienic kolejnosc.</div>
                        </div>
                        <div class="builder-toolbar">
                            <button class="btn ghost" type="button" data-add-block="hero">+ Hero</button>
                            <button class="btn ghost" type="button" data-add-block="text">+ Text</button>
                            <button class="btn ghost" type="button" data-add-block="image">+ Image</button>
                        </div>
                        <div id="builderEmpty" class="builder-empty">Builder jest pusty. Dodaj pierwszy blok.</div>
                        <div id="builderList" class="builder-list"></div>

                        <div class="actions" style="margin-top:18px"><button class="btn" type="submit">Zapisz strone</button><a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>">Wyczysc formularz</a></div>
                    </form>
                </section>
            </div>

            <div class="stack">
                <section class="panel">
                    <h2>Lista stron i podstron</h2>
                    <table>
                        <thead><tr><th>Tytul</th><th>Status</th><th>Akcje</th></tr></thead>
                        <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <strong><?php if (!empty($page['parent_id'])): ?><span class="child-mark">↳</span><?php endif; ?><?= htmlspecialchars($page['title']) ?></strong><br>
                                    <div class="page-path"><?= htmlspecialchars(cms_url('?page=' . urlencode((string) $page['slug']))) ?></div>
                                    <?php if (!empty($page['parent_id']) && isset($parentMap[(int) $page['parent_id']])): ?><small>Rodzic: <?= htmlspecialchars($parentMap[(int) $page['parent_id']]) ?></small><br><?php endif; ?>
                                    <?php if ((int) $page['is_homepage'] === 1): ?> <span class="badge ok">Home</span><?php endif; ?>
                                    <span class="badge">Sort: <?= (int) $page['sort_order'] ?></span>
                                </td>
                                <td><span class="badge <?= $page['status'] === 'published' ? 'ok' : 'off' ?>"><?= htmlspecialchars($page['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/pages.php?edit=' . (int) $page['id'])) ?>">Edytuj</a>
                                        <a class="btn secondary" target="_blank" href="<?= htmlspecialchars(cms_url('?page=' . urlencode((string) $page['slug']))) ?>">Podglad</a>
                                        <form method="post" onsubmit="return confirm('Usunac te strone?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_page">
                                            <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
                                            <button class="btn danger" type="submit">Usun</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </main>
</div>
<script>window.CMS_BUILDER_BLOCKS = <?= json_encode($builderBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= htmlspecialchars(cms_url('admin/assets/dashboard.js')) ?>"></script>
</body>
</html>
