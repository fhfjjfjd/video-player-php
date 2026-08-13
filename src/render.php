<?php

declare(strict_types=1);

/*
 * render.php — page rendering helpers for the server-rendered UI.
 */

/** Load the logged-in user array, or null. */
function current_user(): ?array {
    $uid = current_user_id();
    if ($uid <= 0) return null;
    $user = find_user_by_id($uid);
    return $user ?? null;
}

/** Is the current user allowed to $attribute on $subject? */
function can(int $userId, array $roles, string $attribute, mixed $subject): bool {
    return authz_can($userId, $roles, $attribute, $subject);
}

/** Redirect to a URL (default back home) and stop the request. */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Render a full HTML page: layout shell + named view.
 *
 * Available to every view: $title, $user (?array), $active (string),
 * $flashError / $flashOk (from ?error= / ?ok= query params), $base.
 */
function render_page(string $title, string $view, array $data = []): void {
    $user = current_user();
    $base = (string)($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $flashError = isset($_GET['error']) && is_string($_GET['error']) ? (string)$_GET['error'] : '';
    $flashOk    = isset($_GET['ok']) && is_string($_GET['ok']) ? (string)$_GET['ok'] : '';

    extract($data, EXTR_SKIP);
    $title  = $title;
    $active = $data['active'] ?? '';

    include __DIR__ . '/views/layout.php';
}

/** Signed media URL for a stored file (video or thumbnail). */
function media_url(string $filename): string {
    return '/api/media?t=' . mediatoken_sign($filename, load_media_secret());
}
