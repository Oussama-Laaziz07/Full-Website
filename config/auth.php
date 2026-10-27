<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function createAuthCookie(array $user): void
{
    $secret = getenv('AUTH_SECRET');

    if (!is_string($secret) || strlen($secret) < 32) {
        throw new RuntimeException('AUTH_SECRET is missing or too short.');
    }

    $payload = json_encode([
        'id' => (int)$user['id'],
        'role' => (string)$user['role'],
        'expires' => time() + 60 * 60 * 24 * 7,
    ]);

    if ($payload === false) {
        throw new RuntimeException('Could not create login token.');
    }

    $encodedPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encodedPayload, $secret, true);
    $encodedSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    setcookie('offria_auth', $encodedPayload . '.' . $encodedSignature, [
        'expires' => time() + 60 * 60 * 24 * 7,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function currentUser(PDO $pdo): ?array
{
    $token = $_COOKIE['offria_auth'] ?? '';
    $parts = explode('.', $token);
    $secret = getenv('AUTH_SECRET');
    if (count($parts) !== 2 || !is_string($secret) || strlen($secret) < 32) return null;
    [$payload, $signature] = $parts;
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $secret, true)), '+/', '-_'), '=');
    if (!hash_equals($expected, $signature)) return null;
    $decoded = base64_decode(strtr($payload, '-_', '+/'));
    $claims = json_decode($decoded ?: '', true);
    if (!is_array($claims) || empty($claims['id']) || (int)($claims['expires'] ?? 0) < time()) return null;
    $q = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = ? AND status = \'active\' LIMIT 1');
    $q->execute([(int)$claims['id']]);
    return $q->fetch() ?: null;
}

function requireUser(PDO $pdo): array
{
    $user = currentUser($pdo);
    if (!$user) jsonResponse(['error' => 'Please sign in to continue.'], 401);
    return $user;
}

function requireAdmin(PDO $pdo): array
{
    $user = requireUser($pdo);
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'Administrator access required.'], 403);
    return $user;
}
