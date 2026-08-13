(function () {
  "use strict";

  /* ---------------------------------------------------------- helpers */

  function $(selector, root) {
    return (root || document).querySelector(selector);
  }

  /* -------------------------------------------------- flash auto-hide */

  window.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".flash").forEach(function (el) {
      setTimeout(function () {
        el.style.transition = "opacity 0.6s";
        el.style.opacity = "0";
        setTimeout(function () {
          if (el.parentNode) el.parentNode.removeChild(el);
        }, 600);
      }, 6000);
    });
  });

  /* ----------------------------------------------------- upload modal */

  var modal = $("#upload-modal");
  var openBtn = $("#open-upload");
  var closeBtn = $("#close-upload");

  function openModal() {
    if (!modal) return;
    modal.hidden = false;
    var input = $("#upload-file");
    if (input) input.focus();
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
  }

  if (openBtn) openBtn.addEventListener("click", openModal);
  if (closeBtn) closeBtn.addEventListener("click", closeModal);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeModal();
  });

  if (modal) {
    modal.addEventListener("click", function (e) {
      if (e.target === modal) closeModal();
    });
  }

  /* ------------------------------------------------------ upload form */

  var uploadForm = $("#upload-form");

  function setStatus(message, isError) {
    var el = $("#upload-status");
    if (!el) return;
    el.hidden = false;
    el.textContent = message || "";
    el.className = "form-status" + (isError ? " error" : "");
  }

  function setProgress(percent) {
    var wrap = $("#upload-progress-wrap");
    var bar = $("#upload-progress");
    var label = $("#upload-progress-label");
    if (wrap) wrap.hidden = false;
    if (bar) bar.style.width = percent + "%";
    if (label) label.textContent = Math.round(percent) + "%";
  }

  if (uploadForm) {
    uploadForm.addEventListener("submit", function (e) {
      e.preventDefault();

      var submitBtn = $("#upload-submit");
      var fileInput = $("#upload-file");
      var thumbInput = $("#upload-thumb");

      if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        setStatus("Vui lòng chọn một video.", true);
        return;
      }

      var fd = new FormData();
      fd.append("video", fileInput.files[0]);
      if (thumbInput && thumbInput.files && thumbInput.files.length > 0) {
        fd.append("thumbnail", thumbInput.files[0]);
      }
      var titleInput = $("#upload-title");
      if (titleInput && titleInput.value.trim() !== "") {
        fd.append("title", titleInput.value.trim());
      }

      var xhr = new XMLHttpRequest();
      xhr.open("POST", "/api/videos", true);

      xhr.upload.addEventListener("progress", function (ev) {
        if (ev.lengthComputable) setProgress((ev.loaded / ev.total) * 100);
      });

      xhr.addEventListener("load", function () {
        if (xhr.status >= 200 && xhr.status < 300) {
          window.location.href = "/?ok=" + encodeURIComponent("Đã tải video lên.");
        } else {
          var msg = "Không thể tải video lên. Vui lòng thử lại.";
          try {
            var data = JSON.parse(xhr.responseText);
            if (data && data.error) msg = data.error;
          } catch (err) { /* giữ thông báo mặc định */ }
          setStatus(msg, true);
          setProgress(0);
          if (submitBtn) submitBtn.disabled = false;
        }
      });

      xhr.addEventListener("error", function () {
        setStatus("Mất kết nối trong lúc tải lên. Vui lòng thử lại.", true);
        if (submitBtn) submitBtn.disabled = false;
      });

      if (submitBtn) submitBtn.disabled = true;
      setStatus("");
      setProgress(0);
      xhr.send(fd);
    });
  }

  /* ------------------------------------------------------ HLS player */

  var player = $("#player");
  if (player) {
    var src = player.getAttribute("data-src");
    var isHls = player.getAttribute("data-hls") === "1";

    function playSource() {
      if (isHls && window.Hls && Hls.isSupported()) {
        var hls = new Hls();
        hls.loadSource(src);
        hls.attachMedia(player);
        player.addEventListener("error", function () {
          try { hls.destroy(); } catch (err) { /* noop */ }
        });
      } else if (isHls && player.canPlayType("application/vnd.apple.mpegurl")) {
        player.src = src;
      } else {
        player.src = src;
      }
    }

    playSource();
  }
})();
