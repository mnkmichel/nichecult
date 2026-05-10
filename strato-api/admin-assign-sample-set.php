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
if (!$claims || empty($claims['admin'])) {
    jsonResponse(['ok' => false, 'error' => 'Admin access required'], 403);
}

$sampleSetId = (int) ($_POST['sample_set_id'] ?? 0);
$userId = (int) ($_POST['user_id'] ?? 0);
$status = trim((string) ($_POST['set_status'] ?? 'delivered'));
$hasDeadlineInput = array_key_exists('rating_deadline_at', $_POST);
$ratingDeadlineRaw = trim((string) ($_POST['rating_deadline_at'] ?? ''));
$ratingDeadlineAt = null;

if ($ratingDeadlineRaw !== '') {
    $normalized = str_replace('T', ' ', $ratingDeadlineRaw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
        $normalized .= ':00';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $normalized);
    if (!$dt || $dt->format('Y-m-d H:i:s') !== $normalized) {
        jsonResponse(['ok' => false, 'error' => 'Invalid rating_deadline_at format'], 400);
    }

    $ratingDeadlineAt = $normalized;
}

if ($sampleSetId <= 0 || $userId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid sample_set_id or user_id'], 400);
}

if (!in_array($status, ['assigned', 'delivered', 'completed'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid set_status'], 400);
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

    if (!$hasColumn($pdo, 'user_sample_sets', 'rating_deadline_at')) {
        $pdo->exec('ALTER TABLE user_sample_sets ADD COLUMN rating_deadline_at DATETIME NULL AFTER assigned_at');
    }

    if (!$hasColumn($pdo, 'sample_sets', 'rating_deadline_at')) {
        $pdo->exec('ALTER TABLE sample_sets ADD COLUMN rating_deadline_at DATETIME NULL AFTER image_path');
    }

    $checkSet = $pdo->prepare('SELECT id, rating_deadline_at FROM sample_sets WHERE id = :id LIMIT 1');
    $checkSet->execute(['id' => $sampleSetId]);
    $setRow = $checkSet->fetch();
    if (!$setRow) {
        jsonResponse(['ok' => false, 'error' => 'Sample set not found'], 404);
    }

    if (!$hasDeadlineInput) {
        $ratingDeadlineAt = $setRow['rating_deadline_at'] ?? null;
    }

    $checkUser = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $checkUser->execute(['id' => $userId]);
    if (!$checkUser->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
    }

    $stmt = $pdo->prepare(
                'INSERT INTO user_sample_sets (user_id, sample_set_id, set_status, rating_deadline_at)
                 VALUES (:user_id, :sample_set_id, :set_status, :rating_deadline_at)
         ON DUPLICATE KEY UPDATE
           set_status = VALUES(set_status),
                     rating_deadline_at = VALUES(rating_deadline_at),
           completed_at = CASE WHEN VALUES(set_status) = "completed" THEN CURRENT_TIMESTAMP ELSE NULL END'
    );
    $stmt->execute([
        'user_id' => $userId,
        'sample_set_id' => $sampleSetId,
        'set_status' => $status,
                'rating_deadline_at' => $ratingDeadlineAt,
    ]);

    jsonResponse([
        'ok' => true,
        'assignment' => [
            'user_id' => $userId,
            'sample_set_id' => $sampleSetId,
            'set_status' => $status,
            'rating_deadline_at' => $ratingDeadlineAt,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Sample set assignment failed',
        'details' => $e->getMessage(),
    ], 500);
}
