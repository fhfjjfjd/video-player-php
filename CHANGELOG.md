# Changelog

All notable changes to this project are documented in this file. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the
release workflow is defined in `AGENTS.md`.

## [Unreleased]

### Added

- AI agent support: `CLAUDE.md` imports `AGENTS.md` for Claude Code;
  `.agents/rules/general.md` points Google Antigravity (CLI and IDE) at it;
  Cline CLI, Codex CLI and OpenCode read `AGENTS.md` directly (single source
  of truth).

### Changed

- The `AGENTS.md` release workflow no longer depends on subagents (tác nhân
  phụ): every phase is executed directly by the main agent, and subagents are
  at most optional helpers for research or verification.

## [1.1.2] - 2026-08-14

### Changed

- Release workflow in `AGENTS.md` now covers testing, security/error-control
  review, migration checks, a code-review subagent, preview (unstable)
  releases, a rollback procedure, and the changelog.

## [1.1.1] - 2026-08-14

### Changed

- README (EN + VI) note that the project's tech stack may change at any time.

## [1.1.0] - 2026-08-14

### Added

- gzip compression for HTML pages, JSON API responses, and text-based static
  assets (`Vary: Accept-Encoding`); media streams stay raw for Range requests.
- Shared 10-second video-library cache, invalidated immediately on upload and
  delete.
- SQLite tuning: WAL journaling, `synchronous=NORMAL`, in-memory temp tables,
  query-plan indexes, and one-time migrations via `PRAGMA user_version`.
- OPcache with tracing JIT enabled by default in the start scripts.
- `src/accounts.php`: shared account service used by both the server-rendered
  forms and the JSON API.

### Changed

- Media streaming reads 256 KB chunks instead of 8 KB.
- `hls.min.js` is only loaded on HLS watch pages and cached for a year.
- Symfony Validator instance is created once and reused.

## [1.0.0] - 2026-08-13

### Added

- Initial release: pure-PHP successor of
  [video-player-bun](https://github.com/fhfjjfjd/video-player-bun) with
  register/login, email verification, video upload and streaming, search,
  shareable per-video URLs, signed media tokens, per-IP rate limiting, and
  role-based authorization.
