# Test Results

## 2026-09-12 M1 final acceptance

- Focused audit/settings/navigation regression: PASS, 33 tests / 217 assertions. Branch administration regression after the browser-found translation defect: PASS, 10 / 77.
- SQLite full suite: PASS, 142 tests / 1,096 assertions on PHP 8.4.21; PASS, 142 / 1,096 on PHP 8.5.8.
- Isolated MySQL: MySQL 8.4.11, InnoDB, fresh migrations PASS; full suite PASS, 142 / 1,096. The task-owned server was stopped afterward and no shared database was changed.
- `npm run build`, Pint, `composer audit`, `npm audit`, route listing, documentation validation, and `git diff --check`: PASS. Dependency audits found zero known vulnerabilities. Vite emitted only the optional `fontaine` optimization notice.
- Real browser at 910px tablet width: Arabic `lang=ar`, `dir=rtl`, no page-level horizontal overflow, active navigation and skip target present, no unknown settings action/reason labels, no untranslated branch-settings key, and zero console warnings/errors. Earlier T32 English/LTR and same-page locale switching remain accepted.
- Final documentation validator checked 31 Markdown files with 0 errors and two historical placeholder-marker warnings in the execution ledger/evidence archive.

Boundary: M1 local acceptance only. M2–M6 and production TLS, monitoring, backup/restore, deployment, and operational readiness are not claimed.

## 2026-09-12 T30 and T32 final acceptance

- T30 isolated MySQL 8.4.11/InnoDB: migrations PASS; focused 46 tests / 389 assertions; full 138 / 1,059.
- T30 SQLite focused: 46 tests / 389 assertions; Pint and diff checks PASS.
- T32 focused: 25 tests / 233 assertions PASS.
- T32 real browser: platform provisioning and status lifecycle exercised; Arabic/English branch-settings locale switch remained at `/app/branches/1/settings` with correct RTL/LTR.
- Integrated `codex/first`: focused 54 tests / 444 assertions; full 141 / 1,077; Pint and `git diff --check` PASS.
- Temporary T32 SQLite/seed artifacts were not committed. Task-owned runtime was stopped. PHP 8.5 remains unverified.

## 2026-09-12 T21-T23 integration acceptance

- Focused: `php artisan test --filter='StaffInvitationTest|BranchAdministrationTest|AuditLogViewTest'` — PASS, 23 tests / 188 assertions.
- Full: `php artisan test` — PASS, 103 tests / 691 assertions.
- Environment: process-local SQLite `:memory:`, empty `DB_URL`, array sessions, `SESSION_CONNECTION` unset.
- `php vendor/bin/pint --test` — PASS.
- `npm run build` — PASS; optional fontaine notice only.
- `python tools/validate_documentation.py` — PASS, 0 errors / 2 existing placeholder warnings.
- `git diff --check` — PASS.

Initial focused run exposed only test setup defects in the invitation login scenario; the owner session was explicitly logged out and the login request now has the login-page referer. Browser is DEFERRED_BY_USER. MySQL migration/locking and PHP 8.5 are unverified.

## 2026-09-12 T18-T20 integrated feature wave

Three requested Luna/xhigh workers delivered complete features in isolated worktrees from `bb5f883`. Integrated T18 `ee9a35b` (owner all-active-branch access), T19 `7c6e171` plus `401a235` (existing non-owner staff status and per-row validation correction), T20 `3066663` (fixed branch assignment management). T20 worker later amended its commit to `95e99b6` for SQL-null assertion handling; coordinator applied that correction directly. A subsequent worker-only `f9803fa` flash-key consistency amendment was not integrated: the tested original controller/view already use matching status keys. Shared audit migration, route composition, owner navigation and canonical documentation are coordinator-owned.

Delivered: active owners list/select/read all active own-tenant branches; ownership revocation clears selected context unless an independent permitted staff assignment remains. Owners manage existing non-owner staff status at `/app/staff` and fixed branch roles/access at `/app/assignments`. All owner accounts remain protected. Administration requires reason codes, expected-state conflict checks, scoped transaction locks and atomic successful-change audit. No user creation, invitation delivery, owner assignment/transfer, platform access or arbitrary permission maps.

Review found and corrected Query Builder misuse, query callback type mismatch, JSON audit serialization, per-row old-input contamination, and generic HTML409 presentation. Added a localized conflict page. Tests were inspected as code as well as executed; a stale-branch regression's setup used the wrong query and was corrected before acceptance.

Verification in isolated integration checkout: focused `php artisan test --filter='OwnerBranchAccessTest|StaffManagementTest|BranchAssignmentManagementTest'` PASS 28 tests / 187 assertions after three initial failures were resolved. Full `php artisan test` PASS 80 tests / 503 assertions. Process-local SQLite `:memory:`, empty DB_URL, array sessions, SESSION_CONNECTION unset. `php vendor/bin/pint --test` PASS after scoped formatting; `npm run build` PASS (optional fontaine notice); documentation validator PASS 0 errors / 2 existing warnings; `git diff --check` PASS.

Status: DONE for local implementation and automated acceptance. Browser DEFERRED_BY_USER. MySQL migration/row-lock concurrency and PHP8.5 remain unverified; SQLite transaction rollback/expected-state tests are not evidence of MySQL concurrent behavior. Successful admin-change audit is delivered, not the complete denied/security audit program. Full M1/production acceptance remain incomplete. No shared service changes, main merge or push.

## 2026-09-12 T14-T17 owner-read integration acceptance

Reviewed and integrated T15 `bb6df9e` (backend), T16 `a451bca` (UI), T17 `96fbe14` (tests) from base `9c4ea91` into `codex/first`. Three workers used requested `gpt-5.6-luna` / `xhigh`; each returned owned-file commits and actual static-check results. Worker application tests were not accepted as evidence: their isolated worktrees lack vendor. Coordinator ran application verification after integration.

Review corrections: policy now queries persisted active tenant state instead of trusting a loaded model; regression updates tenant through the query builder to keep the passed model stale. Replaced default English pagination with localized native paginator links, retained empty-page recovery, and added an unknown-status label. Backend worker independently reviewed security tests after its implementation; no additional findings.

| Check | Actual result |
| --- | --- |
| `php artisan test --filter=TenantOwnerReadTest` | PASS: 15 tests / 68 assertions |
| `php artisan test` | PASS: 52 tests / 315 assertions |
| Automated environment | Process-local SQLite `:memory:`, empty DB_URL, array sessions; SESSION_CONNECTION unset |
| `php vendor/bin/pint --test` | PASS |
| `npm run build` | PASS; optional fontaine notice only |
| `php artisan route:list --path=app/tenant -v` | tenant.show under web/auth/tenant.access |
| `python tools/validate_documentation.py` | PASS: 0 errors / 2 existing placeholder warnings |
| `git diff --check` | PASS |

Checks exercise real migration constraints and policy/HTTP rendering, role spoof rejection, tenant-filtered selected fields and pagination, account/ownership/tenant revocation, Arabic/English and empty-page navigation. Blade rendering is automated HTTP evidence, not browser acceptance. Browser remains DEFERRED_BY_USER; this new migration has not been validated on MySQL or PHP 8.5. No shared services, main merge or push. No production/full-M1 acceptance.

## 2026-09-12 combined integration checkpoint

Recovered local integration at `6dafe21`: T10 `c3b2065` and T11 `c61f9e8` are merged into `codex/first`; T12 report `4093e8d` is integrated. Reviewed status enforcement, forward migration/backfill, mass-assignment exclusion, policy checks, locale middleware/controller and the failed-login interaction. Retained the pending focused fix that restores the validated locale after failed-login session invalidation; its regression checks guest state and the following Arabic RTL page.

Combined verification: `php artisan test` PASS (37 tests / 247 assertions), process-local SQLite `:memory:` and array sessions; `php vendor/bin/pint --test` PASS; `npm run build` PASS (optional fontaine notice); documentation validator PASS (0 errors / 2 existing placeholder warnings); `git diff --check` PASS.

Three Terra review agents were attempted (`staff_review_now`, `locale_review_now`, `owner_review_now`); all ended with usage-limit errors, so no independent agent review is claimed. Coordinator performed the code review. T12 is accepted as investigation only: owner representation and owner branch scope remain unresolved; no owner/platform implementation is authorized by that report.

Status: code integrated and automated checks PASS; T13 runtime acceptance remains PARTIAL. No combined T10/T11 browser or MySQL acceptance is claimed; historical T07/T09 evidence does not cover this wave. Next bounded acceptance is active login -> locale switch/invalid-login persistence -> select branch -> suspend user -> protected reload clears authentication/context. Then close T13 and define owner representation before dispatching the next implementation wave. Full M1, PHP 8.5 and production remain incomplete. No main merge, push or shared service change.

## 2026-09-10 T09 integration accepted

T09 integrated locally into codex/first from accepted 0c93d50, preserving coordinator 27ade78. The only conflict was .ai/TEST_RESULTS.md; both evidence sections were retained. Combined tests PASS: 26 tests/156 assertions; Pint PASS; docs validator PASS (0 errors/2 known warnings); diff check PASS. Application/tests/dependencies match accepted worker code. No additional build, MySQL or browser run was needed for this documentation-only conflict resolution. T09 integration DONE; full M1, owner/platform permissions, PHP 8.5 and production remain incomplete. No main merge or push.

This entry supersedes earlier T09 pending-review/not-integrated statements below.

## 2026-09-10 independent T09 review at 0c93d50

Executed in the isolated t09-branch-view-policy worktree: full suite PASS 26 tests/156 assertions (SQLite :memory:/array sessions), Pint PASS, documentation validator PASS 0 errors/2 known warnings, diff check PASS. Reviewed all changed policy integration paths and worker browser outputs for selected-context revocation and direct 403. No confirmed findings. Worker tree clean; no application edits, integration, service restart or live browser rerun by coordinator. T09 accepted on worker branch only; MySQL coverage for this policy has not been claimed.

## 2026-09-10 T09 branch view authorization

Base: `92aca4457e9b4b8fc5ab6f992f23d42110747171` on isolated branch `codex/t09-branch-view-policy` at `C:\Users\N\.codex\worktrees\t09-branch-view-policy\PlayNexus`.

| Check | Command / evidence | Result |
|---|---|---|
| Focused policy/HTTP tests | `php artisan test --filter=BranchViewAuthorizationTest` | PASS: 5 tests / 75 assertions |
| Full suite | `php artisan test` | PASS: 26 tests / 156 assertions |
| Resolved automated test environment | `php artisan config:show ...` with process-local overrides | PASS: SQLite `:memory:`, `SESSION_DRIVER=array`, `SESSION_CONNECTION=null`; tracked `phpunit.xml` unchanged |
| Formatting | `php vendor/bin/pint --test` | PASS |
| Documentation | `python tools/validate_documentation.py` | PASS: 0 errors, 2 review-placeholder warnings: `docs/agent-plan.md` and `.ai/TEST_RESULTS.md` |
| Whitespace | `git diff --check` | PASS |
| Browser runtime | `php artisan serve --host=127.0.0.1 --port=8194` with task-local SQLite file and file sessions | PASS: synthetic runtime only, SQLite 3.51.3, PHP 8.4.21, Laravel 13.31.0; server stopped and port released |

### Browser evidence

- URL `http://127.0.0.1:8194/login`: synthetic identity `T09 Operator` signed in successfully and reached `/app`.
- URL `http://127.0.0.1:8194/app`: permitted list showed `T09 Main Branch`; unsupported-role `T09 Hidden Branch` was absent. Selecting the permitted branch showed `Current branch: T09 Main Branch`; reload retained the same context.
- After the task-local SQLite pivot role changed from `reception_staff` to `game_operator`, reload of `/app` showed `Select an assigned branch to continue.` and `No active branch assignments are available.` with no current branch.
- URL `http://127.0.0.1:8194/branches/1`: direct read rendered HTTP 403 with `This action is unauthorized.` after revocation. No credentials or runtime artifacts were committed.

T09 is `REVIEW_REQUIRED` pending orchestrator review. This slice does not implement tenant-owner/platform authorization or claim broader M1 completion. T07's separately accepted MySQL 8.4 evidence remains historical/accepted evidence; this T09 browser and ordinary automated rerun used isolated SQLite as recorded above.

## 2026-09-10 independent T08 acceptance at 7a3963e

- Accepted ancestry and four-file documentation-only diff verified; worker evidence and substantive coordinator entries retained. Only an obsolete no-scaffold/no-tests sentence from main was omitted.
- Independently reran php artisan test: PASS, 21 tests/81 assertions with process-local SQLite :memory: and array sessions. Pint, npm run build and diff checks PASS. Build has optional fontaine notice. Documentation validator PASS, 0 errors/2 known status-marker warnings.
- Integration checkout clean before acceptance-record edits; main remains b6d09e1 with original dirty/untracked state and stash; QA remains 37b4f6b. No runtime restart/main merge/push. T08 DONE; full M1 and PHP 8.5/production remain incomplete.

## 2026-09-10 T08 local integration baseline

| Check | Command / evidence | Result |
|---|---|---|
| Integration checkout | `C:\Users\N\.codex\worktrees\t08-integration\PlayNexus`, branch `codex/first` | PASS: isolated worktree created for existing branch; no main checkout or other worktree changes |
| Accepted history | `git merge --ff-only 37b4f6b782c0020984567bb774d982b42428d3cb` | PASS: fast-forward `03108c6` -> `37b4f6b`; accepted target is an ancestor of the resulting HEAD |
| Coordinator ledger import | SHA-256 of `docs/agent-plan.md` | PASS: imported from main checkout; source and integration hash `E7F78F42DB8F2A308D4F2B5E54725E1BF211FE6CF4C8E0E8F4F888787E1654CF` |
| Test configuration | `php artisan config:show database.default`; SQLite/session config probes | PASS: current-process overrides resolve database `sqlite`, database `:memory:`, session driver `array`; inherited MySQL QA overrides were cleared only in this process |
| Application tests | `php artisan test` | PASS: 21 tests / 81 assertions; resolved config remained SQLite `:memory:` and array sessions |
| Formatting | `php vendor/bin/pint --test` | PASS |
| Frontend build | `npm run build` | PASS: Vite production build; optional `fontaine` package notice and plugin timing output |
| Documentation | `python tools/validate_documentation.py` | PASS: 30 Markdown files, 199 SRS IDs, 50 stories, 14 use cases, 24 OQs, 56 OpenAPI paths / 70 operations; 0 errors, 2 review-placeholder warnings in `docs/agent-plan.md` and `.ai/TEST_RESULTS.md` |
| Whitespace | `git diff --check` | PASS: no whitespace errors |
| Scope | diff from accepted target `37b4f6b` | PASS: only the four owned coordination files are intended to differ; no application or dependency diff |

T08 status is `REVIEW_REQUIRED` pending orchestrator review. The bounded auth/branch slice and T07 MySQL 8.4 acceptance remain accepted; full M1, PHP 8.5 validation and production readiness remain incomplete. No policies/gates work was started. Main's modified `.ai/TEST_RESULTS.md`, untracked `.codex/`, `bootstrap/`, and `docs/agent-plan.md`, the existing stash, and QA's pre-existing untracked `ibrahim.err` remain outside this checkout and were preserved.

## 2026-09-10 coordinator acceptance of T07B at 37b4f6b

- Reviewed one-file diff, ancestry, actual worker execution transcript and committed runtime evidence. MySQL output proves 8.4.11 at loopback 33407, 12/12 InnoDB tables, four migrations and cross-tenant composite FK rejection (1452).
- Worker MySQL commands/test output: focused 19/78 and full 21/81 PASS with process-local connection/session overrides. Session inspection after logout: 0 authenticated rows and 0 branch-context rows; Arabic DOM reports ar/rtl. Browser flows accepted from worker evidence; no coordinator browser rerun or screenshot rendering claimed.
- Current read-only listener check: no listeners on 33407, 8190, 8191. Worker application code unchanged since 9056439; unchanged tests were not rerun here. `git diff --check 9056439..37b4f6b` PASS; codex/first is an ancestor of 37b4f6b.
- T07/T07B and bounded T06 slice accepted; integration T08 remains pending. Main working changes and worker untracked ibrahim.err preserved. PHP 8.5/full M1/production are not accepted by these checks.

## 2026-09-10 coordinator review of T07 evidence 835346d

- Verified single-file documentation commit, parent 9056439 and clean QA worktree. Inspected worker T07 browser-call history and runtime command results in task 01a08871-17c7-7752-8b7b-68d5d29fb433. Accepted fallback evidence only; browser was not rerun and screenshots were not independently rendered here.
- Read-only retained SQLite inspection: evidence file exists, expected migrated tables, 0 foreign-key-check issues, 3 session rows. No session payloads or credentials output. Session row count does not establish authentication state.
- No current listeners observed on 3306-3308 or 8187-8188; docker/mysqld absent from PATH. MySQL target runtime remains unverified and blocked pending isolated provisioning.
- No application changes since 9056439; full tests not redundantly rerun. T07A DONE, T07 BLOCKED, T07B READY for environment provisioning and MySQL acceptance. No integration performed.

## 2026-09-10 independent correction review at 9056439

Executed in the auth worker worktree, which remained clean:

- `php artisan test`: PASS, 21 tests / 81 assertions.
- `php vendor/bin/pint --test`: PASS.
- `python tools/validate_documentation.py`: PASS, 29 files, 0 errors/warnings.
- `git diff --check`: PASS.
- Temporary independent regression probes: PASS, 2 tests / 4 assertions. Suspended-tenant logout now returns 302 and clears authentication; array email returns normal JSON validation failure. Removed the earlier probe's exception-handler bypass so expected validation responses could render.
- Both findings closed. No frontend changes, so previous successful build was not repeated. MySQL/database-session, browser acceptance and target PHP 8.5 remain unverified. Full slice REVIEW_REQUIRED pending T07; no merge or push performed.

## 2026-09-10 independent review of auth worker c94a046

Executed in `C:/Users/N/.codex/worktrees/t05-auth-branch-access/PlayNexus`, not this documentation-only main checkout:

- `php artisan test`: PASS, 19 tests / 72 assertions.
- `php vendor/bin/pint --test`: PASS.
- `npm run build`: PASS; optional fontaine notice only.
- `python tools/validate_documentation.py`: PASS, 29 Markdown files, 0 errors/warnings.
- `git diff --check`: PASS; worker Git status clean after checks.
- `php vendor/bin/phpunit --configuration phpunit.xml <TEMP>/PlayNexusAuthReviewTest.php`: two additional probes, 1 failure / 1 error. Suspended-tenant logout returns 404 and leaves auth active; array-valued login email raises a conversion exception at AppServiceProvider.php:27 before validation.
- Verdict: CHANGES_REQUIRED. MySQL/database sessions and browser RTL/LTR not verified. No implementation edits or integration performed. See `docs/agent-plan.md` for ownership and correction acceptance.

## 2026-09-10 orchestration startup on main at b6d09e1

- `python tools/validate_documentation.py`: PASS, 30 Markdown files, 56 OpenAPI paths / 70 operations, 0 errors and 1 placeholder-marker warning in `docs/agent-plan.md` (the required TODO status vocabulary).
- `git diff --check`: PASS for tracked changes; the orchestration ledger is untracked and excluded from this command.
- Inspected local branches/worktrees/history and `codex/first:tests/Feature/TenantBranchTest.php`. Application tests, MySQL migrations and browser flows were not run. Earlier worker results in the ledger remain historical evidence.
- Startup changes are limited to the coordination ledger and this verification record; no implementation or integration performed.

## 2026-09-10 next-task planning

- Re-inspected Git status, branches, worktrees, foundation history, routes, middleware, tenant context and branch-specific decision changes.
- Recommended T05 independent foundation verification before the T06 login/branch-entry slice; no workers launched.
- `python tools/validate_documentation.py`: PASS, 0 errors, 2 marker warnings in the ledger and this file; `git diff --check`: PASS, with Git's LF/CRLF conversion notice. Application checks not rerun.

## 2026-09-10 Laravel foundation scaffold

### T03 review correction verification

| Check | Command | Result |
|---|---|---|
| Default tests | `php artisan test` | Pass: 2 tests, 2 assertions |
| Frontend build | `npm run build` | Pass: Vite production build |
| Application timezone | `php artisan config:show app` | Pass: `timezone .. UTC` |
| Diff | `git diff --check` | Pass: no whitespace errors |
| Documentation | `python tools/validate_documentation.py` | Pass: 30 Markdown files, 0 errors, 0 warnings |

| Check | Command | Result |
|---|---|---|
| PHP | `php -v` | Pass: PHP 8.4.21; target is PHP 8.5 |
| Composer | local Composer PHAR `--version` | Pass: Composer 2.10.3 during scaffold; fresh checkout requires Composer on `PATH` as documented |
| Framework install | local Composer PHAR `install --no-interaction` | Pass: dependencies installed and autoload generated; reproducible command is now `composer install --no-interaction --prefer-dist --no-progress` |
| Node/npm | `node --version`; `npm --version` | Pass: Node 24.15.0, npm 11.12.1 |
| Frontend install | `npm install --ignore-scripts` | Pass |
| Key | `php artisan key:generate --force` | Pass during initial scaffold only; clean setup now generates a key only when `.env` is absent |
| Tests | `php artisan test` | Pass: framework default PHPUnit tests (2 tests, 2 assertions) |
| Assets | `npm run build` | Pass: Vite production build |
| MySQL | `mysql --version` | BLOCKED: MySQL CLI unavailable; no database migration run. This does not determine whether a database service is available elsewhere. |
| Smoke | `php artisan serve --host=127.0.0.1 --port=8000` + `curl.exe --max-time 20 -o NUL -w '%{http_code}' http://127.0.0.1:8000/` with temporary file session/cache override | Pass: HTTP 200. With configured MySQL-backed sessions, `127.0.0.1:3306/playnexus` refused a connection during this check; no service was installed, restarted, or altered. |
| Diff | `git diff --check` | Pass: no whitespace errors |
| Documentation | `python tools/validate_documentation.py` | Pass: 30 Markdown files, 0 errors, 0 warnings |

The entries above prove only the installed framework scaffold, default PHPUnit harness, frontend build, and file-session/cache route smoke. They do not prove MySQL migrations/runtime, tenant isolation, authorization, money/time correctness, security, or business workflows.

## 2026-09-10 T04 tenant/branch foundation

| Check | Command | Result |
|---|---|---|
| Focused foundation tests | `php artisan test --filter=TenantBranchTest` | Pass: 4 tests, 14 assertions |
| Full tests | `php artisan test` | Pass: 6 tests, 16 assertions |
| Formatting | `vendor/bin/pint --test` | Pass after `vendor/bin/pint` formatting |
| Frontend build | `npm run build` | Pass: Vite production build |
| Documentation | `python tools/validate_documentation.py` | Pass: 30 Markdown files, 0 errors, 0 warnings |
| Diff | `git diff --check` | Pass: no whitespace errors |
| Database | SQLite in-memory via `phpunit.xml` | Pass: constraints and behavior covered; MySQL migration/runtime BLOCKED_BY_ENVIRONMENT (no MySQL CLI/service verified) |

## 2026-09-10 T04 tenant integrity follow-up

| Check | Command | Result |
|---|---|---|
| Focused integrity tests | `php artisan test --filter=TenantBranchTest` | Pass: 5 tests, 15 assertions |
| Full tests | `php artisan test` | Pass: 7 tests, 17 assertions |
| Formatting | `vendor/bin/pint --test` | Pass |
| Frontend build | `npm run build` | Pass: Vite production build |
| Documentation | `python tools/validate_documentation.py` | Pass: 30 Markdown files, 0 errors, 0 warnings |
| Diff | `git diff --check` | Pass: no whitespace errors |
| Database | SQLite in-memory via `phpunit.xml` | Composite tenant/branch and tenant/user foreign keys reject mismatched assignment; MySQL migration/runtime remains BLOCKED_BY_ENVIRONMENT |

## 2026-09-10 T05 staff authentication and branch context

| Check | Command | Result |
|---|---|---|
| Authentication features | `php artisan test --filter=Authentication` | Pass: 12 tests, 54 assertions |
| Branch features and T04 regression | `php artisan test --filter=Branch` | Pass: 11 tests, 40 assertions |
| Full suite | `php artisan test` | Pass: 19 tests, 72 assertions |
| Formatting | `vendor/bin/pint --test` | Pass |
| Frontend build | `npm run build` | Pass: Vite production build; optional `fontaine` optimized-fallback notice only |
| Documentation | `python tools/validate_documentation.py` | Pass: 29 Markdown files, 199 SRS IDs, 50 stories, 14 use cases, 24 OQs, 56 OpenAPI paths/70 operations; 0 errors, 0 warnings |
| Diff | `git diff --check` | Pass: no whitespace errors |
| Database | SQLite in-memory via `phpunit.xml` | Pass: auth and branch boundaries tested. MySQL migration/runtime `BLOCKED_BY_ENVIRONMENT`; no MySQL service or shared configuration was changed. |

## 2026-09-10 T05 auth follow-up

| Check | Command | Result |
|---|---|---|
| Authentication regressions | `php artisan test --filter=Authentication` | Pass: 14 tests, 63 assertions |
| Branch denial regression | `php artisan test --filter=Branch` | Pass: 11 tests, 40 assertions |
| Full suite | `php artisan test` | Pass: 21 tests, 81 assertions |
| Formatting | `php vendor/bin/pint --test` | Pass |
| Documentation | `python tools/validate_documentation.py` | Pass: 29 Markdown files, 199 SRS IDs, 50 stories, 14 use cases, 24 OQs, 56 OpenAPI paths/70 operations; 0 errors, 0 warnings |
| Diff | `git diff --check` | Pass: no whitespace errors |

## 2026-08-24 documentation QA

| Check | Real result |
|---|---|
| Compare original and re-attached PRD with SHA-256 | Pass: both 27,198 bytes; hash `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2` |
| `tools/validate_documentation.py` with PyYAML | Pass after adding `DESIGN.md`: 29 Markdown files, 199 SRS IDs, 50 stories, 14 use cases, 24 OQs, 56 OpenAPI paths/70 operations; 0 errors, 0 warnings |
| `python -m openapi_spec_validator docs/contracts/openapi.yaml` | Pass: OpenAPI 3.1 contract OK |
| `tools/smoke_wireframes.cjs` | Pass: 15 screens, desktop/tablet/narrow and RTL layouts, unique IDs, labels/accessibility names, and no console errors |
| Python compile and Node syntax check for QA scripts | Pass |
| Scope/tool artifact scan | Pass: 0 PostgreSQL/psql references and 0 Word/DOCX artifacts or generators |
| Design fallback color contrast check | Pass for the approved non-purple/non-cream palette: primary text `14.34:1`, muted text `5.44:1`, primary action pairs `5.90:1`, and semantic text pairs `5.25:1` or better |
| Final ZIP content audit | Pass after adding `DESIGN.md`: 34 entries, all required artifacts present, and 0 Word/DOCX, QA-output, generator, duplicate-summary, or `__pycache__` entries |

These checks validate documentation structure and the static contract/prototype only. They do not demonstrate application behavior, migrations, tenant isolation, money/time correctness, security, or release readiness; those require the Laravel scaffold and real tests.

The first post-move OpenAPI and browser commands could not load their isolated validator and Playwright dependencies from the default shell. They were rerun successfully with the workspace's bundled Python/Node runtimes and isolated QA dependencies; no specification or wireframe defect caused those environment failures.

## 2026-09-10 decision synchronization QA

| Check | Result |
|---|---|
| `python tools/validate_documentation.py` | Pass: 30 Markdown files, 199 SRS IDs, 50 stories, 14 use cases, 24 OQs, 56 OpenAPI paths/70 operations, 0 errors/warnings |
| `python -m openapi_spec_validator docs/contracts/openapi.yaml` | Not run: validator package is unavailable in the default Python runtime; rerun with the bundled QA environment before scaffold merge |
| Contract synchronization | Pass: approved receipt, guardian-verification, and pricing amendments added to SRS, architecture, ERD, permissions, API, OpenAPI extension, wireframes, testing, milestones, checklist, and `.ai/` records |

## 2026-09-10 T07 auth/branch runtime acceptance

Base reviewed: `9056439dc341aea76040dd4723fab7e13e536f0f` on isolated worktree branch `codex/t07-runtime-acceptance`.

| Acceptance item | Result | Evidence |
|---|---|---|
| Isolated MySQL 8.4/InnoDB migration and runtime | **BLOCKED** | Read-only discovery found no MySQL/MariaDB service, process, or listener on ports 3306-3308. The only server binary is `C:\xampp\mysql\bin\mysqld.exe`, MariaDB 10.4.32, not MySQL 8.4. No shared service or configuration was changed. |
| Tracked PHPUnit isolation | **PASS** | `phpunit.xml` selects `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, and `SESSION_DRIVER=array`; it was not used to claim MySQL coverage. |
| Disposable fallback migration and constraints | **PASS (SQLite fallback only)** | `C:\Users\N\AppData\Local\Temp\playnexus-t07-5ed7cfa55ab3411dbcec1a335356224e\playnexus-t07.sqlite`; `php artisan migrate:fresh --force` passed all four migrations. Laravel reported driver `sqlite`, version `3.51.3`; `pragma foreign_key_check` returned 0 rows and a cross-tenant `branch_user` insert was rejected. |
| Database-session runtime | **PASS (SQLite fallback only)** | Local servers at `http://127.0.0.1:8187` (English) and `http://127.0.0.1:8188` (Arabic) ran with `SESSION_DRIVER=database`, `SESSION_CONNECTION=sqlite`, and the disposable path above. After suspended-tenant logout, session inspection returned `authenticated_user_rows=0` and `branch_context_rows=0`. |
| English keyboard sign-in, branch select, reload, logout | **PASS** | In-app browser at `http://127.0.0.1:8187/login`: keyboard email/password/Tab/Enter reached `/app` as synthetic `T07 English Staff`; selected `T07 Assigned Branch`; reload retained it; keyboard logout returned `/login`. Visual captures are in this T07 task transcript. |
| Invalid credentials and validation feedback | **PASS** | English browser showed alert `These credentials do not match our records.`; Arabic browser showed `بيانات الدخول غير صحيحة.`. Keyboard Tab/Enter submitted both forms. |
| Empty assignments | **PASS** | Browser `/app` as synthetic `T07 Empty Staff` displayed `No active branch assignments are available.` |
| Foreign and unassigned branch denial | **PASS** | Authenticated browser navigation to `http://127.0.0.1:8187/branches/2` and `/branches/3` rendered actual 404 pages for unassigned and foreign synthetic branches. |
| Revoked branch context | **PASS** | After a selected synthetic assignment was deactivated, browser reload of `/app` removed the current branch and displayed no active assignments. |
| Logout after tenant suspension | **PASS** | After synthetic tenant suspension, normal browser logout redirected to `http://127.0.0.1:8187/login` rather than 404; database-session inspection confirmed no authenticated or branch-context rows. |
| Arabic RTL flow | **PASS** | Browser `http://127.0.0.1:8188/login` reported `lang=ar`, `dir=rtl`; Arabic sign-in, select, reload, and logout passed. Visual captures are in this T07 task transcript. |
| Auth regression suite | **PASS** | `php artisan test`: 21 tests, 81 assertions. This includes `AuthenticationTest::test_array_email_is_rejected_by_login_validation`; it is SQLite/array-session test coverage, not MySQL evidence. |
| Formatting and whitespace | **PASS** | `php vendor/bin/pint --test` passed; `git diff --check` passed. |

Runtime versions: PHP 8.4.21; Laravel 13.31.0; Node 24.15.0; npm 11.12.1; Composer 2.10.3 (local PHAR used only to provision this isolated QA worktree); SQLite 3.51.3. Browser screenshots were intentionally kept in the Codex task transcript; no local browser artifacts, credentials, `.env`, or shared database data were created.

## 2026-09-10 T07B isolated MySQL 8.4 acceptance

Base: `835346d8dbfad3b668813b0e93737c5767b6d371` on `codex/t07-runtime-acceptance`. Only this file is committed by T07B; application code, dependencies, `.env`, XAMPP, and `docs/agent-plan.md` were not changed.

### Private runtime and download verification

- Official source consulted: [MySQL 8.4 Windows installation](https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html) and [package selection](https://dev.mysql.com/doc/refman/8.4/en/windows-choosing-package.html). The manual documents the noinstall ZIP archive and the Microsoft Visual C++ 2019 Redistributable prerequisite; no Windows service was installed.
- Existing task-local cache used after bounded download retry: `C:\Users\N\AppData\Local\Temp\playnexus-t07b-mysql84\mysql-8.4.11-winx64.zip` (281,191,914 bytes; MD5 `2e833921898a9a030ea6bfe81bd811bc`, matching the official downloads page). Extracted server: `...\mysql-8.4.11-winx64\bin\mysqld.exe`.
- Server command: `mysqld.exe --no-defaults --basedir=<ZIP_ROOT> --datadir=<PRIVATE_DATA> --port=33407 --bind-address=127.0.0.1 --mysqlx=OFF --skip-log-bin --console`.
- Runtime proof: `mysqld.exe --version` = `8.4.11`; listener = `127.0.0.1:33407`; no shared port/service was used. A task-local application account and disposable database `playnexus_t07b_accept_20260910` were created with a password kept outside the repository.
- Runtime versions: PHP `8.4.21` with `pdo_mysql` enabled; Laravel `13.31.0`; Node `24.15.0`; npm `11.12.1`; MySQL `8.4.11`. Composer was not on this shell's `PATH`; the existing scaffold record is Composer `2.10.3` via a local PHAR, and no dependency install was needed.

### Acceptance evidence

| Acceptance item | Result | Evidence |
|---|---|---|
| MySQL version, loopback binding, and InnoDB | **PASS** | MySQL query returned `8.4.11`, `MySQL Community Server - GPL`, port `33407`, bind `127.0.0.1`, default engine `InnoDB`; `12/12` application tables reported `InnoDB` (`branch_user`, `branches`, `cache`, `cache_locks`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, `password_reset_tokens`, `sessions`, `tenants`, `users`). |
| Disposable migrations | **PASS** | With temporary `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=33407`, database/user overrides, `SESSION_DRIVER=database`, and `SESSION_CONNECTION=mysql`: `php artisan migrate:fresh --force --no-interaction` completed all four migrations; `php artisan db:show --database=mysql --counts` reported MySQL 8.4.11 and 12 tables. |
| Resolved runtime configuration | **PASS** | `php artisan config:show database.default` = `mysql`; `session.driver` = `database`; `session.connection` = `mysql`; no tracked `phpunit.xml` values were used to claim this coverage. |
| Auth/branch checks against MySQL | **PASS** | Same temporary configuration: `php artisan test --filter='TenantBranchTest|AuthenticationTest'` = 19 tests / 78 assertions; full `php artisan test` = 21 tests / 81 assertions. |
| Cross-tenant assignment rejection | **PASS** | PHPUnit composite-FK test passed; direct MySQL probe inserting tenant `21` + foreign branch `16` failed with `ERROR 1452` on `branch_user_tenant_id_branch_id_foreign`. |
| English database-session browser flow | **PASS** | In-app browser tab 2, `http://127.0.0.1:8190/login`: synthetic `T07B Assigned Staff` signed in, selected `T07B Assigned Branch`, reload retained `Current branch: T07B Assigned Branch`, and logout returned `/login`. The browser used database sessions on MySQL. |
| Invalid credentials | **PASS** | English tab 2 displayed `These credentials do not match our records.`; keyboard entry and submit were exercised. |
| Empty assignments | **PASS** | English tab 2 as synthetic `T07B Empty Staff` displayed `No active branch assignments are available.` |
| Foreign/unassigned denial | **PASS** | Authenticated English tab 2 navigation to `/branches/15` (unassigned) and `/branches/16` (foreign) rendered actual `404 Not Found` pages. |
| Revoked branch context | **PASS** | Synthetic `T07B Revoked Staff` selected `T07B Revoked Branch`; its `branch_user.is_active` was then set to `0` in MySQL; reload of `/app` cleared the current branch and displayed no active assignments. |
| Suspended-tenant logout and session clearing | **PASS** | Synthetic `T07B Suspended Staff` selected its branch; tenant `23` was then suspended in MySQL; normal logout on tab 2 returned `/login`. Post-logout query: `authenticated_user_rows=0`, `branch_context_rows=0` (one anonymous login-page row remained). |
| Arabic RTL flow | **PASS** | In-app browser tab 3, `http://127.0.0.1:8191/login`, returned DOM `lang=ar`, `dir=rtl`; Arabic invalid credentials showed `بيانات الدخول غير صحيحة.`; valid sign-in, branch selection, reload persistence (`الفرع الحالي: T07B Assigned Branch`), and logout returned `/login`. |
| Whitespace/docs checks | **PASS** | `git diff --check` passed; `python tools/validate_documentation.py` passed with 0 errors (the existing required TODO marker warning remains scoped to `docs/agent-plan.md`, which was not changed). |

### Reproducible task-local verification (no secrets)

1. Download `mysql-8.4.11-winx64.zip` from `https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-8.4.11-winx64.zip`, verify MD5 `2e833921898a9a030ea6bfe81bd811bc`, and extract under a dedicated temp directory.
2. Start `mysqld.exe` with `--no-defaults`, a private `--datadir`, `--port=33407`, and `--bind-address=127.0.0.1` (do not register a service).
3. Create a disposable database and least-scope task user via MySQL SQL; supply the password only through a task-local secret environment variable.
4. Run Laravel commands with process-local overrides (PowerShell example):

   `$env:DB_CONNECTION='mysql'; $env:DB_HOST='127.0.0.1'; $env:DB_PORT='33407'; $env:DB_DATABASE='playnexus_t07b_accept_20260910'; $env:DB_USERNAME='<TASK_USER>'; $env:DB_PASSWORD='<TASK_PASSWORD>'; $env:SESSION_DRIVER='database'; $env:SESSION_CONNECTION='mysql'; php artisan migrate:fresh --force --no-interaction`

   Then run `php artisan config:show ...`, `php artisan test`, and the browser server with the same overrides. Never edit the tracked `phpunit.xml` or an existing `.env`.

Task-owned MySQL and Laravel server processes were stopped after verification. Private temp data/credentials remain outside the repository for local cleanup; no shared service or database was altered.
# 2026-09-12 T24-T26 integration acceptance

| Check | Result |
|---|---|
| Focused features | `php artisan test --filter="PasswordResetTest|TenantSettingsTest|BranchSettingsTest"` — PASS, 18 tests / 190 assertions |
| Full regression | `php artisan test` — PASS, 121 tests / 881 assertions |
| Formatting | `php vendor/bin/pint --test` — PASS after formatting the reviewed branch-settings controller |
| Frontend | `npm run build` — PASS; optional `fontaine` fallback notice only |
| Documentation | `python tools/validate_documentation.py` — PASS, 0 errors / 2 existing review-placeholder warnings |
| Whitespace | `git diff --check` — PASS |
| Runtime boundary | SQLite `:memory:` and array sessions from `phpunit.xml`; browser deferred by user; new MySQL migrations/locks and PHP 8.5 unverified |

Review rejected the worker migration's nonexistent `Blueprint::check` calls before acceptance and removed them; request validation, unique tenant branch code and composite tenant/branch foreign keys remain enforced. Blade compilation was exercised through feature tests after correcting the payment-method block. No shared services or databases were changed.
# 2026-09-12 T27-T29 Platform integration acceptance

| Check | Result |
|---|---|
| Platform security and lifecycle | `php artisan test --filter=PlatformAdministrationTest` — PASS, 17 tests / 178 assertions |
| Full regression | `php artisan test` — PASS, 138 tests / 1,059 assertions |
| Blade compilation | `php artisan view:clear && php artisan view:cache` — PASS |
| Formatting | `php vendor/bin/pint --test` — PASS |
| Frontend | `npm run build` — PASS; optional `fontaine` notice only |
| Runtime boundary | SQLite `:memory:` and array sessions; browser deferred by user; current MySQL migrations and PHP 8.5 unverified |

The independent test slice initially exposed integration mismatches. Coordinator corrected production authorization, data minimization, translation, idempotency form wiring and reason-code UI, then corrected two test fixtures that used Query Builder `whereKey` and stale unhydrated defaults. Platform provisioning proves atomic tenant/invited-owner/ownership/audit creation, normalized idempotent replay, conflicting replay denial, rollback, status locking, audit attribution, and tenant-user isolation.
