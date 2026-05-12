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
$ageRaw = $body['age'] ?? null;
$age = filter_var($ageRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 12, 'max_range' => 120]]);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid email'], 400);
}

if (strlen($password) < 8) {
    jsonResponse(['ok' => false, 'error' => 'Password must be at least 8 chars'], 400);
}

if ($age === false) {
    jsonResponse(['ok' => false, 'error' => 'Age must be between 12 and 120'], 400);
}

try {
    $pdo = getPdo($config);

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);

    if ($check->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Email already exists'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, age) VALUES (:email, :password_hash, :first_name, :last_name, :age)');
    $insert->execute([
        'email' => $email,
        'password_hash' => $hash,
        'first_name' => $firstName !== '' ? $firstName : null,
        'last_name' => $lastName !== '' ? $lastName : null,
        'age' => (int) $age,
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    // Auto-assign the existing set titled "Erstes Set" to every new user.
    $setRow = $pdo->prepare(
        'SELECT id, rating_deadline_at
         FROM sample_sets
         WHERE title = :title
         ORDER BY id ASC
         LIMIT 1'
    );
    $setRow->execute(['title' => 'Erstes Set']);
    $sampleSet = $setRow->fetch();

    if ($sampleSet) {
        // Ensure rating_deadline_at column exists before inserting.
        $colCheck = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $colCheck->execute(['table_name' => 'user_sample_sets', 'column_name' => 'rating_deadline_at']);
        $hasDeadlineCol = (int) $colCheck->fetchColumn() > 0;

        if (!$hasDeadlineCol) {
            $pdo->exec('ALTER TABLE user_sample_sets ADD COLUMN rating_deadline_at DATETIME NULL AFTER assigned_at');
        }

        $assignStmt = $pdo->prepare(
            'INSERT IGNORE INTO user_sample_sets (user_id, sample_set_id, set_status, rating_deadline_at)
             VALUES (:user_id, :sample_set_id, :set_status, :rating_deadline_at)'
        );
        $assignStmt->execute([
            'user_id'            => $newUserId,
            'sample_set_id'      => (int) $sampleSet['id'],
            'set_status'         => 'delivered',
            'rating_deadline_at' => $sampleSet['rating_deadline_at'] ?? null,
        ]);
    }

    jsonResponse([
        'ok' => true,
        'userId' => $newUserId,
    ], 201);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Register failed',
        'details' => $e->getMessage(),
    ], 500);
}
