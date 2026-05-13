<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';
require __DIR__ . '/lib/sample_set_assignment.php';

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

$sampleSetId = (int) ($_POST['sample_set_id'] ?? 0);
$userId = (int) ($_POST['user_id'] ?? 0);
$status = trim((string) ($_POST['set_status'] ?? 'delivered'));
$hasDeadlineInput = array_key_exists('rating_deadline_at', $_POST);
$ratingDeadlineRaw = trim((string) ($_POST['rating_deadline_at'] ?? ''));
$ratingDeadlineAt = null;

if ($ratingDeadlineRaw !== '') {
    $normalized = str_replace('T', ' ', $ratingDeadlineRaw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
        $normalized .= ':00';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $normalized);
    if (!$dt || $dt->format('Y-m-d H:i:s') !== $normalized) {
        jsonResponse(['ok' => false, 'error' => 'Invalid rating_deadline_at format'], 400);
    }

    $ratingDeadlineAt = $normalized;
}

if ($sampleSetId <= 0 || $userId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid sample_set_id or user_id'], 400);
}

if (!in_array($status, ['assigned', 'delivered', 'completed'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid set_status'], 400);
}

try {
    $pdo = getPdo($config);
    if (!$hasDeadlineInput) {
        $setRow = findSampleSetById($pdo, $sampleSetId);
        if ($setRow === null) {
            jsonResponse(['ok' => false, 'error' => 'Sample set not found'], 404);
        }

        $ratingDeadlineAt = $setRow['rating_deadline_at'] ?? null;
    }

    assignUserToSampleSet($pdo, $userId, $sampleSetId, $status, $ratingDeadlineAt);

    jsonResponse([
        'ok' => true,
        'assignment' => [
            'user_id' => $userId,
            'sample_set_id' => $sampleSetId,
            'set_status' => $status,
            'rating_deadline_at' => $ratingDeadlineAt,
        ],
    ]);
} catch (Throwable $e) {
    $statusCode = in_array($e->getMessage(), ['User not found', 'Sample set not found'], true) ? 404 : 500;
    jsonResponse([
        'ok' => false,
        'error' => $e->getMessage() === 'User not found' || $e->getMessage() === 'Sample set not found'
            ? $e->getMessage()
            : 'Sample set assignment failed',
        'details' => $e->getMessage(),
    ], $statusCode);
}
