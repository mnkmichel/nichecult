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
if (!$claims || empty($claims['admin'])) {
    jsonResponse(['ok' => false, 'error' => 'Admin access required'], 403);
}

try {
    $pdo = getPdo($config);

    $stmt = $pdo->query(
        'SELECT
            u.id,
            u.email,
            u.first_name,
            u.last_name,
            u.is_admin,
            u.created_at,
                        COUNT(DISTINCT uss.id) AS sample_count,
                        COALESCE(
                            GROUP_CONCAT(
                                DISTINCT CONCAT(ss.title, " [", uss.set_status, "]")
                                ORDER BY ss.id ASC
                                SEPARATOR "||"
                            ),
                            ""
                        ) AS assigned_sets_summary
         FROM users u
                 LEFT JOIN user_sample_sets uss ON uss.user_id = u.id
                 LEFT JOIN sample_sets ss ON ss.id = uss.sample_set_id
         GROUP BY u.id
         ORDER BY u.created_at DESC'
    );

    jsonResponse([
        'ok' => true,
        'users' => $stmt->fetchAll(),
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'User lookup failed',
        'details' => $e->getMessage(),
    ], 500);
}
