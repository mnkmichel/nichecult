<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';

setCorsHeaders($config);
handlePreflightAndExit();

try {
    $pdo = getPdo($config);
    $stmt = $pdo->query('SELECT NOW() AS server_time');
    $row = $stmt->fetch();

    jsonResponse([
        'ok' => true,
        'dbTime' => $row['server_time'] ?? null,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'DB unreachable',
        'details' => $e->getMessage(),
    ], 500);
}
