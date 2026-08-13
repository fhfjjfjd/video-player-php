<?php

declare(strict_types=1);

/*
 * crypto.php — signed tokens and password hashing for the PHP backend.
 *
 * Token format (compatible with the previous native backend):
 *   <base64url(payload)>.<base64url(HMAC-SHA256(payload))>
 * where the payload is a JSON object {"f":"<value>","e":<expiry_ms>}.
 *
 * Passwords are stored as pbkdf2$<iterations>$<salthex>$<keyhex> using
 * PBKDF2-HMAC-SHA256 (100000 iterations by default). Legacy bcrypt hashes
 * produced by older versions of the app are also accepted.
 */

const MEDIA_TOKEN_TTL_MS = 86400000;        // 1 day
const SESSION_TTL_MS     = 30 * 86400000;   // 30 days
const PBKDF2_ITERATIONS  = 100000;

function generate_verification_code(): string {
    return (string)random_int(100000, 999999);
}

function hash_verification_code(string $code): string {
    return hash_hmac('sha256', $code, load_media_secret());
}

function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string {
    $s = strtr($data, '-_', '+/');
    $s .= str_repeat('=', (4 - (strlen($s) % 4)) % 4);
    $decoded = base64_decode($s, true);
    return $decoded === false ? '' : $decoded;
}

/**
 * Load the media-signing secret: MEDIA_URL_SECRET env var, then the secret
 * file (MEDIA_SECRET_FILE or <root>/.media-secret), else generate and save it.
 */
function load_media_secret(): string {
    $env = getenv('MEDIA_URL_SECRET');
    if (is_string($env) && $env !== '') return $env;

    $file = getenv('MEDIA_SECRET_FILE');
    if (!is_string($file) || $file === '') $file = server_root() . '/.media-secret';

    $existing = @file_get_contents($file);
    if (is_string($existing)) {
        $existing = rtrim($existing, "\r\n");
        if ($existing !== '') return $existing;
    }

    $generated = bin2hex(random_bytes(32));
    @file_put_contents($file, $generated, LOCK_EX);
    @chmod($file, 0600);
    return $generated;
}

/**
 * Sign a value into an HMAC token. Returns "payload.signature".
 */
function mediatoken_sign(string $value, string $secret): string {
    $expiry = (int)(microtime(true) * 1000) + MEDIA_TOKEN_TTL_MS;
    $payload = json_encode(['f' => $value, 'e' => $expiry], JSON_UNESCAPED_SLASHES);
    $b64 = b64url_encode($payload);
    $sig = b64url_encode(hash_hmac('sha256', $b64, $secret, true));
    return $b64 . '.' . $sig;
}

/**
 * Verify a token and return the signed value, or null if invalid/expired.
 * Rejects values that could escape the upload directory.
 */
function mediatoken_verify(string $token, string $secret): ?string {
    $dot = strrpos($token, '.');
    if ($dot === false || $dot === 0 || $dot === strlen($token) - 1) return null;

    $payloadB64 = substr($token, 0, $dot);
    $sigB64 = substr($token, $dot + 1);

    $expected = b64url_encode(hash_hmac('sha256', $payloadB64, $secret, true));
    if (!hash_equals($expected, $sigB64)) return null;

    $json = b64url_decode($payloadB64);
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['f']) || !isset($data['e'])) return null;

    $expiry = (int)$data['e'];
    if ($expiry < (int)(microtime(true) * 1000)) return null;

    $value = (string)$data['f'];
    if ($value === '') return null;
    if (str_contains($value, '/') || str_contains($value, '\\') || str_contains($value, '..')) return null;

    return $value;
}

/**
 * Create a session token for a user. The payload is "<userId>:<expiry_ms>".
 */
function create_session_token(int $userId, string $secret): string {
    $expiry = (int)(microtime(true) * 1000) + SESSION_TTL_MS;
    return mediatoken_sign("{$userId}:{$expiry}", $secret);
}

/**
 * Validate a session token and return the user id, or null.
 */
function validate_session_token(string $token, string $secret): ?int {
    $payload = mediatoken_verify($token, $secret);
    if ($payload === null) return null;

    $colon = strpos($payload, ':');
    if ($colon === false) return null;

    $userId = (int)substr($payload, 0, $colon);
    $expiry = (int)substr($payload, $colon + 1);
    if ($userId <= 0) return null;
    if ($expiry < (int)(microtime(true) * 1000)) return null;

    return $userId;
}

function hash_password(string $password): string {
    $salt = random_bytes(16);
    $key = hash_pbkdf2('sha256', $password, $salt, PBKDF2_ITERATIONS, 32, true);
    return 'pbkdf2$' . PBKDF2_ITERATIONS . '$' . bin2hex($salt) . '$' . bin2hex($key);
}

function verify_password(string $password, string $hash): bool {
    if (str_starts_with($hash, 'pbkdf2$')) {
        $parts = explode('$', $hash);
        if (count($parts) !== 4) return false;
        $iterations = (int)$parts[1];
        $salt = hex2bin($parts[2]);
        $expected = hex2bin($parts[3]);
        if ($iterations <= 0 || $salt === false || $expected === false || strlen($expected) !== 32) return false;
        $key = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
        return hash_equals($key, $expected);
    }
    if (str_starts_with($hash, '$2')) {
        return password_verify($password, $hash);
    }
    return false;
}
