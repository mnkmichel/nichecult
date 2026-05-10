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

    $hasTable = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name'
        );
        $stmt->execute([
            'table_name' => $table,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    };

    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
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
    };

    $hasIndex = static function (PDO $pdo, string $table, string $index): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND INDEX_NAME = :index_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasTable($pdo, 'sample_set_perfume_ratings')) {
        $pdo->exec(
            'CREATE TABLE sample_set_perfume_ratings (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                user_sample_set_id BIGINT NOT NULL,
                sample_set_id BIGINT NOT NULL,
                perfume_id BIGINT NOT NULL,
                overall_score TINYINT NULL,
                longevity_score TINYINT NULL,
                sillage_score TINYINT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sspr_user_set_perfume (user_sample_set_id, perfume_id)
            )'
        );
    }

    $requiredRatingColumns = [
        'user_id' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN user_id BIGINT NOT NULL AFTER id',
        'user_sample_set_id' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN user_sample_set_id BIGINT NOT NULL AFTER user_id',
        'sample_set_id' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN sample_set_id BIGINT NOT NULL AFTER user_sample_set_id',
        'perfume_id' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN perfume_id BIGINT NOT NULL AFTER sample_set_id',
        'overall_score' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN overall_score TINYINT NULL AFTER perfume_id',
        'longevity_score' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN longevity_score TINYINT NULL AFTER overall_score',
        'sillage_score' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN sillage_score TINYINT NULL AFTER longevity_score',
        'created_at' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER sillage_score',
        'updated_at' => 'ALTER TABLE sample_set_perfume_ratings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    ];

    foreach ($requiredRatingColumns as $column => $sql) {
        if (!$hasColumn($pdo, 'sample_set_perfume_ratings', $column)) {
            $pdo->exec($sql);
        }
    }

    if (!$hasIndex($pdo, 'sample_set_perfume_ratings', 'uq_sspr_user_set_perfume')) {
        $pdo->exec('ALTER TABLE sample_set_perfume_ratings ADD UNIQUE KEY uq_sspr_user_set_perfume (user_sample_set_id, perfume_id)');
    }

    if (!$hasTable($pdo, 'sample_set_perfume_rating_answers')) {
        $pdo->exec(
            'CREATE TABLE sample_set_perfume_rating_answers (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                rating_id BIGINT NOT NULL,
                question_key VARCHAR(80) NOT NULL,
                answer_value TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sspr_answers_question (rating_id, question_key)
            )'
        );
    }

    $requiredAnswerColumns = [
        'rating_id' => 'ALTER TABLE sample_set_perfume_rating_answers ADD COLUMN rating_id BIGINT NOT NULL AFTER id',
        'question_key' => 'ALTER TABLE sample_set_perfume_rating_answers ADD COLUMN question_key VARCHAR(80) NOT NULL AFTER rating_id',
        'answer_value' => 'ALTER TABLE sample_set_perfume_rating_answers ADD COLUMN answer_value TEXT NOT NULL AFTER question_key',
        'created_at' => 'ALTER TABLE sample_set_perfume_rating_answers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER answer_value',
    ];

    foreach ($requiredAnswerColumns as $column => $sql) {
        if (!$hasColumn($pdo, 'sample_set_perfume_rating_answers', $column)) {
            $pdo->exec($sql);
        }
    }

    if (!$hasIndex($pdo, 'sample_set_perfume_rating_answers', 'idx_sspr_answers_question')) {
        $pdo->exec('ALTER TABLE sample_set_perfume_rating_answers ADD INDEX idx_sspr_answers_question (rating_id, question_key)');
    }

    $hasRatingUpdatedAt = $hasColumn($pdo, 'sample_set_perfume_ratings', 'updated_at');
    $hasRatingAnswersTable = $hasTable($pdo, 'sample_set_perfume_rating_answers');
    $hasUserDeadlineAt = $hasColumn($pdo, 'user_sample_sets', 'rating_deadline_at');
    $hasSetDeadlineAt = $hasColumn($pdo, 'sample_sets', 'rating_deadline_at');
    $ratingDeadlineSelect = ($hasUserDeadlineAt && $hasSetDeadlineAt)
        ? ', COALESCE(uss.rating_deadline_at, ss.rating_deadline_at) AS rating_deadline_at'
        : ($hasUserDeadlineAt
            ? ', uss.rating_deadline_at'
            : ($hasSetDeadlineAt
                ? ', ss.rating_deadline_at AS rating_deadline_at'
                : ', NULL AS rating_deadline_at'));

    $ownership = $pdo->prepare(
        'SELECT uss.sample_set_id' . $ratingDeadlineSelect . '
         FROM user_sample_sets uss
         INNER JOIN sample_sets ss ON ss.id = uss.sample_set_id
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

    $deadlineRaw = (string) ($owned['rating_deadline_at'] ?? '');
    if ($deadlineRaw !== '') {
        $deadline = DateTime::createFromFormat('Y-m-d H:i:s', $deadlineRaw);
        if ($deadline && new DateTime('now') > $deadline) {
            $pdo->rollBack();
            jsonResponse([
                'ok' => false,
                'error' => 'Die Bewertungsfrist fuer dieses Set ist abgelaufen',
            ], 403);
        }
    }

    $sampleSetId = (int) $owned['sample_set_id'];
    $userId = (int) $claims['sub'];

        $duplicateUpdate = 'overall_score = VALUES(overall_score),
            longevity_score = VALUES(longevity_score),
            sillage_score = VALUES(sillage_score)';

        if ($hasRatingUpdatedAt) {
         $duplicateUpdate .= ',
            updated_at = CURRENT_TIMESTAMP';
        }

        $ratingStmt = $pdo->prepare(
         "INSERT INTO sample_set_perfume_ratings (user_id, user_sample_set_id, sample_set_id, perfume_id, overall_score, longevity_score, sillage_score)
          VALUES (:user_id, :user_sample_set_id, :sample_set_id, :perfume_id, :overall_score, :longevity_score, :sillage_score)
          ON DUPLICATE KEY UPDATE
            {$duplicateUpdate}"
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

    if ($hasRatingAnswersTable) {
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
    }

    $countTotalStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM sample_set_items WHERE sample_set_id = :sample_set_id');
    $countTotalStmt->execute(['sample_set_id' => $sampleSetId]);
    $totalPerfumes = (int) (($countTotalStmt->fetch()['total'] ?? 0));

    $countRatedStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM sample_set_perfume_ratings WHERE user_sample_set_id = :user_sample_set_id');
    $countRatedStmt->execute(['user_sample_set_id' => $userSampleSetId]);
    $ratedPerfumes = (int) (($countRatedStmt->fetch()['total'] ?? 0));

    $status = ($totalPerfumes > 0 && $ratedPerfumes >= $totalPerfumes) ? 'completed' : 'delivered';
    $updateSet = $pdo->prepare('UPDATE user_sample_sets SET set_status = :set_status, completed_at = CASE WHEN :completed_status = "completed" THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id = :id');
    $updateSet->execute([
        'set_status' => $status,
        'completed_status' => $status,
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
