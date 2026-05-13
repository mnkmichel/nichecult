<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';
require __DIR__ . '/lib/sample_set_assignment.php';

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
if ($sampleSetId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid sample_set_id'], 400);
}

try {
    $pdo = getPdo($config);

    $sampleSet = getSampleSetWithPerfumeCountById($pdo, $sampleSetId);
    if ($sampleSet === null) {
        jsonResponse(['ok' => false, 'error' => 'Sample set not found'], 404);
    }

    setConfiguredDefaultSampleSetId($pdo, $sampleSetId);

    $stmt = $pdo->prepare(
        'SELECT u.id
         FROM users u
         LEFT JOIN user_sample_sets uss
           ON uss.user_id = u.id
          AND uss.sample_set_id = :sample_set_id
         WHERE uss.id IS NULL
         ORDER BY u.id ASC'
    );
    $stmt->execute(['sample_set_id' => $sampleSetId]);
    $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $assignedCount = 0;
    $ratingDeadlineAt = $sampleSet['rating_deadline_at'] ?? null;
    foreach ($userIds as $userId) {
        assignUserToSampleSet($pdo, $userId, $sampleSetId, 'delivered', $ratingDeadlineAt);
        $assignedCount++;
    }

    jsonResponse([
        'ok' => true,
        'default_set_id' => $sampleSetId,
        'default_set_title' => (string) $sampleSet['title'],
        'assigned_count' => $assignedCount,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Set default assignment failed',
        'details' => $e->getMessage(),
    ], 500);
}
