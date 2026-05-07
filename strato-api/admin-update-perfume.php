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

$id = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$brandName = trim((string) ($_POST['brand_name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$priceCents = (int) ($_POST['price_cents'] ?? 0);
$discountPercent = (int) ($_POST['discount_percent'] ?? 0);
$isActive = (int) ($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
$sizeMl = isset($_POST['size_ml']) ? (int) $_POST['size_ml'] : null;

if ($id <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid perfume id'], 400);
}

if ($name === '') {
    jsonResponse(['ok' => false, 'error' => 'Perfume name is required'], 400);
}

try {
    $pdo = getPdo($config);

    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    };

    $existingStmt = $pdo->prepare('SELECT image_path FROM perfumes WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existing = $existingStmt->fetch();
    if (!$existing) {
        jsonResponse(['ok' => false, 'error' => 'Perfume not found'], 404);
    }

    $imagePath = $existing['image_path'] ?? null;
    $uploadedPath = saveUploadedImage();
    if ($uploadedPath !== null) {
        $imagePath = $uploadedPath;
    }

    $updateFields = [
        'name = :name',
        'brand_name = :brand_name',
        'description = :description',
        'image_path = :image_path',
        'size_ml = :size_ml',
        'price_cents = :price_cents',
        'discount_percent = :discount_percent',
        'is_active = :is_active',
    ];

    if ($hasColumn($pdo, 'perfumes', 'updated_at')) {
        $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
    }

    $stmt = $pdo->prepare(
        'UPDATE perfumes
         SET ' . implode(",\n             ", $updateFields) . '
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'name' => $name,
        'brand_name' => $brandName !== '' ? $brandName : null,
        'description' => $description !== '' ? $description : null,
        'image_path' => $imagePath,
        'size_ml' => $sizeMl,
        'price_cents' => $priceCents,
        'discount_percent' => $discountPercent,
        'is_active' => $isActive,
    ]);

    jsonResponse([
        'ok' => true,
        'perfume' => [
            'id' => $id,
            'name' => $name,
            'brand_name' => $brandName,
            'description' => $description,
            'size_ml' => $sizeMl,
            'price_cents' => $priceCents,
            'discount_percent' => $discountPercent,
            'is_active' => $isActive,
            'image_url' => publicAssetUrl($imagePath),
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Perfume update failed',
        'details' => $e->getMessage(),
    ], 500);
}
