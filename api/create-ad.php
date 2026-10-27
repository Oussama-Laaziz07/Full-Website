<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    sendJson(['error' => 'Method not allowed.'], 405);
}

$data = getJsonInput();

$title = trim((string) ($data['title'] ?? ''));
$price = filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);
$category = trim((string) ($data['category'] ?? ''));
$city = trim((string) ($data['city'] ?? ''));
$image = trim((string) ($data['image'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));

if ($title === '' || strlen($title) > 200) {
    sendJson(['error' => 'Enter a title of 1 to 200 characters.'], 400);
}

if ($price === false || $price < 0) {
    sendJson(['error' => 'Enter a valid price greater than or equal to zero.'], 400);
}

if ($category === '' || $city === '' || $description === '') {
    sendJson(['error' => 'Category, city, and description are required.'], 400);
}

if (strlen($image) > 2048) {
    sendJson(['error' => 'The image URL is too long.'], 400);
}

$db = db();
$user = requireUser($db);

try {
    $db->beginTransaction();

    $categoryQuery = $db->prepare('SELECT id FROM categories WHERE name = ?');
    $categoryQuery->execute([$category]);
    $categoryId = $categoryQuery->fetchColumn();

    $cityQuery = $db->prepare('SELECT id FROM cities WHERE name = ?');
    $cityQuery->execute([$city]);
    $cityId = $cityQuery->fetchColumn();

    if (!$categoryId || !$cityId) {
        $db->rollBack();
        sendJson(['error' => 'The selected category or city was not found.'], 400);
    }

    $insertAd = $db->prepare(
        "INSERT INTO ads (owner_id, category_id, city_id, title, price, description, status)
         VALUES (?, ?, ?, ?, ?, ?, 'pending_review')"
    );
    $insertAd->execute([
        (int)$user['id'],
        $categoryId,
        $cityId,
        $title,
        $price,
        $description,
    ]);

    $adId = (int) $db->lastInsertId();

    if ($image !== '') {
        $insertImage = $db->prepare(
            'INSERT INTO ad_images (ad_id, image_url, sort_order)
             VALUES (?, ?, 0)'
        );
        $insertImage->execute([$adId, $image]);
    }

    $db->commit();

    sendJson([
        'success' => true,
        'message' => 'Your ad was submitted for administrator review.',
        'ad' => [
            'id' => $adId,
            'title' => $title,
            'price' => (float) $price,
            'category' => $category,
            'city' => $city,
            'image' => $image,
            'description' => $description,
        ],
    ], 201);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log($error->getMessage());
    sendJson(['error' => 'Could not create the ad.'], 500);
}
