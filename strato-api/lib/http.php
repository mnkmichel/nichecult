<?php

declare(strict_types=1);

function setCorsHeaders(array $config): void
{
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = $config['cors']['allow_origins'] ?? [];

    $origin = '*';
    if (is_array($allowedOrigins) && !empty($allowedOrigins)) {
        if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
            $origin = $requestOrigin;
        } else {
            $origin = (string) $allowedOrigins[0];
        }
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');
    header('Content-Type: application/json; charset=utf-8');
}

function handlePreflightAndExit(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON body'], 400);
    }

    return $decoded;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getBearerToken(): ?string
{
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
    ];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            $candidates[] = $headers['Authorization'] ?? '';
            $candidates[] = $headers['authorization'] ?? '';
        }
    }

    foreach ($candidates as $auth) {
        if (preg_match('/^Bearer\s+(.*)$/i', (string) $auth, $matches) === 1) {
            return trim($matches[1]);
        }
    }

    return null;
}
