<?php

declare(strict_types=1);

/*
 * bootstrap.php — runtime configuration, shared helpers and rate limiting
 * for the video-player-php app. Required by the front controller (index.php)
 * before any request is dispatched.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', '0');
ini_set('max_execution_time', '600');
@ini_set('upload_max_filesize', '1100M');
@ini_set('post_max_size', '1100M');
@ini_set('memory_limit', '512M');

const SESSION_COOKIE   = 'session';
const SESSION_TTL_SEC  = 2592000;            // 30 days
const MAX_UPLOAD_SIZE  = 1024 * 1024 * 1024; // 1GB
const VERIFICATION_TTL_SEC = 600;            // 10 minutes

const RATE_LIMIT_WINDOW_SEC = 60;
const RATE_LIMIT_DEFAULT    = 120;           // fallback: 120 req / min / IP
const RATE_LIMIT_ROUTES = [
    'health'            => [0, 60],          // 0 = unlimited
    'register'          => [5, 60],
    'verify_email'      => [10, 60],
    'resend_verification' => [3, 60],
    'login'             => [10, 60],
    'logout'            => [0, 60],
    'me'                => [0, 60],
    'list_videos'       => [0, 60],
    'upload_video'      => [5, 60],
    'delete_video'      => [30, 60],
    'media'             => [1200, 60],       // generous — player issues many Range requests
    'home'              => [0, 60],
    'watch'             => [0, 60],
    'pages'             => [0, 60],
];

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/authz.php';
require_once __DIR__ . '/accounts.php';

/* ------------------------------------------------------------------ paths */

function server_root(): string {
    return dirname(__DIR__);
}

function upload_dir(): string {
    $d = getenv('UPLOAD_DIR');
    return is_string($d) && $d !== '' ? $d : server_root() . '/uploads';
}

/* ---------------------------------------------------------------- SMTP env */

function smtp_env(string $key, string $default): string {
    $v = getenv($key);
    return is_string($v) && $v !== '' ? $v : $default;
}

function smtp_host(): string {
    return smtp_env('MAIL_HOST', 'smtp.gmail.com');
}

function smtp_port(): int {
    return (int)smtp_env('MAIL_PORT', '587');
}

function smtp_user(): string {
    return smtp_env('MAIL_USER', '');
}

function smtp_pass(): string {
    return smtp_env('MAIL_PASS', '');
}

function smtp_from(): string {
    return smtp_env('MAIL_FROM', '');
}

function smtp_encryption(): string {
    return smtp_env('MAIL_ENCRYPTION', 'tls');
}

function smtp_configured(): bool {
    return smtp_user() !== '' && smtp_pass() !== '';
}

/* ---------------------------------------------------------------- helpers */

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mime_for_path(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'html': return 'text/html';
        case 'css':  return 'text/css';
        case 'js':   return 'application/javascript';
        case 'json':
        case 'map':  return 'application/json';
        case 'png':  return 'image/png';
        case 'jpg':
        case 'jpeg': return 'image/jpeg';
        case 'gif':  return 'image/gif';
        case 'svg':  return 'image/svg+xml';
        case 'ico':  return 'image/x-icon';
        case 'woff': return 'font/woff';
        case 'woff2':return 'font/woff2';
        case 'ttf':  return 'font/ttf';
        case 'mp4':  return 'video/mp4';
        case 'webm': return 'video/webm';
        case 'mkv':  return 'video/x-matroska';
        case 'avi':  return 'video/x-msvideo';
        case 'm3u8': return 'application/vnd.apple.mpegurl';
        case 'mpd':  return 'application/dash+xml';
        case 'mp3':  return 'audio/mpeg';
        case 'wav':  return 'audio/wav';
        case 'ogg':  return 'audio/ogg';
        case 'pdf':  return 'application/pdf';
        case 'zip':  return 'application/zip';
        case 'gz':   return 'application/gzip';
        case 'wasm': return 'application/wasm';
        case 'txt':  return 'text/plain';
        case 'xml':  return 'application/xml';
    }
    return 'application/octet-stream';
}

function apply_security_headers(): void {
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; media-src \'self\' blob:; font-src \'self\' data:; connect-src \'self\'; object-src \'none\'; base-uri \'self\'; frame-ancestors \'none\'; form-action \'self\'');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function err(string $message): array {
    return ['error' => $message];
}

/* ------------------------------------------------------------------- gzip */

/**
 * Does the client advertise gzip in Accept-Encoding?
 */
function accept_gzip(): bool {
    $enc = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    if ($enc === '') return false;
    foreach (explode(',', $enc) as $part) {
        $part = strtolower(trim(explode(';', $part)[0]));
        if ($part === 'gzip') return true;
    }
    return false;
}

/**
 * Is this MIME type worth gzip-compressing? Binary media (video, images,
 * already-compressed fonts) is left untouched.
 */
function mime_is_compressible(string $mime): bool {
    return str_starts_with($mime, 'text/')
        || $mime === 'application/javascript'
        || $mime === 'application/json'
        || $mime === 'application/xml'
        || $mime === 'image/svg+xml'
        || $mime === 'application/wasm';
}

/**
 * Wrap the rest of the response in ob_gzhandler. Only started when the client
 * accepts gzip and the zlib extension exists. ob_gzhandler sets
 * Content-Encoding/Vary itself; callers must NOT set Content-Length while a
 * gzip buffer is open (the server falls back to chunked transfer).
 */
function begin_gzip(): void {
    if (!accept_gzip() || !function_exists('ob_gzhandler')) return;
    header('Vary: Accept-Encoding');
    ob_start('ob_gzhandler');
}

function respond_json(int $status, $data): void {
    $body = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $status = 500;
        $body   = '{"error":"Internal Server Error"}';
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if (ob_get_level() === 0) {
        header('Content-Length: ' . strlen($body));
    }
    echo $body;
}

function request_cookie(string $name): ?string {
    $cookies = $_SERVER['HTTP_COOKIE'] ?? '';
    if ($cookies === '') return null;
    foreach (explode(';', $cookies) as $pair) {
        $pair = trim($pair);
        $eq = strpos($pair, '=');
        if ($eq === false) continue;
        if (strcasecmp(substr($pair, 0, $eq), $name) === 0) {
            return substr($pair, $eq + 1);
        }
    }
    return null;
}

function set_session_cookie(string $token): void {
    header('Set-Cookie: ' . SESSION_COOKIE . '=' . $token . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=' . SESSION_TTL_SEC);
}

function clear_session_cookie(): void {
    header('Set-Cookie: ' . SESSION_COOKIE . '=; Path=/; Max-Age=0');
}

function current_user_id(): int {
    $token = request_cookie(SESSION_COOKIE);
    if ($token === null || $token === '') return 0;
    $uid = validate_session_token($token, load_media_secret());
    return $uid ?? 0;
}

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function generate_uuid(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $hex = bin2hex($b);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-'
        . substr($hex, 20, 12);
}

/* --------------------------------------------------------------- rate limit */

function rate_limiter_cache_dir(): string {
    return server_root() . '/cache/rate-limiter';
}

function rate_limiter_factory_for(string $bucket): Symfony\Component\RateLimiter\RateLimiterFactory {
    static $factories = [];
    if (isset($factories[$bucket])) return $factories[$bucket];

    $cfg = RATE_LIMIT_ROUTES[$bucket] ?? [RATE_LIMIT_DEFAULT, RATE_LIMIT_WINDOW_SEC];
    [$limit, $window] = $cfg;

    return $factories[$bucket] = new Symfony\Component\RateLimiter\RateLimiterFactory(
        [
            'id'       => 'rl_' . $bucket,
            'policy'   => 'fixed_window',
            'limit'    => $limit,
            'interval' => $window . ' seconds',
        ],
        new Symfony\Component\RateLimiter\Storage\CacheStorage(
            new Symfony\Component\Cache\Adapter\FilesystemAdapter(
                'ratelimiter',
                0,
                rate_limiter_cache_dir()
            )
        )
    );
}

function rate_limit_apply(string $bucket): void {
    $cfg = RATE_LIMIT_ROUTES[$bucket] ?? [RATE_LIMIT_DEFAULT, RATE_LIMIT_WINDOW_SEC];
    [$limit, $window] = $cfg;
    if ($limit <= 0) return;

    $result  = rate_limiter_factory_for($bucket)->create(client_ip())->consume(1);

    $now    = time();
    $reset  = intdiv($now, $window) * $window + $window;
    header('X-RateLimit-Limit: ' . $limit);
    header('X-RateLimit-Remaining: ' . $result->getRemainingTokens());
    header('X-RateLimit-Reset: ' . $reset);

    if ($result->isAccepted()) return;

    $retryAfter = max(1, $reset - $now);
    header('Retry-After: ' . $retryAfter);
    respond_json(429, err('Quá nhiều yêu cầu. Vui lòng thử lại sau ' . $retryAfter . ' giây.'));
    exit;
}

function json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
