<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (cms_is_installed()) {
    cms_redirect(cms_url('admin/index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim($_POST['site_name'] ?? '');
    $tagline = trim($_POST['site_tagline'] ?? '');
    $siteMode = ($_POST['site_mode'] ?? 'multipage') === 'onepage' ? 'onepage' : 'multipage';
    $dbDriver = ($_POST['db_driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $config = [
        'driver' => $dbDriver,
        'sqlite_path' => CMS_SQLITE_DEFAULT_PATH,
        'mysql_host' => trim($_POST['mysql_host'] ?? '127.0.0.1'),
        'mysql_port' => trim($_POST['mysql_port'] ?? '3306'),
        'mysql_database' => trim($_POST['mysql_database'] ?? ''),
        'mysql_username' => trim($_POST['mysql_username'] ?? ''),
        'mysql_password' => (string) ($_POST['mysql_password'] ?? ''),
        'mysql_charset' => 'utf8mb4',
    ];

    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Nieprawidlowy token bezpieczenstwa.';
    } elseif ($siteName === '' || $username === '' || $email === '') {
        $error = 'Uzupelnij wymagane pola.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podaj poprawny adres e-mail.';
    } elseif (strlen($password) < 10) {
        $error = 'Haslo musi miec minimum 10 znakow.';
    } elseif ($password !== $password2) {
        $error = 'Hasla nie sa identyczne.';
    } elseif ($dbDriver === 'mysql' && ($config['mysql_host'] === '' || $config['mysql_database'] === '' || $config['mysql_username'] === '')) {
        $error = 'Dla MySQL uzupelnij host, baze i uzytkownika.';
    } else {
        $previousConfig = cms_load_config();
        try {
            cms_write_config($config);
            $db = cms_db(true);
            $stmt = $db->prepare('INSERT INTO cms_users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'admin']);

            cms_set_setting('site_name', $siteName);
            cms_set_setting('site_tagline', $tagline !== '' ? $tagline : 'Nowy system CMS');
            cms_set_setting('site_mode', $siteMode);
            cms_set_setting('theme_variant', $siteMode);
            cms_set_setting('cms_installed', '1');

            if ($siteMode === 'onepage') {
                $homeId = cms_save_page([
                    'title' => 'Start',
                    'slug' => 'start',
                    'excerpt' => 'Sekcja startowa strony typu onepage.',
                    'content' => '',
                    'builder_data' => json_encode([
                        ['type' => 'hero', 'title' => 'Witaj w nowym CMS', 'text' => 'Skonfiguruj sekcje metoda drag and drop i buduj landing page krok po kroku.', 'background_color' => '#0f172a', 'background_image' => '', 'background_attachment' => 'scroll', 'min_height' => '520', 'align' => 'center', 'button_text' => 'Przejdz do panelu', 'button_url' => cms_url('admin/index.php'), 'image_url' => '', 'image_alt' => ''],
                    ], JSON_UNESCAPED_UNICODE),
                    'status' => 'published',
                    'is_homepage' => 1,
                    'sort_order' => 0,
                    'template' => 'default',
                ]);
                cms_save_page([
                    'title' => 'O nas',
                    'slug' => 'o-nas',
                    'excerpt' => 'Druga sekcja onepage.',
                    'content' => '',
                    'builder_data' => json_encode([
                        ['type' => 'text', 'title' => 'O projekcie', 'text' => 'To jest przykladowa sekcja onepage. Mozesz dodawac kolejne bloki, tla i obrazy.', 'background_color' => '#ffffff', 'background_image' => '', 'background_attachment' => 'scroll', 'min_height' => '320', 'align' => 'left', 'button_text' => '', 'button_url' => '', 'image_url' => '', 'image_alt' => ''],
                    ], JSON_UNESCAPED_UNICODE),
                    'status' => 'published',
                    'is_homepage' => 0,
                    'sort_order' => 10,
                    'template' => 'default',
                ]);
                cms_save_page([
                    'title' => 'Kontakt',
                    'slug' => 'kontakt',
                    'excerpt' => 'Sekcja kontaktowa.',
                    'content' => '',
                    'builder_data' => json_encode([
                        ['type' => 'text', 'title' => 'Kontakt', 'text' => 'Dodaj tu formularz, dane firmy albo CTA do kontaktu.', 'background_color' => '#e2e8f0', 'background_image' => '', 'background_attachment' => 'scroll', 'min_height' => '300', 'align' => 'center', 'button_text' => 'Napisz do nas', 'button_url' => 'mailto:' . $email, 'image_url' => '', 'image_alt' => ''],
                    ], JSON_UNESCAPED_UNICODE),
                    'status' => 'published',
                    'is_homepage' => 0,
                    'sort_order' => 20,
                    'template' => 'default',
                ]);
                cms_save_page([
                    'title' => 'Regulamin',
                    'slug' => 'regulamin',
                    'excerpt' => 'Przykladowa podstrona potomna.',
                    'content' => '<h2>Regulamin</h2><p>To przykladowa podstrona potomna w systemie CMS.</p>',
                    'builder_data' => '[]',
                    'status' => 'published',
                    'is_homepage' => 0,
                    'sort_order' => 0,
                    'parent_id' => $homeId,
                    'template' => 'default',
                ]);
            } else {
                cms_save_page([
                    'title' => 'Strona glowna',
                    'slug' => 'start',
                    'excerpt' => 'Pierwsza strona utworzona przez instalator CMS.',
                    'content' => '<h2>Witaj w nowym CMS</h2><p>Instalacja zakonczyla sie poprawnie. Teraz mozesz tworzyc strony, podstrony, wlaczac pluginy i budowac wyglad przez bloki.</p>',
                    'builder_data' => '[]',
                    'status' => 'published',
                    'is_homepage' => 1,
                    'sort_order' => 0,
                    'template' => 'default',
                ]);
                $offerId = cms_save_page([
                    'title' => 'Oferta',
                    'slug' => 'oferta',
                    'excerpt' => 'Przykladowa strona uslug.',
                    'content' => '<h2>Oferta</h2><p>To przykladowa strona w trybie wielostronicowym.</p>',
                    'builder_data' => '[]',
                    'status' => 'published',
                    'is_homepage' => 0,
                    'sort_order' => 10,
                    'template' => 'default',
                ]);
                cms_save_page([
                    'title' => 'Cennik',
                    'slug' => 'cennik',
                    'excerpt' => 'Przykladowa podstrona.',
                    'content' => '<h2>Cennik</h2><p>Podstrona potomna dzialajaca w trybie wielostronicowym.</p>',
                    'builder_data' => '[]',
                    'status' => 'published',
                    'is_homepage' => 0,
                    'sort_order' => 0,
                    'parent_id' => $offerId,
                    'template' => 'default',
                ]);
            }

            cms_flash('success', 'Instalacja zakonczona. Zaloguj sie do panelu CMS.');
            cms_redirect(cms_url('admin/index.php'));
        } catch (Throwable $e) {
            cms_write_config($previousConfig);
            cms_db(true);
            $error = 'Blad instalacji: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalator CMS</title>
    <style>
        body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:32px}
        .wrap{max-width:980px;margin:0 auto;background:#111827;border:1px solid #334155;border-radius:20px;padding:32px;box-shadow:0 20px 80px rgba(0,0,0,.35)}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.full{grid-column:1 / -1}
        label{display:block;font-size:14px;margin-bottom:8px;color:#cbd5e1}input,select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #475569;background:#0b1220;color:#fff}
        .choice{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.choice label{display:flex;gap:10px;align-items:center;padding:14px;border:1px solid #334155;border-radius:14px;background:#0b1220}.choice input{width:auto;padding:0;margin:0}
        .error{background:#7f1d1d;color:#fecaca;padding:12px;border-radius:12px;margin-bottom:16px}.btn{margin-top:20px;padding:14px 18px;border:none;border-radius:12px;background:#3b82f6;color:#fff;font-weight:700;cursor:pointer}.muted{color:#94a3b8}.db-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        @media (max-width:700px){.grid,.choice,.db-fields{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Instalator CMS</h1>
    <p class="muted">Wybierasz teraz tryb strony, silnik bazy oraz konto administratora. CMS zapisze konfiguracje bez mieszania z portfolio.</p>
    <?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
        <div class="grid">
            <div>
                <label>Nazwa strony</label>
                <input type="text" name="site_name" required value="My CMS">
            </div>
            <div>
                <label>Tagline</label>
                <input type="text" name="site_tagline" value="Lekki system CMS w PHP">
            </div>
            <div class="full">
                <label>Tryb strony / motywu</label>
                <div class="choice">
                    <label><input type="radio" name="site_mode" value="onepage"> Onepage: sekcje przewijane na jednej stronie</label>
                    <label><input type="radio" name="site_mode" value="multipage" checked> Wiele stron: klasyczne strony i podstrony</label>
                </div>
            </div>
            <div class="full">
                <label>Silnik bazy danych</label>
                <div class="choice">
                    <label><input type="radio" name="db_driver" value="sqlite" checked> SQLite: najszybszy start, bez konfiguracji serwera DB</label>
                    <label><input type="radio" name="db_driver" value="mysql"> MySQL: dla wiekszych wdrozen i hostingu z baza</label>
                </div>
            </div>
            <div class="full db-fields" id="mysqlFields">
                <div><label>MySQL host</label><input type="text" name="mysql_host" value="127.0.0.1"></div>
                <div><label>MySQL port</label><input type="text" name="mysql_port" value="3306"></div>
                <div><label>MySQL database</label><input type="text" name="mysql_database"></div>
                <div><label>MySQL username</label><input type="text" name="mysql_username"></div>
                <div class="full"><label>MySQL password</label><input type="password" name="mysql_password"></div>
            </div>
            <div>
                <label>Login admina</label>
                <input type="text" name="username" required value="admin">
            </div>
            <div>
                <label>E-mail admina</label>
                <input type="email" name="email" required>
            </div>
            <div>
                <label>Haslo</label>
                <input type="password" name="password" required>
            </div>
            <div>
                <label>Powtorz haslo</label>
                <input type="password" name="password2" required>
            </div>
        </div>
        <button class="btn" type="submit">Zainstaluj CMS</button>
    </form>
</div>
<script>
(function(){var radios=document.querySelectorAll('input[name="db_driver"]');var wrap=document.getElementById('mysqlFields');function sync(){var mysql=document.querySelector('input[name="db_driver"]:checked').value==='mysql';wrap.style.display=mysql?'grid':'none';}radios.forEach(function(r){r.addEventListener('change',sync);});sync();}());
</script>
</body>
</html>
