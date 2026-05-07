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
$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'active'));
$assignUserId = (int) ($_POST['assign_user_id'] ?? 0);
$perfumeIdsRaw = trim((string) ($_POST['perfume_ids'] ?? '[]'));
$perfumeIds = json_decode($perfumeIdsRaw, true);

if ($id <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid sample set id'], 400);
}

if ($title === '') {
    jsonResponse(['ok' => false, 'error' => 'Set title is required'], 400);
}

if (!is_array($perfumeIds)) {
    jsonResponse(['ok' => false, 'error' => 'perfume_ids must be a JSON array'], 400);
}

$perfumeIds = array_values(array_unique(array_map('intval', $perfumeIds)));
if (count($perfumeIds) !== 5) {
    jsonResponse(['ok' => false, 'error' => 'A sample set must contain exactly 5 perfumes'], 400);
}

if (!in_array($status, ['active', 'inactive'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid set status'], 400);
}

try {
    $pdo = getPdo($config);
    $pdo->beginTransaction();

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

    $existingStmt = $pdo->prepare('SELECT image_path FROM sample_sets WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existing = $existingStmt->fetch();
    if (!$existing) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Sample set not found'], 404);
    }

    $check = $pdo->prepare('SELECT id FROM perfumes WHERE id IN (' . implode(',', array_fill(0, count($perfumeIds), '?')) . ')');
    $check->execute($perfumeIds);
    $foundIds = array_map('intval', array_column($check->fetchAll(), 'id'));
    sort($foundIds);
    $sortedPerfumeIds = $perfumeIds;
    sort($sortedPerfumeIds);

    if ($foundIds !== $sortedPerfumeIds) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'One or more perfume ids are invalid'], 400);
    }

    $imagePath = $existing['image_path'] ?? null;
    $uploadedPath = saveUploadedImage();
    if ($uploadedPath !== null) {
        $imagePath = $uploadedPath;
    }

    $updateFields = [
        'title = :title',
        'description = :description',
        'image_path = :image_path',
        'status = :status',
    ];

    if ($hasColumn($pdo, 'sample_sets', 'updated_at')) {
        $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
    }

    $updateSet = $pdo->prepare(
        'UPDATE sample_sets
         SET ' . implode(",\n             ", $updateFields) . '
         WHERE id = :id'
    );
    $updateSet->execute([
        'id' => $id,
        'title' => $title,
        'description' => $description !== '' ? $description : null,
        'image_path' => $imagePath,
        'status' => $status,
    ]);

    $deleteItems = $pdo->prepare('DELETE FROM sample_set_items WHERE sample_set_id = :sample_set_id');
    $deleteItems->execute(['sample_set_id' => $id]);

    $insertItem = $pdo->prepare('INSERT INTO sample_set_items (sample_set_id, perfume_id, sort_order) VALUES (:sample_set_id, :perfume_id, :sort_order)');
    foreach ($perfumeIds as $index => $perfumeId) {
        $insertItem->execute([
            'sample_set_id' => $id,
            'perfume_id' => $perfumeId,
            'sort_order' => $index + 1,
        ]);
    }

    if ($assignUserId > 0) {
        $assign = $pdo->prepare("INSERT INTO user_sample_sets (user_id, sample_set_id, set_status) VALUES (:user_id, :sample_set_id, 'delivered') ON DUPLICATE KEY UPDATE set_status = 'delivered', completed_at = NULL");
        $assign->execute([
            'user_id' => $assignUserId,
            'sample_set_id' => $id,
        ]);
    }

    $pdo->commit();

    jsonResponse([
        'ok' => true,
        'sampleSet' => [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'image_url' => publicAssetUrl($imagePath),
            'perfume_ids' => $perfumeIds,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'ok' => false,
        'error' => 'Sample set update failed',
        'details' => $e->getMessage(),
    ], 500);
}
