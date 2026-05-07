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
$userSampleSetId = (int) ($body['userSampleSetId'] ?? 0);
$perfumeId = (int) ($body['perfumeId'] ?? 0);
$overallScore = (int) ($body['overallScore'] ?? 0);
$longevityScore = (int) ($body['longevityScore'] ?? 0);
$sillageScore = (int) ($body['sillageScore'] ?? 0);
$answers = $body['answers'] ?? [];

if ($userSampleSetId <= 0 || $perfumeId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid userSampleSetId or perfumeId'], 400);
}

foreach ([$overallScore, $longevityScore, $sillageScore] as $score) {
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

    $ownership = $pdo->prepare(
        'SELECT uss.sample_set_id
         FROM user_sample_sets uss
         INNER JOIN sample_set_items ssi ON ssi.sample_set_id = uss.sample_set_id
         WHERE uss.id = :user_sample_set_id AND uss.user_id = :user_id AND ssi.perfume_id = :perfume_id
         LIMIT 1'
    );
    $ownership->execute([
        'user_sample_set_id' => $userSampleSetId,
        'user_id' => (int) $claims['sub'],
        'perfume_id' => $perfumeId,
    ]);
    $owned = $ownership->fetch();

    if (!$owned) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Perfume not found in assigned sample set'], 404);
    }

    $sampleSetId = (int) $owned['sample_set_id'];
    $userId = (int) $claims['sub'];

    $ratingStmt = $pdo->prepare(
        'INSERT INTO sample_set_perfume_ratings (user_id, user_sample_set_id, sample_set_id, perfume_id, overall_score, longevity_score, sillage_score)
         VALUES (:user_id, :user_sample_set_id, :sample_set_id, :perfume_id, :overall_score, :longevity_score, :sillage_score)
         ON DUPLICATE KEY UPDATE
           overall_score = VALUES(overall_score),
           longevity_score = VALUES(longevity_score),
           sillage_score = VALUES(sillage_score),
           updated_at = CURRENT_TIMESTAMP'
    );
    $ratingStmt->execute([
        'user_id' => $userId,
        'user_sample_set_id' => $userSampleSetId,
        'sample_set_id' => $sampleSetId,
        'perfume_id' => $perfumeId,
        'overall_score' => $overallScore,
        'longevity_score' => $longevityScore,
        'sillage_score' => $sillageScore,
    ]);

    $ratingIdStmt = $pdo->prepare('SELECT id FROM sample_set_perfume_ratings WHERE user_sample_set_id = :user_sample_set_id AND perfume_id = :perfume_id LIMIT 1');
    $ratingIdStmt->execute([
        'user_sample_set_id' => $userSampleSetId,
        'perfume_id' => $perfumeId,
    ]);
    $rating = $ratingIdStmt->fetch();
    $ratingId = (int) ($rating['id'] ?? 0);

    $deleteAnswers = $pdo->prepare('DELETE FROM sample_set_perfume_rating_answers WHERE rating_id = :rating_id');
    $deleteAnswers->execute(['rating_id' => $ratingId]);

    if (!empty($answers)) {
        $insertAnswer = $pdo->prepare('INSERT INTO sample_set_perfume_rating_answers (rating_id, question_key, answer_value) VALUES (:rating_id, :question_key, :answer_value)');
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

    $countTotalStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM sample_set_items WHERE sample_set_id = :sample_set_id');
    $countTotalStmt->execute(['sample_set_id' => $sampleSetId]);
    $totalPerfumes = (int) (($countTotalStmt->fetch()['total'] ?? 0));

    $countRatedStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM sample_set_perfume_ratings WHERE user_sample_set_id = :user_sample_set_id');
    $countRatedStmt->execute(['user_sample_set_id' => $userSampleSetId]);
    $ratedPerfumes = (int) (($countRatedStmt->fetch()['total'] ?? 0));

    $status = ($totalPerfumes > 0 && $ratedPerfumes >= $totalPerfumes) ? 'completed' : 'delivered';
    $updateSet = $pdo->prepare('UPDATE user_sample_sets SET set_status = :set_status, completed_at = CASE WHEN :set_status = "completed" THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id = :id');
    $updateSet->execute([
        'set_status' => $status,
        'id' => $userSampleSetId,
    ]);

    $pdo->commit();

    jsonResponse([
        'ok' => true,
        'ratingId' => $ratingId,
        'setStatus' => $status,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'ok' => false,
        'error' => 'Save perfume rating failed',
        'details' => $e->getMessage(),
    ], 500);
}
