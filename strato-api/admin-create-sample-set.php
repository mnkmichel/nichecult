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

$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'active'));
$assignUserId = (int) ($_POST['assign_user_id'] ?? 0);
$perfumeIdsRaw = trim((string) ($_POST['perfume_ids'] ?? '[]'));
$perfumeIds = json_decode($perfumeIdsRaw, true);

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
    $imagePath = saveUploadedImage();
    $pdo = getPdo($config);
    $pdo->beginTransaction();

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

    $insertSet = $pdo->prepare('INSERT INTO sample_sets (title, description, image_path, status) VALUES (:title, :description, :image_path, :status)');
    $insertSet->execute([
        'title' => $title,
        'description' => $description !== '' ? $description : null,
        'image_path' => $imagePath,
        'status' => $status,
    ]);

    $sampleSetId = (int) $pdo->lastInsertId();

    $insertItem = $pdo->prepare('INSERT INTO sample_set_items (sample_set_id, perfume_id, sort_order) VALUES (:sample_set_id, :perfume_id, :sort_order)');
    foreach ($perfumeIds as $index => $perfumeId) {
        $insertItem->execute([
            'sample_set_id' => $sampleSetId,
            'perfume_id' => $perfumeId,
            'sort_order' => $index + 1,
        ]);
    }

    if ($assignUserId > 0) {
        $assign = $pdo->prepare("INSERT INTO user_sample_sets (user_id, sample_set_id, set_status) VALUES (:user_id, :sample_set_id, 'delivered') ON DUPLICATE KEY UPDATE set_status = 'delivered', completed_at = NULL");
        $assign->execute([
            'user_id' => $assignUserId,
            'sample_set_id' => $sampleSetId,
        ]);
    }

    $pdo->commit();

    jsonResponse([
        'ok' => true,
        'sampleSet' => [
            'id' => $sampleSetId,
            'title' => $title,
            'description' => $description,
            'image_url' => publicAssetUrl($imagePath),
            'perfume_ids' => $perfumeIds,
        ],
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'ok' => false,
        'error' => 'Sample set creation failed',
        'details' => $e->getMessage(),
    ], 500);
}
