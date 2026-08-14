<?php /* upload.php — dedicated upload page. */
?>
<section class="hero">
  <h1>Tải video lên</h1>
  <p class="muted">Chọn file video (tối đa 1GB), tiêu đề và ảnh đại diện tùy chọn.</p>
</section>

<div class="upload-card">
  <form id="upload-form" action="/upload" method="post" enctype="multipart/form-data" novalidate>
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
