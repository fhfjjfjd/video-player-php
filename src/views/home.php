<?php /* home.php — library with search and video grid. */
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$rows = list_videos_cached($q);
$uid = current_user_id();
?>
<section class="hero">
  <h1>Thư viện video</h1>
  <form method="get" action="/" class="search-form" id="search-form">
    <input type="search" name="q" id="search-q" placeholder="Tìm kiếm video..." value="<?= e($q) ?>" autocomplete="off">
    <button type="submit" class="btn btn-primary">Tìm</button>
  </form>
</section>

<?php if ($rows === []): ?>
  <div class="empty">
    <?= $q !== '' ? 'Không tìm thấy video nào cho "' . e($q) . '".' : 'Chưa có video nào. Hãy tải lên video đầu tiên!' ?>
  </div>
<?php else: ?>
  <div class="video-grid" id="video-grid">
    <?php foreach ($rows as $row):
        $thumb = (string)($row['thumbnail_filename'] ?? '');
        $isMine = $uid > 0 && (int)$row['user_id'] === $uid;
    ?>
      <a class="video-card" href="/video/<?= (int)$row['id'] ?>">
        <div class="video-card-media">
          <?php if ($thumb !== ''): ?>
            <img src="<?= e(media_url($thumb)) ?>" alt="" loading="lazy" decoding="async">
          <?php else: ?>
            <div class="video-card-nothumb"></div>
          <?php endif; ?>
          <?php if ($isMine): ?><span class="video-card-badge">Của bạn</span><?php endif; ?>
        </div>
        <div class="video-card-body">
          <h3 class="video-card-title"><?= e((string)$row['title']) ?></h3>
          <p class="video-card-meta"><?= e((string)$row['created_at']) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
