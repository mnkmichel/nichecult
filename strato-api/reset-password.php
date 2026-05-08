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
$token = trim((string) ($body['token'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($token === '' || strlen($password) < 8) {
    jsonResponse(['ok' => false, 'error' => 'Invalid request'], 400);
}

try {
    $pdo = getPdo($config);
    $tokenHash = hash('sha256', $token);

    $find = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $find->execute(['token_hash' => $tokenHash]);
    $reset = $find->fetch();

    if (!$reset) {
        jsonResponse(['ok' => false, 'error' => 'Reset token is invalid or expired'], 400);
    }

    $pdo->beginTransaction();

    $newHash = password_hash($password, PASSWORD_DEFAULT);

    $updateUser = $pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :user_id');
    $updateUser->execute([
        'password_hash' => $newHash,
        'user_id' => (int) $reset['user_id'],
    ]);

    $markUsed = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
    $markUsed->execute(['id' => (int) $reset['id']]);

    $invalidateOthers = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL');
    $invalidateOthers->execute(['user_id' => (int) $reset['user_id']]);

    $pdo->commit();

    jsonResponse([
        'ok' => true,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'ok' => false,
        'error' => 'Password reset failed',
        'details' => $e->getMessage(),
    ], 500);
}
