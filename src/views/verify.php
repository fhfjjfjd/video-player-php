<?php /* verify.php */
$email = isset($_GET['email']) ? (string)$_GET['email'] : '';
?>
<section class="auth">
  <div class="auth-card">
    <h1>Xác thực email</h1>
    <p class="auth-note">
      Chúng tôi đã gửi mã xác thực gồm 6 chữ số tới <strong><?= e($email) ?></strong>.
      Mã có hiệu lực trong 10 phút.
    </p>
    <form method="post" action="/verify" class="auth-form" id="verify-form" novalidate>
      <input type="hidden" name="email" value="<?= e($email) ?>">
      <label class="field">
        <span>Mã xác thực</span>
        <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
               placeholder="123456" required autofocus autocomplete="one-time-code">
      </label>
      <button type="submit" class="btn btn-primary btn-block">Xác nhận</button>
    </form>
    <form method="post" action="/verify/resend" class="auth-resend" id="resend-form">
      <input type="hidden" name="email" value="<?= e($email) ?>">
      <button type="submit" class="btn-link">Gửi lại mã</button>
    </form>
  </div>
</section>
