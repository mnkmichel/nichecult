<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';

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

    $hasTable = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name'
        );
        $stmt->execute([
            'table_name' => $table,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    };

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

    if (!$hasTable($pdo, 'sample_set_perfume_ratings')) {
        jsonResponse([
            'ok' => true,
            'rows' => [],
        ]);
    }

    $updatedAtSelect = $hasColumn($pdo, 'sample_set_perfume_ratings', 'updated_at')
        ? 'r.updated_at'
        : 'NULL AS updated_at';

    $answersTableAvailable = $hasTable($pdo, 'sample_set_perfume_rating_answers');
    $hasFavoritePerfumeColumn = $hasColumn($pdo, 'user_sample_sets', 'favorite_perfume_id');
    $favoriteSelect = $hasFavoritePerfumeColumn
        ? 'CASE WHEN uss.favorite_perfume_id IS NOT NULL AND uss.favorite_perfume_id = r.perfume_id THEN 1 ELSE 0 END AS is_favorite'
        : '0 AS is_favorite';

    $stmt = $pdo->query(
        "SELECT
            r.id AS rating_id,
            r.user_id,
            u.email AS user_email,
            u.first_name,
            u.last_name,
            r.user_sample_set_id,
            r.sample_set_id,
            ss.title AS sample_set_title,
            r.perfume_id,
            p.name AS perfume_name,
            p.brand_name,
            ssi.sort_order,
            r.overall_score,
            r.longevity_score,
            r.sillage_score,
            r.created_at,
            {$updatedAtSelect},
                uss.set_status,
                {$favoriteSelect}
         FROM sample_set_perfume_ratings r
         INNER JOIN users u ON u.id = r.user_id
         INNER JOIN sample_sets ss ON ss.id = r.sample_set_id
         INNER JOIN perfumes p ON p.id = r.perfume_id
         LEFT JOIN sample_set_items ssi
           ON ssi.sample_set_id = r.sample_set_id
          AND ssi.perfume_id = r.perfume_id
         LEFT JOIN user_sample_sets uss ON uss.id = r.user_sample_set_id
         ORDER BY r.sample_set_id DESC, r.user_id ASC, ssi.sort_order ASC, r.perfume_id ASC"
    );

    $rows = $stmt->fetchAll();
    if (!$rows) {
        jsonResponse([
            'ok' => true,
            'rows' => [],
        ]);
    }

    $answersByRatingId = [];

    if ($answersTableAvailable) {
        $ratingIds = array_map(static fn(array $row): int => (int) $row['rating_id'], $rows);
        $ratingIds = array_values(array_unique($ratingIds));

        if (!empty($ratingIds)) {
            $placeholders = implode(',', array_fill(0, count($ratingIds), '?'));
            $answersStmt = $pdo->prepare(
                "SELECT rating_id, question_key, answer_value
                 FROM sample_set_perfume_rating_answers
                 WHERE rating_id IN ({$placeholders})"
            );
            $answersStmt->execute($ratingIds);

            foreach ($answersStmt->fetchAll() as $answer) {
                $rid = (int) ($answer['rating_id'] ?? 0);
                if (!isset($answersByRatingId[$rid])) {
                    $answersByRatingId[$rid] = [];
                }
                $answersByRatingId[$rid][(string) ($answer['question_key'] ?? '')] = (string) ($answer['answer_value'] ?? '');
            }
        }
    }

    $normalizedRows = array_map(static function (array $row) use ($answersByRatingId): array {
        $ratingId = (int) ($row['rating_id'] ?? 0);
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);

        return [
            'rating_id' => $ratingId,
            'user_id' => (int) ($row['user_id'] ?? 0),
            'user_email' => (string) ($row['user_email'] ?? ''),
            'user_name' => $fullName,
            'user_sample_set_id' => (int) ($row['user_sample_set_id'] ?? 0),
            'sample_set_id' => (int) ($row['sample_set_id'] ?? 0),
            'sample_set_title' => (string) ($row['sample_set_title'] ?? ''),
            'perfume_id' => (int) ($row['perfume_id'] ?? 0),
            'perfume_name' => (string) ($row['perfume_name'] ?? ''),
            'brand_name' => isset($row['brand_name']) ? (string) $row['brand_name'] : null,
            'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : null,
            'overall_score' => isset($row['overall_score']) ? (int) $row['overall_score'] : null,
            'longevity_score' => isset($row['longevity_score']) ? (int) $row['longevity_score'] : null,
            'sillage_score' => isset($row['sillage_score']) ? (int) $row['sillage_score'] : null,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            'set_status' => isset($row['set_status']) ? (string) $row['set_status'] : null,
            'is_favorite' => (int) ($row['is_favorite'] ?? 0) === 1,
            'answers' => $answersByRatingId[$ratingId] ?? [],
        ];
    }, $rows);

    jsonResponse([
        'ok' => true,
        'rows' => $normalizedRows,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Admin rating analytics failed',
        'details' => $e->getMessage(),
    ], 500);
}
