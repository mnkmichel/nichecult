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
if (!$claims || empty($claims['admin'])) {
    jsonResponse(['ok' => false, 'error' => 'Admin access required'], 403);
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

    $updatedAtSelect = $hasColumn($pdo, 'sample_sets', 'updated_at')
        ? 'ss.updated_at'
        : 'NULL AS updated_at';
    $setDeadlineSelect = $hasColumn($pdo, 'sample_sets', 'rating_deadline_at')
        ? 'ss.rating_deadline_at'
        : 'NULL AS rating_deadline_at';

    $hasRatingDeadlineColumn = $hasColumn($pdo, 'user_sample_sets', 'rating_deadline_at');
    $deadlineSelect = $hasRatingDeadlineColumn
        ? 'MIN(uss.rating_deadline_at) AS next_rating_deadline_at,
            SUM(CASE WHEN uss.rating_deadline_at IS NOT NULL AND uss.set_status <> "completed" AND uss.rating_deadline_at < NOW() THEN 1 ELSE 0 END) AS overdue_assignments'
        : 'NULL AS next_rating_deadline_at,
            0 AS overdue_assignments';

    $stmt = $pdo->query(
        "SELECT
            ss.id,
            ss.title,
            ss.description,
            ss.image_path,
            ss.status,
            {$setDeadlineSelect},
            ss.created_at,
            {$updatedAtSelect},
            COUNT(DISTINCT ssi.id) AS perfume_count,
            COUNT(DISTINCT uss.id) AS assigned_count,
                {$deadlineSelect},
            GROUP_CONCAT(DISTINCT ssi.perfume_id ORDER BY ssi.sort_order ASC) AS perfume_ids_csv
         FROM sample_sets ss
         LEFT JOIN sample_set_items ssi ON ssi.sample_set_id = ss.id
         LEFT JOIN user_sample_sets uss ON uss.sample_set_id = ss.id
         GROUP BY ss.id
         ORDER BY ss.created_at DESC"
    );

    $sets = array_map(static function (array $set): array {
        $set['image_url'] = publicAssetUrl($set['image_path'] ?? null);
        $set['perfume_ids'] = [];
        if (!empty($set['perfume_ids_csv'])) {
            $set['perfume_ids'] = array_map('intval', explode(',', (string) $set['perfume_ids_csv']));
        }
        unset($set['perfume_ids_csv']);
        return $set;
    }, $stmt->fetchAll());

    jsonResponse([
        'ok' => true,
        'sampleSets' => $sets,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Sample set list failed',
        'details' => $e->getMessage(),
    ], 500);
}
