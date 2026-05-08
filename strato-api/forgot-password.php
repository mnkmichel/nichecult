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
$appUrl = trim((string) ($body['appUrl'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid email'], 400);
}

try {
    $pdo = getPdo($config);

    $userStmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
    $userStmt->execute(['email' => $email]);
    $user = $userStmt->fetch();

    if ($user) {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 60 MINUTE))');
        $insert->execute([
            'user_id' => (int) $user['id'],
            'token_hash' => $tokenHash,
        ]);

        $baseUrl = $appUrl !== '' ? rtrim($appUrl, '/') : 'https://nichecult.de';
        $resetUrl = $baseUrl . '/reset-password?token=' . urlencode($rawToken);

        $subject = 'Nichecult Passwort zuruecksetzen';
        $message = "Hallo,\n\nSie haben angefordert, Ihr Passwort zurueckzusetzen.\n" .
            "Bitte nutzen Sie diesen Link (60 Minuten gueltig):\n" .
            $resetUrl . "\n\n" .
            "Falls Sie das nicht waren, ignorieren Sie diese E-Mail.";

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: Nichecult <no-reply@nichecult.de>',
        ];

        @mail((string) $user['email'], $subject, $message, implode("\r\n", $headers));
    }

    jsonResponse([
        'ok' => true,
        'message' => 'Wenn ein Konto mit dieser E-Mail existiert, wurde ein Reset-Link versendet.',
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Password reset request failed',
        'details' => $e->getMessage(),
    ], 500);
}
