<?php /* error.php — shown for 403/404/500 pages. */
$status = $status ?? 404;
$message = $message ?? 'Trang không tồn tại.';
?>
<section class="error">
  <h1><?= (int)$status ?></h1>
  <p><?= e($message) ?></p>
  <a href="/" class="btn btn-primary">Về trang chủ</a>
</section>
