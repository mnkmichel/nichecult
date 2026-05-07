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
if (!$claims || !isset($claims['sub'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid token'], 401);
}

$body = readJsonBody();
$userSampleId = (int) ($body['userSampleId'] ?? 0);
$overallScore = (int) ($body['overallScore'] ?? 0);
$longevityScore = (int) ($body['longevityScore'] ?? 0);
$sillageScore = (int) ($body['sillageScore'] ?? 0);
$answers = $body['answers'] ?? [];

if ($userSampleId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid userSampleId'], 400);
}

$scoreValues = [$overallScore, $longevityScore, $sillageScore];
foreach ($scoreValues as $score) {
    if ($score < 1 || $score > 10) {
        jsonResponse(['ok' => false, 'error' => 'Scores must be between 1 and 10'], 400);
    }
}

if (!is_array($answers)) {
    jsonResponse(['ok' => false, 'error' => 'answers must be an object'], 400);
}

try {
    $pdo = getPdo($config);
    $pdo->beginTransaction();

    $ownership = $pdo->prepare('SELECT sample_id FROM user_samples WHERE id = :id AND user_id = :user_id LIMIT 1');
    $ownership->execute([
        'id' => $userSampleId,
        'user_id' => (int) $claims['sub'],
    ]);

    $owned = $ownership->fetch();
    if (!$owned) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Sample not found for user'], 404);
    }

    $sampleId = (int) $owned['sample_id'];
    $userId = (int) $claims['sub'];

    $ratingStmt = $pdo->prepare(
        'INSERT INTO sample_ratings (user_id, sample_id, overall_score, longevity_score, sillage_score)
         VALUES (:user_id, :sample_id, :overall_score, :longevity_score, :sillage_score)
         ON DUPLICATE KEY UPDATE
           overall_score = VALUES(overall_score),
           longevity_score = VALUES(longevity_score),
           sillage_score = VALUES(sillage_score),
           updated_at = CURRENT_TIMESTAMP'
    );

    $ratingStmt->execute([
        'user_id' => $userId,
        'sample_id' => $sampleId,
        'overall_score' => $overallScore,
        'longevity_score' => $longevityScore,
        'sillage_score' => $sillageScore,
    ]);

    $getRatingId = $pdo->prepare('SELECT id FROM sample_ratings WHERE user_id = :user_id AND sample_id = :sample_id LIMIT 1');
    $getRatingId->execute([
        'user_id' => $userId,
        'sample_id' => $sampleId,
    ]);

    $ratingRow = $getRatingId->fetch();
    $ratingId = (int) ($ratingRow['id'] ?? 0);

    if ($ratingId <= 0) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Could not resolve rating id'], 500);
    }

    $deleteAnswers = $pdo->prepare('DELETE FROM sample_rating_answers WHERE rating_id = :rating_id');
    $deleteAnswers->execute(['rating_id' => $ratingId]);

    if (!empty($answers)) {
        $insertAnswer = $pdo->prepare(
            'INSERT INTO sample_rating_answers (rating_id, question_key, answer_value)
             VALUES (:rating_id, :question_key, :answer_value)'
        );

        foreach ($answers as $questionKey => $answerValue) {
            $key = trim((string) $questionKey);
            if ($key === '') {
                continue;
            }

            $value = is_scalar($answerValue)
                ? (string) $answerValue
                : json_encode($answerValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $insertAnswer->execute([
                'rating_id' => $ratingId,
                'question_key' => $key,
                'answer_value' => $value,
            ]);
        }
    }

    $updateUserSample = $pdo->prepare(
        "UPDATE user_samples
         SET sample_status = 'rated', rated_at = CURRENT_TIMESTAMP
         WHERE id = :id AND user_id = :user_id"
    );
    $updateUserSample->execute([
        'id' => $userSampleId,
        'user_id' => $userId,
    ]);

    $pdo->commit();

    jsonResponse([
        'ok' => true,
        'ratingId' => $ratingId,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'ok' => false,
        'error' => 'Save rating failed',
        'details' => $e->getMessage(),
    ], 500);
}
