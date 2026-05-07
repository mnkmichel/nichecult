<?php

declare(strict_types=1);

function saveUploadedImage(string $fieldName = 'image'): ?string
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $image = $_FILES[$fieldName];
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

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Could not create upload directory');
    }

    $filename = 'img-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('Could not move uploaded file');
    }

    return 'uploads/' . $filename;
}

function currentBaseUrl(): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'api.nichecult.de';

    return $scheme . '://' . $host;
}

function publicAssetUrl(?string $path): ?string
{
    if (!$path) {
        return null;
    }

    return currentBaseUrl() . '/' . ltrim($path, '/');
}
