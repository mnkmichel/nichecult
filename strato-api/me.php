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
if (!$claims || !isset($claims['sub'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid token'], 401);
}

try {
    $pdo = getPdo($config);

    $stmt = $pdo->prepare('SELECT id, email, first_name, last_name, is_admin, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $claims['sub']]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
    }

    jsonResponse([
        'ok' => true,
        'user' => $user,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Profile lookup failed',
        'details' => $e->getMessage(),
    ], 500);
}
