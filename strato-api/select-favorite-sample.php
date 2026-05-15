<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';
require __DIR__ . '/lib/favorite_selection.php';

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

if ($userSampleSetId <= 0 || $perfumeId <= 0) {
	jsonResponse(['ok' => false, 'error' => 'Invalid userSampleSetId or perfumeId'], 400);
}

try {
	$pdo = getPdo($config);
	$userId = (int) $claims['sub'];

	$verifyOwnership = $pdo->prepare(
		'SELECT id
		 FROM user_sample_sets
		 WHERE id = :id AND user_id = :user_id
		 LIMIT 1'
	);
	$verifyOwnership->execute([
		'id' => $userSampleSetId,
		'user_id' => $userId,
	]);

	if (!$verifyOwnership->fetch(PDO::FETCH_ASSOC)) {
		jsonResponse(['ok' => false, 'error' => 'Sample set not found or not owned by user'], 404);
	}

	if (!setFavorite($pdo, $userId, $userSampleSetId, $perfumeId)) {
		jsonResponse(['ok' => false, 'error' => 'Perfume cannot be set as favorite'], 400);
	}

	$state = calculateFavoriteState($pdo, $userId, $userSampleSetId);

	jsonResponse([
		'ok' => true,
		'favorite_id' => $perfumeId,
		'auto_favorite' => false,
		'tied_samples' => $state['tied_samples'],
		'needs_question' => false,
		'highest_score' => $state['highest_score'],
	]);
} catch (Throwable $e) {
	jsonResponse([
		'ok' => false,
		'error' => 'Select favorite sample failed',
		'details' => $e->getMessage(),
	], 500);
}
