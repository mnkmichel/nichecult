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

$userSampleSetId = (int) ($_GET['user_sample_set_id'] ?? 0);
if ($userSampleSetId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid user_sample_set_id'], 400);
}

try {
    $pdo = getPdo($config);

    $setStmt = $pdo->prepare(
        'SELECT
            uss.id AS user_sample_set_id,
            uss.set_status,
            uss.assigned_at,
            uss.completed_at,
            ss.id AS sample_set_id,
            ss.title,
            ss.description,
            ss.image_path
         FROM user_sample_sets uss
         INNER JOIN sample_sets ss ON ss.id = uss.sample_set_id
         WHERE uss.id = :user_sample_set_id AND uss.user_id = :user_id
         LIMIT 1'
    );
    $setStmt->execute([
        'user_sample_set_id' => $userSampleSetId,
        'user_id' => (int) $claims['sub'],
    ]);
    $set = $setStmt->fetch();

    if (!$set) {
        jsonResponse(['ok' => false, 'error' => 'Sample set not found'], 404);
    }

    $set['image_url'] = publicAssetUrl($set['image_path'] ?? null);

    $itemsStmt = $pdo->prepare(
        'SELECT
            p.id AS perfume_id,
            p.name,
            p.brand_name,
            p.description,
            p.image_path,
            ssi.sort_order,
            r.id AS rating_id,
            r.overall_score,
            r.longevity_score,
            r.sillage_score
         FROM sample_set_items ssi
         INNER JOIN perfumes p ON p.id = ssi.perfume_id
         LEFT JOIN sample_set_perfume_ratings r
           ON r.user_sample_set_id = :user_sample_set_id
          AND r.perfume_id = p.id
         WHERE ssi.sample_set_id = :sample_set_id
         ORDER BY ssi.sort_order ASC'
    );
    $itemsStmt->execute([
        'user_sample_set_id' => $userSampleSetId,
        'sample_set_id' => (int) $set['sample_set_id'],
    ]);

    $perfumes = array_map(static function (array $perfume): array {
        $perfume['image_url'] = publicAssetUrl($perfume['image_path'] ?? null);
        return $perfume;
    }, $itemsStmt->fetchAll());

    jsonResponse([
        'ok' => true,
        'sampleSet' => $set,
        'perfumes' => $perfumes,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Sample set detail failed',
        'details' => $e->getMessage(),
    ], 500);
}
