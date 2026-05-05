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
    $stmt = $db->prepare('SELECT id, username, email, role, twofa_enabled, twofa_totp_secret, twofa_mijauth_key, twofa_mijauth_token FROM cms_users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        cms_flash('error', 'Nieprawidlowy token bezpieczenstwa.');
        cms_redirect(cms_url('admin/settings.php'));
    }

    try {
        $action = (string) ($_POST['action'] ?? 'save_settings');
        if ($action === 'twofa_generate_setup') {
            if (!$user || empty($user['id'])) {
                throw new RuntimeException('Nie mozna zaladowac uzytkownika.');
            }
            $setup = cms_generate_user_2fa_bootstrap($user);
            cms_session_start();
            $_SESSION['cms_twofa_setup'] = $setup;
            cms_flash('success', 'Wygenerowano nowa konfiguracje 2FA. Zeskanuj kod TOTP i zapisz plik .mijauth.');
            cms_redirect(cms_url('admin/settings.php'));
        }

        if ($action === 'twofa_regenerate_file') {
            if (!$user || empty($user['id'])) {
                throw new RuntimeException('Nie mozna zaladowac uzytkownika.');
            }
            $fresh = $loadUserById((int) $user['id']);
            if (!$fresh) {
                throw new RuntimeException('Nie mozna zaladowac danych 2FA.');
            }
            $result = cms_regenerate_user_mijauth_file($fresh);
            cms_session_start();
            $_SESSION['cms_twofa_setup'] = array_merge($_SESSION['cms_twofa_setup'] ?? [], $result);
            cms_flash('success', 'Wygenerowano nowy plik .mijauth. Aby aktywowac, potwierdz kodem TOTP.');
            cms_redirect(cms_url('admin/settings.php'));
        }

        if ($action === 'twofa_enable') {
            if (!$user || empty($user['id'])) {
                throw new RuntimeException('Nie mozna zaladowac uzytkownika.');
            }
            $fresh = $loadUserById((int) $user['id']);
            if (!$fresh) {
                throw new RuntimeException('Nie mozna zaladowac danych 2FA.');
            }
            $totpCode = trim((string) ($_POST['twofa_totp_code'] ?? ''));
            $mijauthFile = trim((string) ($_POST['twofa_mijauth_file'] ?? ''));
            if ($totpCode === '' || $mijauthFile === '') {
                throw new RuntimeException('Podaj kod TOTP oraz zawartosc pliku .mijauth.');
            }
            if (!cms_verify_user_2fa_challenge($fresh, $totpCode, $mijauthFile)) {
                throw new RuntimeException('Weryfikacja 2FA nie powiodla sie. Sprawdz kod i plik .mijauth.');
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
            cms_flash('success', '2FA zostalo aktywowane. Od kolejnego logowania wymagany jest TOTP i plik .mijauth.');
            cms_redirect(cms_url('admin/settings.php'));
        }

        if ($action === 'twofa_disable') {
            if (!$user || empty($user['id'])) {
                throw new RuntimeException('Nie mozna zaladowac uzytkownika.');
            }
            cms_disable_user_2fa((int) $user['id']);
            cms_session_start();
            unset($_SESSION['cms_twofa_setup']);
            cms_flash('success', '2FA zostalo wylaczone.');
            cms_redirect(cms_url('admin/settings.php'));
        }

        if ($action === 'save_translations') {
            $translationLang = cms_normalize_lang_code((string) ($_POST['translation_lang'] ?? 'en'), 'en');
            $translationsJson = (string) ($_POST['translations_json'] ?? '{}');
            $decoded = json_decode($translationsJson, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('JSON tlumaczen jest niepoprawny.');
            }
            foreach ($decoded as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                cms_set_translation($translationLang, $key, is_scalar($value) ? (string) $value : '');
            }
            cms_flash('success', 'Slownik tlumaczen zostal zapisany.');
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
        cms_set_setting('site_default_language', $defaultLanguage);
        cms_set_setting('site_enabled_languages', implode(',', $enabled));
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
$twoFaEnabled = (bool) ((int) ($userTwoFa['twofa_enabled'] ?? 0));
$twoFaHasSecrets = trim((string) ($userTwoFa['twofa_totp_secret'] ?? '')) !== '' && trim((string) ($userTwoFa['twofa_mijauth_key'] ?? '')) !== '';
$twoFaProvisioningUri = $twoFaHasSecrets
    ? MijAuth::getTotpProvisioningUri((string) ($userTwoFa['username'] ?? 'admin'), (string) $userTwoFa['twofa_totp_secret'], 'MikroCMS')
    : '';
$twoFaFileContent = (string) ($twoFaSetup['mijauth_file_content'] ?? '');
$twoFaSecretPreview = (string) ($twoFaSetup['totp_secret'] ?? ($userTwoFa['twofa_totp_secret'] ?? ''));
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
                        <div class="split">
                            <div class="field"><label>Domyslny jezyk</label><input type="text" name="site_default_language" placeholder="pl" value="<?= htmlspecialchars(cms_get_setting('site_default_language', 'pl')) ?>"></div>
                            <div class="field"><label>Aktywne jezyki (CSV)</label><input type="text" name="site_enabled_languages" placeholder="pl,en,de" value="<?= htmlspecialchars(cms_get_setting('site_enabled_languages', 'pl,en')) ?>"></div>
                        </div>
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

                <section class="panel">
                    <h2>Bezpieczenstwo i 2FA</h2>
                    <p class="muted">Wymaga kodu TOTP oraz pliku .mijauth przy logowaniu.</p>
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
                            <p class="muted" style="margin-top:-4px">Plik zapisz jako <strong>user-<?= (int) ($userTwoFa['id'] ?? 0) ?>.mijauth</strong>.</p>
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

                <section class="panel">
                    <h2>Tlumaczenia UI</h2>
                    <p class="muted">Edytuj slownik tlumaczen (key -> value) w formacie JSON dla wybranego jezyka.</p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_translations">
                        <div class="field"><label>Jezyk slownika</label><input type="text" name="translation_lang" value="<?= htmlspecialchars($translationLang) ?>"></div>
                        <div class="field"><label>JSON tlumaczen</label><textarea name="translations_json" style="min-height:280px"><?= htmlspecialchars(is_string($translationsJson) ? $translationsJson : '{}') ?></textarea></div>
                        <button class="btn" type="submit">Zapisz tlumaczenia</button>
                    </form>
                </section>
            </div>
        </div>
    </main>
</div>
</body>
</html>
