# Video Player (PHP)

**English** | [Tiếng Việt](./README.vi.md)

Phiên bản kế thừa đang phát triển, render phía server, của
[video-player-bun](https://github.com/fhfjjfjd/video-player-bun). Đăng ký, đăng
nhập, tải video lên, xem video trực tuyến, tìm kiếm và chia sẻ video qua URL
riêng. Toàn bộ ứng dụng thuần PHP — không cần Node, không bước build, không
phải cài database riêng.

## Tính năng

- Xem video công khai mà không cần đăng nhập
- Đăng ký / đăng nhập để tải video lên và quản lý video của mình (đăng ký bắt buộc có email Gmail — email phải kết thúc bằng `@gmail.com`; đăng nhập nhận Gmail hoặc username)
- Xác thực email khi đăng ký: đăng ký sẽ gửi một mã xác thực gồm 6 chữ số tới địa chỉ Gmail, phải nhập mã này trên màn hình xác nhận trước khi tài khoản được tạo (mã có hiệu lực 10 phút, hỗ trợ gửi lại mã)
- Các tài khoản cũ chưa từng xác thực phải xác thực email khi đăng nhập
- Chỉ chủ sở hữu mới xóa được video của mình — quyền được kiểm tra bằng Symfony Security (voter + access decision manager), và tài khoản có vai trò `admin` trong database (cột `users.role`) có thể xóa mọi video
- Hình ảnh thu nhỏ (thumbnail): tự động trích xuất bằng FFmpeg khi tải lên, hoặc ảnh tùy chỉnh
- Giao diện dark kiểu streaming đáp ứng với nhận diện thương hiệu gradient emerald → teal → cyan
- Mỗi video có URL riêng (`/video/:id`) để chia sẻ
- Nhiều thiết bị có thể cùng xem một video cùng lúc — backend chạy nhiều tiến trình worker PHP nên luồng của người xem này không bao giờ chặn người xem khác
- Server không bao giờ lộ URL media trực tiếp — API trả token media ký HMAC có thời hạn ngắn, player phát qua `/api/media?t=<token>` (hỗ trợ Range request)
- Tăng cường bảo mật: Content-Security-Policy, `X-Content-Type-Options`, `X-Frame-Options` và các header bảo mật khác trên mọi request
- Giới hạn số lượng yêu cầu (rate limiting) theo IP cho mọi endpoint (cửa sổ thời gian cố định, dùng Symfony Rate Limiter): bảo vệ đăng nhập, đăng ký và tải lên; client bị giới hạn sẽ nhận HTTP 429 kèm header `Retry-After` và `X-RateLimit-*`
- Xác thực mọi payload phía server bằng Symfony Validator — thông báo tiếng Việt
- Player đầy đủ (thẻ video gốc + hls.js cho luồng HLS `.m3u8`)
- Nút "Góp ý" mở trang GitHub Issues của dự án; nút "Nguồn" liên kết tới kho chứa này

## Công nghệ

- PHP 8.1+ — ứng dụng render phía server thuần PHP, không framework frontend, không bước build
- SQLite qua PDO — không cần cài database riêng (`data.db`)
- Các component Symfony: `validator` (xác thực dữ liệu), `rate-limiter` (giới hạn theo IP), `security-core` (voter + phân quyền theo vai trò)
- PHPMailer gửi email qua SMTP
- hls.js phát HLS (bundle tại `assets/js/hls.min.js`)
- Dependencies PHP được bundle sẵn trong `vendor/` — không cần Composer lúc cài đặt

## Cài đặt nhanh (một lệnh duy nhất)

Không cần cấu hình thủ công. Chạy script cài đặt cho hệ điều hành của bạn —
script tự cài PHP (runtime) nếu chưa có, clone mã nguồn tại GitHub release mới
nhất, và tạo lệnh `videohub`:

- **Linux / macOS / Android (Termux):**

  ```bash
  curl -fsSL https://raw.githubusercontent.com/fhfjjfjd/video-player-php/main/scripts/install.sh | bash
  ```

- **Windows (PowerShell):**

  ```powershell
  Invoke-WebRequest -Uri "https://raw.githubusercontent.com/fhfjjfjd/video-player-php/main/scripts/install.bat" -OutFile install.bat
  .\install.bat
  ```

Khi xong, mở terminal mới và gõ:

```bash
videohub
```

Ứng dụng được cài vào `~/videohub` (đặt `VIDEOHUB_DIR` để đổi vị trí). Quản lý
từ mọi nơi:

```bash
videohub           # chạy ứng dụng
videohub update    # cập nhật mã nguồn tại chỗ
videohub reinstall # cài lại từ đầu (hỏi có giữ uploads/ + data.db không)
videohub uninstall # gỡ launcher, PATH và ứng dụng (hỏi có giữ uploads/ + data.db không)
```

`videohub reinstall` và `videohub uninstall` luôn hỏi bạn có muốn giữ video đã
tải lên (`uploads/` và `data.db`) không. Trả lời `y` để giữ dữ liệu, bất kỳ
câu trả lời nào khác sẽ xóa hết. Các luồng tương tự hoạt động như
`bash scripts/install.sh reinstall|uninstall` (Unix) hoặc
`scripts/install.bat reinstall|uninstall` (Windows).

**Khóa phiên bản:** cài đặt và cập nhật luôn lấy **GitHub release mới nhất** —
mã nguồn được checkout đúng tag của release.

## Chạy từ mã nguồn

```bash
bash scripts/start.sh              # hoặc scripts/start.cmd trên Windows
```

Server mặc định lắng nghe tại `127.0.0.1:3000`. Đặt `HOST` để chia sẻ qua LAN
và `PORT` để đổi cổng. PHP built-in server mặc định đơn luồng nên các start
script chạy với nhiều tiến trình worker (`PHP_WORKERS`, mặc định `4`) để nhiều
thiết bị cùng xem hoặc tải lên cùng lúc:

```bash
HOST=0.0.0.0 PORT=3000 PHP_WORKERS=8 bash scripts/start.sh
```

Yêu cầu:

- PHP 8.1+ có extension `pdo_sqlite` (SQLite đi kèm, không cần cài database riêng)
- `ffmpeg` trong PATH để tự trích xuất thumbnail (tùy chọn — thumbnail tùy chỉnh vẫn hoạt động nếu không có)
- Dependencies PHP được bundle sẵn trong `vendor/` — không cần Composer lúc chạy

Các installer tự cài PHP và ffmpeg nếu thiếu, đồng thời kiểm tra extension
`pdo_sqlite` trước khi cài đặt.

### Xác thực email / SMTP

Đăng ký yêu cầu cấu hình SMTP — mã xác thực 6 chữ số được gửi qua SMTP và phải
nhập trên màn hình xác nhận trước khi tài khoản được tạo. Nếu không có SMTP,
đăng ký sẽ báo lỗi và không gửi mã. Cấu hình bằng biến môi trường trước khi
chạy:

```bash
export MAIL_HOST=smtp.gmail.com
export MAIL_PORT=587
export MAIL_USER=youraccount@gmail.com
export MAIL_PASS=your-gmail-app-password
export MAIL_FROM=youraccount@gmail.com   # tùy chọn, mặc định lấy MAIL_USER
export MAIL_ENCRYPTION=tls               # tls (STARTTLS) hoặc ssl
bash scripts/start.sh
```

Mã có hiệu lực 10 phút; người dùng có thể yêu cầu gửi lại mã khi đăng ký đang
chờ xác thực.

## Cấu trúc

- `index.php` — front controller (router cho `php -S`): các trang, JSON API,
  file tĩnh, phát media hỗ trợ Range
- `src/bootstrap.php` — cấu hình runtime, helper, header bảo mật và giới hạn yêu cầu theo IP
- `src/db.php` — lưu trữ SQLite qua PDO (schema giống hệt backend cũ nên `data.db` cũ vẫn dùng được)
- `src/crypto.php` — token media ký, token phiên, băm mật khẩu PBKDF2
- `src/validation.php` — xác thực request bằng `symfony/validator`
- `src/mailer.php` — gửi email qua PHPMailer bằng SMTP
- `src/authz.php` — kiểm tra quyền bằng `symfony/security-core` voter (hành động riêng của chủ sở hữu + vai trò `admin`)
- `src/render.php` — render trang phía server
- `src/views/` — các template trang (home, watch, đăng nhập, xác thực, lỗi)
- `assets/` — file tĩnh: `css/app.css`, `js/app.js`, `js/hls.min.js`, `favicon.svg`
- `scripts/install.sh` / `scripts/install.bat` — installer một lệnh
- `scripts/start.sh` / `scripts/start.cmd` — khởi động ứng dụng qua `php -S`
- `vendor/` — dependencies PHP được bundle sẵn (Symfony + PHPMailer), không cần Composer
