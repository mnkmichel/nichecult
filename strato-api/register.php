<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/sample_set_assignment.php';

const PRIVACY_VERSION = '2026-05-15';

function registerHasColumn(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function ensureUserPrivacyConsentColumns(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }

    if (!registerHasColumn($pdo, 'users', 'privacy_accepted')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN privacy_accepted TINYINT(1) NOT NULL DEFAULT 0 AFTER age');
    }

    if (!registerHasColumn($pdo, 'users', 'privacy_accepted_at')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN privacy_accepted_at DATETIME NULL AFTER privacy_accepted');
    }

    if (!registerHasColumn($pdo, 'users', 'privacy_version')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN privacy_version VARCHAR(20) NULL AFTER privacy_accepted_at');
    }

    if (!registerHasColumn($pdo, 'users', 'contact_consent')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN contact_consent TINYINT(1) NOT NULL DEFAULT 0 AFTER privacy_version');
    }
}

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
$privacyAccepted = filter_var($body['privacyAccepted'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$contactConsent = filter_var($body['contactConsent'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid email'], 400);
}

if (strlen($password) < 8) {
    jsonResponse(['ok' => false, 'error' => 'Password must be at least 8 chars'], 400);
}

if ($age === false) {
    jsonResponse(['ok' => false, 'error' => 'Age must be between 12 and 120'], 400);
}

if ($privacyAccepted !== true) {
    jsonResponse(['ok' => false, 'error' => 'Bitte bestätige die Datenschutzerklärung, um fortzufahren.'], 400);
}

if ($contactConsent === null) {
    $contactConsent = false;
}

try {
    $pdo = getPdo($config);

    // Run schema safety checks before opening a transaction.
    // ALTER TABLE can trigger implicit commits in MySQL.
    ensureSampleSetDeadlineColumns($pdo);
    ensureUserPrivacyConsentColumns($pdo);

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);

    if ($check->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Email already exists'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, age, privacy_accepted, privacy_accepted_at, privacy_version, contact_consent) VALUES (:email, :password_hash, :first_name, :last_name, :age, :privacy_accepted, :privacy_accepted_at, :privacy_version, :contact_consent)');
    $insert->execute([
        'email' => $email,
        'password_hash' => $hash,
        'first_name' => $firstName !== '' ? $firstName : null,
        'last_name' => $lastName !== '' ? $lastName : null,
        'age' => (int) $age,
        'privacy_accepted' => 1,
        'privacy_accepted_at' => date('Y-m-d H:i:s'),
        'privacy_version' => PRIVACY_VERSION,
        'contact_consent' => $contactConsent ? 1 : 0,
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    assignDefaultSetToUser($pdo, $newUserId); // default set is assigned immediately after signup

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    jsonResponse([
        'ok' => true,
        'userId' => $newUserId,
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse([
        'ok' => false,
        'error' => 'Register failed',
        'details' => $e->getMessage(),
    ], 500);
}
