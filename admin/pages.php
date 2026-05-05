<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

cms_require_login();
$user = cms_current_user();
$db = cms_db();
$defaultLang = cms_default_language();
$enabledLangs = cms_enabled_languages();
$editorLang = isset($_GET['lang']) ? cms_normalize_lang_code((string) $_GET['lang'], $defaultLang) : $defaultLang;
if (!in_array($editorLang, $enabledLangs, true)) {
    $editorLang = $defaultLang;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        cms_flash('error', cms_t('admin.flash.csrf_invalid', 'Nieprawidlowy token bezpieczenstwa.'));
        cms_redirect(cms_url('admin/pages.php'));
    }

    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'save_page':
                $id = isset($_POST['page_id']) && $_POST['page_id'] !== '' ? (int) $_POST['page_id'] : null;
                $savedId = cms_save_page($_POST, $id);
                $editLangPost = cms_normalize_lang_code((string) ($_POST['edit_lang'] ?? $defaultLang), $defaultLang);
                if ($editLangPost !== $defaultLang) {
                    cms_save_page_translation($savedId, $editLangPost, $_POST);
                }
                cms_flash('success', $id ? cms_t('admin.pages.flash.updated', 'Strona zostala zaktualizowana.') : cms_t('admin.pages.flash.created', 'Strona zostala dodana.'));
                break;
            case 'delete_page':
                cms_delete_page((int) ($_POST['page_id'] ?? 0));
                cms_flash('success', cms_t('admin.pages.flash.deleted', 'Strona zostala usunieta.'));
                break;
        }
    } catch (Throwable $e) {
        cms_flash('error', $e->getMessage());
    }

    cms_redirect(cms_url('admin/pages.php?lang=' . urlencode($editorLang)));
}

$flash = cms_pull_flash();
$pages = cms_all_pages(false);
$rootPages = cms_root_pages(false);
$editPage = null;
if (isset($_GET['edit'])) {
    $editPage = cms_page_by_id((int) $_GET['edit']);
}

if ($editPage && $editorLang !== $defaultLang) {
    $translation = cms_page_translation((int) ($editPage['id'] ?? 0), $editorLang);
    if (is_array($translation)) {
        foreach (['title', 'excerpt', 'content', 'builder_data'] as $field) {
            if (isset($translation[$field])) {
                $editPage[$field] = (string) $translation[$field];
            }
        }
    }
}

$builderBlocks = cms_normalize_builder_blocks($editPage['builder_data'] ?? '[]');
$parentMap = [];
foreach ($pages as $pageItem) {
    $parentMap[(int) $pageItem['id']] = $pageItem['title'];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(cms_admin_language()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(cms_t('admin.pages.title', 'Strony CMS')) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('admin/assets/dashboard.css')) ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">CMS</div>
        <div class="muted"><?= htmlspecialchars(cms_t('admin.nav.panel', 'Panel zarzadzania')) ?></div>
        <nav class="nav">
            <a href="<?= htmlspecialchars(cms_url('admin/dashboard.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.dashboard', 'Dashboard')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>" class="active"><?= htmlspecialchars(cms_t('admin.nav.pages', 'Strony')) ?></a>
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
                    <h1 style="margin:0 0 6px"><?= htmlspecialchars(cms_t('admin.pages.heading', 'Strony i podstrony')) ?></h1>
                    <div class="muted"><?= htmlspecialchars(cms_t('admin.pages.subheading', 'Tworzenie stron, podstron i builder drag and drop.')) ?></div>
                </div>
            </div>
            <form method="get" class="actions" style="align-items:center">
                <?php if ($editPage && !empty($editPage['id'])): ?><input type="hidden" name="edit" value="<?= (int) $editPage['id'] ?>"><?php endif; ?>
                <label class="muted" for="langSwitcher"><?= htmlspecialchars(cms_t('admin.pages.edit_lang', 'Jezyk edycji')) ?></label>
                <select id="langSwitcher" name="lang" onchange="this.form.submit()">
                    <?php foreach ($enabledLangs as $langCode): ?>
                        <option value="<?= htmlspecialchars($langCode) ?>" <?= $editorLang === $langCode ? 'selected' : '' ?>><?= strtoupper(htmlspecialchars($langCode)) ?><?= $langCode === $defaultLang ? ' (' . htmlspecialchars(cms_t('admin.pages.default', 'default')) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="stack">
                <section class="panel">
                    <h2><?= htmlspecialchars($editPage ? cms_t('admin.pages.form.edit', 'Edytuj strone / podstrone') : cms_t('admin.pages.form.add', 'Dodaj nowa strone / podstrone')) ?></h2>
                    <form method="post" id="pageEditorForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_page">
                        <input type="hidden" name="page_id" value="<?= htmlspecialchars((string) ($editPage['id'] ?? '')) ?>">
                        <input type="hidden" name="edit_lang" value="<?= htmlspecialchars($editorLang) ?>">
                        <input type="hidden" name="builder_data" id="builderDataInputV2" value="<?= htmlspecialchars(json_encode($builderBlocks, JSON_UNESCAPED_UNICODE)) ?>">

                        <div class="split">
                            <div class="field"><label><?= htmlspecialchars(cms_t('admin.pages.form.title', 'Tytul')) ?></label><input type="text" name="title" required value="<?= htmlspecialchars($editPage['title'] ?? '') ?>"></div>
                            <div class="field"><label><?= htmlspecialchars(cms_t('admin.pages.form.slug', 'Slug')) ?></label><input type="text" name="slug" value="<?= htmlspecialchars($editPage['slug'] ?? '') ?>"></div>
                        </div>

                        <div class="split">
                            <div class="field">
                                <label><?= htmlspecialchars(cms_t('admin.pages.form.parent', 'Rodzic strony')) ?></label>
                                <select name="parent_id">
                                    <option value=""><?= htmlspecialchars(cms_t('admin.pages.form.no_parent', 'Brak rodzica')) ?></option>
                                    <?php foreach ($rootPages as $rootPage): ?>
                                        <?php if ($editPage && (int) $editPage['id'] === (int) $rootPage['id']) { continue; } ?>
                                        <option value="<?= (int) $rootPage['id'] ?>" <?= (($editPage['parent_id'] ?? null) == $rootPage['id']) ? 'selected' : '' ?>><?= htmlspecialchars($rootPage['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label><?= htmlspecialchars(cms_t('admin.pages.form.sort', 'Kolejnosc')) ?></label><input type="number" name="sort_order" value="<?= htmlspecialchars((string) ($editPage['sort_order'] ?? '0')) ?>"></div>
                        </div>

                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.pages.form.excerpt', 'Lead / zajawka')) ?></label><textarea name="excerpt"><?= htmlspecialchars($editPage['excerpt'] ?? '') ?></textarea></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.pages.form.content', 'Tresc dodatkowa (fallback)')) ?></label><textarea name="content"><?= htmlspecialchars($editPage['content'] ?? '') ?></textarea></div>

                        <div class="split">
                            <div class="field"><label><?= htmlspecialchars(cms_t('admin.pages.form.status', 'Status')) ?></label><select name="status"><option value="draft" <?= (($editPage['status'] ?? '') === 'draft') ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.pages.form.status.draft', 'Szkic')) ?></option><option value="published" <?= (($editPage['status'] ?? '') === 'published') ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.pages.form.status.published', 'Opublikowana')) ?></option></select></div>
                            <div class="field inline"><label><input type="checkbox" name="is_homepage" value="1" <?= !empty($editPage['is_homepage']) ? 'checked' : '' ?>> <?= htmlspecialchars(cms_t('admin.pages.form.home', 'Ustaw jako strone glowna')) ?></label></div>
                        </div>

                        <div class="field">
                            <label><?= htmlspecialchars(cms_t('admin.pages.form.builder', 'Builder wygladu strony')) ?></label>
                            <div class="tiny"><?= htmlspecialchars(cms_t('admin.pages.form.builder_help', 'Dodawaj bloki, przeciagnij aby zmienic kolejnosc, eksportuj/importuj JSON i dziel gotowe uklady.')) ?></div>
                            <div class="tiny" style="margin-top:6px"><?= htmlspecialchars(cms_t('admin.pages.form.builder_help_easy', 'Tryb wizualny: otworz okno kreatora i ukladaj sekcje metoda drag and drop bez znajomosci kodu.')) ?></div>
                        </div>
                        <button class="btn" type="button" id="openBuilderWindowBtn" style="margin-bottom:12px"><?= htmlspecialchars(cms_t('admin.pages.form.open_builder_window', 'Otworz okno kreatora strony')) ?></button>

                        <div id="builderWindowBackdrop" class="builder-window-backdrop"></div>
                        <div id="builderWindowShell" class="builder-window-shell" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(cms_t('admin.pages.form.builder', 'Builder wygladu strony')) ?>">
                            <div class="builder-window-head">
                                <div>
                                    <strong><?= htmlspecialchars(cms_t('admin.pages.form.builder_window_title', 'Kreator strony - drag and drop')) ?></strong>
                                    <div class="tiny"><?= htmlspecialchars(cms_t('admin.pages.form.builder_window_subtitle', 'Dodawaj, przesuwaj i dopasowuj sekcje w jednym oknie.')) ?></div>
                                </div>
                                <button class="btn danger" type="button" id="closeBuilderWindowBtn"><?= htmlspecialchars(cms_t('admin.pages.form.close_builder_window', 'Zamknij okno')) ?></button>
                            </div>
                            <div class="builder-toolbar">
                                <button class="btn ghost" type="button" data-builder2-add="hero">+ Hero</button>
                                <button class="btn ghost" type="button" data-builder2-add="text">+ Text</button>
                                <button class="btn ghost" type="button" data-builder2-add="image">+ Image</button>
                                <button class="btn ghost" type="button" data-builder2-add="container">+ Container</button>
                                <button class="btn ghost" type="button" data-builder2-add="gallery">+ Gallery</button>
                                <button class="btn ghost" type="button" data-builder2-add="plugin_slot">+ Plugin Slot</button>
                                <button class="btn secondary" type="button" id="builderSectionsFocusBtn"><?= htmlspecialchars(cms_t('admin.pages.form.show_sections', 'Pokaz sekcje strony')) ?></button>
                                <button class="btn secondary" type="button" id="builderExportBtn"><?= htmlspecialchars(cms_t('admin.pages.form.export_json', 'Eksport JSON')) ?></button>
                                <label class="btn secondary" for="builderImportFile" style="display:inline-flex;align-items:center;cursor:pointer"><?= htmlspecialchars(cms_t('admin.pages.form.import_json', 'Import JSON')) ?></label>
                                <input id="builderImportFile" type="file" accept="application/json,.json" style="display:none">
                            </div>
                            <div id="builderEmptyV2" class="builder-empty"><?= htmlspecialchars(cms_t('admin.pages.form.builder_empty', 'Builder jest pusty. Dodaj pierwszy blok.')) ?></div>
                            <div id="builderListV2" class="builder-list"></div>
                        </div>

                        <div class="actions" style="margin-top:18px;align-items:center;flex-wrap:wrap">
                            <button class="btn" type="submit"><?= htmlspecialchars(cms_t('admin.pages.form.save', 'Zapisz strone')) ?></button>
                            <?php if ($editPage && !empty($editPage['slug'])): ?>
                            <button id="pagePreviewBtn" class="btn secondary" type="button"><?= htmlspecialchars(cms_t('admin.pages.form.preview', 'Podglad')) ?> &#9673;</button>
                            <?php endif; ?>
                            <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>"><?= htmlspecialchars(cms_t('admin.pages.form.clear', 'Wyczysc formularz')) ?></a>
                            <span id="autosaveBadge" class="autosave-badge"></span>
                        </div>
                    </form>
                </section>
            </div>

            <div class="stack">
                <section class="panel">
                    <h2><?= htmlspecialchars(cms_t('admin.pages.list.heading', 'Lista stron i podstron')) ?></h2>
                    <table>
                        <thead><tr><th><?= htmlspecialchars(cms_t('admin.pages.list.title', 'Tytul')) ?></th><th><?= htmlspecialchars(cms_t('admin.pages.list.status', 'Status')) ?></th><th><?= htmlspecialchars(cms_t('admin.pages.list.actions', 'Akcje')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <strong><?php if (!empty($page['parent_id'])): ?><span class="child-mark">↳</span><?php endif; ?><?= htmlspecialchars($page['title']) ?></strong><br>
                                    <div class="page-path"><?= htmlspecialchars(cms_url_with_lang(['page' => (string) $page['slug'], 'lang' => $editorLang])) ?></div>
                                    <?php if (!empty($page['parent_id']) && isset($parentMap[(int) $page['parent_id']])): ?><small><?= htmlspecialchars(cms_t('admin.pages.list.parent', 'Rodzic:')) ?> <?= htmlspecialchars($parentMap[(int) $page['parent_id']]) ?></small><br><?php endif; ?>
                                    <?php if ((int) $page['is_homepage'] === 1): ?> <span class="badge ok"><?= htmlspecialchars(cms_t('admin.pages.list.home', 'Home')) ?></span><?php endif; ?>
                                    <span class="badge"><?= htmlspecialchars(cms_t('admin.pages.list.sort', 'Sort:')) ?> <?= (int) $page['sort_order'] ?></span>
                                </td>
                                <td><span class="badge <?= $page['status'] === 'published' ? 'ok' : 'off' ?>"><?= htmlspecialchars($page['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn secondary" href="<?= htmlspecialchars(cms_url('admin/pages.php?edit=' . (int) $page['id'])) ?>"><?= htmlspecialchars(cms_t('admin.pages.list.edit', 'Edytuj')) ?></a>
                                        <a class="btn secondary" target="_blank" href="<?= htmlspecialchars(cms_url_with_lang(['page' => (string) $page['slug'], 'lang' => $editorLang])) ?>"><?= htmlspecialchars(cms_t('admin.pages.list.preview', 'Podglad')) ?></a>
                                        <form method="post" onsubmit="return confirm('<?= htmlspecialchars(cms_t('admin.pages.confirm_delete', 'Usunac te strone?')) ?>');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_page">
                                            <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
                                            <button class="btn danger" type="submit"><?= htmlspecialchars(cms_t('admin.pages.list.delete', 'Usun')) ?></button>
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
<?php
$previewUrl = ($editPage && !empty($editPage['slug']))
    ? cms_url_with_lang(['page' => (string) $editPage['slug'], 'lang' => $editorLang])
    : '';
$draftKey = 'cms_draft_' . ($editPage ? (int) ($editPage['id'] ?? 0) : 'new') . '_' . $editorLang;
?>
<div id="pagePreviewOverlay" class="page-preview-overlay">
    <div class="page-preview-bar">
        <strong><?= htmlspecialchars(cms_t('admin.pages.preview.title', 'Podglad strony')) ?></strong>
        <span class="muted"><?= htmlspecialchars(cms_t('admin.pages.preview.note', 'Wyswietla ostatnio zapisana wersje strony')) ?></span>
        <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
            <?php if ($previewUrl !== ''): ?>
            <a class="btn secondary" href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" rel="noopener" style="font-size:13px;padding:8px 14px"><?= htmlspecialchars(cms_t('admin.pages.preview.new_tab', 'Nowa karta')) ?> &#8599;</a>
            <?php endif; ?>
            <button id="pagePreviewClose" class="btn danger" type="button" style="font-size:13px;padding:8px 14px"><?= htmlspecialchars(cms_t('admin.pages.preview.close', 'Zamknij')) ?> &times;</button>
        </div>
    </div>
    <iframe id="pagePreviewFrame" class="page-preview-frame" src="" title="<?= htmlspecialchars(cms_t('admin.pages.preview.iframe_title', 'Podglad strony')) ?>"></iframe>
</div>
<script>
window.CMS_BUILDER_BLOCKS = <?= json_encode($builderBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.CMS_DRAFT_KEY = <?= json_encode($draftKey) ?>;
window.CMS_PAGE_PREVIEW_URL = <?= json_encode($previewUrl) ?>;
</script>
<script src="<?= htmlspecialchars(cms_url('admin/assets/dashboard.js?v=' . rawurlencode(CMS_CODE_VERSION))) ?>"></script>
<script src="<?= htmlspecialchars(cms_url('admin/assets/page-builder.js?v=' . rawurlencode(CMS_CODE_VERSION))) ?>"></script>
</body>
</html>
