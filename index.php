<?php

declare(strict_types=1);

/*
 * index.php — front controller for video-player-php.
 *
 * Runs as the router script of PHP's built-in web server:
 *   php -S <hostname>:<port> index.php
 *
 * Serves the server-rendered pages (home, watch, auth screens), the JSON API
 * used by the page JavaScript (upload, delete, media streaming) and the
 * static assets. Every endpoint is rate-limited per client IP.
 */

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/render.php';

apply_security_headers();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

/* ------------------------------------------------------------ static files */

if ($path === '/favicon.svg' || str_starts_with($path, '/assets/')) {
    serve_static_file($path);
    exit;
}

/* ------------------------------------------------------------------- API */

if (str_starts_with($path, '/api/')) {
    dispatch_api($method, $path);
    exit;
}

/* ---------------------------------------------------------------- pages */

if ($method === 'GET') {
    if ($path === '/') {
        rate_limit_apply('pages');
        render_page('Thư viện', 'home', ['active' => 'home']);
    } elseif ($path === '/login') {
        rate_limit_apply('pages');
        render_page('Đăng nhập', 'login', ['active' => 'login']);
    } elseif ($path === '/register') {
        rate_limit_apply('pages');
        render_page('Đăng ký', 'register', ['active' => 'register']);
    } elseif ($path === '/verify') {
        rate_limit_apply('pages');
        render_page('Xác thực email', 'verify');
    } elseif (preg_match('#^/video/([0-9]+)$#', $path, $m)) {
        rate_limit_apply('watch');
        show_watch_page((int)$m[1]);
    } else {
        render_error_page(404, 'Trang không tồn tại.');
    }
} elseif ($method === 'POST') {
    if ($path === '/register') {
        submit_register();
    } elseif ($path === '/login') {
        submit_login();
    } elseif ($path === '/verify') {
        submit_verify();
    } elseif ($path === '/verify/resend') {
        submit_resend();
    } elseif ($path === '/logout') {
        submit_logout();
    } elseif (preg_match('#^/video/([0-9]+)/delete$#', $path, $m)) {
        submit_delete_video((int)$m[1]);
    } else {
        render_error_page(404, 'Trang không tồn tại.');
    }
} else {
    render_error_page(405, 'Phương thức không được hỗ trợ.');
}

/* ------------------------------------------------------------ static file */

function serve_static_file(string $path): void {
    if (str_contains($path, '..')) {
        http_response_code(403);
        echo 'Forbidden';
        return;
    }
    $file = server_root() . $path;
    if (!is_file($file)) {
        http_response_code(404);
        echo 'Not Found';
        return;
    }
    header('Content-Type: ' . mime_for_path($file));
    header('Content-Length: ' . (string)filesize($file));
    header('Cache-Control: public, max-age=3600');
    readfile($file);
}

function render_error_page(int $status, string $message): void {
    http_response_code($status);
    render_page($status . ' ' . $message, 'error', ['status' => $status, 'message' => $message]);
}

/* ------------------------------------------------------------------- pages */

function show_watch_page(int $id): void {
    if ($id <= 0) {
        render_error_page(404, 'Video không tồn tại.');
        return;
    }
    $row = find_video_by_id($id);
    if ($row === null) {
        render_error_page(404, 'Video không tồn tại.');
        return;
    }
    $uid = current_user_id();
    $owner = find_user_by_id((int)$row['user_id']);
    $user = $uid > 0 ? find_user_by_id($uid) : null;
    $canDelete = $uid > 0
        && can($uid, authz_roles_for($user), VideoVoter::DELETE, $row);
    render_page((string)$row['title'], 'watch', [
        'row'       => $row,
        'ownerName' => $owner !== null ? (string)$owner['username'] : '',
        'canDelete' => $canDelete,
        'active'    => 'home',
    ]);
}

function submit_register(): void {
    rate_limit_apply('register');
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $email    = isset($_POST['email']) ? strtolower(trim((string)$_POST['email'])) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    $errors = validate_payload(['username' => $username, 'email' => $email, 'password' => $password], register_constraints());
    if ($errors !== []) {
        redirect('/register?error=' . urlencode((string)reset($errors))
            . '&username=' . urlencode($username) . '&email=' . urlencode($email));
    }
    if (find_user_by_username($username) !== null) {
        redirect('/register?error=' . urlencode('Username đã tồn tại.') . '&username=' . urlencode($username) . '&email=' . urlencode($email));
    }
    if (find_user_by_email($email) !== null) {
        redirect('/register?error=' . urlencode('Email Gmail này đã được dùng để đăng ký.') . '&username=' . urlencode($username) . '&email=' . urlencode($email));
    }
    if (!smtp_configured()) {
        redirect('/register?error=' . urlencode('Chưa cấu hình SMTP nên không thể gửi email xác thực. Vui lòng liên hệ quản trị viên.') . '&username=' . urlencode($username) . '&email=' . urlencode($email));
    }

    $code       = generate_verification_code();
    $expiresAt  = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
    $passwordHash = hash_password($password);
    save_email_verification($username, $email, $passwordHash, hash_verification_code($code), $expiresAt);

    if (!send_verification_email($email, $code)) {
        delete_email_verification($email);
        redirect('/register?error=' . urlencode('Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.') . '&username=' . urlencode($username) . '&email=' . urlencode($email));
    }
    redirect('/verify?email=' . urlencode($email) . '&ok=' . urlencode('Đã gửi mã xác thực tới email của bạn.'));
}

function login_and_set_cookie(int $uid): void {
    $secret = load_media_secret();
    $token  = create_session_token($uid, $secret);
    create_session($uid, $token, (string)(time() + SESSION_TTL_SEC));
    set_session_cookie($token);
}

function submit_login(): void {
    rate_limit_apply('login');
    $identifier = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password   = isset($_POST['password']) ? (string)$_POST['password'] : '';

    $errors = validate_payload(['username' => $identifier, 'password' => $password], login_constraints());
    if ($errors !== []) {
        redirect('/login?error=' . urlencode((string)reset($errors)) . '&username=' . urlencode($identifier));
    }

    $user = find_user_by_identifier($identifier);
    if ($user === null || !verify_password($password, (string)$user['password_hash'])) {
        redirect('/login?error=' . urlencode('Sai Gmail/username hoặc password.') . '&username=' . urlencode($identifier));
    }

    if ((int)$user['email_verified'] !== 1) {
        $email = isset($user['email']) && is_string($user['email']) ? (string)$user['email'] : '';
        if ($email === '') {
            redirect('/login?error=' . urlencode('Tài khoản này chưa có email nên không thể xác thực.'));
        }
        if (!smtp_configured()) {
            redirect('/login?error=' . urlencode('Tài khoản của bạn chưa xác thực email. Hiện máy chủ chưa cấu hình SMTP nên không thể gửi mã xác thực.'));
        }
        $code      = generate_verification_code();
        $expiresAt = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
        save_email_verification((string)$user['username'], $email, (string)$user['password_hash'], hash_verification_code($code), $expiresAt);
        if (!send_verification_email($email, $code)) {
            delete_email_verification($email);
            redirect('/login?error=' . urlencode('Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.'));
        }
        redirect('/verify?email=' . urlencode($email) . '&ok=' . urlencode('Tài khoản chưa xác thực email. Chúng tôi đã gửi mã xác thực tới email của bạn.'));
    }

    login_and_set_cookie((int)$user['id']);
    redirect('/');
}

function submit_verify(): void {
    rate_limit_apply('verify_email');
    $email = isset($_POST['email']) ? strtolower(trim((string)$_POST['email'])) : '';
    $code  = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
    $back  = '/verify?email=' . urlencode($email);

    if ($email === '' || $code === '') {
        redirect($back . '&error=' . urlencode('Thiếu email hoặc mã xác thực.'));
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        redirect($back . '&error=' . urlencode('Mã xác thực phải gồm 6 chữ số.'));
    }

    $pending = find_email_verification($email);
    if ($pending === null) {
        redirect($back . '&error=' . urlencode('Không tìm thấy yêu cầu xác thực cho email này.'));
    }
    if (strtotime((string)$pending['expires_at']) < time()) {
        delete_email_verification($email);
        redirect($back . '&error=' . urlencode('Mã xác thực đã hết hạn. Vui lòng gửi lại mã.'));
    }
    if (!hash_equals((string)$pending['code_hash'], hash_verification_code($code))) {
        redirect($back . '&error=' . urlencode('Mã xác thực không đúng.'));
    }

    $existing = find_user_by_email($email);
    if ($existing !== null) {
        delete_email_verification($email);
        $id = (int)$existing['id'];
        if ((int)$existing['email_verified'] !== 1) {
            mark_user_verified($id);
        }
    } else {
        if (find_user_by_username((string)$pending['username']) !== null) {
            delete_email_verification($email);
            redirect($back . '&error=' . urlencode('Tài khoản đã tồn tại.'));
        }
        $id = create_user((string)$pending['username'], $email, (string)$pending['password_hash']);
        delete_email_verification($email);
        if ($id === null) {
            redirect($back . '&error=' . urlencode('Không thể tạo tài khoản, vui lòng thử lại.'));
        }
    }

    login_and_set_cookie($id);
    redirect('/?ok=' . urlencode('Đăng nhập thành công.'));
}

function submit_resend(): void {
    rate_limit_apply('resend_verification');
    $email = isset($_POST['email']) ? strtolower(trim((string)$_POST['email'])) : '';
    $back  = '/verify?email=' . urlencode($email);

    if ($email === '') {
        redirect('/verify?error=' . urlencode('Thiếu email.'));
    }
    if (!smtp_configured()) {
        redirect($back . '&error=' . urlencode('Chưa cấu hình SMTP nên không thể gửi email xác thực.'));
    }

    $pending = find_email_verification($email);
    if ($pending === null) {
        redirect($back . '&error=' . urlencode('Không tìm thấy yêu cầu xác thực cho email này.'));
    }

    $code      = generate_verification_code();
    $expiresAt = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
    save_email_verification((string)$pending['username'], $email, (string)$pending['password_hash'], hash_verification_code($code), $expiresAt);

    if (!send_verification_email($email, $code)) {
        redirect($back . '&error=' . urlencode('Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.'));
    }
    redirect($back . '&ok=' . urlencode('Đã gửi lại mã xác thực tới email của bạn.'));
}

function submit_logout(): void {
    rate_limit_apply('logout');
    $token = request_cookie(SESSION_COOKIE);
    if ($token !== null) {
        delete_session($token);
    }
    clear_session_cookie();
    redirect('/');
}

function submit_delete_video(int $id): void {
    rate_limit_apply('delete_video');
    $uid = current_user_id();
    if ($uid <= 0) {
        redirect('/login?error=' . urlencode('Vui lòng đăng nhập để xóa video.'));
    }
    $row = find_video_by_id($id);
    if ($row === null) {
        render_error_page(404, 'Video không tồn tại.');
        return;
    }
    $user = find_user_by_id($uid);
    if (!can($uid, authz_roles_for($user), VideoVoter::DELETE, $row)) {
        redirect('/video/' . $id . '?error=' . urlencode('Bạn không có quyền xóa video này.'));
    }
    delete_video($id);
    redirect('/?ok=' . urlencode('Đã xóa video.'));
}

/* ------------------------------------------------------------------- API */

function dispatch_api(string $method, string $path): void {
    if ($path === '/api/health' && $method === 'GET') {
        handle_health();
    } elseif ($path === '/api/me' && $method === 'GET') {
        handle_me();
    } elseif ($path === '/api/register' && $method === 'POST') {
        handle_register();
    } elseif ($path === '/api/verify-email' && $method === 'POST') {
        handle_verify_email();
    } elseif ($path === '/api/resend-verification' && $method === 'POST') {
        handle_resend_verification();
    } elseif ($path === '/api/login' && $method === 'POST') {
        handle_login();
    } elseif ($path === '/api/logout' && $method === 'POST') {
        handle_logout();
    } elseif ($path === '/api/videos' && $method === 'GET') {
        handle_list_videos();
    } elseif ($path === '/api/videos' && $method === 'POST') {
        handle_upload_video();
    } elseif ($path === '/api/media' && $method === 'GET') {
        handle_media();
    } elseif (preg_match('#^/api/videos/([0-9]+)$#', $path, $m) && $method === 'GET') {
        handle_get_video((int)$m[1]);
    } elseif (preg_match('#^/api/videos/([0-9]+)$#', $path, $m) && $method === 'DELETE') {
        handle_delete_video((int)$m[1]);
    } else {
        respond_json(404, err('Not Found'));
    }
}

function handle_health(): void {
    rate_limit_apply('health');
    respond_json(200, ['status' => 'ok', 'uptime' => time()]);
}

function handle_me(): void {
    rate_limit_apply('me');
    $uid = current_user_id();
    if ($uid <= 0) {
        respond_json(401, err('Chưa đăng nhập.'));
        return;
    }
    $user = find_user_by_id($uid);
    if ($user === null) {
        respond_json(404, err('User not found'));
        return;
    }
    respond_json(200, ['user' => [
        'id'       => (int)$user['id'],
        'username' => (string)$user['username'],
        'email'    => (string)$user['email'],
        'role'     => (string)$user['role'],
    ]]);
}

function handle_register(): void {
    rate_limit_apply('register');
    $data = json_body();
    if ($data === []) {
        respond_json(400, err('Body JSON không hợp lệ.'));
        return;
    }
    $errors = validate_payload($data, register_constraints());
    if ($errors !== []) {
        respond_json(400, err((string)reset($errors)));
        return;
    }
    $username = (string)$data['username'];
    $email    = strtolower(trim((string)$data['email']));
    $password = (string)$data['password'];

    if (find_user_by_username($username) !== null) {
        respond_json(409, err('Username đã tồn tại.'));
        return;
    }
    if (find_user_by_email($email) !== null) {
        respond_json(409, err('Email Gmail này đã được dùng để đăng ký.'));
        return;
    }
    if (!smtp_configured()) {
        respond_json(503, err('Chưa cấu hình SMTP nên không thể gửi email xác thực. Vui lòng liên hệ quản trị viên.'));
        return;
    }
    $code       = generate_verification_code();
    $expiresAt  = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
    $passwordHash = hash_password($password);
    save_email_verification($username, $email, $passwordHash, hash_verification_code($code), $expiresAt);

    if (!send_verification_email($email, $code)) {
        delete_email_verification($email);
        respond_json(503, err('Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.'));
        return;
    }
    respond_json(202, ['ok' => true, 'message' => 'Đã gửi mã xác thực tới email của bạn.']);
}

function handle_verify_email(): void {
    rate_limit_apply('verify_email');
    $data = json_body();
    if ($data === []) {
        respond_json(400, err('Body JSON không hợp lệ.'));
        return;
    }
    $email = isset($data['email']) && is_string($data['email']) ? strtolower(trim($data['email'])) : '';
    $code  = isset($data['code']) && is_string($data['code']) ? trim($data['code']) : '';
    if ($email === '' || $code === '') {
        respond_json(400, err('Thiếu email hoặc mã xác thực.'));
        return;
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        respond_json(400, err('Mã xác thực phải gồm 6 chữ số.'));
        return;
    }
    $pending = find_email_verification($email);
    if ($pending === null) {
        respond_json(400, err('Không tìm thấy yêu cầu xác thực cho email này.'));
        return;
    }
    if (strtotime((string)$pending['expires_at']) < time()) {
        delete_email_verification($email);
        respond_json(410, err('Mã xác thực đã hết hạn. Vui lòng gửi lại mã.'));
        return;
    }
    if (!hash_equals((string)$pending['code_hash'], hash_verification_code($code))) {
        respond_json(400, err('Mã xác thực không đúng.'));
        return;
    }
    $existing = find_user_by_email($email);
    if ($existing !== null) {
        delete_email_verification($email);
        $id = (int)$existing['id'];
        if ((int)$existing['email_verified'] !== 1) {
            mark_user_verified($id);
        }
    } else {
        if (find_user_by_username((string)$pending['username']) !== null) {
            delete_email_verification($email);
            respond_json(409, err('Tài khoản đã tồn tại.'));
            return;
        }
        $id = create_user((string)$pending['username'], $email, (string)$pending['password_hash']);
        delete_email_verification($email);
        if ($id === null) {
            respond_json(500, err('Failed to create user'));
            return;
        }
    }
    login_and_set_cookie($id);
    respond_json(200, ['ok' => true, 'user' => ['id' => $id, 'username' => $existing !== null ? (string)$existing['username'] : (string)$pending['username']]]);
}

function handle_resend_verification(): void {
    rate_limit_apply('resend_verification');
    $data = json_body();
    if ($data === []) {
        respond_json(400, err('Body JSON không hợp lệ.'));
        return;
    }
    $email = isset($data['email']) && is_string($data['email']) ? strtolower(trim($data['email'])) : '';
    if ($email === '') {
        respond_json(400, err('Thiếu email.'));
        return;
    }
    if (!smtp_configured()) {
        respond_json(503, err('Chưa cấu hình SMTP nên không thể gửi email xác thực.'));
        return;
    }
    $pending = find_email_verification($email);
    if ($pending === null) {
        respond_json(400, err('Không tìm thấy yêu cầu xác thực cho email này.'));
        return;
    }
    $code      = generate_verification_code();
    $expiresAt = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
    save_email_verification((string)$pending['username'], $email, (string)$pending['password_hash'], hash_verification_code($code), $expiresAt);

    if (!send_verification_email($email, $code)) {
        respond_json(503, err('Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.'));
        return;
    }
    respond_json(200, ['ok' => true, 'message' => 'Đã gửi lại mã xác thực tới email của bạn.']);
}

function handle_login(): void {
    rate_limit_apply('login');
    $data = json_body();
    if ($data === []) {
        respond_json(400, err('Body JSON không hợp lệ.'));
        return;
    }
    $errors = validate_payload($data, login_constraints());
    if ($errors !== []) {
        respond_json(400, err((string)reset($errors)));
        return;
    }
    $identifier = (string)$data['username'];
    $password   = (string)$data['password'];
    $user = find_user_by_identifier($identifier);
    if ($user === null || !verify_password($password, (string)$user['password_hash'])) {
        respond_json(401, err('Sai Gmail/username hoặc password.'));
        return;
    }
    if ((int)$user['email_verified'] !== 1) {
        $email = isset($user['email']) && is_string($user['email']) ? (string)$user['email'] : '';
        if ($email === '') {
            respond_json(403, err('Tài khoản này chưa có email nên không thể xác thực.'));
            return;
        }
        if (!smtp_configured()) {
            respond_json(403, err('Tài khoản của bạn chưa xác thực email. Hiện máy chủ chưa cấu hình SMTP nên không thể gửi mã xác thực.'));
            return;
        }
        $code      = generate_verification_code();
        $expiresAt = date('Y-m-d H:i:s', time() + VERIFICATION_TTL_SEC);
        save_email_verification((string)$user['username'], $email, (string)$user['password_hash'], hash_verification_code($code), $expiresAt);
        if (!send_verification_email($email, $code)) {
            delete_email_verification($email);
            respond_json(503, err('Không thể gửi email xác thực. Vui lòng kiểm tra cấu hình SMTP và thử lại.'));
            return;
        }
        respond_json(403, [
            'code'    => 'EMAIL_NOT_VERIFIED',
            'email'   => $email,
            'message' => 'Tài khoản chưa xác thực email. Chúng tôi đã gửi mã xác thực tới ' . $email . '.',
        ]);
        return;
    }
    login_and_set_cookie((int)$user['id']);
    respond_json(200, ['user' => [
        'id'       => (int)$user['id'],
        'username' => (string)$user['username'],
    ]]);
}

function handle_logout(): void {
    rate_limit_apply('logout');
    $token = request_cookie(SESSION_COOKIE);
    if ($token !== null) {
        delete_session($token);
    }
    clear_session_cookie();
    respond_json(200, ['ok' => true]);
}

function handle_list_videos(): void {
    rate_limit_apply('list_videos');
    $q = $_GET['q'] ?? '';
    $rows = list_all_videos(is_string($q) ? $q : '');
    $uid = current_user_id();
    $viewer = $uid > 0 ? $uid : null;
    $secret = load_media_secret();
    $videos = array_map(static fn(array $row): array => video_json($row, $viewer, $secret), $rows);
    respond_json(200, ['videos' => $videos]);
}

function handle_get_video(int $id): void {
    rate_limit_apply('list_videos');
    if ($id <= 0) {
        respond_json(400, err('ID video không hợp lệ.'));
        return;
    }
    $row = find_video_by_id($id);
    if ($row === null) {
        respond_json(404, err('Video không tồn tại.'));
        return;
    }
    $uid = current_user_id();
    respond_json(200, ['video' => video_json($row, $uid > 0 ? $uid : null, load_media_secret())]);
}

function handle_delete_video(int $id): void {
    rate_limit_apply('delete_video');
    $uid = current_user_id();
    if ($uid <= 0) {
        respond_json(401, err('Chưa đăng nhập.'));
        return;
    }
    if ($id <= 0) {
        respond_json(400, err('ID video không hợp lệ.'));
        return;
    }
    $row = find_video_by_id($id);
    if ($row === null) {
        respond_json(404, err('Video không tồn tại.'));
        return;
    }
    $user = find_user_by_id($uid);
    if (!authz_can($uid, authz_roles_for($user), VideoVoter::DELETE, $row)) {
        respond_json(403, err('Bạn không có quyền xóa video này.'));
        return;
    }
    delete_video($id);
    respond_json(200, ['ok' => true]);
}

function handle_upload_video(): void {
    rate_limit_apply('upload_video');
    $uid = current_user_id();
    if ($uid <= 0) {
        respond_json(401, err('Chưa đăng nhập.'));
        return;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if (stripos($contentType, 'multipart/form-data') === false) {
        respond_json(400, err('Expected multipart/form-data'));
        return;
    }

    $video = $_FILES['video'] ?? $_FILES['file'] ?? null;
    if (!is_array($video)
        || ($video['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || (int)($video['size'] ?? 0) <= 0) {
        respond_json(400, err('Thiếu file video trong request.'));
        return;
    }

    $size = (int)$video['size'];
    if ($size > MAX_UPLOAD_SIZE) {
        respond_json(400, err('File vượt quá giới hạn 1GB.'));
        return;
    }

    $videoContentType = isset($video['type']) && is_string($video['type']) ? $video['type'] : 'video/mp4';
    if (strpos($videoContentType, 'video/') !== 0) {
        respond_json(400, err('File không phải là video.'));
        return;
    }

    $origName = isset($video['name']) && is_string($video['name']) ? $video['name'] : '';
    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
    if ($title === '') {
        $title = $origName !== '' ? $origName : 'Video';
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === '' || strlen($ext) > 12) $ext = 'mp4';

    $upload = upload_dir();
    if (!is_dir($upload)) {
        @mkdir($upload, 0755, true);
    }

    $stored    = generate_uuid() . '.' . $ext;
    $finalPath = $upload . '/' . $stored;
    if (!move_uploaded_file($video['tmp_name'], $finalPath)) {
        respond_json(500, err('Failed to store video'));
        return;
    }

    $thumbStored = null;
    $thumb = $_FILES['thumbnail'] ?? null;
    if (is_array($thumb)
        && ($thumb['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        && (int)($thumb['size'] ?? 0) > 0) {
        $thumbName = generate_uuid() . '.jpg';
        if (move_uploaded_file($thumb['tmp_name'], $upload . '/' . $thumbName)) {
            $thumbStored = $thumbName;
        }
    } else {
        $thumbName = generate_uuid() . '.jpg';
        $thumbPath = $upload . '/' . $thumbName;
        $src = escapeshellarg($finalPath);
        $dst = escapeshellarg($thumbPath);
        exec('ffmpeg -y -ss 00:00:01 -i ' . $src . ' -vframes 1 -q:v 2 ' . $dst . ' 2>/dev/null', $o, $rc);
        if ($rc !== 0) {
            exec('ffmpeg -y -i ' . $src . ' -vframes 1 -q:v 2 ' . $dst . ' 2>/dev/null', $o, $rc);
        }
        if (is_file($thumbPath) && filesize($thumbPath) > 0) {
            $thumbStored = $thumbName;
        } else {
            @unlink($thumbPath);
        }
    }

    $videoId = create_video($uid, $title, $stored, $size, $videoContentType, $thumbStored);
    if ($videoId === null) {
        @unlink($finalPath);
        if ($thumbStored !== null) @unlink($upload . '/' . $thumbStored);
        respond_json(500, err('Failed to create video'));
        return;
    }

    $row = find_video_by_id($videoId) ?? [
        'id' => $videoId, 'user_id' => $uid, 'title' => $title, 'filename' => $stored,
        'size' => $size, 'content_type' => $videoContentType, 'thumbnail_filename' => $thumbStored ?? '',
        'created_at' => 'now',
    ];
    respond_json(201, ['video' => video_json($row, $uid, load_media_secret())]);
}

function handle_media(): void {
    rate_limit_apply('media');
    $token = $_GET['t'] ?? null;
    if (!is_string($token) || $token === '') {
        respond_json(400, err('Missing token'));
        return;
    }

    $filename = mediatoken_verify($token, load_media_secret());
    if ($filename === null) {
        respond_json(403, err('Forbidden'));
        return;
    }

    $path = upload_dir() . '/' . $filename;
    if (!is_file($path)) {
        respond_json(404, err('Not Found'));
        return;
    }

    $size = (int)filesize($path);
    $start = 0;
    $end = $size - 1;
    $hasRange = false;

    $range = $_SERVER['HTTP_RANGE'] ?? null;
    if (is_string($range) && strncmp($range, 'bytes=', 6) === 0) {
        $hasRange = true;
        $spec = substr($range, 6);
        $comma = strpos($spec, ',');
        if ($comma !== false) $spec = substr($spec, 0, $comma);
        $dash = strpos($spec, '-');
        if ($dash !== false) {
            $start = (int)substr($spec, 0, $dash);
            $endStr = substr($spec, $dash + 1);
            if ($endStr !== '') {
                $end = (int)$endStr;
            } else {
                $end = $size - 1;
            }
        } else {
            $start = (int)$spec;
        }
        if ($end < 0 || $end >= $size) $end = $size - 1;
    }

    if ($start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        header('Content-Length: 0');
        return;
    }

    ignore_user_abort(true);
    http_response_code($hasRange ? 206 : 200);
    header('Content-Type: ' . mime_for_path($path));
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($end - $start + 1));
    if ($hasRange) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $fp = @fopen($path, 'rb');
    if ($fp === false) return;
    if ($start > 0) fseek($fp, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $remaining));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($fp);
}

/* ------------------------------------------------------------ wire format */

function video_json(array $row, ?int $viewerId, string $secret): array {
    $thumb = isset($row['thumbnail_filename']) ? (string)$row['thumbnail_filename'] : '';
    return [
        'id'                => (int)$row['id'],
        'user_id'           => (int)$row['user_id'],
        'owner_id'          => (int)$row['user_id'],
        'title'             => (string)$row['title'],
        'filename'          => (string)$row['filename'],
        'url'               => mediatoken_sign((string)$row['filename'], $secret),
        'thumbnail_filename'=> $thumb,
        'thumbnail_url'     => $thumb !== '' ? mediatoken_sign($thumb, $secret) : null,
        'size'              => (int)$row['size'],
        'content_type'      => (string)$row['content_type'],
        'created_at'        => (string)$row['created_at'],
        'is_mine'           => $viewerId !== null && (int)$row['user_id'] === $viewerId,
    ];
}
