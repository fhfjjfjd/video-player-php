<?php /* watch.php — video player page. */
$src = media_url($row['filename']);
$isHls = stripos((string)$row['filename'], '.m3u8') !== false || stripos((string)$row['content_type'], 'mpegurl') !== false;
$loadHls = $isHls;
$thumb = isset($row['thumbnail_filename']) ? (string)$row['thumbnail_filename'] : '';
?>
<section class="watch">
  <div class="player-shell">
    <video id="player" class="player" controls preload="metadata" playsinline
           data-src="<?= e($src) ?>" data-hls="<?= $isHls ? '1' : '0' ?>"
           <?= $thumb !== '' ? 'poster="' . e(media_url($thumb)) . '"' : '' ?>></video>
  </div>
  <div class="watch-info">
    <h1 class="watch-title"><?= e((string)$row['title']) ?></h1>
    <p class="watch-meta">
      <?= e($ownerName !== '' ? $ownerName : 'Người dùng') ?> ·
      <?= e((string)$row['created_at']) ?> ·
      <?= e(number_format((int)$row['size'] / 1024 / 1024, 1)) ?> MB
    </p>
    <?php if ((int)($row['is_hidden'] ?? 0) === 1): ?>
      <p class="watch-hidden-note">Video này đang bị ẩn — chỉ bạn và quản trị viên xem được.</p>
    <?php endif; ?>
    <?php if ($canDelete): ?>
      <form method="post" action="/video/<?= (int)$row['id'] ?>/delete" class="watch-actions"
            onsubmit="return confirm('Bạn chắc chắn muốn xóa video này?');">
        <button type="submit" class="btn btn-danger">Xóa video</button>
      </form>
    <?php endif; ?>
  </div>
</section>
