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

$name = trim((string)($input['full_name'] ?? ''));
$email = strtolower(trim((string)($input['email'] ?? '')));
$password = (string)($input['password'] ?? '');

if ($name === '' || mb_strlen($name) > 150) {
    jsonResponse(['error' => 'Enter your name (up to 150 characters).'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    jsonResponse(['error' => 'Enter a valid email address.'], 400);
}

if (strlen($password) < 8) {
    jsonResponse(['error' => 'Your password must be at least 8 characters.'], 400);
}

try {
    $pdo = db();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $query = $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, role)
         VALUES (?, ?, ?, 'user')"
    );
    $query->execute([$name, $email, $passwordHash]);

    $user = [
        'id' => (int)$pdo->lastInsertId(),
        'role' => 'user',
    ];

    createAuthCookie($user);

    jsonResponse([
        'message' => 'Account created successfully.',
        'user' => ['full_name' => $name, 'email' => $email, 'role' => 'user'],
    ], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonResponse(['error' => 'An account with this email already exists.'], 409);
    }

    error_log((string)$e);
    jsonResponse(['error' => 'Could not create the account. Check the Vercel logs.'], 500);
} catch (Throwable $e) {
    error_log((string)$e);
    jsonResponse(['error' => 'Server error. Check the Vercel logs.'], 500);
}