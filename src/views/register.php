<?php /* register.php */
$username = isset($_GET['username']) ? (string)$_GET['username'] : '';
$email    = isset($_GET['email']) ? (string)$_GET['email'] : '';
?>
<section class="auth">
  <div class="auth-card">
    <h1>Đăng ký</h1>
    <form method="post" action="/register" class="auth-form" id="register-form" novalidate>
      <label class="field">
        <span>Username</span>
        <input type="text" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username"
               pattern="[A-Za-z0-9_]{3,32}">
        <small>3–32 ký tự chữ, số hoặc gạch dưới.</small>
      </label>
      <label class="field">
        <span>Gmail</span>
        <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
        <small>Phải là tài khoản Gmail (…@gmail.com) — mã xác thực sẽ được gửi tới đây.</small>
      </label>
      <label class="field">
        <span>Password</span>
        <input type="password" name="password" required autocomplete="new-password" minlength="6">
        <small>Ít nhất 6 ký tự.</small>
      </label>
      <button type="submit" class="btn btn-primary btn-block">Đăng ký</button>
    </form>
    <p class="auth-switch">Đã có tài khoản? <a href="/login">Đăng nhập</a></p>
  </div>
</section>
