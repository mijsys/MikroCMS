<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

if (cms_is_logged_in()) {
    cms_redirect(cms_url('admin/dashboard.php'));
}

$error = '';
$flash = cms_pull_flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = cms_t('admin.flash.csrf_invalid', 'Nieprawidlowy token bezpieczenstwa.');
    } else {
        $user = cms_authenticate_credentials((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!$user) {
            $error = cms_t('admin.login.error.credentials', 'Nieprawidlowy login lub haslo.');
        } elseif (cms_user_has_2fa($user)) {
            cms_session_start();
            $_SESSION['cms_pending_2fa_user_id'] = (int) $user['id'];
            cms_redirect(cms_url('admin/verify-2fa.php'));
        } else {
            cms_finalize_login($user);
            cms_redirect(cms_url('admin/dashboard.php'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(cms_admin_language()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(cms_t('admin.login.title', 'Logowanie CMS')) ?></title>
    <style>
        body{font-family:system-ui,-apple-system,sans-serif;background:#020617;color:#e2e8f0;display:grid;place-items:center;min-height:100vh;margin:0}
        .box{width:min(420px,92vw);background:#0f172a;border:1px solid #334155;border-radius:20px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,.35)}
        input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #475569;background:#0b1220;color:#fff;margin-top:8px}
        label{display:block;margin-top:14px;color:#cbd5e1}.btn{width:100%;margin-top:20px;padding:14px;border:none;border-radius:12px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}.msg{padding:12px;border-radius:12px;margin-top:12px}.err{background:#7f1d1d;color:#fecaca}.ok{background:#052e16;color:#bbf7d0}
    </style>
</head>
<body>
<div class="box">
    <h1><?= htmlspecialchars(cms_t('admin.login.heading', 'CMS Admin')) ?></h1>
    <p><?= htmlspecialchars(cms_t('admin.login.desc', 'Zaloguj sie do panelu zarzadzania.')) ?></p>
    <?php if ($flash): ?><div class="msg ok"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
        <label><?= htmlspecialchars(cms_t('admin.login.username', 'Login')) ?><input type="text" name="username" required autofocus></label>
        <label><?= htmlspecialchars(cms_t('admin.login.password', 'Haslo')) ?><input type="password" name="password" required></label>
        <button class="btn" type="submit"><?= htmlspecialchars(cms_t('admin.login.submit', 'Zaloguj sie')) ?></button>
    </form>
</div>
</body>
</html>
