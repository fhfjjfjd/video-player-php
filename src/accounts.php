<?php

declare(strict_types=1);

/*
 * accounts.php — shared account service.
 *
 * Registration, login, email verification and code resend have exactly the
 * same business logic whether they are reached through the server-rendered
 * forms (submit_* in index.php) or the JSON API (handle_* in index.php).
 * The helpers here are that shared logic. Each returns an array:
 *
 *   ['ok' => true, ...]                          — success
 *   ['need_verify' => true, 'email' => string]   — login OK but email unverified, code sent
 *   ['error' => string, 'status' => int]         — failure (status is the JSON API code)
 *
 * Callers are responsible for validation (validate_payload) and rate limiting.
 */

/**
 * Register a new account. SMTP must be configured; a verification code is
 * emailed to the address and stored pending the /verify step.
 */
function account_register(string $username, string $email, string $password): array {
    if (find_user_by_username($username) !== null) {
        return ['error' => 'Username đã tồn tại.', 'status' => 409];
    }
    if (find_user_by_email($email) !== null) {
        return ['error' => 'Email Gmail này đã được dùng để đăng ký.', 'status' => 409];
    }
    if (!smtp_configured()) {
        return ['error' => 'Chưa cấu hình SMTP nên không thể gửi email xác thực. Vui lòng liên hệ quản trị viên.', 'status' => 503];
    }

    $code       = generate_verification_code();
    $expiresAt  = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
    $passwordHash = hash_password($password);
    save_email_verification($username, $email, $passwordHash, hash_verification_code($code), $expiresAt);

    if (!send_verification_email($email, $code)) {
        delete_email_verification($email);
        return ['error' => 'Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.', 'status' => 503];
    }
    return ['ok' => true, 'email' => $email, 'message' => 'Đã gửi mã xác thực tới email của bạn.'];
}

/**
 * Log a user in. Sets the session cookie on success. If the account was never
 * verified, a fresh code is emailed and ['need_verify' => true] is returned.
 */
function account_login(string $identifier, string $password): array {
    $user = find_user_by_identifier($identifier);
    if ($user === null || !verify_password($password, (string)$user['password_hash'])) {
        return ['error' => 'Sai Gmail/username hoặc password.', 'status' => 401];
    }

    if ((int)$user['email_verified'] !== 1) {
        $email = isset($user['email']) && is_string($user['email']) ? (string)$user['email'] : '';
        if ($email === '') {
            return ['error' => 'Tài khoản này chưa có email nên không thể xác thực.', 'status' => 403];
        }
        if (!smtp_configured()) {
            return ['error' => 'Tài khoản của bạn chưa xác thực email. Hiện máy chủ chưa cấu hình SMTP nên không thể gửi mã xác thực.', 'status' => 403];
        }
        $code      = generate_verification_code();
        $expiresAt = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
        save_email_verification((string)$user['username'], $email, (string)$user['password_hash'], hash_verification_code($code), $expiresAt);
        if (!send_verification_email($email, $code)) {
            delete_email_verification($email);
            return ['error' => 'Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.', 'status' => 503];
        }
        return ['need_verify' => true, 'email' => $email];
    }

    login_and_set_cookie((int)$user['id']);
    return ['ok' => true, 'user' => ['id' => (int)$user['id'], 'username' => (string)$user['username']]];
}

/**
 * Verify a pending registration code, create (or verify) the account and log
 * the user in. Sets the session cookie on success.
 */
function account_verify(string $email, string $code): array {
    $pending = find_email_verification($email);
    if ($pending === null) {
        return ['error' => 'Không tìm thấy yêu cầu xác thực cho email này.', 'status' => 400];
    }
    if (strtotime((string)$pending['expires_at']) < time()) {
        delete_email_verification($email);
        return ['error' => 'Mã xác thực đã hết hạn. Vui lòng gửi lại mã.', 'status' => 410];
    }
    if (!hash_equals((string)$pending['code_hash'], hash_verification_code($code))) {
        return ['error' => 'Mã xác thực không đúng.', 'status' => 400];
    }

    $existing = find_user_by_email($email);
    if ($existing !== null) {
        delete_email_verification($email);
        $id = (int)$existing['id'];
        if ((int)$existing['email_verified'] !== 1) {
            mark_user_verified($id);
        }
        $username = (string)$existing['username'];
    } else {
        if (find_user_by_username((string)$pending['username']) !== null) {
            delete_email_verification($email);
            return ['error' => 'Tài khoản đã tồn tại.', 'status' => 409];
        }
        $id = create_user((string)$pending['username'], $email, (string)$pending['password_hash']);
        delete_email_verification($email);
        if ($id === null) {
            return ['error' => 'Không thể tạo tài khoản, vui lòng thử lại.', 'status' => 500];
        }
        $username = (string)$pending['username'];
    }

    login_and_set_cookie($id);
    return ['ok' => true, 'user' => ['id' => $id, 'username' => $username]];
}

/**
 * Re-send the verification code for a still-pending registration.
 */
function account_resend(string $email): array {
    if (!smtp_configured()) {
        return ['error' => 'Chưa cấu hình SMTP nên không thể gửi email xác thực.', 'status' => 503];
    }
    $pending = find_email_verification($email);
    if ($pending === null) {
        return ['error' => 'Không tìm thấy yêu cầu xác thực cho email này.', 'status' => 400];
    }

    $code      = generate_verification_code();
    $expiresAt = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
    save_email_verification((string)$pending['username'], $email, (string)$pending['password_hash'], hash_verification_code($code), $expiresAt);

    if (!send_verification_email($email, $code)) {
        return ['error' => 'Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.', 'status' => 503];
    }
    return ['ok' => true, 'email' => $email, 'message' => 'Đã gửi lại mã xác thực tới email của bạn.'];
}

/**
 * Create a session row for the user and set the session cookie.
 */
function login_and_set_cookie(int $uid): void {
    $secret = load_media_secret();
    $token  = create_session_token($uid, $secret);
    create_session($uid, $token, (string)(time() + SESSION_TTL_SEC));
    set_session_cookie($token);
}
