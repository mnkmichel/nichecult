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

try {
    $pdo = getPdo($config);

    $defaultSet = resolveDefaultSampleSet($pdo);
    if ($defaultSet === null) {
        jsonResponse(['ok' => false, 'error' => 'No active sample set available'], 404);
    }

    $stmt = $pdo->query(
        'SELECT u.id
         FROM users u
         LEFT JOIN user_sample_sets uss ON uss.user_id = u.id
         WHERE uss.id IS NULL
         ORDER BY u.id ASC'
    );
    $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $assignedCount = 0;
    foreach ($userIds as $userId) {
        $result = assignDefaultSetToUser($pdo, $userId);
        if (empty($result['already_existed'])) {
            $assignedCount++;
        }
    }

    jsonResponse([
        'ok' => true,
        'assigned_count' => $assignedCount,
        'skipped_count' => 0,
        'default_set_id' => (int) $defaultSet['id'],
        'default_set_title' => (string) $defaultSet['title'],
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Default set backfill failed',
        'details' => $e->getMessage(),
    ], 500);
}
