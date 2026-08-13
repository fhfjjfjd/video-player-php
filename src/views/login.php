<?php /* login.php */
$username = isset($_GET['username']) ? (string)$_GET['username'] : '';
?>
<section class="auth">
  <div class="auth-card">
    <h1>Đăng nhập</h1>
    <form method="post" action="/login" class="auth-form" id="login-form">
      <label class="field">
        <span>Gmail hoặc username</span>
        <input type="text" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username">
      </label>
      <label class="field">
        <span>Password</span>
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
    </form>
    <p class="auth-switch">Chưa có tài khoản? <a href="/register">Đăng ký</a></p>
  </div>
</section>
