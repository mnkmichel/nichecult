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

$body = readJsonBody();
$email = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    jsonResponse(['ok' => false, 'error' => 'Invalid credentials'], 400);
}

try {
    $pdo = getPdo($config);

    $stmt = $pdo->prepare('SELECT id, email, password_hash, is_admin FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Invalid credentials'], 401);
    }

    $now = time();
    $exp = $now + (int) $config['jwt']['ttl_seconds'];
    $claims = [
        'sub' => (int) $user['id'],
        'email' => (string) $user['email'],
        'admin' => (int) $user['is_admin'] === 1,
        'iss' => (string) $config['jwt']['issuer'],
        'iat' => $now,
        'exp' => $exp,
    ];

    $token = createJwt($claims, (string) $config['jwt']['secret']);

    jsonResponse([
        'ok' => true,
        'token' => $token,
        'expiresAt' => $exp,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Login failed',
        'details' => $e->getMessage(),
    ], 500);
}
