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
$sampleSetId = 1;

try {
    $pdo = getPdo($config);

    // Check if already assigned
    $checkStmt = $pdo->prepare(
        'SELECT id FROM user_sample_sets WHERE user_id = :user_id AND sample_set_id = :sample_set_id LIMIT 1'
    );
    $checkStmt->execute(['user_id' => $userId, 'sample_set_id' => $sampleSetId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        jsonResponse([
            'ok' => true,
            'user_sample_set_id' => (int) $existing['id'],
            'already_existed' => true,
        ]);
    }

    // Verify sample set 1 exists
    $setStmt = $pdo->prepare('SELECT id FROM sample_sets WHERE id = :id LIMIT 1');
    $setStmt->execute(['id' => $sampleSetId]);
    if (!$setStmt->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Sample Set 1 not found'], 404);
    }

    // Insert new assignment
    $insertStmt = $pdo->prepare(
        'INSERT INTO user_sample_sets (user_id, sample_set_id, set_status)
         VALUES (:user_id, :sample_set_id, :set_status)'
    );
    $insertStmt->execute([
        'user_id'       => $userId,
        'sample_set_id' => $sampleSetId,
        'set_status'    => 'delivered',
    ]);

    $newId = (int) $pdo->lastInsertId();

    jsonResponse([
        'ok'                => true,
        'user_sample_set_id' => $newId,
        'already_existed'   => false,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok'      => false,
        'error'   => 'Assignment failed',
        'details' => $e->getMessage(),
    ], 500);
}
