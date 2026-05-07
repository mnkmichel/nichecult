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
if (!$claims || empty($claims['admin'])) {
    jsonResponse(['ok' => false, 'error' => 'Admin access required'], 403);
}

$code = trim((string) ($_POST['code'] ?? ''));
$perfumeName = trim((string) ($_POST['perfume_name'] ?? ''));
$brandName = trim((string) ($_POST['brand_name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'active'));
$assignUserId = (int) ($_POST['assign_user_id'] ?? 0);

if ($code === '' || $perfumeName === '') {
    jsonResponse(['ok' => false, 'error' => 'Code and perfume name are required'], 400);
}

if (!in_array($status, ['active', 'inactive'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid sample status'], 400);
}

function buildBaseUrl(): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'api.nichecult.de';
    return $scheme . '://' . $host;
}

function saveImageUpload(): ?string
{
    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        return null;
    }

    $image = $_FILES['image'];
    if (($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($image['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed');
    }

    $tmpName = (string) ($image['tmp_name'] ?? '');
    if ($tmpName === '') {
        throw new RuntimeException('Temporary upload file missing');
    }

    $mime = mime_content_type($tmpName) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Unsupported image type');
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Could not create upload directory');
    }

    $filename = 'sample-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('Could not move uploaded file');
    }

    return 'uploads/' . $filename;
}

try {
    $pdo = getPdo($config);
    $pdo->beginTransaction();

    $imagePath = saveImageUpload();

    $insert = $pdo->prepare(
        'INSERT INTO samples (code, perfume_name, brand_name, description, image_path, status)
         VALUES (:code, :perfume_name, :brand_name, :description, :image_path, :status)'
    );

    $insert->execute([
        'code' => $code,
        'perfume_name' => $perfumeName,
        'brand_name' => $brandName !== '' ? $brandName : null,
        'description' => $description !== '' ? $description : null,
        'image_path' => $imagePath,
        'status' => $status,
    ]);

    $sampleId = (int) $pdo->lastInsertId();

    if ($assignUserId > 0) {
        $assign = $pdo->prepare(
            "INSERT INTO user_samples (user_id, sample_id, sample_status)
             VALUES (:user_id, :sample_id, 'delivered')
             ON DUPLICATE KEY UPDATE sample_status = 'delivered', rated_at = NULL"
        );
        $assign->execute([
            'user_id' => $assignUserId,
            'sample_id' => $sampleId,
        ]);
    }

    $pdo->commit();

    jsonResponse([
        'ok' => true,
        'sample' => [
            'id' => $sampleId,
            'code' => $code,
            'perfume_name' => $perfumeName,
            'brand_name' => $brandName,
            'description' => $description,
            'status' => $status,
            'image_url' => $imagePath ? buildBaseUrl() . '/' . $imagePath : null,
        ],
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'ok' => false,
        'error' => 'Sample creation failed',
        'details' => $e->getMessage(),
    ], 500);
}
