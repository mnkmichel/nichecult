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
if (!$claims || empty($claims['sub'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid or expired token'], 401);
}

$userId = (int) $claims['sub'];

try {
    $pdo = getPdo($config);
    ensureSampleSetDeadlineColumns($pdo);

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

    $setRow = resolveDefaultSampleSet($pdo);
    if (!$setRow) {
        jsonResponse(['ok' => false, 'error' => 'No active sample set available'], 404);
    }

    $userSampleSetId = assignUserToSampleSet(
        $pdo,
        $userId,
        (int) $setRow['id'],
        'delivered',
        $setRow['rating_deadline_at'] ?? null
    );

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
