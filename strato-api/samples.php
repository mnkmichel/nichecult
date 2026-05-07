<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/jwt.php';

setCorsHeaders($config);
handlePreflightAndExit();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
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

try {
    $pdo = getPdo($config);
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    $baseUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'api.nichecult.de');

    $stmt = $pdo->prepare(
        'SELECT
            us.id AS user_sample_id,
            us.sample_status,
            us.assigned_at,
            us.rated_at,
            s.id AS sample_id,
            s.code,
            s.perfume_name,
            s.brand_name,
            s.description,
            s.image_path
         FROM user_samples us
         INNER JOIN samples s ON s.id = us.sample_id
         WHERE us.user_id = :user_id
         ORDER BY us.assigned_at DESC'
    );

    $stmt->execute(['user_id' => (int) $claims['sub']]);
    $samples = array_map(
        static function (array $sample) use ($baseUrl): array {
            $sample['image_url'] = !empty($sample['image_path'])
                ? $baseUrl . '/' . ltrim((string) $sample['image_path'], '/')
                : null;

            return $sample;
        },
        $stmt->fetchAll()
    );

    jsonResponse([
        'ok' => true,
        'samples' => $samples,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Sample lookup failed',
        'details' => $e->getMessage(),
    ], 500);
}
