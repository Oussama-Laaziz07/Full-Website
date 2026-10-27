<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    sendJson(['error' => 'Method not allowed.'], 405);
}

$data = getJsonInput();

$adId = filter_var($data['ad_id'] ?? null, FILTER_VALIDATE_INT);
$name = trim((string) ($data['sender_name'] ?? ''));
$email = trim((string) ($data['sender_email'] ?? ''));
$message = trim((string) ($data['message_text'] ?? ''));

if (!$adId || $adId < 1) {
    sendJson(['error' => 'A valid ad ID is required.'], 400);
}

if ($name === '' || strlen($name) > 150) {
    sendJson(['error' => 'Enter your name (up to 150 characters).'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    sendJson(['error' => 'Enter a valid email address.'], 400);
}

if ($message === '') {
    sendJson(['error' => 'Enter a message.'], 400);
}

$db = getDatabaseOrFail();

try {
    $statement = $db->prepare(
        'INSERT INTO messages (ad_id, sender_name, sender_email, message_text)
         VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$adId, $name, $email, $message]);

    sendJson(['success' => true], 201);
} catch (PDOException $error) {
    error_log($error->getMessage());

    if ($error->getCode() === '23000') {
        sendJson(['error' => 'That ad was not found.'], 404);
    }

    sendJson(['error' => 'Could not save the message.'], 500);
}