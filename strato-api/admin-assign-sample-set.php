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

if ($sampleSetId <= 0 || $userId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid sample_set_id or user_id'], 400);
}

if (!in_array($status, ['assigned', 'delivered', 'completed'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid set_status'], 400);
}

try {
    $pdo = getPdo($config);

    $checkSet = $pdo->prepare('SELECT id FROM sample_sets WHERE id = :id LIMIT 1');
    $checkSet->execute(['id' => $sampleSetId]);
    if (!$checkSet->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Sample set not found'], 404);
    }

    $checkUser = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $checkUser->execute(['id' => $userId]);
    if (!$checkUser->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO user_sample_sets (user_id, sample_set_id, set_status)
         VALUES (:user_id, :sample_set_id, :set_status)
         ON DUPLICATE KEY UPDATE
           set_status = VALUES(set_status),
           completed_at = CASE WHEN VALUES(set_status) = "completed" THEN CURRENT_TIMESTAMP ELSE NULL END'
    );
    $stmt->execute([
        'user_id' => $userId,
        'sample_set_id' => $sampleSetId,
        'set_status' => $status,
    ]);

    jsonResponse([
        'ok' => true,
        'assignment' => [
            'user_id' => $userId,
            'sample_set_id' => $sampleSetId,
            'set_status' => $status,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Sample set assignment failed',
        'details' => $e->getMessage(),
    ], 500);
}
