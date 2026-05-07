<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';
require __DIR__ . '/lib/uploads.php';

setCorsHeaders($config);
handlePreflightAndExit();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$token = getBearerToken();
if (!$token) {
    jsonResponse(['ok' => false, 'error' => 'Missing bearer token'], 401);
}

$claims = verifyJwt($token, (string) $config['jwt']['secret']);
if (!$claims || empty($claims['admin'])) {
    jsonResponse(['ok' => false, 'error' => 'Admin access required'], 403);
}

$name = trim((string) ($_POST['name'] ?? ''));
$brandName = trim((string) ($_POST['brand_name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$priceCents = (int) ($_POST['price_cents'] ?? 0);
$discountPercent = (int) ($_POST['discount_percent'] ?? 0);
$isActive = (int) ($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

if ($name === '') {
    jsonResponse(['ok' => false, 'error' => 'Perfume name is required'], 400);
}

try {
    $imagePath = saveUploadedImage();
    $pdo = getPdo($config);
    $stmt = $pdo->prepare('INSERT INTO perfumes (name, brand_name, description, image_path, price_cents, discount_percent, is_active) VALUES (:name, :brand_name, :description, :image_path, :price_cents, :discount_percent, :is_active)');
    $stmt->execute([
        'name' => $name,
        'brand_name' => $brandName !== '' ? $brandName : null,
        'description' => $description !== '' ? $description : null,
        'image_path' => $imagePath,
        'price_cents' => $priceCents,
        'discount_percent' => $discountPercent,
        'is_active' => $isActive,
    ]);

    jsonResponse([
        'ok' => true,
        'perfume' => [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
            'brand_name' => $brandName,
            'description' => $description,
            'image_url' => publicAssetUrl($imagePath),
        ],
    ], 201);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Perfume creation failed',
        'details' => $e->getMessage(),
    ], 500);
}
