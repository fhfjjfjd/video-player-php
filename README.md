# Video Player (PHP)

**English** | [Tiếng Việt](./README.vi.md)

The active, server-rendered successor of
[video-player-bun](https://github.com/fhfjjfjd/video-player-bun). Register, log
in, upload videos, watch online, search, and share videos through dedicated
per-video URLs. The whole app is pure PHP — no Node, no build step, no separate
database to install.

## Features

- Watch videos publicly without logging in
- Register / log in to upload videos and manage your own uploads (registration requires a Gmail address — the email must end in `@gmail.com`; login accepts your Gmail or username)
- Email verification on registration: registering sends a 6-digit verification code to the Gmail address, which must be entered on the confirmation screen before the account is created (codes expire after 10 minutes, resend supported)
- Existing accounts that were never verified must verify their email when logging in
- Only the owner can delete a video — authorization is enforced by the Symfony Security component (voters + access decision manager), and accounts marked `admin` in the database (`users.role`) can delete any video
- Video thumbnails: automatic extraction with FFmpeg on upload, or a custom image
- Responsive dark streaming-style UI with a cinematic emerald → teal → cyan gradient brand
- Every video has its own shareable URL (`/video/:id`)
- Several devices can watch the same video at the same time — the backend runs multiple PHP worker processes, so one viewer's stream never blocks another
- Direct media URLs are never exposed — the API returns a short-lived HMAC-signed media token, and the player streams through `/api/media?t=<token>` (Range requests supported)
- Hardened responses: Content-Security-Policy, `X-Content-Type-Options`, `X-Frame-Options` and other security headers on every request
- Per-IP rate limiting on every endpoint (fixed time windows, Symfony Rate Limiter): protected login, registration and uploads; limited clients get HTTP 429 with a `Retry-After` header and `X-RateLimit-*` headers
- Server-side validation of every payload with the Symfony Validator component — Vietnamese messages
- Full-featured player (native video element + hls.js for HLS `.m3u8` streams)
- The "Góp ý" button links to the GitHub Issues page; the "Nguồn" button links to this repository
- Compressed responses: HTML pages, JSON API and text-based static assets are served gzip-compressed (`Vary: Accept-Encoding`); media streams are always served raw so Range requests and seeking stay fast
- Shared video-library cache: the home page and the API read the video list from a short-lived shared cache (10 s) instead of re-querying SQLite on every request — invalidated immediately on upload and delete
- Tuned SQLite: WAL journal + `synchronous=NORMAL` + in-memory temp tables, query-plan indexes, and one-time migrations tracked via `PRAGMA user_version` (no per-connection schema scans)
- OPcache with JIT enabled by default: the start scripts launch PHP with bytecode caching and tracing JIT so repeated requests avoid recompiling the app
- The vendored `hls.min.js` is only loaded on watch pages that actually play HLS streams, and is cached by browsers for a year

## Tech Stack

- PHP 8.1+ — pure server-rendered app, no frontend framework, no build step
- SQLite via PDO — no separate database to install (`data.db`)
- Symfony components: `validator` (payload validation), `rate-limiter` (per-IP limits), `cache` (shared video-list cache + rate-limiter storage), `security-core` (voters + role-based authorization)
- PHPMailer for outbound email over SMTP
- hls.js for HLS playback (vendored at `assets/js/hls.min.js`)
- PHP dependencies are bundled in `vendor/` — no Composer needed at install time

> **Note:** The tech stack is not set in stone. The programming language and
> technologies used by this project may change at any time — this document is
> updated to reflect whatever the current stack is.

## Performance & caching

- **gzip everywhere except media** — pages, JSON API responses and text assets
  (`text/*`, JSON, JS, SVG, XML, WASM) are compressed on the fly with
  `ob_gzhandler` and marked `Vary: Accept-Encoding`. Media streaming never gets
  compressed: the player needs raw byte ranges for smooth seeking.
- **Video-list cache** — `list_videos_cached()` keeps the library (including
  search results) in a shared FilesystemAdapter cache for 10 seconds. Uploads
  and deletes clear the cache instantly, so the library always looks current
  without every request hitting SQLite.
- **SQLite tuning** — WAL journaling, `synchronous=NORMAL` (safe with WAL, much
  faster writes), `temp_store=MEMORY`, indexes on `videos(created_at, id)`,
  `videos(user_id)` and `email_verifications(email)`, and migrations gated by
  `PRAGMA user_version` so schema checks run once, not per connection.
- **OPcache + JIT** — `scripts/start.sh` and `scripts/start.cmd` launch PHP with
  `opcache.enable_cli=1`, tracing JIT and a 32 MB JIT buffer. Each worker keeps
  the compiled app in memory across requests.
- **Bigger streaming chunks** — the media loop reads 256 KB at a time instead
  of 8 KB, so fewer read/flush round-trips per stream.
- **Slimmer watch pages** — `hls.min.js` (~543 KB, ~120 KB gzipped) is now
  fetched only on pages that actually play `.m3u8` streams, and is served with
  `Cache-Control: public, max-age=31536000, immutable` since it is a pinned
  vendored dependency.
- **Reused services** — the Symfony Validator instance is created once and
  reused; account logic (register/login/verify/resend) lives in a single
  `src/accounts.php` shared by both the server-rendered forms and the JSON API.

## Quick install (one command)

No manual setup needed. Run the installer for your OS — it installs PHP (the
runtime) if missing, clones the source at the latest GitHub release, and
creates a `videohub` command:

- **Linux / macOS / Android (Termux):**

  ```bash
  curl -fsSL https://raw.githubusercontent.com/fhfjjfjd/video-player-php/main/scripts/install.sh | bash
  ```

- **Windows (PowerShell):**

  ```powershell
  Invoke-WebRequest -Uri "https://raw.githubusercontent.com/fhfjjfjd/video-player-php/main/scripts/install.bat" -OutFile install.bat
  .\install.bat
  ```

When it finishes, open a new terminal and just type:

```bash
videohub
```

The app installs to `~/videohub` (set `VIDEOHUB_DIR` to change the location).
Manage it from anywhere:

```bash
videohub           # start the app
videohub update    # update source in place
videohub reinstall # fresh install (asks whether to keep uploads/ + data.db)
videohub uninstall # remove launcher, PATH entries, and app (asks whether to keep uploads/ + data.db)
```

`videohub reinstall` and `videohub uninstall` always ask whether you want to
keep your uploaded videos (`uploads/` and `data.db`). Answer `y` to keep the
data, anything else to delete everything. The same flows work as
`bash scripts/install.sh reinstall|uninstall` (Unix) or
`scripts/install.bat reinstall|uninstall` (Windows).

**Version pinning:** install and update always fetch the **latest GitHub
release** — the source is checked out at the release tag.

## Run from source

```bash
bash scripts/start.sh              # or scripts/start.cmd on Windows
```

The server binds to `127.0.0.1:3000` by default. Set `HOST` to share over LAN
and `PORT` to change the port. The built-in PHP web server is single-threaded,
so the start scripts launch it with multiple worker processes
(`PHP_WORKERS`, defaults to `4`) to let several devices watch or upload at the
same time:

```bash
HOST=0.0.0.0 PORT=3000 PHP_WORKERS=8 bash scripts/start.sh
```

Requirements:

- PHP 8.1+ with the `pdo_sqlite` extension (SQLite is embedded, no separate
  database to install)
- `ffmpeg` on PATH for automatic thumbnail extraction (optional — custom
  thumbnails still work without it)
- PHP dependencies are bundled in `vendor/` — no Composer needed at runtime

The installers install PHP and ffmpeg when missing and verify the `pdo_sqlite`
extension before setting things up.

### Email verification / SMTP

Registration requires SMTP to be configured — the 6-digit verification code is
emailed via SMTP and must be entered on the confirmation screen before the
account is created. Without SMTP, registration returns an error and no code is
sent. Configure it with environment variables before starting:

```bash
export MAIL_HOST=smtp.gmail.com
export MAIL_PORT=587
export MAIL_USER=youraccount@gmail.com
export MAIL_PASS=your-gmail-app-password
export MAIL_FROM=youraccount@gmail.com   # optional, defaults to MAIL_USER
export MAIL_ENCRYPTION=tls               # tls (STARTTLS) or ssl
bash scripts/start.sh
```

Codes are valid for 10 minutes; users can ask to resend a code while a
registration is pending.

## Structure

- `index.php` — the front controller (router for `php -S`): pages, JSON API,
  static files, media streaming with Range support
- `src/bootstrap.php` — runtime configuration, helpers, security headers and
  per-IP rate limiting
- `src/db.php` — SQLite storage via PDO (schema identical to the previous
  native backend, so an existing `data.db` keeps working)
- `src/crypto.php` — signed media tokens, session tokens, PBKDF2 password hashing
- `src/validation.php` — request validation via `symfony/validator`
- `src/mailer.php` — outbound email via PHPMailer over SMTP
- `src/authz.php` — authorization via `symfony/security-core` voters (owner-only
  actions with an `admin` role)
- `src/accounts.php` — shared account service (registration, login, email
  verification, code resend) used by both the server-rendered forms and the
  JSON API, so the two surfaces can never drift apart
- `src/render.php` — server-side page rendering
- `src/views/` — the page templates (home, watch, auth, verify, error)
- `assets/` — static files: `css/app.css`, `js/app.js`, `js/hls.min.js`, `favicon.svg`
- `scripts/install.sh` / `scripts/install.bat` — one-command installers
- `scripts/start.sh` / `scripts/start.cmd` — start the app via `php -S`
- `CHANGELOG.md` / `CHANGELOG.vi.md` — release history (Keep a Changelog format)
- `vendor/` — bundled PHP dependencies (Symfony + PHPMailer), no Composer needed
