<?php
declare(strict_types=1);

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
    $stmt = cms_db()->prepare('SELECT * FROM cms_users WHERE username = ? LIMIT 1');
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    cms_session_start();
    session_regenerate_id(true);
    $_SESSION['cms_user_id'] = (int) $user['id'];
    $_SESSION['cms_username'] = $user['username'];

    return true;
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
    session_destroy();
}

function cms_current_user(): ?array
{
    if (!cms_is_logged_in()) {
        return null;
    }

    $stmt = cms_db()->prepare('SELECT id, username, email, role FROM cms_users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['cms_user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}
