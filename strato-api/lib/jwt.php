<?php

declare(strict_types=1);

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string
{
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }

    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return $decoded === false ? '' : $decoded;
}

function createJwt(array $claims, string $secret): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $encodedHeader = base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $encodedPayload = base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));

    $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true);
    $encodedSignature = base64UrlEncode($signature);

    return $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;
}

function verifyJwt(string $token, string $secret): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

    $expected = base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true));
    if (!hash_equals($expected, $encodedSignature)) {
        return null;
    }

    $payloadJson = base64UrlDecode($encodedPayload);
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        return null;
    }

    $now = time();
    if (isset($payload['exp']) && is_numeric($payload['exp']) && (int) $payload['exp'] < $now) {
        return null;
    }

    return $payload;
}
