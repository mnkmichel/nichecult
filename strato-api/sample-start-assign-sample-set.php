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

    $existingAssignment = getLatestUserSampleSetAssignment($pdo, $userId);
    if ($existingAssignment !== null) {
        jsonResponse([
            'ok' => true,
            'user_sample_set_id' => (int) $existingAssignment['id'],
            'already_existed' => true,
        ]);
    }

    $result = assignDefaultSetToUser($pdo, $userId);

    jsonResponse([
        'ok' => true,
        'user_sample_set_id' => (int) $result['user_sample_set_id'],
        'already_existed' => (bool) $result['already_existed'],
    ]);
} catch (Throwable $e) {
    $statusCode = $e->getMessage() === 'User not found' ? 404 : 500;
    jsonResponse([
        'ok' => false,
        'error' => $e->getMessage() === 'User not found'
            ? 'User not found'
            : 'Sample set assignment failed',
        'details' => $e->getMessage(),
    ], $statusCode);
}
