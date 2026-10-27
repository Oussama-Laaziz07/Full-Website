<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

function sendJson(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        sendJson(['error' => 'Invalid JSON request.'], 400);
    }

    return $data;
}

function getDatabaseOrFail(): PDO
{
    try {
        return db();
    } catch (Throwable $error) {
        error_log($error->getMessage());
        sendJson(['error' => 'Could not connect to the database.'], 500);
    }
}
