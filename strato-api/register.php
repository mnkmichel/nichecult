<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';

setCorsHeaders($config);
handlePreflightAndExit();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$body = readJsonBody();
$email = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');
$firstName = trim((string) ($body['firstName'] ?? ''));
$lastName = trim((string) ($body['lastName'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid email'], 400);
}

if (strlen($password) < 8) {
    jsonResponse(['ok' => false, 'error' => 'Password must be at least 8 chars'], 400);
}

try {
    $pdo = getPdo($config);

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);

    if ($check->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Email already exists'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name) VALUES (:email, :password_hash, :first_name, :last_name)');
    $insert->execute([
        'email' => $email,
        'password_hash' => $hash,
        'first_name' => $firstName !== '' ? $firstName : null,
        'last_name' => $lastName !== '' ? $lastName : null,
    ]);

    jsonResponse([
        'ok' => true,
        'userId' => (int) $pdo->lastInsertId(),
    ], 201);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Register failed',
        'details' => $e->getMessage(),
    ], 500);
}
