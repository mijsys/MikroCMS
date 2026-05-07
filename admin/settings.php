<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

cms_require_login();
$user = cms_current_user();
$db = cms_db();

$loadUserById = static function (int $userId) use ($db): ?array {
    $stmt = $db->prepare('SELECT id, username, email, role, admin_theme, twofa_enabled, twofa_totp_secret, twofa_mijauth_key, twofa_mijauth_token, twofa_recovery_codes FROM cms_users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        cms_flash('error', cms_t('admin.flash.csrf_invalid', 'Nieprawidlowy token bezpieczenstwa.'));
        cms_redirect(cms_url('admin/settings.php'));
    }

    try {
        $action = (string) ($_POST['action'] ?? 'save_settings');
        if ($action === 'twofa_generate_setup') {
            if (!$user || empty($user['id'])) {
                throw new RuntimeException(cms_t('admin.settings.error.user_load', 'Nie mozna zaladowac uzytkownika.'));
            }
            $setup = cms_generate_user_2fa_bootstrap($user);
            cms_session_start();
            $_SESSION['cms_twofa_setup'] = $setup;
            $_SESSION['cms_twofa_autodownload'] = ['mijauth' => true, 'recovery' => true];
            cms_flash('success', cms_t('admin.settings.twofa.generated', 'Wygenerowano nowa konfiguracje 2FA. Zeskanuj kod TOTP i zapisz plik .mijauth.'));
            cms_redirect(cms_url('admin/settings.php'));
        }

        if ($action === 'twofa_regenerate_file') {
            if (!$user || empty($user['id'])) {
                throw new RuntimeException(cms_t('admin.settings.error.user_load', 'Nie mozna zaladowac uzytkownika.'));
            }
            $fresh = $loadUserById((int) $user['id']);
            if (!$fresh) {
                throw new RuntimeException(cms_t('admin.settings.error.twofa_load', 'Nie mozna zaladowac danych 2FA.'));
            }
            $result = cms_regenerate_user_mijauth_file($fresh);
            cms_session_start();
            $_SESSION['cms_twofa_setup'] = array_merge($_SESSION['cms_twofa_setup'] ?? [], $result);
            $_SESSION['cms_twofa_autodownload'] = ['mijauth' => true, 'recovery' => false];
            cms_flash('success', cms_t('admin.settings.twofa.regenerated', 'Wygenerowano nowy plik .mijauth. Aby aktywowac, potwierdz kodem TOTP.'));
            cms_redirect(cms_url('admin/settings.php'));
        }

        if ($action === 'twofa_enable') {
            if (!$user || empty($user['id'])) {
                throw new RuntimeException(cms_t('admin.settings.error.user_load', 'Nie mozna zaladowac uzytkownika.'));
            }
            $fresh = $loadUserById((int) $user['id']);
            if (!$fresh) {
                throw new RuntimeException(cms_t('admin.settings.error.twofa_load', 'Nie mozna zaladowac danych 2FA.'));
            }
            $totpCode = trim((string) ($_POST['twofa_totp_code'] ?? ''));
            $mijauthFile = trim((string) ($_POST['twofa_mijauth_file'] ?? ''));
            if ($totpCode === '' || $mijauthFile === '') {
                throw new RuntimeException(cms_t('admin.settings.twofa.require_code_file', 'Podaj kod TOTP oraz zawartosc pliku .mijauth.'));
            }
            if (!cms_verify_user_2fa_challenge($fresh, $totpCode, $mijauthFile)) {
                throw new RuntimeException(cms_t('admin.settings.twofa.verify_failed', 'Weryfikacja 2FA nie powiodla sie. Sprawdz kod i plik .mijauth.'));
            }

            cms_update_user_2fa_setup(
                (int) $fresh['id'],
                (string) ($fresh['twofa_totp_secret'] ?? ''),
                (string) ($fresh['twofa_mijauth_key'] ?? ''),
                (string) ($fresh['twofa_mijauth_token'] ?? ''),
                true
            );
            cms_session_start();
            unset($_SESSION['cms_twofa_setup']);
            cms_flash('success', cms_t('admin.settings.twofa.activated', '2FA zostalo aktywowane. Od kolejnego logowania wymagany jest TOTP i plik .mijauth.'));
            cms_redirect(cms_url('admin/settings.php'));
        }

        if ($action === 'twofa_disable') {
            if (!$user || empty($user['id'])) {
                throw new RuntimeException(cms_t('admin.settings.error.user_load', 'Nie mozna zaladowac uzytkownika.'));
            }
            cms_disable_user_2fa((int) $user['id']);
            cms_session_start();
            unset($_SESSION['cms_twofa_setup']);
            cms_flash('success', cms_t('admin.settings.twofa.disabled', '2FA zostalo wylaczone.'));
            cms_redirect(cms_url('admin/settings.php'));
        }

        if ($action === 'save_translations') {
            $translationLang = cms_normalize_lang_code((string) ($_POST['translation_lang'] ?? 'en'), 'en');
            $translationsJson = (string) ($_POST['translations_json'] ?? '{}');
            $decoded = json_decode($translationsJson, true);
            if (!is_array($decoded)) {
                throw new RuntimeException(cms_t('admin.settings.error.translations_json', 'JSON tlumaczen jest niepoprawny.'));
            }
            foreach ($decoded as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                cms_set_translation($translationLang, $key, is_scalar($value) ? (string) $value : '');
            }
            cms_flash('success', cms_t('admin.settings.translations.saved', 'Slownik tlumaczen zostal zapisany.'));
            cms_redirect(cms_url('admin/settings.php?translation_lang=' . urlencode($translationLang)));
        }

        cms_set_setting('site_name', trim($_POST['site_name'] ?? 'My CMS'));
        cms_set_setting('site_tagline', trim($_POST['site_tagline'] ?? ''));
        cms_set_setting('site_mode', ($_POST['site_mode'] ?? 'multipage') === 'onepage' ? 'onepage' : 'multipage');
        cms_set_setting('theme_variant', ($_POST['theme_variant'] ?? 'multipage') === 'onepage' ? 'onepage' : 'multipage');
        $defaultLanguage = cms_normalize_lang_code((string) ($_POST['site_default_language'] ?? 'pl'), 'pl');
        $enabledRaw = trim((string) ($_POST['site_enabled_languages'] ?? 'pl,en'));
        $enabledParts = $enabledRaw === '' ? [] : preg_split('/\s*,\s*/', $enabledRaw);
        if (!is_array($enabledParts)) {
            $enabledParts = [];
        }
        $enabled = [];
        foreach ($enabledParts as $part) {
            $normalized = cms_normalize_lang_code((string) $part, '');
            if ($normalized !== '' && !in_array($normalized, $enabled, true)) {
                $enabled[] = $normalized;
            }
        }
        if (!in_array($defaultLanguage, $enabled, true)) {
            array_unshift($enabled, $defaultLanguage);
        }
        $adminLanguage = cms_normalize_lang_code((string) ($_POST['admin_language'] ?? $defaultLanguage), $defaultLanguage);
        if (!in_array($adminLanguage, $enabled, true)) {
            $adminLanguage = $defaultLanguage;
        }
        cms_set_setting('site_default_language', $defaultLanguage);
        cms_set_setting('site_enabled_languages', implode(',', $enabled));
        cms_set_setting('admin_language', $adminLanguage);
        $adminTheme = strtolower(trim((string) ($_POST['admin_theme'] ?? 'dark')));
        if (!in_array($adminTheme, ['dark', 'light', 'oldschool', 'sunset'], true)) {
            $adminTheme = 'dark';
        }
        if ($user && !empty($user['id'])) {
            cms_set_user_admin_theme((int) $user['id'], $adminTheme);
        }
        cms_set_setting('cms_update_manifest_url', trim($_POST['cms_update_manifest_url'] ?? ''));
        cms_set_setting('store_db_manifest_url', trim($_POST['store_db_manifest_url'] ?? ''));
        cms_set_setting('plugin_store_manifest_url', trim($_POST['plugin_store_manifest_url'] ?? ''));
        cms_set_setting('plugin_store_catalog_key', trim($_POST['plugin_store_catalog_key'] ?? 'plugins'));
        cms_set_setting('plugin_store_directory', trim($_POST['plugin_store_directory'] ?? 'plugins'));
        cms_flash('success', cms_t('admin.settings.saved', 'Ustawienia CMS zostaly zapisane.'));
    } catch (Throwable $e) {
        cms_flash('error', $e->getMessage());
    }

    cms_redirect(cms_url('admin/settings.php'));
}

$flash = cms_pull_flash();
$pageCount = (int) $db->query('SELECT COUNT(*) FROM cms_pages')->fetchColumn();
$pluginCount = (int) $db->query('SELECT COUNT(*) FROM cms_plugins')->fetchColumn();
$translationLang = isset($_GET['translation_lang'])
    ? cms_normalize_lang_code((string) $_GET['translation_lang'], 'en')
    : 'en';
$translationsJson = json_encode(cms_translations_for_lang($translationLang), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$userTwoFa = $user && !empty($user['id']) ? $loadUserById((int) $user['id']) : null;
cms_session_start();
$twoFaSetup = $_SESSION['cms_twofa_setup'] ?? null;
if (!is_array($twoFaSetup)) {
    $twoFaSetup = [];
}
$twoFaAutoDownload = $_SESSION['cms_twofa_autodownload'] ?? ['mijauth' => false, 'recovery' => false];
if (!is_array($twoFaAutoDownload)) {
    $twoFaAutoDownload = ['mijauth' => false, 'recovery' => false];
}
unset($_SESSION['cms_twofa_autodownload']);
$twoFaEnabled = (bool) ((int) ($userTwoFa['twofa_enabled'] ?? 0));
$twoFaHasSecrets = trim((string) ($userTwoFa['twofa_totp_secret'] ?? '')) !== '' && trim((string) ($userTwoFa['twofa_mijauth_key'] ?? '')) !== '';
$twoFaProvisioningUri = $twoFaHasSecrets
    ? MijAuth::getTotpProvisioningUri((string) ($userTwoFa['username'] ?? 'admin'), (string) $userTwoFa['twofa_totp_secret'], 'MikroCMS')
    : '';
$twoFaFileContent = (string) ($twoFaSetup['mijauth_file_content'] ?? '');
$twoFaRecoveryFileContent = (string) ($twoFaSetup['recovery_codes_file_content'] ?? '');
$twoFaSecretPreview = (string) ($twoFaSetup['totp_secret'] ?? ($userTwoFa['twofa_totp_secret'] ?? ''));
$twoFaMijauthFileName = 'user-' . (int) ($userTwoFa['id'] ?? 0) . '.mijauth';
$twoFaRecoveryFileName = 'user-' . (int) ($userTwoFa['id'] ?? 0) . '-recovery-codes.txt';
$twoFaMijauthDownloadHref = $twoFaFileContent !== '' ? 'data:application/octet-stream;base64,' . base64_encode($twoFaFileContent) : '';
$twoFaRecoveryDownloadHref = $twoFaRecoveryFileContent !== '' ? 'data:text/plain;charset=utf-8;base64,' . base64_encode($twoFaRecoveryFileContent) : '';
$twoFaQrUrl = $twoFaProvisioningUri !== ''
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($twoFaProvisioningUri)
    : '';
$adminTheme = cms_admin_theme($user);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(cms_admin_language()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(cms_t('admin.settings.title', 'Ustawienia CMS')) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(cms_url('admin/assets/dashboard.css')) ?>">
    <style>
        .settings-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .settings-tab-btn {
            border: 1px solid #334155;
            background: #0f172a;
            color: #cbd5e1;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .settings-tab-btn.active {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .settings-pane-hidden {
            display: none !important;
        }
        .settings-grid {
            display: block;
        }
    </style>
</head>
<body class="admin-theme-<?= htmlspecialchars($adminTheme) ?>">
<div class="layout">
    <aside class="sidebar">
        <div class="brand">CMS</div>
        <div class="muted"><?= htmlspecialchars(cms_t('admin.nav.panel', 'Panel zarzadzania')) ?></div>
        <nav class="nav">
            <a href="<?= htmlspecialchars(cms_url('admin/dashboard.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.dashboard', 'Dashboard')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/pages.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.pages', 'Strony')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/plugins.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.plugins', 'Pluginy')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/appearance.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.appearance', 'Wyglad')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/settings.php')) ?>" class="active"><?= htmlspecialchars(cms_t('admin.nav.settings', 'Ustawienia')) ?></a>
            <a href="<?= htmlspecialchars(cms_url()) ?>" target="_blank"><?= htmlspecialchars(cms_t('admin.nav.preview', 'Podglad strony')) ?></a>
            <a href="<?= htmlspecialchars(cms_url('admin/logout.php')) ?>"><?= htmlspecialchars(cms_t('admin.nav.logout', 'Wyloguj')) ?></a>
        </nav>
        <div style="margin-top:24px" class="muted"><?= htmlspecialchars(cms_t('admin.nav.logged_as', 'Zalogowany:')) ?> <?= htmlspecialchars($user['username'] ?? 'admin') ?></div>
    </aside>
    <main class="main">
        <div class="topbar"><div style="display:flex;align-items:flex-start;gap:12px"><button id="sidebarToggleBtn" class="btn ghost" title="Ukryj panel boczny" style="padding:8px 13px;font-size:18px;line-height:1;flex-shrink:0;margin-top:3px">&#8249;</button><div><h1 style="margin:0 0 6px"><?= htmlspecialchars(cms_t('admin.settings.heading', 'Ustawienia CMS')) ?></h1><div class="muted"><?= htmlspecialchars(cms_t('admin.settings.subheading', 'Konfiguracja ogolna i warstwa danych.')) ?></div></div></div></div>

        <?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

        <div class="settings-tabs" id="settingsTabs" role="tablist" aria-label="Sekcje ustawien">
            <button type="button" class="settings-tab-btn active" data-settings-tab="general" role="tab" aria-selected="true">Ogolne</button>
            <button type="button" class="settings-tab-btn" data-settings-tab="data" role="tab" aria-selected="false">Warstwa danych</button>
            <button type="button" class="settings-tab-btn" data-settings-tab="security" role="tab" aria-selected="false">Bezpieczenstwo / 2FA</button>
            <button type="button" class="settings-tab-btn" data-settings-tab="translations" role="tab" aria-selected="false">Tlumaczenia</button>
        </div>

        <div class="grid settings-grid">
            <div class="stack">
                <section class="panel" data-settings-pane="general" role="tabpanel">
                    <h2><?= htmlspecialchars(cms_t('admin.settings.general', 'Ustawienia ogolne')) ?></h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.site_name', 'Nazwa strony')) ?></label><input type="text" name="site_name" value="<?= htmlspecialchars(cms_get_setting('site_name', 'My CMS')) ?>"></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.tagline', 'Tagline')) ?></label><input type="text" name="site_tagline" value="<?= htmlspecialchars(cms_get_setting('site_tagline', '')) ?>"></div>
                        <div class="split">
                            <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.default_lang', 'Domyslny jezyk')) ?></label><input type="text" name="site_default_language" placeholder="pl" value="<?= htmlspecialchars(cms_get_setting('site_default_language', 'pl')) ?>"></div>
                            <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.enabled_langs', 'Aktywne jezyki (CSV)')) ?></label><input type="text" name="site_enabled_languages" placeholder="pl,en,de" value="<?= htmlspecialchars(cms_get_setting('site_enabled_languages', 'pl,en')) ?>"></div>
                        </div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.admin_lang', 'Jezyk panelu admin')) ?></label><input type="text" name="admin_language" placeholder="pl" value="<?= htmlspecialchars(cms_get_setting('admin_language', cms_default_language())) ?>"></div>
                        <div class="field"><label>Motyw panelu admin</label><select name="admin_theme" id="adminThemeSelect"><option value="dark" <?= $adminTheme === 'dark' ? 'selected' : '' ?>>Nowoczesny ciemny</option><option value="light" <?= $adminTheme === 'light' ? 'selected' : '' ?>>Nowoczesny jasny</option><option value="oldschool" <?= $adminTheme === 'oldschool' ? 'selected' : '' ?>>Oldschool</option><option value="sunset" <?= $adminTheme === 'sunset' ? 'selected' : '' ?>>Neon Sunset</option></select></div>
                        <div class="field" style="margin-top:-2px">
                            <label>Podglad motywu panelu</label>
                            <div class="admin-theme-picker-preview" id="adminThemePreviewCards">
                                <button class="admin-theme-card" type="button" data-theme-preview="dark"><span class="theme-dot"></span><strong>Ciemny</strong></button>
                                <button class="admin-theme-card" type="button" data-theme-preview="light"><span class="theme-dot"></span><strong>Jasny</strong></button>
                                <button class="admin-theme-card" type="button" data-theme-preview="oldschool"><span class="theme-dot"></span><strong>Oldschool</strong></button>
                                <button class="admin-theme-card" type="button" data-theme-preview="sunset"><span class="theme-dot"></span><strong>Neon Sunset</strong></button>
                            </div>
                        </div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.site_mode', 'Tryb strony')) ?></label><select name="site_mode"><option value="multipage" <?= cms_site_mode() === 'multipage' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.settings.site_mode.multipage', 'Wiele stron')) ?></option><option value="onepage" <?= cms_site_mode() === 'onepage' ? 'selected' : '' ?>>Onepage</option></select></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.theme_variant', 'Wariant motywu')) ?></label><select name="theme_variant"><option value="multipage" <?= cms_theme_variant() === 'multipage' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.settings.theme_variant.multipage', 'Motyw wielostronicowy')) ?></option><option value="onepage" <?= cms_theme_variant() === 'onepage' ? 'selected' : '' ?>><?= htmlspecialchars(cms_t('admin.settings.theme_variant.onepage', 'Motyw onepage')) ?></option></select></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.manifest.cms', 'Manifest aktualizacji CMS (GitHub Raw URL)')) ?></label><input type="url" name="cms_update_manifest_url" placeholder="https://raw.githubusercontent.com/.../cms-update.json" value="<?= htmlspecialchars(cms_get_setting('cms_update_manifest_url', '')) ?>"></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.manifest.store', 'Manifest bazy sklepu (GitHub Raw URL)')) ?></label><input type="url" name="store_db_manifest_url" placeholder="https://raw.githubusercontent.com/.../store-db.json" value="<?= htmlspecialchars(cms_get_setting('store_db_manifest_url', '')) ?>"></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.manifest.plugins', 'Manifest pluginow (GitHub Raw URL)')) ?></label><input type="url" name="plugin_store_manifest_url" placeholder="https://raw.githubusercontent.com/.../plugins.json" value="<?= htmlspecialchars(cms_get_setting('plugin_store_manifest_url', '')) ?>"></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.catalog_key', 'Klucz katalogu pluginow w JSON')) ?></label><input type="text" name="plugin_store_catalog_key" placeholder="plugins" value="<?= htmlspecialchars(cms_get_setting('plugin_store_catalog_key', 'plugins')) ?>"></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.plugins_dir', 'Katalog pluginow lokalnie')) ?></label><input type="text" name="plugin_store_directory" placeholder="plugins" value="<?= htmlspecialchars(cms_get_setting('plugin_store_directory', 'plugins')) ?>"></div>
                        <button class="btn" type="submit"><?= htmlspecialchars(cms_t('admin.settings.btn.save', 'Zapisz ustawienia')) ?></button>
                    </form>
                </section>
            </div>
            <div class="stack">
                <section class="panel" data-settings-pane="data" role="tabpanel">
                    <h2><?= htmlspecialchars(cms_t('admin.settings.data_layer', 'Warstwa danych')) ?></h2>
                    <div class="db-meta">
                        <div><strong>Driver:</strong> <?= htmlspecialchars(cms_db_driver()) ?></div>
                        <div><strong>Tryb strony:</strong> <?= htmlspecialchars(cms_site_mode()) ?></div>
                        <div><strong>Wariant motywu:</strong> <?= htmlspecialchars(cms_theme_variant()) ?></div>
                        <div><strong>Config:</strong> <?= htmlspecialchars(cms_config_path()) ?></div>
                        <div><strong>Liczba stron:</strong> <?= $pageCount ?></div>
                        <div><strong>Liczba pluginow:</strong> <?= $pluginCount ?></div>
                    </div>
                </section>

                <section class="panel" data-settings-pane="security" role="tabpanel">
                    <h2><?= htmlspecialchars(cms_t('admin.settings.security_2fa', 'Bezpieczenstwo i 2FA')) ?></h2>
                    <p class="muted"><?= htmlspecialchars(cms_t('admin.settings.security_2fa.desc', 'Wymaga kodu TOTP oraz pliku .mijauth przy logowaniu.')) ?></p>
                    <div class="db-meta" style="margin-bottom:12px">
                        <div><strong>Status 2FA:</strong> <?= $twoFaEnabled ? 'Aktywne' : 'Nieaktywne' ?></div>
                        <div><strong>Sekrety:</strong> <?= $twoFaHasSecrets ? 'Skonfigurowane' : 'Brak' ?></div>
                    </div>

                    <?php if (!$twoFaHasSecrets): ?>
                        <form method="post" style="margin-bottom:12px">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                            <input type="hidden" name="action" value="twofa_generate_setup">
                            <button class="btn" type="submit">Wygeneruj konfiguracje 2FA</button>
                        </form>
                    <?php else: ?>
                        <div class="field"><label>Sekret TOTP</label><input type="text" readonly value="<?= htmlspecialchars($twoFaSecretPreview) ?>"></div>
                        <div class="field"><label>URI TOTP (aplikacja Authenticator)</label><input type="text" readonly value="<?= htmlspecialchars($twoFaProvisioningUri) ?>"></div>
                        <?php if ($twoFaQrUrl !== ''): ?>
                            <div class="field">
                                <label>Kod QR dla aplikacji TOTP</label>
                                <div style="padding:10px;border:1px solid #475569;border-radius:12px;display:inline-block;background:#fff">
                                    <img src="<?= htmlspecialchars($twoFaQrUrl) ?>" alt="QR TOTP" width="220" height="220">
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="post" style="margin-bottom:12px">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                            <input type="hidden" name="action" value="twofa_regenerate_file">
                            <button class="btn" type="submit">Regeneruj plik .mijauth</button>
                        </form>

                        <?php if ($twoFaFileContent !== ''): ?>
                            <div class="field">
                                <label>Zawartosc pliku .mijauth (zapisz lokalnie)</label>
                                <textarea readonly style="min-height:140px"><?= htmlspecialchars($twoFaFileContent) ?></textarea>
                            </div>
                            <div class="actions" style="margin-top:8px">
                                <a class="btn secondary" href="<?= htmlspecialchars($twoFaMijauthDownloadHref) ?>" download="<?= htmlspecialchars($twoFaMijauthFileName) ?>">Pobierz plik .mijauth</a>
                            </div>
                            <p class="muted" style="margin-top:-4px">Plik zapisz jako <strong><?= htmlspecialchars($twoFaMijauthFileName) ?></strong>.</p>
                        <?php endif; ?>

                        <?php if ($twoFaRecoveryFileContent !== ''): ?>
                            <div class="field">
                                <label>Kody bezpieczeństwa (zapisz plik)</label>
                                <textarea readonly style="min-height:180px"><?= htmlspecialchars($twoFaRecoveryFileContent) ?></textarea>
                            </div>
                            <div class="actions" style="margin-top:8px">
                                <a class="btn secondary" href="<?= htmlspecialchars($twoFaRecoveryDownloadHref) ?>" download="<?= htmlspecialchars($twoFaRecoveryFileName) ?>">Pobierz kody bezpieczeństwa</a>
                            </div>
                        <?php endif; ?>

                        <?php if (!$twoFaEnabled): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                                <input type="hidden" name="action" value="twofa_enable">
                                <div class="field"><label>Kod TOTP (6 cyfr)</label><input type="text" name="twofa_totp_code" maxlength="6" required></div>
                                <div class="field"><label>Zawartosc pliku .mijauth</label><textarea name="twofa_mijauth_file" style="min-height:120px" required><?= htmlspecialchars($twoFaFileContent) ?></textarea></div>
                                <button class="btn" type="submit">Aktywuj 2FA</button>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                                <input type="hidden" name="action" value="twofa_disable">
                                <button class="btn" type="submit">Wylacz 2FA</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <section class="panel" data-settings-pane="translations" role="tabpanel">
                    <h2><?= htmlspecialchars(cms_t('admin.settings.translations.heading', 'Tlumaczenia UI')) ?></h2>
                    <p class="muted"><?= htmlspecialchars(cms_t('admin.settings.translations.desc', 'Edytuj slownik tlumaczen (key -> value) w formacie JSON dla wybranego jezyka.')) ?></p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_translations">
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.translations.lang', 'Jezyk slownika')) ?></label><input type="text" name="translation_lang" value="<?= htmlspecialchars($translationLang) ?>"></div>
                        <div class="field"><label><?= htmlspecialchars(cms_t('admin.settings.translations.json', 'JSON tlumaczen')) ?></label><textarea name="translations_json" style="min-height:280px"><?= htmlspecialchars(is_string($translationsJson) ? $translationsJson : '{}') ?></textarea></div>
                        <button class="btn" type="submit"><?= htmlspecialchars(cms_t('admin.settings.translations.save', 'Zapisz tlumaczenia')) ?></button>
                    </form>
                </section>
            </div>
        </div>
    </main>
</div>
<?php if ($twoFaFileContent !== '' || $twoFaRecoveryFileContent !== ''): ?>
<script>
(function(){
    var autoMijauth = <?= !empty($twoFaAutoDownload['mijauth']) ? 'true' : 'false' ?>;
    var autoRecovery = <?= !empty($twoFaAutoDownload['recovery']) ? 'true' : 'false' ?>;
    if (autoMijauth) {
        var a = document.createElement('a');
        a.href = <?= json_encode($twoFaMijauthDownloadHref, JSON_UNESCAPED_SLASHES) ?>;
        a.download = <?= json_encode($twoFaMijauthFileName, JSON_UNESCAPED_SLASHES) ?>;
        document.body.appendChild(a);
        a.click();
        a.remove();
    }
    if (autoRecovery) {
        var b = document.createElement('a');
        b.href = <?= json_encode($twoFaRecoveryDownloadHref, JSON_UNESCAPED_SLASHES) ?>;
        b.download = <?= json_encode($twoFaRecoveryFileName, JSON_UNESCAPED_SLASHES) ?>;
        document.body.appendChild(b);
        b.click();
        b.remove();
    }
}());
</script>
<?php endif; ?>
<script src="<?= htmlspecialchars(cms_url('admin/assets/dashboard.js?v=' . rawurlencode(CMS_CODE_VERSION))) ?>"></script>
<script>
(function(){
    var select = document.getElementById('adminThemeSelect');
    var cardsWrap = document.getElementById('adminThemePreviewCards');
    if (!select || !cardsWrap) { return; }
    var cards = cardsWrap.querySelectorAll('[data-theme-preview]');
    function syncState() {
        var val = String(select.value || 'dark');
        cards.forEach(function(card){
            var active = card.getAttribute('data-theme-preview') === val;
            card.classList.toggle('active', active);
        });
    }
    cards.forEach(function(card){
        card.addEventListener('click', function(){
            select.value = card.getAttribute('data-theme-preview') || 'dark';
            syncState();
        });
    });
    select.addEventListener('change', syncState);
    syncState();
}());

(function(){
    var tabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-settings-tab]'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('[data-settings-pane]'));
    var stacks = Array.prototype.slice.call(document.querySelectorAll('.settings-grid > .stack'));
    if (tabButtons.length === 0 || panes.length === 0) { return; }

    function activateTab(tabKey) {
        tabButtons.forEach(function(btn){
            var active = btn.getAttribute('data-settings-tab') === tabKey;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panes.forEach(function(pane){
            pane.classList.toggle('settings-pane-hidden', pane.getAttribute('data-settings-pane') !== tabKey);
        });
        stacks.forEach(function(stack){
            var visibleCount = stack.querySelectorAll('[data-settings-pane]:not(.settings-pane-hidden)').length;
            stack.style.display = visibleCount > 0 ? '' : 'none';
        });
        try { localStorage.setItem('cms_settings_tab', tabKey); } catch (e) {}
    }

    tabButtons.forEach(function(btn){
        btn.addEventListener('click', function(){
            activateTab(btn.getAttribute('data-settings-tab') || 'general');
        });
    });

    var initial = 'general';
    try {
        var stored = localStorage.getItem('cms_settings_tab');
        if (stored) { initial = stored; }
    } catch (e) {}
    if (!tabButtons.some(function(btn){ return btn.getAttribute('data-settings-tab') === initial; })) {
        initial = 'general';
    }
    activateTab(initial);
}());
</script>
</body>
</html>
