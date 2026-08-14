# Nhật ký thay đổi

Mọi thay đổi đáng chú ý của dự án đều được ghi trong file này. Định dạng dựa
theo [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), và quy trình
phát hành được định nghĩa trong `AGENTS.md`.

## [Chưa phát hành]

## [1.2.0] - 2026-08-14

### Thêm mới

- Trang tải video riêng (`/upload`) và trang quản lý "Video của tôi"
  (`/my-videos`): việc tải lên không còn nằm trong cửa sổ modal trên trang
  chủ; trên "Video của tôi" bạn có thể xóa, ẩn hoặc hiện video của mình.
- Tính năng ẩn video: video ẩn rời khỏi thư viện công khai (trang chủ, tìm
  kiếm và JSON API) với mọi người, vẫn hiển thị với chủ sở hữu trên trang
  "Video của tôi", và có thể hiện lại bất cứ lúc nào. Người không phải chủ sở
  hữu nhận mã lỗi 404 ở trang xem, API và cả luồng media
  (`videos.is_hidden`, migration cộng dồn lên `PRAGMA user_version = 2`).
- Ẩn/hiện chỉ dành cho chủ sở hữu giống như xóa, được kiểm tra bằng
  `VideoVoter::HIDE` (vai trò `admin` có thể thao tác trên mọi video).
- Hỗ trợ tác nhân AI: `CLAUDE.md` import `AGENTS.md` cho Claude Code;
  `.agents/rules/general.md` trỏ Google Antigravity (CLI và IDE) tới nó;
  Cline CLI, Codex CLI và OpenCode đọc thẳng `AGENTS.md` (một nguồn quy tắc
  duy nhất).

### Thay đổi

- Quy trình phát hành trong `AGENTS.md` không còn phụ thuộc vào tác nhân phụ
  (subagent): mọi giai đoạn do tác nhân chính thực hiện trực tiếp, và tác nhân
  phụ nhiều nhất chỉ là trợ giúp tùy chọn cho nghiên cứu hoặc xác minh.

### Sửa lỗi

- Xóa video giờ xóa luôn file đã lưu trong `uploads/` cùng với dòng dữ liệu
  trong database — không để lại file mồ côi.

## [1.1.2] - 2026-08-14

### Thay đổi

- Quy trình phát hành trong `AGENTS.md` giờ bao gồm kiểm thử, đánh giá bảo
  mật/kiểm soát lỗi, kiểm tra migration, tác nhân xem lại mã (review), bản
  preview (không ổn định), quy trình rollback và nhật ký thay đổi.

## [1.1.1] - 2026-08-14

### Thay đổi

- README (EN + VI) ghi rõ công nghệ của dự án có thể thay đổi bất cứ lúc nào.

## [1.1.0] - 2026-08-14

### Thêm mới

- Nén gzip cho trang HTML, phản hồi API JSON và file tĩnh dạng văn bản
  (`Vary: Accept-Encoding`); luồng media luôn phát thô để Range request hoạt
  động.
- Cache thư viện video dùng chung 10 giây, bị xóa ngay khi tải lên hoặc xóa
  video.
- Tinh chỉnh SQLite: WAL journaling, `synchronous=NORMAL`, bảng tạm trong RAM,
  chỉ mục tối ưu truy vấn, và migration chạy một lần qua `PRAGMA user_version`.
- Bật OPcache kèm JIT tracing mặc định trong các start script.
- `src/accounts.php`: dịch vụ tài khoản dùng chung cho cả form render phía
  server và JSON API.

### Thay đổi

- Phát media đọc chunk 256 KB thay vì 8 KB.
- `hls.min.js` chỉ được tải trên trang watch phát HLS và được cache một năm.
- Instance Symfony Validator chỉ tạo một lần và tái sử dụng.

## [1.0.0] - 2026-08-13

### Thêm mới

- Bản phát hành đầu tiên: phiên bản thuần PHP kế thừa
  [video-player-bun](https://github.com/fhfjjfjd/video-player-bun) với đăng ký/
  đăng nhập, xác thực email, tải lên và phát video, tìm kiếm, URL riêng cho
  từng video, token media ký, giới hạn yêu cầu theo IP và phân quyền theo vai
  trò.
