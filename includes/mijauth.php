<?php
declare(strict_types=1);

/**
 * MijAuth - plikowe 2FA + TOTP
 */
class MijAuth
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_LENGTH = 32;
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const VERSION = 1;
    private const AUTH_FILE_TTL_SECONDS = 2592000;
    private const DEVICE_COOKIE_NAME = 'cms_device_id';
    private const DEVICE_COOKIE_TTL_SECONDS = 15552000;

    public static function generateUserKey(): string
    {
        return base64_encode(random_bytes(self::KEY_LENGTH));
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function createAuthFile(string $userId, string $userKeyBase64, ?string $deviceHash = null): array
    {
        $token = self::generateToken();

        $payload = [
            'user_id' => $userId,
            'token' => $token,
            'created_at' => date('c'),
            'device_hash' => $deviceHash,
            'version' => self::VERSION,
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $encryptedContent = self::encrypt($jsonPayload, $userKeyBase64);

        return [
            'file_content' => $encryptedContent,
            'token' => $token,
        ];
    }

    public static function verifyAuthFile(string $fileContent, string $userKeyBase64, ?int $ttlSeconds = self::AUTH_FILE_TTL_SECONDS): ?array
    {
        try {
            $decrypted = self::decrypt($fileContent, $userKeyBase64);
            if ($decrypted === null) {
                return null;
            }

            $payload = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
            if (!isset($payload['user_id'], $payload['token'], $payload['version'], $payload['created_at'])) {
                return null;
            }

            $createdAt = strtotime((string) $payload['created_at']);
            if ($createdAt === false) {
                return null;
            }

            if ($ttlSeconds !== null && $ttlSeconds > 0 && (time() - $createdAt) > $ttlSeconds) {
                return null;
            }

            return $payload;
        } catch (JsonException $e) {
            return null;
        }
    }

    public static function verifyAuthFileWithToken(string $fileContent, string $userKeyBase64, ?string $expectedToken, string $expectedUserId, ?int $ttlSeconds = self::AUTH_FILE_TTL_SECONDS): bool
    {
        if ($expectedToken === null || $expectedToken === '') {
            return false;
        }

        $payload = self::verifyAuthFile($fileContent, $userKeyBase64, $ttlSeconds);
        if ($payload === null) {
            return false;
        }

        return hash_equals($expectedToken, (string) $payload['token'])
            && hash_equals($expectedUserId, (string) $payload['user_id']);
    }

    public static function regenerateAuthFile(string $userId, string $userKeyBase64, ?string $deviceHash = null): array
    {
        return self::createAuthFile($userId, $userKeyBase64, $deviceHash);
    }

    private static function encrypt(string $plaintext, string $keyBase64): string
    {
        $key = base64_decode($keyBase64, true);
        if ($key === false || strlen($key) !== self::KEY_LENGTH) {
            throw new RuntimeException('Nieprawidlowy klucz szyfrowania 2FA.');
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt(string $encryptedBase64, string $keyBase64): ?string
    {
        $key = base64_decode($keyBase64, true);
        $combined = base64_decode($encryptedBase64, true);

        if ($key === false || strlen($key) !== self::KEY_LENGTH || $combined === false) {
            return null;
        }

        if (strlen($combined) < self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv = substr($combined, 0, self::IV_LENGTH);
        $tag = substr($combined, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($combined, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext !== false ? $plaintext : null;
    }

    public static function generateDeviceHash(): string
    {
        $deviceId = (string) ($_COOKIE[self::DEVICE_COOKIE_NAME] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $deviceId)) {
            $deviceId = bin2hex(random_bytes(16));
            if (!headers_sent()) {
                setcookie(self::DEVICE_COOKIE_NAME, $deviceId, [
                    'expires' => time() + self::DEVICE_COOKIE_TTL_SECONDS,
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }

        return hash('sha256', $deviceId);
    }

    public static function verifyTotp(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::calculateTotp($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    private static function calculateTotp(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0, $timeSlice);

        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;

        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart)[1] ?? 0;
        $value = $value & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(str_replace('=', '', $base32));
        static $lookup = [
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
            'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31,
        ];
        $decoded = '';
        $buffer = 0;
        $bufferBits = 0;

        foreach (str_split($base32) as $char) {
            if (!isset($lookup[$char])) {
                continue;
            }
            $val = $lookup[$char];

            $buffer = ($buffer << 5) | $val;
            $bufferBits += 5;

            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $decoded .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }

        return $decoded;
    }

    public static function generateTotpSecret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getTotpProvisioningUri(string $accountName, string $secret, string $issuer = 'MikroCMS'): string
    {
        $issuer = rawurlencode($issuer);
        $accountName = rawurlencode($accountName);
        return "otpauth://totp/{$issuer}:{$accountName}?secret={$secret}&issuer={$issuer}";
    }
}
