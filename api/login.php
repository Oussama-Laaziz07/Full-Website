<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    jsonResponse(['error' => 'Invalid request data.'], 400);
}

$email = strtolower(trim((string)($input['email'] ?? '')));
$password = (string)($input['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    jsonResponse(['error' => 'Enter your email and password.'], 400);
}

try {
    $pdo = db();

    $query = $pdo->prepare(
        'SELECT id, full_name, email, password_hash, role
         FROM users
         WHERE email = ? AND status = 'active'
         LIMIT 1'
    );
    $query->execute([$email]);
    $user = $query->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Email or password is incorrect.'], 401);
    }

    createAuthCookie($user);

    jsonResponse([
        'message' => 'You are signed in.',
        'user' => [
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
    ]);
} catch (Throwable $e) {
    error_log((string)$e);
    jsonResponse(['error' => 'Server error. Check the Vercel logs.'], 500);
}
