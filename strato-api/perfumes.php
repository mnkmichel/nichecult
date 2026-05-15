<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/uploads.php';

setCorsHeaders($config);
handlePreflightAndExit();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

try {
    $pdo = getPdo($config);
    $stmt = $pdo->query('SELECT id, name, brand_name, description, image_path, size_ml, price_cents, discount_percent, is_active FROM perfumes WHERE is_active = 1 ORDER BY name ASC');
    $perfumes = array_map(static function (array $perfume): array {
        $perfume['image_url'] = publicAssetUrl($perfume['image_path'] ?? null);
        return $perfume;
    }, $stmt->fetchAll());

    jsonResponse([
        'ok' => true,
        'perfumes' => $perfumes,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Perfume lookup failed',
        'details' => $e->getMessage(),
    ], 500);
}
