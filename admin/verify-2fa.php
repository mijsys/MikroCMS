<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!cms_is_installed()) {
    cms_redirect(cms_url('install.php'));
}

if (cms_is_logged_in()) {
    cms_redirect(cms_url('admin/dashboard.php'));
}

$user = cms_pending_2fa_user();
if (!$user) {
    cms_flash('error', 'Sesja 2FA wygasla. Zaloguj sie ponownie.');
    cms_redirect(cms_url('admin/index.php'));
}

$twoFaUserId = (int) ($user['id'] ?? 0);

$error = '';
$lockSeconds = cms_2fa_remaining_lock_seconds($twoFaUserId);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Nieprawidlowy token bezpieczenstwa.';
    } elseif ($lockSeconds > 0) {
        $error = 'Zbyt wiele nieudanych prob. Sprobuj ponownie za ' . $lockSeconds . ' s.';
    } else {
        $totpCode = trim((string) ($_POST['totp_code'] ?? ''));
        $fileContent = trim((string) ($_POST['mijauth_content'] ?? ''));

        if ($fileContent === '' && isset($_FILES['mijauth_file']) && is_array($_FILES['mijauth_file']) && (int) ($_FILES['mijauth_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $tmp = (string) ($_FILES['mijauth_file']['tmp_name'] ?? '');
            if ($tmp !== '' && is_file($tmp)) {
                $fileContent = (string) file_get_contents($tmp);
            }
        }

        if (cms_verify_2fa_login($user, $totpCode, $fileContent)) {
            cms_2fa_clear_throttle($twoFaUserId);
            cms_finalize_login($user);
            cms_redirect(cms_url('admin/dashboard.php'));
        }

        $lockSeconds = cms_2fa_register_failure($twoFaUserId);
        if ($lockSeconds > 0) {
            $error = 'Konto zostalo czasowo zablokowane po nieudanych probach. Sprobuj ponownie za ' . $lockSeconds . ' s.';
        } else {
            $error = 'Weryfikacja 2FA nie powiodla sie (kod lub plik .mijauth).';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weryfikacja 2FA</title>
    <style>
        body{font-family:system-ui,-apple-system,sans-serif;background:#020617;color:#e2e8f0;display:grid;place-items:center;min-height:100vh;margin:0}
        .box{width:min(520px,92vw);background:#0f172a;border:1px solid #334155;border-radius:20px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,.35)}
        input,textarea{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #475569;background:#0b1220;color:#fff;margin-top:8px}
        textarea{min-height:120px}
        label{display:block;margin-top:14px;color:#cbd5e1}
        .btn{width:100%;margin-top:20px;padding:14px;border:none;border-radius:12px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}
        .msg{padding:12px;border-radius:12px;margin-top:12px}
        .err{background:#7f1d1d;color:#fecaca}
        .muted{color:#94a3b8;font-size:13px}
    </style>
</head>
<body>
<div class="box">
    <h1>Weryfikacja 2FA</h1>
    <p class="muted">Uzytkownik: <?= htmlspecialchars((string) ($user['username'] ?? 'admin')) ?></p>
    <?php if ($lockSeconds > 0): ?><p class="muted">Logowanie 2FA zablokowane jeszcze przez <?= (int) $lockSeconds ?> s.</p><?php endif; ?>
    <?php if ($error !== ''): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
        <label>Kod TOTP (6 cyfr)
            <input type="text" name="totp_code" pattern="\d{6}" maxlength="6" required>
        </label>
        <label>Plik .mijauth
            <input type="file" name="mijauth_file" accept=".mijauth,.txt,application/octet-stream">
        </label>
        <label>Lub wklej zawartosc pliku .mijauth
            <textarea name="mijauth_content" placeholder="Wklej zawartosc pliku"></textarea>
        </label>
        <button class="btn" type="submit">Potwierdz 2FA</button>
    </form>
</div>
</body>
</html>
