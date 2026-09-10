# Test Results

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
