<?php

declare(strict_types=1);

/**
 * Returns current favorite decision context for a user sample set.
 * Logic uses only overall_score.
 *
 * @return array{
 *   auto_favorite: bool,
 *   tied_samples: array<int>,
 *   needs_question: bool,
 *   current_favorite_id: ?int,
 *   highest_score: ?int
 * }
 */
function calculateFavoriteState(PDO $pdo, int $userId, int $userSampleSetId): array {
	$ratingStmt = $pdo->prepare(
		'SELECT DISTINCT perfume_id, overall_score
		 FROM sample_set_perfume_ratings
		 WHERE user_id = :user_id
		   AND user_sample_set_id = :user_sample_set_id
		   AND overall_score IS NOT NULL
		 ORDER BY overall_score DESC, perfume_id ASC'
	);
	$ratingStmt->execute([
		'user_id' => $userId,
		'user_sample_set_id' => $userSampleSetId,
	]);
	$rows = $ratingStmt->fetchAll(PDO::FETCH_ASSOC);

	$favoriteStmt = $pdo->prepare(
		'SELECT favorite_perfume_id
		 FROM user_sample_sets
		 WHERE id = :id AND user_id = :user_id
		 LIMIT 1'
	);
	$favoriteStmt->execute([
		'id' => $userSampleSetId,
		'user_id' => $userId,
	]);
	$favoriteRow = $favoriteStmt->fetch(PDO::FETCH_ASSOC);
	$currentFavoriteId = $favoriteRow && $favoriteRow['favorite_perfume_id'] !== null
		? (int) $favoriteRow['favorite_perfume_id']
		: null;

	if (empty($rows)) {
		return [
			'auto_favorite' => false,
			'tied_samples' => [],
			'needs_question' => false,
			'current_favorite_id' => $currentFavoriteId,
			'highest_score' => null,
		];
	}

	$highestScore = (int) $rows[0]['overall_score'];
	$tiedSamples = [];

	foreach ($rows as $row) {
		if ((int) $row['overall_score'] !== $highestScore) {
			break;
		}
		$tiedSamples[] = (int) $row['perfume_id'];
	}

	return [
		'auto_favorite' => count($tiedSamples) === 1,
		'tied_samples' => $tiedSamples,
		'needs_question' => count($tiedSamples) > 1,
		'current_favorite_id' => $currentFavoriteId,
		'highest_score' => $highestScore,
	];
}

function setFavorite(PDO $pdo, int $userId, int $userSampleSetId, int $perfumeId): bool {
	$verifyStmt = $pdo->prepare(
		'SELECT COUNT(*)
		 FROM sample_set_perfume_ratings
		 WHERE user_id = :user_id
		   AND user_sample_set_id = :user_sample_set_id
		   AND perfume_id = :perfume_id'
	);
	$verifyStmt->execute([
		'user_id' => $userId,
		'user_sample_set_id' => $userSampleSetId,
		'perfume_id' => $perfumeId,
	]);

	if ((int) $verifyStmt->fetchColumn() === 0) {
		return false;
	}

	$updateStmt = $pdo->prepare(
		'UPDATE user_sample_sets
		 SET favorite_perfume_id = :favorite_perfume_id
		 WHERE id = :id AND user_id = :user_id'
	);

	return $updateStmt->execute([
		'favorite_perfume_id' => $perfumeId,
		'id' => $userSampleSetId,
		'user_id' => $userId,
	]);
}

function clearFavorite(PDO $pdo, int $userId, int $userSampleSetId): bool {
	$stmt = $pdo->prepare(
		'UPDATE user_sample_sets
		 SET favorite_perfume_id = NULL
		 WHERE id = :id AND user_id = :user_id'
	);

	return $stmt->execute([
		'id' => $userSampleSetId,
		'user_id' => $userId,
	]);
}

function getFavorite(PDO $pdo, int $userId, int $userSampleSetId): ?int {
	$stmt = $pdo->prepare(
		'SELECT favorite_perfume_id
		 FROM user_sample_sets
		 WHERE id = :id AND user_id = :user_id
		 LIMIT 1'
	);
	$stmt->execute([
		'id' => $userSampleSetId,
		'user_id' => $userId,
	]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$row || $row['favorite_perfume_id'] === null) {
		return null;
	}

	return (int) $row['favorite_perfume_id'];
}

/**
 * Re-evaluates favorite whenever a rating changes.
 *
 * Rules:
 * - Unique highest overall_score -> auto assign as favorite.
 * - Tied highest overall_score -> ask tie-breaker question.
 *   If previous favorite is no longer in the top tie group, clear favorite first.
 *
 * @return array{
 *   auto_favorite: bool,
 *   tied_samples: array<int>,
 *   needs_question: bool,
 *   favorite_id: ?int,
 *   highest_score: ?int
 * }
 */
function revalidateFavorite(PDO $pdo, int $userId, int $userSampleSetId): array {
	$state = calculateFavoriteState($pdo, $userId, $userSampleSetId);

	$favoriteId = $state['current_favorite_id'];
	$autoFavorite = false;
	$needsQuestion = $state['needs_question'];

	if ($state['auto_favorite'] && count($state['tied_samples']) === 1) {
		$favoriteId = $state['tied_samples'][0];
		setFavorite($pdo, $userId, $userSampleSetId, $favoriteId);
		$autoFavorite = true;
		$needsQuestion = false;
	} elseif ($state['needs_question']) {
		// If multiple perfumes share the highest score, always ask again.
		if ($favoriteId !== null) {
			clearFavorite($pdo, $userId, $userSampleSetId);
		}

		$favoriteId = null;
		$needsQuestion = true;
	}

	return [
		'auto_favorite' => $autoFavorite,
		'tied_samples' => $state['tied_samples'],
		'needs_question' => $needsQuestion,
		'favorite_id' => $favoriteId,
		'highest_score' => $state['highest_score'],
	];
}
