<?php

declare(strict_types=1);

function sampleSetHasColumn(PDO $pdo, string $table, string $column): bool
{
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
}

function ensureSampleSetDeadlineColumns(PDO $pdo): void
{
    if (!sampleSetHasColumn($pdo, 'user_sample_sets', 'rating_deadline_at')) {
        $pdo->exec('ALTER TABLE user_sample_sets ADD COLUMN rating_deadline_at DATETIME NULL AFTER assigned_at');
    }

    if (!sampleSetHasColumn($pdo, 'sample_sets', 'rating_deadline_at')) {
        $pdo->exec('ALTER TABLE sample_sets ADD COLUMN rating_deadline_at DATETIME NULL AFTER image_path');
    }
}

function findSampleSetById(PDO $pdo, int $sampleSetId): ?array
{
    $stmt = $pdo->prepare('SELECT id, title, status, rating_deadline_at FROM sample_sets WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sampleSetId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'status' => (string) $row['status'],
        'rating_deadline_at' => $row['rating_deadline_at'] ?? null,
    ];
}

function ensureUserExists(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);

    if (!$stmt->fetch()) {
        throw new RuntimeException('User not found');
    }
}

function getLatestUserSampleSetAssignment(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, sample_set_id, set_status, rating_deadline_at
         FROM user_sample_sets
         WHERE user_id = :user_id
         ORDER BY assigned_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'sample_set_id' => (int) $row['sample_set_id'],
        'set_status' => (string) $row['set_status'],
        'rating_deadline_at' => $row['rating_deadline_at'] ?? null,
    ];
}

function resolveDefaultSampleSet(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT ss.id, ss.title, ss.status, ss.rating_deadline_at, COUNT(ssi.id) AS perfume_count
         FROM sample_sets ss
         LEFT JOIN sample_set_items ssi ON ssi.sample_set_id = ss.id
         GROUP BY ss.id
         ORDER BY
            CASE WHEN ss.status = "active" THEN 0 ELSE 1 END,
            CASE
              WHEN LOWER(TRIM(ss.title)) = "erstes set" THEN 0
              WHEN LOWER(TRIM(ss.title)) = "erste duftselektion" THEN 1
              WHEN LOWER(TRIM(ss.title)) LIKE "erstes set%" THEN 2
              WHEN LOWER(TRIM(ss.title)) LIKE "erste%" THEN 3
              ELSE 9
            END,
            CASE WHEN COUNT(ssi.id) > 0 THEN 0 ELSE 1 END,
            ss.id ASC
         LIMIT 1'
    );

    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'status' => (string) $row['status'],
        'rating_deadline_at' => $row['rating_deadline_at'] ?? null,
        'perfume_count' => (int) ($row['perfume_count'] ?? 0),
    ];
}

function upsertUserSampleSetAssignment(PDO $pdo, int $userId, int $sampleSetId, string $status = 'delivered', ?string $ratingDeadlineAt = null): void
{
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
}

function getUserSampleSetAssignment(PDO $pdo, int $userId, int $sampleSetId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, user_id, sample_set_id, set_status, rating_deadline_at
         FROM user_sample_sets
         WHERE user_id = :user_id AND sample_set_id = :sample_set_id
         LIMIT 1'
    );
    $stmt->execute([
        'user_id' => $userId,
        'sample_set_id' => $sampleSetId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'user_id' => (int) $row['user_id'],
        'sample_set_id' => (int) $row['sample_set_id'],
        'set_status' => (string) $row['set_status'],
        'rating_deadline_at' => $row['rating_deadline_at'] ?? null,
    ];
}

function assignUserToSampleSet(PDO $pdo, int $userId, int $sampleSetId, string $status = 'delivered', ?string $ratingDeadlineAt = null): int
{
    ensureSampleSetDeadlineColumns($pdo);
    ensureUserExists($pdo, $userId);

    $setRow = findSampleSetById($pdo, $sampleSetId);
    if ($setRow === null) {
        throw new RuntimeException('Sample set not found');
    }

    if ($ratingDeadlineAt === null) {
        $ratingDeadlineAt = $setRow['rating_deadline_at'] ?? null;
    }

    upsertUserSampleSetAssignment($pdo, $userId, $sampleSetId, $status, $ratingDeadlineAt);

    $assignment = getUserSampleSetAssignment($pdo, $userId, $sampleSetId);
    if ($assignment === null) {
        throw new RuntimeException('Sample set assignment failed');
    }

    return (int) $assignment['id'];
}

function assignDefaultSetToUser(PDO $pdo, int $userId): array
{
    ensureSampleSetDeadlineColumns($pdo);
    ensureUserExists($pdo, $userId);

    $existingAssignment = getLatestUserSampleSetAssignment($pdo, $userId);
    if ($existingAssignment !== null) {
        return [
            'user_sample_set_id' => (int) $existingAssignment['id'],
            'sample_set_id' => (int) $existingAssignment['sample_set_id'],
            'already_existed' => true,
        ];
    }

    $sampleSet = resolveDefaultSampleSet($pdo);
    if ($sampleSet === null) {
        throw new RuntimeException('No active sample set configured for auto-assignment.');
    }

    $userSampleSetId = assignUserToSampleSet(
        $pdo,
        $userId,
        (int) $sampleSet['id'],
        'delivered',
        $sampleSet['rating_deadline_at'] ?? null
    );

    return [
        'user_sample_set_id' => $userSampleSetId,
        'sample_set_id' => (int) $sampleSet['id'],
        'already_existed' => false,
    ];
}
