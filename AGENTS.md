# Agent Rules — Docs & Release Workflow

This project lives at `https://github.com/fhfjjfjd/video-player-php` (branch `main`).

These rules are MANDATORY. Follow them every time you complete a task, update
docs, or cut a release. Do not skip steps, do not reorder phases, and do not
rely on memory — re-read the relevant sections each time you start work.

**What this workflow does.** A task is finished by (1) checking whether the
backend runtime (PHP) is installed on this machine and running a syntax check
(`php -l`) on the backend files if it is, (2) updating the documentation,
(3) committing, (4) pushing, (5) bumping the version, (6) tagging, and
(7) creating a GitHub release. There is NO GitHub Actions trigger, NO artifact
download/verification, and NO binary upload. A release is created from docs and
version changes only and does not attach binaries. If the backend runtime is
missing on this machine, skip the syntax check entirely and just publish as
normal.

---

## 0. How this document is organized

- **Section 1** — documentation language rules (multilingual repo).
- **Section 2** — the release workflow, phase by phase (this is the normative
  process; follow it exactly).
- **Section 3** — absolute rules that apply at all times (never-broken
  invariants).
- **Section 4** — how to use subagents (tác nhân) in this workflow.
- **Section 5** — feedback / GitHub Issues handling.

Use **Section 2** as your checklist. Whenever the guide says "delegate to a
subagent", follow the instructions in **Section 4** first.

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

---

## 2. The release workflow

The workflow has **9 phases**. Phase 0–1 analyze the change, Phase 2 compiles
the backend on this machine only if a compiler exists, Phase 3 syncs the docs,
Phase 4 bumps the version, Phases 5–7 commit/push/tag, Phase 8 creates the
release, and Phase 9 closes out. Every phase must complete before the next one
starts. If any phase fails, do not proceed; fix and retry.

> **No GitHub Actions, no binary uploads.** This workflow never triggers a CI
> workflow, downloads artifacts, or attaches binaries to a release. The only
> compilation is done locally (Phase 2) and only when a compiler for the
> backend language is installed on this machine.

### Phase 0 — Intake and reconnaissance

0.1. **Check open GitHub Issues.** Run
     `gh issue list --repo fhfjjfjd/video-player-php`. Read EVERY open issue
     completely (title AND body). Never act on a title alone.

0.2. **Confirm repository state.** Run `git status`, `git log --oneline -10`,
     and `git remote -v`. Confirm you are on `main`, the working tree matches
     your expectations, and there are no unexpected local commits.

0.3. **Confirm the latest release.** Run `gh release list --repo
     fhfjjfjd/video-player-php` and note the newest tag. You will need it in
     Phase 4 to pick a correct version bump.

0.4. **Inventory the affected code.** If the task touches more than one file,
     delegate to an `explore` subagent to produce a concise map of what was
     changed, what calls what, and what could break (see Section 4.1). This
     map becomes the input to Phase 1.

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
     Every classification goes through the same workflow (Phase 2 → 8). The
     classification only picks the version bump size in Phase 4.

1.3. **Draft the version bump** based on the classification (see Phase 4 for
     exact rules). Do not edit `composer.json` yet — first confirm the current
     version and the latest tag are consistent (Phase 0.3).

1.4. **Create a todo list** of the remaining phases (2 → 9) and mark Phase 2
     as in-progress. Track every phase in the todo list; never silently skip
     one.

### Phase 2 — Compilation check and local build (conditional)

2.1. **Determine the backend language.** This project's backend is PHP. The
     language marker is `composer.json` at the project root (the router is
     `index.php`, the rest of the app lives under `src/`). If there is no
     backend at all, skip this phase.

2.2. **Check for a compiler/toolchain on this machine.** For the detected
     language, check whether the toolchain is installed, e.g.:
     `command -v php` (PHP), `g++`/`clang++` (C++), `cargo` (Rust), `go`
     (Go), `python3` (Python), `bun`/`node` (Node), etc.

2.3. **If the toolchain IS installed → run the syntax check on this machine.**
     PHP is interpreted, so there is nothing to compile. Run a syntax check
     (`php -l`) on the router and every backend file:
     `php -l index.php` and `php -l` on each file under `src/`
     (`find src -name '*.php' -exec php -l {} +`). If the syntax check fails,
     fix the code and retry; do not proceed to a release on a failed check.

2.4. **If the toolchain is NOT installed → skip the syntax check.** Do NOT
     install a compiler/interpreter, do NOT trigger CI to build, and do NOT
     worry about missing binaries. Just proceed to the rest of the workflow
     ("đăng như bình thường").

2.5. **Never trigger GitHub Actions and never upload build artifacts.** No
     `gh workflow run`, no polling of runs, no `gh release upload`, no magic-
     byte checks. Even if a local build succeeded, the release is created
     without attaching binaries.

### Phase 3 — Documentation update (mandatory)

3.1. **Documentation is never optional.** Every change — code, config, or
     docs — MUST update `README.md` AND ALL existing per-language mirrors
     (e.g. `README.vi.md`) in the SAME commit.

3.2. List every doc file that ships in the repo before editing: run
     `ls README*.md` (or `glob "*.md"`) and update each one. Never leave a
     mirror stale.

3.3. If multiple translations are needed and the change is large, delegate the
     translation of each mirror to a `general` subagent (one per language),
     then review the results yourself before committing.

3.4. Keep every doc file in a single language. Never interleave languages.

3.5. Do the doc sync BEFORE Phase 5 (commit). Check the staged set in 5.3
     includes every doc file; if a doc was not modified, STOP and update it
     before committing.

### Phase 4 — Version bump

4.1. **Read the current version** in `composer.json` (`"version"` field).

4.2. **Compute the target version.** It must be STRICTLY HIGHER than the
     latest release tag from Phase 0.3. Use these rules:
     - `chore` / `bugfix` (non-visible) → patch bump: `x.y.z+1`.
     - `feature` → minor bump: `x.(y+1).0`.
     - `breaking` → major bump: `(x+1).0.0`.
     - If the previous tag was never reflected in `composer.json` (the file
       lags behind the latest tag), still bump from the TAG, not from the
       file.

4.3. **Apply the bump** by editing the `version` field in `composer.json`.

4.4. **If you are unsure** which version to use, STOP and ask the user. Never
     guess a version number.

### Phase 5 — Commit

5.1. Run `git status` and `git diff` once more to confirm exactly what will be
     staged.

5.2. **Never stage or commit** `data.db*`, `uploads/`, `node_modules/`,
     `cache/`, `.opencode/`, or any secret/credential file. `vendor/` is
     bundled (no Composer at install time) and IS committed. If `git status`
     shows any of the ignored paths above, something is wrong — investigate
     before staging.

5.3. Stage with `git add -A`, then review the staged set with
     `git diff --cached --stat` and confirm only intended files are present.

5.4. Commit with a concise message matching the repo style, using one of these
     prefixes: `Add:`, `Fix:`, `Change:`, `Remove:`, `Docs:`.
     Example: `Docs: sync README for version 4.3.0`.

### Phase 6 — Push

6.1. Push to origin: `git push origin main`. Confirm the push succeeded and
     the remote `main` is now ahead by exactly one commit.

### Phase 7 — Tag

7.1. Create the tag matching the version from Phase 4:
     `git tag v<version>` (e.g. `git tag v4.3.0`).

7.2. Push the tag: `git push origin v<version>`.

7.3. If the tag was pushed earlier and needs to move, you may delete and
     recreate it ONLY while no release exists for it yet:
     `git tag -d v<version> && git push origin :refs/tags/v<version>` then
     redo 7.1–7.2. Once a release is published, never touch the tag
     (Section 3.4).

### Phase 8 — Create the release

8.1. Create the release against the tag from Phase 7:
     ```bash
     gh release create v<version> --repo fhfjjfjd/video-player-php \
       --title "v<version> - <short summary>" \
       --latest \
       --notes "<what changed>"
     ```
     **No binaries are attached.** Do not upload, link, or reference any
     build artifact — even if a local build succeeded in Phase 2.
     **Release titles and notes MUST be written in English.** Do not use
     Vietnamese or any other language in a release title or its notes — the
     notes summarize the changes in this version for a global audience.

8.2. Record the release URL (from the `gh release create` output or
     `gh release view v<version> --repo fhfjjfjd/video-player-php --json url
     --jq .url`).

### Phase 9 — Close-out

9.1. **Close fixed issues.** If any open GitHub Issue was addressed by this
     work, reply on the issue explaining what was done (in a language the
     reporter can read, matching theirs if possible) and close it:
     `gh issue close <number> --comment "<explanation>"`.

9.2. **Confirm a clean tree.** Run `git status` and confirm nothing is left
     uncommitted or untracked.

9.3. **Report to the user.** Give a concise summary: what changed, whether the
     backend was compiled locally (or skipped because no compiler exists), the
     new version, the tag, and the release URL.

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
     language, or fixing typos, is normal and does not count as "modifying
     the release". Never re-upload binaries or repoint the tag of a published
     release.

3.5. **Always use the GitHub CLI (`gh`) for release operations.** It is
     authenticated; do not fall back to unauthenticated curl for release
     management.

3.6. **Keep the local repo clean.** Never commit secrets, credentials,
     databases (`data.db*`), uploads (`uploads/`), `node_modules/`, caches
     (`cache/`), `.opencode/`, or any secret/credential file. Note: `vendor/`
     is bundled on purpose (the app runs without Composer) and IS committed.

3.7. **Never guess a version.** When in doubt, ask the user.

3.8. **Never push a change without its docs.** A change that alters behavior,
     config, commands, or CI MUST also update `README.md` and every existing
     per-language mirror (Phase 3). Pushing before the docs are synced is a
     violation — fix the docs and amend/push again before releasing.

3.9. **The two installers must stay in sync.** When the installers ship,
     `scripts/install.sh` (Unix/Termux) and `scripts/install.bat` (Windows)
     are the SAME product on two OSes. A change to one MUST update the other
     in the SAME commit: same commands/modes (`install`, `update`,
     `reinstall`, `uninstall`), same keep-data prompt (`uploads/` + `data.db`),
     and the same `videohub` launcher passthrough. Never ship a mode in one
     installer without the other.

---

## 4. Using subagents (tác nhân) in this workflow

Subagents help reduce context usage and parallelize work. Use them for
research and verification, NEVER for making final decisions.

4.1. **`explore` subagent** — for fast codebase searches and summaries. Use in
     Phase 0.4 to map affected code, and anytime you need to find files or
     symbols quickly. Specify a thoroughness level (`quick` / `medium` /
     `very thorough`) so it knows how deep to go.

4.2. **`general` subagent** — for multi-step analysis and parallelizable
     work. Use in Phase 3.3 (translations) and for an independent review of a
     non-trivial change before committing.

4.3. **Delegation rules:**
     - Give the subagent a fully self-contained prompt: what to do, which
       commands/files to use, and EXACTLY what to return. The subagent starts
       with fresh context — it cannot see our conversation.
     - Tell it whether you expect code changes or research only.
     - Launch independent subagents in parallel (one message, multiple tool
       calls) whenever they do not depend on each other.
     - Once a task is delegated, do not duplicate that work yourself; wait for
       the result and continue with non-overlapping tasks.
     - **Never delegate a phase decision** (version choice, release go/no-go,
       whether to close an issue). Those stay with you / the user.

---

## 5. Feedback / Issues handling

User feedback goes to **GitHub Issues** — there is no in-app feedback folder
anymore. The app's "Góp ý" button links directly to the Issues page of this
repository.

Rules:

- Check the GitHub Issues for this repository when starting work and after
  every change: `gh issue list --repo fhfjjfjd/video-player-php`.
- Read every open issue COMPLETELY (title + body) before acting. Never act on
  the title alone.
- If an issue asks for a fix/feature, implement it following the workflow
  above (Phase 0 → 9).
- **Re-read the issue after every compile.** Issues can change while you work:
  the reporter may edit the body or add comments. Each time you compile or
  build the code (Phase 2, `php -l`, etc.), run
  `gh issue view <number>` again and check the body, comments, and
  `updatedAt`. If anything changed, fold the new information into the work
  before continuing. Never finish against a stale version of the request.
  Re-reading is ONLY to catch changes — it is NOT the signal to close.
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
- **Closing is the final step.** Close the issue ONLY in Phase 9.1, AFTER the
  whole workflow is done: compile, docs, version bump, commit, push, tag, and
  release (Phases 2 → 8). Never close after merely re-reading the issue, and
  never close before the change is committed, pushed, and released. Then reply
  on the issue explaining what was done and close it:
  `gh issue close <number> --comment "<explanation>"`. Write the reply in any
  language (matching the issue's language if you can) so the reporter can read
  it.
- Never reopen or "fix" issues that are already closed.
