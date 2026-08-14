<?php /* home.php — library with search, video grid and upload modal. */
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$rows = list_videos_cached($q);
$secret = load_media_secret();
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

<?php if ($user !== null): ?>
<div class="modal-backdrop" id="upload-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="upload-title">
    <div class="modal-head">
      <h2 id="upload-title">Tải video lên</h2>
      <button type="button" class="modal-close" id="close-upload" aria-label="Đóng">×</button>
    </div>
    <form id="upload-form" novalidate>
      <label class="field">
        <span>Chọn video (tối đa 1GB)</span>
        <input type="file" name="video" id="upload-file" accept="video/*" required>
      </label>
      <label class="field">
        <span>Ảnh đại diện (tùy chọn)</span>
        <input type="file" name="thumbnail" id="upload-thumb" accept="image/*">
      </label>
      <label class="field">
        <span>Tiêu đề</span>
        <input type="text" name="title" id="upload-title" maxlength="200" placeholder="Tên video...">
      </label>
      <div class="upload-progress" id="upload-progress-wrap" hidden>
        <div class="upload-progress-bar"><div class="upload-progress-fill" id="upload-progress"></div></div>
        <span class="upload-progress-label" id="upload-progress-label">0%</span>
      </div>
      <p class="form-status" id="upload-status" hidden></p>
      <button type="submit" class="btn btn-primary btn-block" id="upload-submit">Tải lên</button>
    </form>
  </div>
</div>
<?php endif; ?>
