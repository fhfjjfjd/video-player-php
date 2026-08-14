# Agent Rules — Docs & Release Workflow

This project lives at `https://github.com/fhfjjfjd/video-player-php` (branch `main`).

These rules are MANDATORY. Follow them every time you complete a task, update
docs, or cut a release. Do not skip steps, do not reorder phases, and do not
rely on memory — re-read the relevant sections each time you start work.

**What this workflow does.** A task is finished by (1) checking whether the
backend runtime (PHP) is installed on this machine and, if it is, running a
syntax check (`php -l`) and any automated tests, (2) checking database
migrations when the schema changes, (3) running a security and error-control
review, (4) updating the documentation AND the changelog, (5) getting an
independent code review for non-trivial changes, (6) committing, (7) pushing,
(8) bumping the version, (9) tagging, (10) creating a PREVIEW (unstable)
release for users to test, (11) promoting it to stable after confirmation, and
then (12)–(20) stewarding the shipped version: monitoring its health,
collecting feedback, checking performance, reviewing accessibility and
localization, verifying rollback readiness, refreshing security and dependency
advisories, running a blameless postmortem, reporting DORA metrics, and
triaging feedback back into Phase 0.
There is NO GitHub Actions trigger, NO artifact download/verification, and NO
binary upload. A release is created from docs and version changes only and
does not attach binaries. If the backend runtime is missing on this machine,
skip the syntax check and tests entirely and just publish as normal.

---

## 0. How this document is organized

- **Section 1** — documentation language rules (multilingual repo).
- **Section 2** — the release workflow, phase by phase (this is the normative
  process; follow it exactly).
- **Section 3** — absolute rules that apply at all times (never-broken
  invariants).
- **Section 4** — subagents are optional helpers; the workflow never depends on them.
- **Section 5** — feedback / GitHub Issues handling.
- **Section 6** — rollback and data safety.
- **Section 7** — the changelog.
- **Section 8** — coding standards (PHP).
- **Section 9** — the security hardening baseline (OWASP-informed).
- **Section 10** — definition of done (pre-commit checklist).
- **Section 11** — testing rules.
- **Section 12** — performance non-regression.
- **Section 13** — privacy and data handling.
- **Section 14** — incident response.
- **Section 15** — maintaining this file (AGENTS.md policy).

Use **Section 2** as your checklist.

---

## 1. Documentation language

- The repo is **multilingual**. Docs are NOT fixed to any single language —
  write in any language, and add translations when useful.
- Keep `README.md` as the primary doc; add a per-language mirror file (e.g.
  `README.vi.md` for Vietnamese, `README.zh.md` for Chinese) for each
  translation you add.
- Never mix multiple languages inside one doc; keep each doc file in one
  language. A doc file must be 100% one language: a document that contains no
  Vietnamese must be written ENTIRELY in English (no Vietnamese at all), and a
  document in Vietnamese must be ENTIRELY Vietnamese (no English at all). This
  applies regardless of the language of the report or issue being worked on.
- Update the README (and its translation mirrors) whenever behavior, commands,
  or the tech stack change.
- **Docs are mandatory, not an afterthought.** Every change — code, config,
  CI, or docs — MUST also update `README.md` and ALL existing per-language
  mirrors (e.g. `README.vi.md`) in the SAME commit. A change is not complete
  until its docs are synced in every shipped language. If a mirror is missing
  but that language already ships in the repo, create it.
- **The changelog is part of the doc set.** Behavior, command, config, or
  stack changes MUST also update `CHANGELOG.md` (and its mirrors) in the same
  commit (see Phase 4.1 and Section 7).

---

## 2. The release workflow

The workflow has **20 phases**. Phase 0–1 analyze the change, Phase 2 runs the
local checks (syntax, tests, migrations), Phase 3 is the security and
error-control review, Phase 4 syncs the docs and the changelog, Phase 5 bumps
the version, Phases 6–8 commit/push/tag, Phase 9 creates the PREVIEW (unstable)
release and promotes it to stable, and Phase 10 closes out. Every phase must
complete before the next one starts. If any phase fails, do not proceed; fix
and retry.

Phases 11–19 are the **post-release stewardship** loop. They run on the shipped
version — a bounded watch window, then on a calendar cadence — and close the
cycle: Phase 11 monitors the release's health, Phase 12 collects user feedback,
Phase 13 checks performance regression, Phase 14 audits accessibility and
localization, Phase 15 verifies rollback readiness, Phase 16 refreshes security
and dependency advisories, Phase 17 runs a blameless postmortem when needed,
Phase 18 reports DORA metrics, and Phase 19 triages all feedback back into
Phase 0. They are time-based, not build-based: start them after Phase 10 and
complete them on their own deadlines.

> **No GitHub Actions, no binary uploads.** This workflow never triggers a CI
> workflow, downloads artifacts, or attaches binaries to a release. The only
> compilation is done locally (Phase 2) and only when a compiler for the
> backend language is installed on this machine.

### Phase 0 — Intake and reconnaissance

0.1. **Check open GitHub Issues.** Run
     `gh issue list --repo fhfjjfjd/video-player-php` and read EVERY open
     issue COMPLETELY before acting: title, full body, ALL comments
     (`gh issue view <n> -c`), and the issue's live state — `createdAt` /
     `updatedAt`, labels, reactions (upvotes signal demand), `assignees`,
     `milestone`, and linked PRs / `closedByPullRequestsReferences` (a
     "fixes #n" PR may mean the work is already done). Search for duplicates
     with the report's own keywords
     (`gh issue list --repo fhfjjfjd/video-player-php --search '<terms>
     in:title,body' --state all`) before deep-reading. Never act on a title
     alone and never judge an issue from a partial read — the body can be
     edited and comments added while you work.

0.2. **Confirm repository state.** Run `git status`, `git log --oneline -10`,
     and `git remote -v`. Confirm you are on `main`, the working tree matches
     your expectations, and there are no unexpected local commits.

0.3. **Confirm the latest release.** Run `gh release list --repo
     fhfjjfjd/video-player-php` and note the newest tag. You will need it in
     Phase 5 to pick a correct version bump.

0.4. **Inventory the affected code.** If the task touches more than one file,
     produce a concise map yourself of what was changed, what calls what, and
     what could break. This map becomes the input to Phase 1.

### Phase 1 — Change analysis and planning

1.1. **Understand the scope.** Using the Phase 0.4 map (and your own reading
     of the diff), write down in your own words: what the change does, which
     surfaces it touches (frontend, backend, docs, config), and the risk
     areas.

1.2. **Classify the change type.** Pick exactly one:
     - `bugfix` — fixes incorrect behavior, no new capability.
     - `feature` — adds new capability or changes visible behavior.
     - `breaking` — removes features, changes storage formats, or breaks
       existing deployments.
     - `chore` — docs, config, refactor with no behavior change.
     Every classification goes through the same workflow (Phase 2 → 10). The
     classification only picks the version bump size in Phase 5.

1.3. **Draft the version bump** based on the classification (see Phase 5 for
     exact rules). Do not edit `composer.json` yet — first confirm the current
     version and the latest tag are consistent (Phase 0.3).

1.4. **Create a todo list** of the remaining phases (2 → 10, plus the
     post-release stewardship phases 11 → 19) and mark Phase 2 as in-progress.
     Track every phase in the todo list; never silently skip one.

### Phase 2 — Compilation check, tests, and migration check (conditional)

2.1. **Determine the backend language.** This project's backend is PHP. The
     language marker is `composer.json` at the project root (the router is
     `index.php`, the rest of the app lives under `src/`). If there is no
     backend at all, skip this phase.

2.2. **Check for a compiler/toolchain on this machine.** For the detected
     language, check whether the toolchain is installed, e.g.:
     `command -v php` (PHP), `g++`/`clang++` (C++), `cargo` (Rust), `go`
     (Go), `python3` (Python), `bun`/`node` (Node), etc.

2.3. **If the toolchain IS installed → run the syntax check AND any automated
     tests on this machine.** PHP is interpreted, so there is nothing to
     compile. Run a syntax check (`php -l`) on the router and every backend
     file: `php -l index.php` and `php -l` on each file under `src/`
     (`find src -name '*.php' -exec php -l {} +`). If the repo ships an
     automated test suite (e.g. a `tests/` directory, a `phpunit.xml*`, or a
     `composer test` script), run it too. If the syntax check or any test
     fails, fix the code and retry; do not proceed to a release on a failed
     check.

2.4. **Migration check (only when the schema changes).** If the change touches
     the database schema, review the migration logic in `src/db.php`.
     Migrations are tracked via `PRAGMA user_version` and MUST be
     forward-only and additive: they add tables/columns/indexes, never drop or
     rewrite existing data, and bump `user_version` by exactly one per
     migration. A migration that must change or remove existing data is a
     `breaking` change (Phase 1.2) and requires explicit user approval — test
     it against a copy of an existing `data.db` before proceeding.

2.5. **If the toolchain is NOT installed → skip the syntax check and tests.**
     Do NOT install a compiler/interpreter, do NOT trigger CI to build, and do
     NOT worry about missing binaries. The migration check (2.4) still
     applies. Just proceed to the rest of the workflow ("đăng như bình
     thường").

2.6. **Never trigger GitHub Actions and never upload build artifacts.** No
     `gh workflow run`, no polling of runs, no `gh release upload`, no magic-
     byte checks. Even if a local build succeeded, the release is created
     without attaching binaries.

2.7. **Dependency audit (only when Composer exists on this machine).** If
     `command -v composer` succeeds AND the change adds, updates, or removes
     a dependency, run `composer audit` locally against `composer.lock`
     (checks the PHP Security Advisories Database). A known-vulnerable
     dependency is a release blocker: update it, or document a deliberate
     exception with the CVE id and the user's approval. Never trigger CI for
     this — it runs locally or not at all.

### Phase 3 — Security and error-control review (mandatory)

3.1. **Scan the diff for secrets.** Inspect the staged diff for credentials:
     API keys, tokens, `MAIL_PASS`-style values, and private key material. If
     anything matches, STOP and remove it before continuing.

3.2. **Check error control.** Confirm that production responses never leak
     stack traces, SQL, or file paths: `display_errors` stays off for the web
     server and errors are logged, not echoed, and the front controller still
     applies the security headers (`apply_security_headers()`).

3.3. **Check the touched endpoints.** Every new or changed endpoint must still
     validate input server-side, keep per-IP rate limiting, and never bypass
     authorization (`authz_can()` / voters).

3.4. Any finding must be fixed and this review re-run until clean before the
     change moves to Phase 4.

### Phase 4 — Documentation and changelog update (mandatory)

4.1. **Documentation is never optional.** Every change — code, config, or
     docs — MUST update `README.md`, ALL existing per-language mirrors
     (e.g. `README.vi.md`), AND `CHANGELOG.md` (plus its per-language mirrors,
     e.g. `CHANGELOG.vi.md`) in the SAME commit.

4.2. List every doc file that ships in the repo before editing: run
     `ls README*.md CHANGELOG*.md` (or `glob "*.md"`) and update each one.
     Never leave a mirror stale.

4.3. If multiple translations are needed and the change is large, write each
     mirror yourself in its own language (one language per file, never
     interleaved), then review the results yourself before committing.

4.4. Keep every doc file in a single language. Never interleave languages.

4.5. **Every release gets a changelog entry.** Add a dated entry to
     `CHANGELOG.md` (and every mirror) under a
     `## [<version>] - <YYYY-MM-DD>` heading, listing Added / Changed / Fixed /
     Removed. Keep a `## [Unreleased]` section at the top for in-progress
     items. The release notes in Phase 9 must match the changelog entry.

4.6. Do the doc sync BEFORE Phase 6 (commit). Check the staged set in 6.3
     includes every doc file; if a doc was not modified, STOP and update it
     before committing.

### Phase 5 — Version bump

5.1. **Read the current version** in `composer.json` (`"version"` field).

5.2. **Compute the target version.** It must be STRICTLY HIGHER than the
     latest release tag from Phase 0.3. Use these rules:
     - `chore` / `bugfix` (non-visible) → patch bump: `x.y.z+1`.
     - `feature` → minor bump: `x.(y+1).0`.
     - `breaking` → major bump: `(x+1).0.0`.
     - If the previous tag was never reflected in `composer.json` (the file
       lags behind the latest tag), still bump from the TAG, not from the
       file.

5.3. **Apply the bump** by editing the `version` field in `composer.json`.

5.4. **If you are unsure** which version to use, STOP and ask the user. Never
     guess a version number.

### Phase 6 — Commit

6.1. Run `git status` and `git diff` once more to confirm exactly what will be
     staged.

6.2. **Never stage or commit** `data.db*`, `uploads/`, `node_modules/`,
     `cache/`, `.opencode/`, or any secret/credential file. `vendor/` is
     bundled (no Composer at install time) and IS committed. If `git status`
     shows any of the ignored paths above, something is wrong — investigate
     before staging.

6.3. Stage with `git add -A`, then review the staged set with
     `git diff --cached --stat` and confirm only intended files are present.

6.4. **Self-review the staged diff before committing.** If the change is more
     than a trivial docs/version edit, review the staged diff
     (`git diff --cached`) yourself against the Phase 3 checklist, the coding
     standards (Section 8) and the definition of done (Section 10); look for
     bugs, security findings, missing docs/changelog sync, and style issues.
     Fix any findings and re-stage. The final go/no-go stays with you / the
     user.

6.5. Commit with a concise message matching the repo style, using one of these
     prefixes: `Add:`, `Fix:`, `Change:`, `Remove:`, `Docs:`.
     Example: `Docs: sync README for version 4.3.0`.

6.6. **Keep commits atomic.** One logical change per commit; never bundle
     unrelated edits into a single commit. A docs sync belongs with the code
     change it documents (Phase 4), not in a catch-all commit.

6.7. **Never rewrite pushed history.** No `git push --force` /
     `--force-with-lease` on `main`, no amend of a commit already pushed, no
     interactive rebase of published commits. Fix forward with a new commit
     (Section 3.10); amend freely only while the commit is still local.

6.8. **No merge commits on `main`.** History is linear; keep it that way.

### Phase 7 — Push

7.1. Push to origin: `git push origin main`. Confirm the push succeeded and
     the remote `main` is now ahead by exactly one commit.

### Phase 8 — Tag

8.1. Create the tag matching the version from Phase 5:
     `git tag v<version>` (e.g. `git tag v4.3.0`).

8.2. Push the tag: `git push origin v<version>`.

8.3. If the tag was pushed earlier and needs to move, you may delete and
     recreate it ONLY while no release exists for it yet:
     `git tag -d v<version> && git push origin :refs/tags/v<version>` then
     redo 8.1–8.2. Once a release is published, never touch the tag
     (Section 3.4).

### Phase 9 — Preview release and promotion to stable

9.1. **Create the release as a PRE-RELEASE (unstable preview)** against the
     tag from Phase 8:
     ```bash
     gh release create v<version> --repo fhfjjfjd/video-player-php \
       --title "v<version> - Preview (unstable) - <short summary>" \
       --prerelease \
       --notes "<what changed>"
     ```
     **No binaries are attached.** Do not upload, link, or reference any
     build artifact — even if a local build succeeded in Phase 2.
     **Release titles and notes MUST be written in English.** Do not use
     Vietnamese or any other language in a release title or its notes — the
     notes summarize the changes in this version for a global audience.

9.2. **Record the preview URL** (from the `gh release create` output or
     `gh release view v<version> --repo fhfjjfjd/video-player-php --json url
     --jq .url`) and present it to the user for testing.

9.3. **If the user reports problems** with the preview: fix, bump a patch
     version, and redo from Phase 2. Never promote a broken preview, and never
     touch the preview release's tag once it exists.

9.4. **When the user confirms, promote to stable.** This is a metadata-only
     edit — it does NOT move the tag and does NOT add content (allowed under
     Section 3.4):
     ```bash
     gh release edit v<version> --repo fhfjjfjd/video-player-php \
       --title "v<version> - <short summary>" \
       --prerelease=false
     ```
     The newest non-prerelease release becomes `Latest` automatically.

### Phase 10 — Close-out

10.1. **Close fixed issues.** If any open GitHub Issue was addressed by this
      work, reply on the issue explaining what was done (in a language the
      reporter can read, matching theirs if possible) and close it:
      `gh issue close <number> --comment "<thanks + what changed + version + how to reopen>"`.
      See Section 5 for the full closing-comment structure and the reply
      templates.

10.2. **Confirm a clean tree.** Run `git status` and confirm nothing is left
      uncommitted or untracked.

10.3. **Report to the user.** Give a concise summary: what changed, whether
      the backend was verified locally (syntax check / tests) or skipped
      because no compiler exists, the new version, the tag, the preview URL,
      and the stable release URL. Then hand off to the post-release
      stewardship phases (11 → 19).

### Phase 11 — Release health monitoring (stability watch window)

Purpose: observe the shipped version for a bounded window and decide — from
logs, not vibes — whether to promote the preview or cut a patch.

11.1. **Set the error budget and the revert trigger BEFORE shipping.** Over a
      24-hour window, treat any of these as a significant event (promote only
      after it is explained or fixed): ≥3 distinct 500 responses, any
      `PHP Fatal error` / `Uncaught` exception, any `SQLSTATE[HY000]` SQLite
      error (e.g. `database is locked`), or a cluster of HTTP 429s on one
      rate-limit bucket.
11.2. **Scan the error log.** `src/bootstrap.php` keeps `log_errors=1` /
      `display_errors=0`; locate the configured `error_log` path (default SAPI
      log). Compare the window since the release tag against the same-length
      window right before it, so pre-existing noise is not blamed on the
      release:
      ```bash
      grep -c 'PHP Fatal error' <error_log>
      grep -n 'PHP Fatal error\|Uncaught\|Stack trace' <error_log> | tail -n 20
      grep -c 'SQLSTATE\[HY000\]' <error_log>
      ```
11.3. **Watch for slow or hung requests.** A `Maximum execution time ...
      exceeded` line IS a slow response; a `database is locked` spike signals
      writer contention under the `PHP_WORKERS` multi-worker server.
11.4. **Time-box the decision.** At 24 h and again at 72 h after the tag, run
      the checks above, record the counts, and either promote the preview
      (Phase 9.4) or cut a patch (Phase 9.3). A window that never ends is not
      a policy.

### Phase 12 — User acceptance testing and feedback collection

Purpose: get real user signal on the preview build, then turn it into a short
go/no-go for promotion.

12.1. **Broadcast the preview URL** (Phase 9.2) and seed a handful of testers
      (~10 "lighthouse users") who each cover register → verify → login →
      upload → watch → delete, on BOTH the rendered forms and the JSON API.
12.2. **Reuse the "Góp ý" button** (it links to GitHub Issues, Section 5).
      Ask testers to report with a short template: category (bug / i18n /
      a11y / perf / feature), browser, and locale (VI or EN).
12.3. **Keep any exit survey short** (5–7 questions) and run it after the
      first upload and before the release closes — not at the end of a long
      session.
12.4. **Triage weekly and close the loop.** Cluster open-text feedback by
      theme, label the issues, and tell testers what changed. Testers who hear
      back respond more next time.
12.5. **Gate the promotion.** Before Phase 9.4: zero unaddressed critical /
      serious bugs, and every new user-facing string present in BOTH the VI
      and EN locale sets.

### Phase 13 — Performance regression check

Purpose: prove the candidate is no slower than a recorded baseline under a
stable, representative workload.

13.1. **Define the fixed benchmark suite and record a baseline.** Benchmark the
      load-bearing endpoints: the gzip HTML home page (`/`), the JSON list API
      (`/api/videos`), and one Range-requested HLS chunk. Use `hey` or `wrk`
      (`ab` under-reports on fast PHP servers) and store the baseline
      versioned, e.g. `perf/baseline-*.json`.
13.2. **Run before/after under identical conditions.** Same machine, one
      warm-up request, same concurrency (e.g. `-c 20`), same `PHP_WORKERS`.
      Run each twice and keep the better p99.
13.3. **Diff p95/p99 first, then error rate, then throughput.** Gate: p95 not
      worse than baseline by more than 15%, p99 under an absolute ceiling,
      errors under 0.5%. Report exactly what regressed and by how much.
13.4. **Verify the load-bearing invariants explicitly.** Text responses stay
      gzip-compressed with `Vary: Accept-Encoding` (`curl -H "Accept-Encoding:
      gzip" ... -w "%{size_download}"`); media stays uncompressed and serves
      Range requests (`curl -H "Range: bytes=0-255" ...` must return `206` /
      256 bytes).
13.5. **Re-baseline after major releases; keep the gate cheap.** For patch
      releases, diff against the last known-good baseline instead of
      re-baselining.

### Phase 14 — Accessibility and localization review

Purpose: run the W3C quick-win checks plus an automated WCAG scan on the core
journeys, and verify the VI/EN pairing is complete and correct.

14.1. **Run the W3C "Easy Checks" on every page**, in both languages: page
      `<title>`, `alt` text, heading structure, color contrast ≥ 4.5:1, 200%
      zoom without overlap, keyboard access + visible focus, and labels/errors
      on the login, register and upload forms.
14.2. **Gate the core journeys with axe-core** (WCAG A/AA), failing only on
      `critical` / `serious` impacts (keyboard traps, missing accessible
      names, unlabeled controls). Carry only those into a hard gate or it will
      be disabled. Automated tools find ~35–57% of issues; keep the manual
      checks.
14.3. **Verify the `<html lang>` attribute switches with the language
      toggle** (`vi` ↔ `en`) and matches the served locale — screen readers
      announce the language first.
14.4. **Diff the VI/EN string sets for parity.** Every user-facing string —
      forms, validation errors, JSON API error messages, player controls —
      must exist in both locales with no fall-through. Fail if a key exists in
      one set and not the other; spot-check interpolated strings (translate
      whole sentences, never assemble), date/number formats, diacritics, and
      text-overflow in buttons.
14.5. **Smoke-test both locales end-to-end** (register → upload → watch) and
      confirm no hardcoded English leaked into the Vietnamese UI.

### Phase 15 — Rollback readiness (backup verification and restore drill)

Purpose: guarantee that a backup that was never restored is never trusted, and
that the revert path is rehearsed before it is needed.

15.1. **Verify every pre-release backup before trusting it.** After the backup
      and before the release:
      ```bash
      sqlite3 data.db.bak "PRAGMA integrity_check;"   # must print 'ok'
      sqlite3 data.db.bak "PRAGMA user_version;"      # must match the live DB
      ```
      Also compare the `videos` row count and `uploads/` file parity
      (`find ... | sort | diff -`) plus a checksummed sample file.
15.2. **Run a restore drill on a copy, never on live data.** Point a throwaway
      app directory at the backup, run `PRAGMA integrity_check`, count rows,
      and play one file through `/api/media` so the DB rows and the media
      files are proven to still agree. Then delete the scratch copy.
15.3. **Rehearse the `git revert` on a throwaway branch.** Because history is
      linear, `git revert <release-commit>` applies cleanly — practice it:
      branch → revert → `php -l` → smoke-test → delete the branch. A rollback
      that was never tested is a rollback that fails under pressure.
15.4. **A real rollback is a NEW patch release** (Section 3.10): `git revert`
      the offending commit, re-run Phases 2–4 in the SAME commit, bump a patch,
      and release. Never `reset --hard`, never force-push, never move a
      published tag.
15.5. **Restore the data BEFORE re-running reverted code.** If the release
      touched the schema, the reverted code may not understand the newer
      `user_version`: roll the code back first, then restore `data.db` and
      `uploads/` from the pre-release backup, then smoke-test.

### Phase 16 — Security and dependency advisory refresh

Purpose: guarantee no released version ships a known-vulnerable dependency,
with no CI to lean on.

16.1. **Fixed monthly audit plus a pre-release gate.** Run `composer audit
      --locked` on a fixed day AND before every release (Phase 2.7 already
      blocks releases on findings when Composer exists). `--locked` audits
      `composer.lock` without touching `vendor/`.
16.2. **Enable Dependabot alerts and security updates** (event-driven, so they
      fire on disclosure regardless of your cadence) and keep a
      `.github/dependabot.yml` for the `composer` ecosystem, monthly, with a
      groups block so routine bumps land as one PR.
16.3. **Sync `vendor/` on every dependency change.** A Dependabot PR only edits
      `composer.json` / `composer.lock`; because `vendor/` is bundled and
      committed, run `composer install` (or `composer update <pkg>`) locally
      and commit `composer.json` + `composer.lock` + `vendor/` in the SAME
      commit, then re-run `composer audit --locked` and `php -l` before
      shipping (Phase 2.3).
16.4. **Optional second opinion with OSV** (`osv-scanner scan .` reads
      `composer.lock` against OSV.dev, which aggregates GitHub Security
      Advisories and FriendsOfPHP). Run on the same monthly cadence; the two
      sources occasionally differ.
16.5. **Triage every finding:** record CVE id + severity, judge reachability
      for this app, then fix via 16.3 or log an approved exception (CVE id +
      approval) per Section 9.7.4. Never silently skip a finding.

### Phase 17 — Blameless postmortem / retrospective

Purpose: turn every failure into a systemic fix — never a blame event — with
tracked, verifiable action items.

17.1. **Triggers:** a hotfix after a preview (Phase 9.3), a `git revert`
      (Section 3.10), a restore from `data.db` backup, a broken-install
      report, or an advisory hitting a shipped version. Write it within about
      a week of the incident — promptness is accuracy.
17.2. **Store the artifact in-repo** (`docs/postmortems/YYYY-MM-DD-v<tag>.md`)
      and rebuild the timeline from records you already keep: `git log
      --format='%ci %s' <bad_tag>..<fix_tag>`, `gh issue view <n>`, and the
      release notes.
17.3. **Use the SRE Workbook template sections** adapted to a one-person org:
      Summary, Impact (with numbers), Timeline, Root Cause & Trigger, What went
      well / went poorly, Action items (owner, due date, verifiable end state),
      Lessons. Write blamelessly — "what condition allowed this", not "who did
      this".
17.4. **Close the loop through GitHub Issues:** one issue per action item, and
      treat unresolved blockers as release blockers (Section 10).
17.5. **Feed the MTTR ledger** for Phase 18: detected date = issue `createdAt`
      or the preview date; restored date = the fixing release's `publishedAt`.

### Phase 18 — Metrics and DORA reporting

Purpose: derive the four DORA metrics from git history and GitHub releases
alone — no CI required.

18.1. **Deployment frequency** = releases per month: `gh release list --repo
      fhfjjfjd/video-player-php --limit 500`, bucket `publishedAt` by month
      (fall back to `git tag -l 'v*'` for pre-release tags). A
      preview→stable promotion is one deploy.
18.2. **Lead time for changes** per release = tag date minus the oldest commit
      in that release's change set (`git log --format=%cI <prev_tag>..<tag> |
      sort | head -1` vs `git show -s --format=%cI <tag>`). Report the
      median/p75 over the last 30–90 days, never the mean.
18.3. **MTTR** = detected → restored: `gh issue view <n> --json
      createdAt,closedAt` for the issue that surfaced the break and `gh release
      view vX.Y.Z --json publishedAt` for the fixing release (fall back to the
      Phase 17 ledger).
18.4. **Change failure rate** = failed releases ÷ total releases in the
      window. A release "failed" if it needed a hotfix, a revert, was a preview
      never promoted, or drew a broken-install report.
18.5. **Report once per quarter** into `docs/metrics.md` or the changelog: one
      number per metric plus a trend beats a dashboard for a solo maintainer.
      Note which inputs are estimates.

### Phase 19 — Feedback triage and roadmap loop-back

Purpose: route every piece of feedback — issues, surveys, metrics, postmortem
actions — back into Phase 0 so nothing is dropped.

19.1. **Review the open issue queue** (Section 5) and the Phase 12 survey
      clusters; label and, where possible, estimate each item.
19.2. **Decide the outcome for each item:** classify it for the next cycle as
      `feature` / `bugfix` / `breaking` / `chore` (Phase 1.2) or decline it
      explicitly — with a polite reply in the reporter's language (Section 5).
      An item with no decision is still open.
19.3. **When the released version is healthy** (Phases 11–18 green) and the
      triage is done, record the baseline and the cycle is complete. The next
      change starts again at Phase 0.
19.4. **Never silently drop feedback.** Every report gets a triage outcome and
      the reporter hears it, closing the loop.

---

## 3. Absolute rules (apply at all times)

3.1. **Run the PHP syntax check locally ONLY when `php` exists.** The only
     check that ever happens is Phase 2 on this machine, and only when the
     backend runtime is installed (`command -v php` succeeds). If `php` is
     missing, never install one, never trigger CI, and never fail the task —
     just publish as normal.

3.2. **Never trigger, wait on, or inspect GitHub Actions.** Do not run
     `gh workflow run`, do not poll `gh run list`, do not read failed logs,
     and do not download CI artifacts. Releases are created from docs and
     version changes only.

3.3. **Never create a release with build artifacts.** No binary upload, no
     `gh release upload`, no asset naming, no magic-byte checks. A release
     here is documentation + version + notes.

3.4. **Never add content to a published release or move its tag.** Once a
     release is published, do NOT attach new assets and never move the tag it
     points to — if you need to ship a fix, make the change and cut a NEW
     version via the workflow. **Language and wording edits are allowed:**
     fixing the title or notes of an already-published release to the correct
     language, fixing typos, and promoting a preview to stable (Phase 9.4) are
     normal and do not count as "modifying the release". Never re-upload
     binaries or repoint the tag of a published release.

3.5. **Always use the GitHub CLI (`gh`) for release operations.** It is
     authenticated; do not fall back to unauthenticated curl for release
     management.

3.6. **Keep the local repo clean.** Never commit secrets, credentials,
     databases (`data.db*`), uploads (`uploads/`), `node_modules/`, caches
     (`cache/`), `.opencode/`, or any secret/credential file. Note: `vendor/`
     is bundled on purpose (the app runs without Composer) and IS committed.

3.7. **Never guess a version.** When in doubt, ask the user.

3.8. **Never push a change without its docs.** A change that alters behavior,
     config, commands, or CI MUST also update `README.md`, every existing
     per-language mirror, and the changelog (Phase 4). Pushing before the docs
     are synced is a violation — fix the docs and amend/push again before
     releasing.

3.9. **The two installers must stay in sync.** When the installers ship,
     `scripts/install.sh` (Unix/Termux) and `scripts/install.bat` (Windows)
     are the SAME product on two OSes. A change to one MUST update the other
     in the SAME commit: same commands/modes (`install`, `update`,
     `reinstall`, `uninstall`), same keep-data prompt (`uploads/` + `data.db`),
     and the same `videohub` launcher passthrough. Never ship a mode in one
     installer without the other.

3.10. **Rollback is a NEW patch release, never history rewriting.** If a
     released change is broken, do NOT amend history, force-push, or move a
     published tag (Section 3.4). Use `git revert` on the offending commit,
     re-run Phases 2–4, bump a patch version, and release the revert through
     the normal workflow. If `data.db` was damaged, restore it from the most
     recent backup before reverting (Section 6).

3.11. **Migrations are forward-only and additive.** Never drop or rewrite
     existing data in a migration; bump `PRAGMA user_version` by exactly one
     per migration. A destructive migration is a `breaking` change (Phase 1.2)
     and needs explicit user approval.

3.12. **Every release starts as an unstable preview.** Never publish a release
     as stable without first shipping it as an unstable preview and getting
     user confirmation (Phase 9). A version that was never previewed is not
     released.

---

## 4. Subagents are optional helpers (never required)

The workflow NEVER depends on subagents (tác nhân phụ). Every phase — from
Phase 0 intake through Phase 19 triage — is performed directly by the main
agent, with no delegation step that can block progress. Subagents, if the
current tool happens to provide them, may be used as OPTIONAL helpers for
research or verification only. When they are used, these rules still apply:

- The workflow must remain fully executable without any subagent; never
  reorder, gate, or skip a phase because one is unavailable.
- Verify every subagent result yourself before relying on it: re-read files it
  claims to have written, re-run critical commands, and check the
  highest-severity findings against the code. Its output is a claim, not a
  fact.
- Never delegate a final decision — version choice, release go/no-go, whether
  to close an issue, whether a finding blocks a release. Those stay with you /
  the user.
- A subagent that writes code must still NOT run `git commit`, `git push`,
  `git tag`, or any `gh release` / `gh issue close` command; those stay in the
  main thread, done by you.

---

## 5. Feedback / Issues handling

User feedback goes to **GitHub Issues** — there is no in-app feedback folder
anymore. The app's "Góp ý" button links directly to the Issues page of this
repository.

Rules:

- Check the GitHub Issues for this repository when starting work and after
  every change: `gh issue list --repo fhfjjfjd/video-player-php`.
- **Read every open issue COMPLETELY before acting** — never act on the title
  alone. A full read is: title, full body, ALL comments
  (`gh issue view <n> -c`), and the issue's live metadata — `createdAt` /
  `updatedAt`, labels, reactions (upvotes signal demand), `assignees`,
  `milestone`, and linked PRs / `closedByPullRequestsReferences` (a "fixes #n"
  PR may mean the work already shipped). Search for duplicates with the
  report's own keywords before deep-reading
  (`gh issue list --repo fhfjjfjd/video-player-php --search '<terms>
  in:title,body' --state all`); close obvious duplicates with a polite note
  and a link to the canonical issue.
- **Interpret the report, don't just transcribe it.** Treat the reporter's
  words as symptoms, not a diagnosis: "không xem được" could mean the list,
  the watch page, or the stream — three different fixes. Map vague phrases to
  surfaces (login → `src/accounts.php` + session/verify flow; upload →
  `MAX_UPLOAD_SIZE` / `uploads/`; playback → `/api/media` HMAC token + HLS;
  listing → the 10 s video-list cache). When evidence is missing, the single
  highest-value fact is the exact error text; a screenshot of the watch page
  plus the URL bar beats a paragraph.
- **Clarify vs. act.** Ask ONE targeted, polite clarifying question (in the
  reporter's language, with ready-made options a non-technical user can pick)
  when the surface is ambiguous or two plausible readings need different
  fixes. Never guess a version or a surface — ask. When the interpretation is
  unambiguous and low-risk, act on what is written. Never let a vague report
  sit unasked and unfixed.
- **Triage cadence.** Acknowledge every report within about 48–72 h (a label
  or one sentence beats silence) and hold a fixed weekly triage slot instead
  of drip-checking daily. Publish that this is a one-person project with
  replies in days, not hours. Security-related reports (auth, injection,
  secret exposure) are handled immediately — never discuss exploit details
  publicly.
- **Stale policy.** An issue awaiting a reporter's answer is stale after 14
  days of silence; an untriaged issue after 90 days. Send ONE gentle bump
  ("still relevant? otherwise I'll close it — you can always reopen"), then
  close after 7 more silent days. Never close a confirmed bug, an in-progress
  item, or a shipped-feature tracker for inactivity alone.
- **Reuse short reply templates** for the four common outcomes — request-more-
  info, duplicate, decline (wontfix), and shipped. Each is: thanks → one-line
  reason → next step → how to reopen. Keep a copy in BOTH English and
  Vietnamese (e.g. as GitHub saved replies, with a versioned copy in the
  repo) so every response is warm, plain, and consistent.
- If an issue asks for a fix/feature, implement it following the workflow
  above (Phase 0 → 10).
- **Re-read the issue after every compile.** Issues can change while you work:
  the reporter may edit the body, add comments, attach screenshots, or answer
  your clarifying question. Each time you compile or build the code (Phase 2,
  `php -l`, etc.), run `gh issue view <number> -c` again and check the body,
  every comment, `updatedAt`, and any new labels. If anything changed, fold
  the new information into the work before continuing — building against your
  guess instead of the reporter's answer ships the wrong fix. Never finish
  against a stale version of the request. Re-reading is ONLY to catch changes
  — it is NOT the signal to close.
- **Replies must be polite and clear.** Always answer the reporter with
  courtesy and respect: greet/thank them, state the answer in plain language,
  explain the reason briefly, and confirm the outcome. Never give a curt,
  robotic, or perfunctory answer ("just for the sake of saying"). Match the
  reporter's language when you can; a Vietnamese reporter gets a warm,
  natural Vietnamese reply, not a cold or mechanical one.
- **Reply ENTIRELY in the reporter's language.** A question, request, or
  description written in a given language MUST get a reply in that same
  language — a Vietnamese comment gets a reply written 100% in Vietnamese with
  no English words mixed in, an English comment gets an English reply. Never
  sprinkle words from another language into a reply.
- **Formal first person when apologizing.** When a reply contains an apology,
  use the formal first person ("tôi" in Vietnamese) — never the casual "mình".
  Keep this respectful, formal register in every official reply.
- **Never reveal a real identity.** If a reporter asks "who are you" or
  anything similar, answer as the project itself using the PROJECT's name
  (e.g. "Video Player" / "VideoPlayer"), never a real personal name, persona,
  or any information that could identify a real person.
- **Closing is the final step.** Close the issue ONLY in Phase 10.1, AFTER the
  release work is done: compile, tests, docs, security review, changelog,
  version bump, commit, push, tag, preview, and stable release (Phases 2 → 10).
  Never close after merely re-reading the issue, and never close before the
  change is committed, pushed, and released. The closing comment is the
  permanent record a future searcher will read, so make it count: thanks,
  what changed in plain language, which version ships it, how to test, and
  how to reopen:
  `gh issue close <number> --comment "<thanks + what changed + version + how to reopen>"`.
  Write the reply entirely in the reporter's language so they can read it.
- Never reopen or "fix" issues that are already closed.

---

## 6. Rollback and data safety

6.1. **When to roll back.** A release is rolled back when it breaks existing
     behavior, breaks the install, corrupts data, or introduces a security
     problem. Rollback is a documented recovery path, not a way to redo
     history.

6.2. **How to roll back.** Because a release is documentation + version +
     notes (no binaries), rolling back means shipping a NEW patch release that
     reverts the change:
     1. `git revert <offending-commit>` locally.
     2. Restore `data.db` from the most recent backup BEFORE the revert if the
        change touched the database.
     3. Re-run Phases 2–4 (checks, docs, changelog entry for the revert).
     4. Bump a patch version and release it through Phases 5–9.
     Never move a published tag, never force-push `main`, and never edit a
     published release's content (Section 3.4).

6.3. **Data safety.** `data.db` lives in the app data directory, never in git.
     The keep-data prompts in `scripts/install.sh` / `scripts/install.bat`
     protect `uploads/` and `data.db` on reinstall/uninstall. Before any
     migration that touches existing tables, back up `data.db` and test the
     migration against a copy first (Phase 2.4).

---

## 7. Changelog

`CHANGELOG.md` (and its per-language mirrors, e.g. `CHANGELOG.vi.md`) is a
first-class doc in this repo. It follows Keep a Changelog conventions:
`Added` / `Changed` / `Fixed` / `Removed` per release, a `## [Unreleased]`
section at the top, and one dated `## [<version>] - <YYYY-MM-DD>` section per
released version. Every change MUST update it in the same commit as its
README sync (Phase 4). The release notes in Phase 9 must match the changelog
entry for the version being shipped.

---

## 8. Coding standards (PHP)

These are mandatory for every line of PHP written in this repo
(`index.php`, `src/`). The router and all backend files follow them.

### 8.1. Language level and style

8.1.1. Every PHP file begins with `declare(strict_types=1);`. No exceptions.
8.1.2. Follow PSR-12: 4-space indentation, braces on the same line, one
      statement per line, no trailing whitespace, UTF-8 files without BOM.
8.1.3. Type everything: typed parameters and return types on every function;
      document `array` shapes where a generic type is impossible.
8.1.4. Keep functions small (aim ≤ 40 lines) and files focused (aim ≤ 400
      lines). When a file grows, extract a module under `src/` with a single
      responsibility — the way `src/accounts.php` keeps the rendered forms
      and the JSON API from drifting apart.
8.1.5. Never add decorative comments. A short file-header docblock, and a
      comment only where the logic is non-obvious (e.g. WAL tuning, the
      `ob_gzhandler` / `Content-Length` interplay), are fine; everything else
      is noise.
8.1.6. No dead code, no commented-out code, no debug leftovers
      (`var_dump`, `print_r`, `die`/`exit` in library code). Remove what you
      touch.

### 8.2. Database access

8.2.1. Every SQL statement uses PDO prepared statements with bound
      parameters. Never concatenate user input into a SQL string.
8.2.2. All database access goes through the functions in `src/db.php`. Never
      open a second PDO connection or issue raw SQL from the router, views,
      or API handlers.
8.2.3. The only DDL lives in the `db_init()` migrations in `src/db.php`
      (see Phase 2.4). No `CREATE` / `ALTER` / `DROP` anywhere else.
8.2.4. When user input is matched with `LIKE`, escape `\`, `%` and `_` exactly
      as `list_all_videos()` does — and still bind the value as a parameter.
8.2.5. Read with `PDO::FETCH_ASSOC`; never rely on column order or untyped
      fetches for logic.

### 8.3. Output escaping (XSS)

8.3.1. Every value that reaches HTML — from `$_GET`, `$_POST`, the database,
      or any other source — MUST be escaped with `e()`
      (`htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`) at the
      point of rendering. Never echo raw.
8.3.2. Never interpolate a value into a JavaScript context. If a page needs
      JSON inside a `<script>` tag, use `json_encode()` with `JSON_HEX_TAG`
      and `JSON_HEX_AMP`.
8.3.3. Never build HTML by string-concatenating unescaped data; use the
      `src/render.php` + `src/views/` templates and escape every field there.
8.3.4. Input validation is NOT a substitute for output escaping. Escape
      regardless of what validation passed.

### 8.4. Forbidden patterns

8.4.1. No `eval()`, no `assert()` for program logic, no `create_function()`,
      no `unserialize()` on user-controlled data, no `include`/`require` of
      user-controlled paths.
8.4.2. No shell execution unless truly necessary. Where it is (e.g. FFmpeg
      thumbnail extraction), every argument MUST be `escapeshellarg()`-quoted
      and derived from validated values only. Never use backticks or
      `shell_exec` with interpolation, and never pass raw user input to a
      shell.
8.4.3. No `@` error-suppression on security- or data-critical operations
      (auth, DB writes, token verification, file moves). Where a legacy `@`
      exists, leave a comment explaining why it is safe.
8.4.4. Keep `error_reporting(E_ALL)` + `display_errors=0` + `log_errors=1`
      exactly as `src/bootstrap.php` sets them. Never raise `display_errors`
      and never silence `E_ALL`.

---

## 9. Security hardening baseline (OWASP-informed, mandatory)

Every change is reviewed against this list in Phase 3. This is the security
contract of the app — a change may not weaken it.

### 9.1. Secrets and credentials

9.1.1. Secrets come ONLY from environment variables or the `.media-secret`
      file (created with mode `0600`): `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`,
      `MAIL_PASS`, `MAIL_FROM`, `MAIL_ENCRYPTION`, `MEDIA_URL_SECRET`,
      `MEDIA_SECRET_FILE`. Never hardcode, never commit, never echo a value.
9.1.2. Never write a secret into a log, an error message, a response, or a
      doc example. The README may show placeholder variable names — never
      real values.
9.1.3. If a secret is ever exposed (pushed, printed, logged), treat it as
      compromised: rotate it immediately, then follow Section 14.
9.1.4. `data.db` and `uploads/` never leave the machine via git;
      `.media-secret` is gitignored.

### 9.2. Authentication and authorization

9.2.1. Identity always derives from the validated session token
      (`current_user_id()` → `validate_session_token()`). Never accept a
      user-supplied id/role/email as proof of identity.
9.2.2. Session cookies keep `HttpOnly`, `SameSite=Lax`, `Path=/` and the
      30-day expiry. Never weaken these flags or make the cookie readable by
      JavaScript.
9.2.3. Token and code comparisons MUST use constant-time functions
      (`hash_equals()`). Never compare secrets with `==` or `===`.
9.2.4. Every sensitive action goes through `authz_can()` / voters
      (`VideoVoter::DELETE`, owner-only + `admin` role). Never inline a raw
      ownership check that bypasses the voter layer.
9.2.5. Passwords are stored as PBKDF2 (100 000 iterations) or legacy bcrypt
      and verified via `verify_password()`. Never introduce MD5/SHA1/plaintext
      password handling.
9.2.6. Email verification codes are stored hashed (HMAC), are 6 digits,
      expire after 10 minutes, and resends are rate-limited (bucket
      `resend_verification`).
9.2.7. State-changing requests (POSTs that mutate data) rely on the
      `SameSite=Lax` session cookie as their CSRF defense. Never accept a
      cross-origin state-changing request without protection: if `SameSite`
      is ever weakened or bypassable, verify the `Origin` / `Referer` header
      against the app's own origin before acting, or require a per-session
      CSRF token. Cookie presence alone is NOT CSRF defense.
9.2.8. Never enable permissive CORS (`Access-Control-Allow-Origin: *`) or
      `Access-Control-Allow-Credentials: true` on any endpoint; the API is
      same-origin by design.

### 9.3. Input validation

9.3.1. Every payload — forms AND JSON API — is validated server-side with
      `validate_payload()` and the Symfony Validator constraints. Client-side
      checks are cosmetic only.
9.3.2. New fields or endpoints must add constraints in `src/validation.php`
      and use them from BOTH the rendered forms and the JSON API (the
      `src/accounts.php` pattern). Never validate in only one surface.
9.3.3. Never trust `$_FILES` metadata (name, type) as a security boundary —
      verify size, extension and content-type server-side, and never accept a
      client-declared MIME type as the sole gate.

### 9.4. Rate limiting

9.4.1. Every endpoint keeps its `rate_limit_apply('<bucket>')` call. Never
      remove one to "fix" a slow response — slowness is a caching/query
      problem, not a license to drop protection.
9.4.2. Never raise a sensitive limit (register, login, verify, upload)
      without explicit approval; these limits are the main brute-force
      defense.
9.4.3. `client_ip()` reads `REMOTE_ADDR` only. Never trust `X-Forwarded-For`,
      `CF-Connecting-IP` or similar headers unless the app is behind a
      trusted proxy AND that behavior is explicitly configured.
9.4.4. Rate-limited responses keep the `Retry-After` and `X-RateLimit-*`
      headers.

### 9.5. Media and uploads

9.5.1. Uploads: enforce `MAX_UPLOAD_SIZE` (1 GB), require `video/*`
      content-type, allowlist the extension (see `mime_for_path()`), store
      under a random UUID name, and never use the user-supplied filename on
      disk.
9.5.2. Media and thumbnails are served ONLY through `/api/media` with an
      HMAC token verified via `hash_equals()` that rejects `..`, `/` and `\`
      and expires after one day. Never add a static route that serves
      `uploads/` directly, and never serve a file without a valid token.
9.5.3. Upload directories must never execute code: no `.php` files may ever
      be written into `uploads/`, and the directory must not be
      world-writable.
9.5.4. Deleting a video removes the DB row AND the stored file(s); never
      leave orphaned files behind.

### 9.6. Response hardening

9.6.1. `apply_security_headers()` stays the first thing the front controller
      does. Never weaken the CSP: `style-src 'unsafe-inline'` is allowed as
      shipped, but never add `'unsafe-inline'` or `'unsafe-eval'` to
      `script-src` and never use `*` in `default-src`.
9.6.2. Production responses never contain stack traces, SQL, or filesystem
      paths. JSON errors return a plain message via `err()`; anything else is
      a bug.
9.6.3. Never add headers or code that leak the PHP version, real paths, or
      internal details.

### 9.7. Dependencies

9.7.1. `composer.lock` pins every dependency and `vendor/` is committed so
      the app runs without Composer. Keep them in sync with any
      `composer.json` change IN THE SAME commit.
9.7.2. Prefer the stdlib and already-bundled Symfony components over new
      packages. Every new dependency is a deliberate, documented decision
      (changelog entry required).
9.7.3. Never patch files inside `vendor/` by hand; if a vendored fix is
      unavoidable, document it and make it reproducible, or it will be lost
      on the next update.
9.7.4. When Composer exists on this machine, run `composer audit` before any
      release that touches dependencies (Phase 2.7); never ship a dependency
      with a known advisory against `composer.lock` without an approved,
      documented exception (CVE id + approval).

---

## 10. Definition of done (pre-commit checklist)

A task is NOT done until ALL of these are true. Run through them before
Phase 6 (commit):

1. Behavior matches the plan written in Phase 1.1.
2. `php -l` passes on every touched PHP file (when `php` exists on this
   machine).
3. No new secrets; no sensitive data (emails, tokens, codes, passwords)
   added to logs or responses.
4. Every new/changed endpoint is server-side validated, rate-limited, and
   authz-checked.
5. Every rendered value is escaped via `e()`; no raw `echo` of
   user-controlled data.
6. All SQL uses prepared statements; no DDL outside `db_init()`.
7. `README.md` and ALL mirrors, plus `CHANGELOG.md` and ALL mirrors, are
   updated in this change.
8. If `scripts/` changed, both installers (`install.sh` + `install.bat`) and
   the start scripts are in sync.
9. No `data.db*`, `uploads/`, `cache/`, `node_modules/`, `.opencode/` or
   secret files are staged.
10. The Phase 6.4 self-review of the staged diff was completed for
    non-trivial changes and found no open blockers.
11. Commit message uses a repo prefix (`Add:` / `Fix:` / `Change:` /
    `Remove:` / `Docs:`).
12. If dependencies changed, `composer audit` is clean (when Composer
    exists) or an approved exception with the CVE id is documented.

---

## 11. Testing rules

11.1. If a test suite exists (a `tests/` directory, `phpunit.xml*`, or a
      `composer test` script), new logic MUST ship with tests for its success
      and failure paths, and the FULL suite must pass before release
      (Phase 2.3).
11.2. When no suite exists, the `php -l` syntax check (Phase 2.3) on every
      touched file is the minimum bar.
11.3. Before every preview release, smoke-test the core flows against a local
      instance: register → verify → login → upload → watch → delete, on BOTH
      the rendered forms and the JSON API. Note any manual steps you could
      not run.
11.4. Never write tests that require a live SMTP server or external network;
      `src/mailer.php` is the only place that touches SMTP, keep it that way.

---

## 12. Performance non-regression

These properties are load-bearing; a change must never silently undo them.

12.1. Text responses (HTML, JSON, CSS, JS, SVG, XML, WASM) stay
      gzip-compressed with `Vary: Accept-Encoding`; media streaming is NEVER
      compressed and always supports Range requests (`206` / `Content-Range`,
      256 KB chunks).
12.2. The shared video-list cache keeps its short TTL (10 s) and is
      invalidated instantly on upload and delete.
12.3. SQLite tuning (`WAL`, `synchronous=NORMAL`, `temp_store=MEMORY`,
      `foreign_keys=ON`) and the existing indexes stay intact. New queries
      must be indexed; never introduce per-request scans of `videos` in a hot
      path.
12.4. The start scripts keep the OPcache + JIT flags and the multi-worker
      launch (`PHP_WORKERS`). `hls.min.js` stays lazy-loaded and served with
      `Cache-Control: public, max-age=31536000, immutable`.
12.5. Optimize by measuring, never by guessing — and never trade a security
      property (Section 9) or a cache-invalidation guarantee for a marginal
      speedup.

12.6. The start scripts run multiple PHP workers (`PHP_WORKERS`). SQLite's
      WAL mode is what makes concurrent readers plus one writer safe — never
      change the journal/sync modes and never open a second write connection.
      Smoke-test (11.3) with the default multi-worker config, not just a
      single worker.

---

## 13. Privacy and data handling

13.1. Personal data (email, username) is exposed only where the feature
      requires it. `/api/me` is the sole surface that returns the viewer's
      own email; video listings and watch pages never include emails.
13.2. Never log, echo, or store in cache: passwords, password hashes beyond
      the DB, verification codes, session tokens, media tokens, or raw email
      addresses unless the feature's storage requires them.
13.3. Email verification codes and session tokens are stored hashed/signed,
      never plaintext.
13.4. Deletion is final and complete: deleting a video removes the row AND
      the file; never leave copies behind in the cache (keep
      `clear_video_list_cache()` wired to upload and delete).
13.5. Before any destructive operation on real data (migration, revert
      touching `data.db`, manual cleanup), make a backup first (Section 6)
      and verify it restores.

---

## 14. Incident response

14.1. **Classify.** `critical` — authentication bypass, authorization bypass,
      RCE, SQL injection, data leak, secret exposure. `high` — broken
      release, broken install, data loss. `medium` — degraded behavior with
      a workaround. `low` — cosmetic.
14.2. **Critical incidents:** (1) stop the affected surface if it is
      exploitable in the wild, (2) rotate any exposed secrets, (3) preserve
      evidence (logs, timestamps, affected users) without altering it,
      (4) notify the user immediately, (5) ship a patch fix through the
      NORMAL workflow (patch bump, Phases 2 → 10). Never fix an exploit by
      moving tags or editing history.
14.3. **High / medium:** roll back via Section 6 if the release is broken;
      otherwise fix forward with a patch release.
14.4. **After any incident:** add a regression guard (test or explicit code
      rule), document the incident honestly in the changelog, and update this
      file if a rule was missing. Never hide an incident to "protect the
      project" — transparency is the policy.

---

## 15. Maintaining this file

AGENTS.md is living policy — it is read on every session, so every line costs
context and every rule must be accurate.

15.1. **Changes to AGENTS.md are code.** They go through the same review and
      commit discipline as any change: staged with `git add -A`, reviewed,
      committed with a `Docs:` prefix. A rule with no enforcement point is a
      bug — every MUST must map to a phase, checklist item, or command.
15.2. **Keep cross-references correct.** After any structural edit, verify
      section and phase numbers still resolve
      (`grep -n '^## ' AGENTS.md`). A dangling reference erodes the whole
      document's authority.
15.3. **Never let it contradict the repo.** If code, commands, or layout
      change and make a rule stale, fix the rule in the SAME commit as the
      change. An AGENTS.md that lies about the repo is worse than none.
15.4. **Stay strict, stay specific.** Prefer concrete commands, file paths,
      and constants from this repo over vague exhortations. When unsure a
      rule is true, verify against the code before writing it.
15.5. **Contradictions are fatal.** If two rules conflict, the stricter one
      wins and the conflict MUST be resolved in the same commit; never leave
      the file self-contradicting.
15.6. **Keep the agent rule files in sync.** `AGENTS.md` is read directly by
      Cline CLI, Codex CLI and OpenCode; `CLAUDE.md` (Claude Code) imports
      `AGENTS.md` via `@AGENTS.md`, and `.agents/rules/general.md` points
      Antigravity (CLI and IDE) at it, so all tools share one source of truth.
      Never duplicate rules into `CLAUDE.md`; Claude-specific instructions go
      below the import there, never in `AGENTS.md`.
