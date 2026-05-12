<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';

setCorsHeaders($config);
handlePreflightAndExit();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
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

    $setRow = $pdo->query(
        'SELECT id, title, status, rating_deadline_at
         FROM sample_sets
         WHERE LOWER(TRIM(title)) IN ("erstes set", "erste duftselektion")
         ORDER BY
            CASE LOWER(TRIM(title))
              WHEN "erstes set" THEN 0
              WHEN "erste duftselektion" THEN 1
              ELSE 2
            END,
            id ASC
         LIMIT 1'
    )->fetch();

    $resolution = 'title-match';

    if (!$setRow) {
        $setRow = $pdo->query(
            'SELECT id, title, status, rating_deadline_at
             FROM sample_sets
             WHERE status = "active"
             ORDER BY id ASC
             LIMIT 1'
        )->fetch();
        $resolution = 'first-active';
    }

    if (!$setRow) {
        jsonResponse([
            'ok' => false,
            'error' => 'No active sample set available',
        ], 404);
    }

    jsonResponse([
        'ok' => true,
        'defaultSampleSet' => [
            'id' => (int) $setRow['id'],
            'title' => (string) $setRow['title'],
            'status' => (string) $setRow['status'],
            'rating_deadline_at' => $setRow['rating_deadline_at'] ?? null,
            'resolution' => $resolution,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Default sample set lookup failed',
        'details' => $e->getMessage(),
    ], 500);
}
