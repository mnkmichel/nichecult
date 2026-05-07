<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';
require __DIR__ . '/lib/uploads.php';

setCorsHeaders($config);
handlePreflightAndExit();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$token = getBearerToken();
if (!$token) {
    jsonResponse(['ok' => false, 'error' => 'Missing bearer token'], 401);
}

$claims = verifyJwt($token, (string) $config['jwt']['secret']);
if (!$claims || !isset($claims['sub'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid token'], 401);
}

try {
    $pdo = getPdo($config);
    $stmt = $pdo->prepare(
        'SELECT
            uss.id AS user_sample_set_id,
            uss.set_status,
            uss.assigned_at,
            uss.completed_at,
            ss.id AS sample_set_id,
            ss.title,
            ss.description,
            ss.image_path,
            COUNT(ssi.id) AS perfume_count
         FROM user_sample_sets uss
         INNER JOIN sample_sets ss ON ss.id = uss.sample_set_id
         LEFT JOIN sample_set_items ssi ON ssi.sample_set_id = ss.id
         WHERE uss.user_id = :user_id
         GROUP BY uss.id, ss.id
         ORDER BY uss.assigned_at DESC'
    );
    $stmt->execute(['user_id' => (int) $claims['sub']]);
    $sets = array_map(static function (array $set): array {
        $set['image_url'] = publicAssetUrl($set['image_path'] ?? null);
        return $set;
    }, $stmt->fetchAll());

    jsonResponse([
        'ok' => true,
        'sampleSets' => $sets,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Sample set lookup failed',
        'details' => $e->getMessage(),
    ], 500);
}
