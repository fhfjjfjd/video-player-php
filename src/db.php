<?php

declare(strict_types=1);

/*
 * db.php — SQLite storage layer (PDO).
 *
 * Schema is identical to the previous native backend so an existing
 * data.db keeps working after the upgrade.
 */

function db_path(): string {
    $p = getenv('DATABASE_PATH');
    return is_string($p) && $p !== '' ? $p : server_root() . '/data.db';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . db_path());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        db_init($pdo);
    }
    return $pdo;
}

function db_init(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users ('
        . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
        . 'username TEXT NOT NULL UNIQUE,'
        . 'password_hash TEXT NOT NULL,'
        . 'email TEXT,'
        . 'created_at TEXT NOT NULL DEFAULT (datetime(\'now\')));'
    );
    $userCols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
    $hasVerified = false;
    $hasRole = false;
    foreach ($userCols as $c) {
        $name = strtolower((string)$c['name']);
        if ($name === 'email_verified') {
            $hasVerified = true;
        } elseif ($name === 'role') {
            $hasRole = true;
        }
    }
    if (!$hasVerified) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 0');
    }
    if (!$hasRole) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'");
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sessions ('
        . 'token TEXT PRIMARY KEY,'
        . 'user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,'
        . 'created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),'
        . 'expires_at TEXT NOT NULL);'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS videos ('
        . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
        . 'user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,'
        . 'title TEXT NOT NULL,'
        . 'filename TEXT NOT NULL UNIQUE,'
        . 'size INTEGER NOT NULL,'
        . 'content_type TEXT NOT NULL,'
        . 'thumbnail_filename TEXT,'
        . 'created_at TEXT NOT NULL DEFAULT (datetime(\'now\')));'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS feedback ('
        . 'id TEXT PRIMARY KEY,'
        . 'type TEXT NOT NULL,'
        . 'title TEXT NOT NULL,'
        . 'body TEXT NOT NULL,'
        . 'status TEXT NOT NULL DEFAULT \'open\','
        . 'created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),'
        . 'author TEXT);'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS email_verifications ('
        . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
        . 'username TEXT NOT NULL,'
        . 'email TEXT NOT NULL,'
        . 'password_hash TEXT NOT NULL,'
        . 'code_hash TEXT NOT NULL,'
        . 'expires_at TEXT NOT NULL,'
        . 'created_at TEXT NOT NULL DEFAULT (datetime(\'now\')));'
    );
}

function create_user(string $username, string $email, string $hash): ?int {
    try {
        $st = db()->prepare('INSERT INTO users (username, email, password_hash, email_verified) VALUES (?, ?, ?, 1)');
        $st->execute([$username, $email, $hash]);
        return (int)db()->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}

function mark_user_verified(int $userId): void {
    try {
        $st = db()->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
        $st->execute([$userId]);
    } catch (PDOException $e) {
        // bỏ qua — lỗi ghi không làm hỏng luồng xác thực.
    }
}

function find_user_by_username(string $username): ?array {
    $st = db()->prepare('SELECT id, username, email, password_hash, email_verified, role FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function find_user_by_email(string $email): ?array {
    $st = db()->prepare('SELECT id, username, email, password_hash, email_verified, role FROM users WHERE lower(email) = lower(?)');
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function find_user_by_identifier(string $identifier): ?array {
    if (str_contains($identifier, '@')) {
        $st = db()->prepare('SELECT id, username, email, password_hash, email_verified, role FROM users WHERE lower(email) = lower(?)');
    } else {
        $st = db()->prepare('SELECT id, username, email, password_hash, email_verified, role FROM users WHERE username = ?');
    }
    $st->execute([$identifier]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function find_user_by_id(int $userId): ?array {
    $st = db()->prepare('SELECT id, username, email, email_verified, role FROM users WHERE id = ?');
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function save_email_verification(string $username, string $email, string $passwordHash, string $codeHash, string $expiresAt): void {
    try {
        $st = db()->prepare('DELETE FROM email_verifications WHERE lower(email) = lower(?)');
        $st->execute([$email]);
        $st = db()->prepare('INSERT INTO email_verifications (username, email, password_hash, code_hash, expires_at) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$username, $email, $passwordHash, $codeHash, $expiresAt]);
    } catch (PDOException $e) {
        // bỏ qua lỗi ghi — luồng xác thực sẽ báo thất bại ở bước gửi email.
    }
}

function find_email_verification(string $email): ?array {
    $st = db()->prepare('SELECT id, username, email, password_hash, code_hash, expires_at FROM email_verifications WHERE lower(email) = lower(?)');
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function delete_email_verification(string $email): void {
    try {
        $st = db()->prepare('DELETE FROM email_verifications WHERE lower(email) = lower(?)');
        $st->execute([$email]);
    } catch (PDOException $e) {
        // bỏ qua.
    }
}

function create_session(int $userId, string $token, string $expiresAt): void {
    try {
        $st = db()->prepare('INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, ?)');
        $st->execute([$token, $userId, $expiresAt]);
    } catch (PDOException $e) {
        // Session rows are informational only (auth is token-based); ignore errors.
    }
}

function find_user_by_session_token(string $token): ?int {
    $st = db()->prepare("SELECT u.id FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.token = ? AND s.expires_at > datetime('now')");
    $st->execute([$token]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int)$id;
}

function delete_session(string $token): void {
    $st = db()->prepare('DELETE FROM sessions WHERE token = ?');
    $st->execute([$token]);
}

function create_video(int $userId, string $title, string $filename, int $size, string $contentType, ?string $thumb): ?int {
    try {
        $st = db()->prepare('INSERT INTO videos (user_id, title, filename, size, content_type, thumbnail_filename) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([$userId, $title, $filename, $size, $contentType, $thumb]);
        return (int)db()->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}

function list_all_videos(string $query): array {
    if ($query !== '') {
        $st = db()->prepare("SELECT id, user_id, title, filename, size, content_type, thumbnail_filename, created_at FROM videos WHERE title LIKE '%' || ? || '%' ESCAPE '\\' ORDER BY created_at DESC, id DESC");
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $st->execute([$escaped]);
    } else {
        $st = db()->query('SELECT id, user_id, title, filename, size, content_type, thumbnail_filename, created_at FROM videos ORDER BY created_at DESC, id DESC');
    }
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function find_video_by_id(int $id): ?array {
    $st = db()->prepare('SELECT id, user_id, title, filename, size, content_type, thumbnail_filename, created_at FROM videos WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function find_video_by_id_and_user(int $id, int $userId): ?array {
    $st = db()->prepare('SELECT id, user_id, title, filename, size, content_type, thumbnail_filename, created_at FROM videos WHERE id = ? AND user_id = ?');
    $st->execute([$id, $userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function find_video_by_filename(string $filename): ?array {
    $st = db()->prepare('SELECT id, user_id, title, filename, size, content_type, thumbnail_filename, created_at FROM videos WHERE filename = ?');
    $st->execute([$filename]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function delete_video(int $id): void {
    $st = db()->prepare('DELETE FROM videos WHERE id = ?');
    $st->execute([$id]);
}
