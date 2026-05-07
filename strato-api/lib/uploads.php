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
        $errorCode = (int) ($image['error'] ?? UPLOAD_ERR_OK);
        $errorMap = [
            UPLOAD_ERR_INI_SIZE => 'Image upload failed: file exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Image upload failed: file exceeds form size limit',
            UPLOAD_ERR_PARTIAL => 'Image upload failed: partial upload',
            UPLOAD_ERR_NO_TMP_DIR => 'Image upload failed: missing temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Image upload failed: cannot write file to disk',
            UPLOAD_ERR_EXTENSION => 'Image upload failed: blocked by PHP extension',
        ];

        throw new RuntimeException($errorMap[$errorCode] ?? 'Image upload failed');
    }

    $tmpName = (string) ($image['tmp_name'] ?? '');
    if ($tmpName === '') {
        throw new RuntimeException('Temporary upload file missing');
    }

    $mime = '';
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmpName) ?: '';
    }
    if ($mime === '' && class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpName);
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $originalName = strtolower((string) ($image['name'] ?? ''));
    $originalExtension = pathinfo($originalName, PATHINFO_EXTENSION);
    $extensionByName = [
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'webp' => 'webp',
    ];

    $resolvedExtension = $extensions[$mime] ?? ($extensionByName[$originalExtension] ?? null);

    if ($resolvedExtension === null) {
        throw new RuntimeException('Unsupported image type');
    }

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Could not create upload directory');
    }

    $filename = 'img-' . bin2hex(random_bytes(8)) . '.' . $resolvedExtension;
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
