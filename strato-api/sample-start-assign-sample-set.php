<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';

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
if (!$claims || empty($claims['sub'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid or expired token'], 401);
}

$userId = (int) $claims['sub'];

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

    if (!$hasColumn($pdo, 'user_sample_sets', 'rating_deadline_at')) {
        $pdo->exec('ALTER TABLE user_sample_sets ADD COLUMN rating_deadline_at DATETIME NULL AFTER assigned_at');
    }

    if (!$hasColumn($pdo, 'sample_sets', 'rating_deadline_at')) {
        $pdo->exec('ALTER TABLE sample_sets ADD COLUMN rating_deadline_at DATETIME NULL AFTER image_path');
    }

    $checkUser = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $checkUser->execute(['id' => $userId]);
    if (!$checkUser->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
    }

    $checkAnyAssignment = $pdo->prepare(
        'SELECT id
         FROM user_sample_sets
         WHERE user_id = :user_id
         ORDER BY assigned_at DESC, id DESC
         LIMIT 1'
    );
    $checkAnyAssignment->execute(['user_id' => $userId]);
    $existingAssignment = $checkAnyAssignment->fetch();
    if ($existingAssignment) {
        jsonResponse([
            'ok' => true,
            'user_sample_set_id' => (int) $existingAssignment['id'],
            'already_existed' => true,
        ]);
    }

    $checkSet = $pdo->prepare(
        'SELECT id, rating_deadline_at, title
         FROM sample_sets
         WHERE LOWER(TRIM(title)) IN ("erstes set", "erste duftselektion")
         ORDER BY
            CASE LOWER(TRIM(title))
              WHEN "erstes set" THEN 0
              WHEN "erste duftselektion" THEN 1
              ELSE 2
            END,
            id ASC
         LIMIT 1'
    );
    $checkSet->execute();
    $setRow = $checkSet->fetch();

    if (!$setRow) {
        $fallbackSet = $pdo->prepare(
            'SELECT id, rating_deadline_at, title
             FROM sample_sets
             WHERE status = "active"
             ORDER BY id ASC
             LIMIT 1'
        );
        $fallbackSet->execute();
        $setRow = $fallbackSet->fetch();
    }

    if (!$setRow) {
        jsonResponse(['ok' => false, 'error' => 'No active sample set available'], 404);
    }

    $ratingDeadlineAt = $setRow['rating_deadline_at'] ?? null;

    $stmt = $pdo->prepare(
        'INSERT INTO user_sample_sets (user_id, sample_set_id, set_status, rating_deadline_at)
         VALUES (:user_id, :sample_set_id, :set_status, :rating_deadline_at)
         ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            set_status = set_status,
            rating_deadline_at = rating_deadline_at'
    );
    $stmt->execute([
        'user_id' => $userId,
        'sample_set_id' => (int) $setRow['id'],
        'set_status' => 'delivered',
        'rating_deadline_at' => $ratingDeadlineAt,
    ]);

    $userSampleSetId = (int) $pdo->lastInsertId();
    jsonResponse([
        'ok' => true,
        'user_sample_set_id' => $userSampleSetId,
        'already_existed' => false,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Sample set assignment failed',
        'details' => $e->getMessage(),
    ], 500);
}
