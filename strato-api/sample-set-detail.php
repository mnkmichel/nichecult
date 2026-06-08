<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';
require __DIR__ . '/lib/uploads.php';
require __DIR__ . '/lib/favorite_selection.php';

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

    $sampleSetImageSelect = $hasColumn($pdo, 'sample_sets', 'image_path')
        ? 'ss.image_path'
        : 'NULL AS image_path';

    $perfumeImageSelect = $hasColumn($pdo, 'perfumes', 'image_path')
        ? 'p.image_path'
        : 'NULL AS image_path';

    $perfumeSizeSelect = $hasColumn($pdo, 'perfumes', 'size_ml')
        ? 'p.size_ml'
        : 'NULL AS size_ml';

    $perfumePriceSelect = $hasColumn($pdo, 'perfumes', 'price_cents')
        ? 'p.price_cents'
        : '0 AS price_cents';

    $hasUserDeadline = $hasColumn($pdo, 'user_sample_sets', 'rating_deadline_at');
    $hasSetDeadline = $hasColumn($pdo, 'sample_sets', 'rating_deadline_at');
    $ratingDeadlineSelect = ($hasUserDeadline && $hasSetDeadline)
        ? 'COALESCE(uss.rating_deadline_at, ss.rating_deadline_at) AS rating_deadline_at'
        : ($hasUserDeadline
            ? 'uss.rating_deadline_at'
            : ($hasSetDeadline
                ? 'ss.rating_deadline_at AS rating_deadline_at'
                : 'NULL AS rating_deadline_at'));

    $setStmt = $pdo->prepare(
        "SELECT
            uss.id AS user_sample_set_id,
            uss.set_status,
            uss.assigned_at,
            {$ratingDeadlineSelect},
            uss.completed_at,
            ss.id AS sample_set_id,
            ss.title,
            ss.description,
            {$sampleSetImageSelect}
         FROM user_sample_sets uss
         INNER JOIN sample_sets ss ON ss.id = uss.sample_set_id
         WHERE uss.id = :user_sample_set_id AND uss.user_id = :user_id
         LIMIT 1"
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
        "SELECT
            p.id AS perfume_id,
            p.name,
            p.brand_name,
            p.description,
            {$perfumeImageSelect},
            {$perfumeSizeSelect},
            {$perfumePriceSelect},
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
                 ORDER BY ssi.sort_order ASC"
    );
    $itemsStmt->execute([
        'user_sample_set_id' => $userSampleSetId,
        'sample_set_id' => (int) $set['sample_set_id'],
    ]);

    $perfumes = array_map(static function (array $perfume): array {
        $perfume['image_url'] = publicAssetUrl($perfume['image_path'] ?? null);
        return $perfume;
    }, $itemsStmt->fetchAll());

    $hasFavoritePerfume = $hasColumn($pdo, 'user_sample_sets', 'favorite_perfume_id');
    $favoritePerfumeId = $hasFavoritePerfume
        ? getFavorite($pdo, (int) $claims['sub'], $userSampleSetId)
        : null;

    jsonResponse([
        'ok' => true,
        'sampleSet' => $set,
        'perfumes' => $perfumes,
        'favoritePerfumeId' => $favoritePerfumeId,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Sample set detail failed',
        'details' => $e->getMessage(),
    ], 500);
}
