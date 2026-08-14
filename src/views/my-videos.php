<?php /* my-videos.php — manage your own uploads: delete and hide/unhide. */
?>
<section class="hero">
  <h1>Video của tôi</h1>
  <p class="muted">Chỉ bạn thấy danh sách này. Video đã ẩn sẽ không xuất hiện trên thư viện công khai.</p>
  <a href="/upload" class="btn btn-primary">Tải video lên</a>
</section>

<?php if ($myVideos === []): ?>
  <div class="empty">Bạn chưa tải lên video nào. Hãy tải lên video đầu tiên!</div>
<?php else: ?>
  <div class="my-videos">
    <?php foreach ($myVideos as $row):
        $isHidden = (int)($row['is_hidden'] ?? 0) === 1;
        $thumb = (string)($row['thumbnail_filename'] ?? '');
    ?>
      <article class="video-card<?= $isHidden ? ' is-hidden' : '' ?>">
        <div class="video-card-media">
          <?php if ($thumb !== ''): ?>
            <img src="<?= e(media_url($thumb)) ?>" alt="" loading="lazy" decoding="async">
          <?php else: ?>
            <div class="video-card-nothumb"></div>
          <?php endif; ?>
          <?php if ($isHidden): ?><span class="video-card-badge badge-hidden">Đã ẩn</span><?php endif; ?>
        </div>
        <div class="video-card-body">
          <h3 class="video-card-title"><a href="/video/<?= (int)$row['id'] ?>"><?= e((string)$row['title']) ?></a></h3>
          <p class="video-card-meta"><?= e((string)$row['created_at']) ?> · <?= e(number_format((int)$row['size'] / 1024 / 1024, 1)) ?> MB</p>
          <div class="my-videos-actions">
            <?php if ($isHidden): ?>
              <form method="post" action="/video/<?= (int)$row['id'] ?>/unhide" class="inline-form">
                <button type="submit" class="btn btn-primary">Hiện video</button>
              </form>
            <?php else: ?>
              <form method="post" action="/video/<?= (int)$row['id'] ?>/hide" class="inline-form">
                <button type="submit" class="btn btn-primary">Ẩn video</button>
              </form>
            <?php endif; ?>
            <form method="post" action="/video/<?= (int)$row['id'] ?>/delete" class="inline-form"
                  onsubmit="return confirm('Bạn chắc chắn muốn xóa video này?');">
              <button type="submit" class="btn btn-danger">Xóa</button>
            </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
