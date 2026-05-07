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

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid perfume id'], 400);
}

try {
    $pdo = getPdo($config);
    $stmt = $pdo->prepare('DELETE FROM perfumes WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() < 1) {
        jsonResponse(['ok' => false, 'error' => 'Perfume not found'], 404);
    }

    jsonResponse(['ok' => true]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Perfume delete failed',
        'details' => $e->getMessage(),
    ], 500);
}
