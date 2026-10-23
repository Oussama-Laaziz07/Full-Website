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
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}