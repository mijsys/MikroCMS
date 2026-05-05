<?php
declare(strict_types=1);

const CMS_2FA_MAX_ATTEMPTS = 3;
const CMS_2FA_LOCK_SECONDS = 900;

function cms_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('cms_session');
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => false,
            'cookie_samesite' => 'Lax',
            'gc_maxlifetime' => 7200,
        ]);
    }
}

function cms_csrf_token(): string
{
    cms_session_start();
    if (empty($_SESSION['cms_csrf_token'])) {
        $_SESSION['cms_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['cms_csrf_token'];
}

function cms_verify_csrf(?string $token): bool
{
    cms_session_start();
    return is_string($token) && isset($_SESSION['cms_csrf_token']) && hash_equals($_SESSION['cms_csrf_token'], $token);
}

function cms_is_logged_in(): bool
{
    cms_session_start();
    return !empty($_SESSION['cms_user_id']);
}

function cms_login(string $username, string $password): bool
{
    $user = cms_authenticate_credentials($username, $password);
    if (!$user) {
        return false;
    }

    if (cms_user_has_2fa($user)) {
        return false;
    }

    cms_finalize_login($user);
    return true;
}

function cms_authenticate_credentials(string $username, string $password): ?array
{
    $stmt = cms_db()->prepare('SELECT * FROM cms_users WHERE username = ? LIMIT 1');
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return null;
    }

    return $user;
}

function cms_finalize_login(array $user): void
{

    cms_session_start();
    session_regenerate_id(true);
    $_SESSION['cms_user_id'] = (int) $user['id'];
    $_SESSION['cms_username'] = $user['username'];
    unset($_SESSION['cms_pending_2fa_user_id']);
    cms_2fa_clear_throttle((int) ($user['id'] ?? 0));
}

function cms_user_has_2fa(array $user): bool
{
    return (int) ($user['twofa_enabled'] ?? 0) === 1
        && trim((string) ($user['twofa_totp_secret'] ?? '')) !== ''
        && trim((string) ($user['twofa_mijauth_key'] ?? '')) !== ''
        && trim((string) ($user['twofa_mijauth_token'] ?? '')) !== '';
}

function cms_pending_2fa_user(): ?array
{
    cms_session_start();
    $userId = (int) ($_SESSION['cms_pending_2fa_user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $stmt = cms_db()->prepare('SELECT * FROM cms_users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return is_array($user) ? $user : null;
}

function cms_2fa_remaining_lock_seconds(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    cms_session_start();
    $state = $_SESSION['cms_2fa_throttle'][$userId] ?? null;
    if (!is_array($state)) {
        return 0;
    }

    $lockedUntil = (int) ($state['locked_until'] ?? 0);
    $remaining = $lockedUntil - time();
    return $remaining > 0 ? $remaining : 0;
}

function cms_2fa_register_failure(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    cms_session_start();
    $state = $_SESSION['cms_2fa_throttle'][$userId] ?? ['failures' => 0, 'locked_until' => 0];
    if (!is_array($state)) {
        $state = ['failures' => 0, 'locked_until' => 0];
    }

    $now = time();
    $lockedUntil = (int) ($state['locked_until'] ?? 0);
    if ($lockedUntil > $now) {
        return $lockedUntil - $now;
    }

    $failures = (int) ($state['failures'] ?? 0) + 1;
    if ($failures >= CMS_2FA_MAX_ATTEMPTS) {
        $state['failures'] = 0;
        $state['locked_until'] = $now + CMS_2FA_LOCK_SECONDS;
    } else {
        $state['failures'] = $failures;
        $state['locked_until'] = 0;
    }

    $_SESSION['cms_2fa_throttle'][$userId] = $state;
    return cms_2fa_remaining_lock_seconds($userId);
}

function cms_2fa_clear_throttle(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    cms_session_start();
    if (isset($_SESSION['cms_2fa_throttle'][$userId])) {
        unset($_SESSION['cms_2fa_throttle'][$userId]);
    }
}

function cms_verify_2fa_login(array $user, string $totpCode, string $mijauthFileContent): bool
{
    $secret = trim((string) ($user['twofa_totp_secret'] ?? ''));
    $userKey = trim((string) ($user['twofa_mijauth_key'] ?? ''));
    $token = trim((string) ($user['twofa_mijauth_token'] ?? ''));
    $userId = (string) ($user['id'] ?? '');

    if ($secret === '' || $userKey === '' || $token === '' || $userId === '') {
        return false;
    }

    if (!MijAuth::verifyTotp($secret, $totpCode)) {
        return false;
    }

    return MijAuth::verifyAuthFileWithToken($mijauthFileContent, $userKey, $token, $userId);
}

function cms_require_login(): void
{
    if (!cms_is_logged_in()) {
        header('Location: ' . cms_url('admin/index.php'));
        exit;
    }
}

function cms_logout(): void
{
    cms_session_start();
    $_SESSION = [];
    unset($_SESSION['cms_pending_2fa_user_id']);
    session_destroy();
}

function cms_current_user(): ?array
{
    if (!cms_is_logged_in()) {
        return null;
    }

    $stmt = cms_db()->prepare('SELECT id, username, email, role, twofa_enabled, twofa_totp_secret, twofa_mijauth_key, twofa_mijauth_token FROM cms_users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['cms_user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function cms_update_user_2fa_setup(int $userId, string $totpSecret, string $mijauthKeyBase64, string $mijauthToken, bool $enabled): void
{
    cms_db()->prepare('UPDATE cms_users SET twofa_totp_secret = ?, twofa_mijauth_key = ?, twofa_mijauth_token = ?, twofa_enabled = ? WHERE id = ?')
        ->execute([$totpSecret, $mijauthKeyBase64, $mijauthToken, $enabled ? 1 : 0, $userId]);
}

function cms_disable_user_2fa(int $userId): void
{
    cms_db()->prepare('UPDATE cms_users SET twofa_totp_secret = ?, twofa_mijauth_key = NULL, twofa_mijauth_token = NULL, twofa_enabled = 0 WHERE id = ?')
        ->execute(['', $userId]);
}

function cms_generate_user_2fa_bootstrap(array $user): array
{
    $userId = (string) ($user['id'] ?? '');
    $username = (string) ($user['username'] ?? 'admin');
    if ($userId === '') {
        throw new RuntimeException('Brak poprawnego ID uzytkownika 2FA.');
    }

    $totpSecret = MijAuth::generateTotpSecret();
    $mijauthKey = MijAuth::generateUserKey();
    $authFile = MijAuth::createAuthFile($userId, $mijauthKey, MijAuth::generateDeviceHash());

    cms_update_user_2fa_setup((int) $userId, $totpSecret, $mijauthKey, (string) $authFile['token'], false);

    return [
        'totp_secret' => $totpSecret,
        'provisioning_uri' => MijAuth::getTotpProvisioningUri($username, $totpSecret, 'MikroCMS'),
        'mijauth_file_content' => (string) $authFile['file_content'],
        'mijauth_token' => (string) $authFile['token'],
    ];
}

function cms_regenerate_user_mijauth_file(array $user): array
{
    $userId = (string) ($user['id'] ?? '');
    $totpSecret = trim((string) ($user['twofa_totp_secret'] ?? ''));
    $mijauthKey = trim((string) ($user['twofa_mijauth_key'] ?? ''));

    if ($userId === '' || $totpSecret === '' || $mijauthKey === '') {
        throw new RuntimeException('Najpierw wygeneruj konfiguracje 2FA.');
    }

    $authFile = MijAuth::regenerateAuthFile($userId, $mijauthKey, MijAuth::generateDeviceHash());
    cms_update_user_2fa_setup((int) $userId, $totpSecret, $mijauthKey, (string) $authFile['token'], false);

    return [
        'mijauth_file_content' => (string) $authFile['file_content'],
        'mijauth_token' => (string) $authFile['token'],
    ];
}
