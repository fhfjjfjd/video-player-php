<?php /* layout.php — HTML shell shared by all pages. */ ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#031016">
<title><?= e($title) ?> · Video Player</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="site-header">
  <div class="nav-inner">
    <a class="brand" href="/">
      <svg class="brand-mark" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
      <span class="brand-name">Video Player</span>
    </a>
    <nav class="nav-links">
      <a href="/" class="nav-link<?= $active === 'home' ? ' active' : '' ?>">Trang chủ</a>
      <?php if ($user !== null): ?>
        <button type="button" class="nav-link btn-ghost" id="open-upload">Tải lên</button>
        <span class="nav-user"><?= e((string)$user['username']) ?></span>
        <form method="post" action="/logout" class="inline-form">
          <button type="submit" class="nav-link btn-ghost">Đăng xuất</button>
        </form>
      <?php else: ?>
        <a href="/login" class="nav-link<?= $active === 'login' ? ' active' : '' ?>">Đăng nhập</a>
        <a href="/register" class="nav-link<?= $active === 'register' ? ' active' : '' ?>">Đăng ký</a>
      <?php endif; ?>
      <a class="nav-link" href="https://github.com/fhfjjfjd/video-player-bun" target="_blank" rel="noopener">Nguồn</a>
      <a class="nav-link" href="https://github.com/fhfjjfjd/video-player-bun/issues" target="_blank" rel="noopener">Góp ý</a>
    </nav>
  </div>
</header>

<?php if ($flashError !== ''): ?><div class="flash flash-error"><?= e($flashError) ?></div><?php endif; ?>
<?php if ($flashOk !== ''): ?><div class="flash flash-ok"><?= e($flashOk) ?></div><?php endif; ?>

<main class="page">
  <?php include __DIR__ . '/' . $view . '.php'; ?>
</main>

<script src="/assets/js/app.js"></script>
<?php if ($view === 'watch' && !empty($loadHls)): ?>
<script src="/assets/js/hls.min.js"></script>
<?php endif; ?>
</body>
</html>
