# M0 Audit Report — Decisions and Scaffold

**Review date:** 2026-09-14  
**Status:** `LOCALLY_ACCEPTED / HOSTED_CI_PENDING`  
**Scope:** M0 only. No M1–M3 functional or UI review; one import-order-only correction was made so the repository formatting gate can pass.

## 1. Executive summary

The M0 repair set is locally accepted. A minimal hosted workflow now installs locked dependencies, validates a disposable database target, migrates MySQL 8.4, runs the M0 isolation/seed gate on MySQL, runs the full regression on SQLite, checks Pint, builds Vite and validates canonical documentation. Local evidence passes on both SQLite and an isolated MySQL 8.4.11/InnoDB process. The remaining closure gate is the first hosted workflow execution; it cannot be claimed from an unpushed local worktree.

The product-wide UI direction is now explicitly **desktop-first and full-screen**: at 1024px and above, PlayNexus is an operational workspace rather than an enlarged tablet layout. This is recorded as a foundation requirement only; implementation must wait for owner approval and the milestone-by-milestone review gate.

## 2. Scope and exclusions

M0 covers product decisions required for scaffolding, supported versions, Laravel scaffold, local/CI setup, migrations, safe synthetic tenant/branch/role seed data, and one tenant-isolation feature proof. Authentication/app shell (M1), family workflows (M2), and pricing/tickets/check-in (M3) are excluded even though their code exists.

## 3. Requirement Traceability Matrix

| ID | Requirement and source | Status | Files / database / tests | Actual evidence and gap |
|---|---|---|---|---|
| M0-R01 | Approve scaffold-affecting MVP decisions — `15-Delivery-Milestones.md` | Implemented | `.ai/DECISIONS.md`, `00-INDEX.md` | Egypt, EGP, timezone, shared-schema tenancy and core access decisions are sufficient for the scaffold. Later business questions stay with their own milestone. |
| M0-R02 | Choose supported versions — Tooling §1–3 | Implemented | `composer.json`, `composer.lock`, `package.json`, CI | PHP is constrained to `^8.4`; CI uses PHP 8.5, Node 24 and MySQL 8.4; PHPUnit 12 is documented consistently. The local Composer command is still an explicit workstation prerequisite. |
| M0-R03 | Scaffold Laravel | Implemented and verified | `artisan`, `app/`, `bootstrap/`, migrations | Current application boots and focused Artisan tests execute. |
| M0-R04 | Configure reproducible local environment | Implemented | `.env.example`, `composer.json`, `tools/ensure-safe-database.php` | SQLite is the safe project-local default. MySQL requires `PLAYNEXUS_DB_TARGET` to equal the selected non-default database before migration. Existing `.env` and `APP_KEY` values are preserved. |
| M0-R05 | Configure CI | Implemented; hosted run pending | `.github/workflows/ci.yml` | Workflow covers locked installs, MySQL 8.4 migration/M0 tests, full SQLite regression, Pint, Vite and docs. YAML and equivalent local gates pass; GitHub execution remains external. |
| M0-R06 | Seed deterministic tenant/branch/role demo data | Implemented and verified | `DatabaseSeeder.php`, `M0DemoSeederTest.php` | Two synthetic tenants, four branches and Owner/Manager/Reception/Cashier fixtures are idempotent and restricted to local/testing environments. |
| M0-R07 | Prove tenant isolation with one feature test | Implemented and verified on SQLite/MySQL | foundation migration; `TenantBranchTest.php` | PASS on both engines: context is user-derived, foreign/unassigned/inactive scope is hidden, and composite FK rejects cross-tenant assignment. |
| M0-R08 | Rebuild from scratch | Implemented and verified | all 21 migrations; SQLite and isolated MySQL 8.4.11 | Fresh MySQL migration PASS with 31 InnoDB tables; local full SQLite regression PASS. Hosted clean install remains the external CI gate. |
| M0-R09 | Desktop-first full-screen design foundation | Contract implemented; UI repair deferred | `DESIGN.md` | Canonical rule now keeps 1366px as desktop, uses the full workspace at `>=1024px`, and reserves cards for actual legibility pressure. Screen implementation belongs to each later milestone review. |

## 4. Routes and screens inventory

| Surface | Route / entry | M0 result |
|---|---|---|
| Health | `GET /up` | Exists through `bootstrap/app.php`; deployment smoke belongs to release hardening. |
| Setup | Composer `setup` script | Safe SQLite default plus explicit MySQL target guard. |
| Demo context | `DatabaseSeeder` | Deterministic two-tenant role scenario; production execution is rejected. |
| CI | GitHub Actions | M0 gate implemented; first hosted execution pending. |

M0 has no end-user screen. Login and the authenticated shell belong to M1 and were not used to manufacture M0 acceptance.

## 5. Role × Action Matrix

| Actor | Build environment | Migrate | Seed usable roles | Verify tenant isolation |
|---|---:|---:|---:|---:|
| Local engineer | PASS with documented prerequisite | SQLite/MySQL PASS | PASS | SQLite/MySQL PASS |
| CI | Implemented | MySQL 8.4 configured | Seed test configured | M0 tests configured; hosted run pending |
| Synthetic QA | Local/testing only | Safe target required | PASS | Runnable from deterministic fixtures |
| Owner/Manager/Reception/Cashier | Outside M0 runtime scope | Outside scope | Present as synthetic fixtures | Functional role journeys stay with later milestones |

## 6. Database and security review

- The foundation migration adds `tenant_id` to users and branches and makes the assignment key `(tenant_id, branch_id, user_id)`.
- Composite foreign keys bind assignment user and branch to the same tenant; the negative database test passes.
- `branch_user.role` remains free text at database level. Application policy constrains known roles, but a direct invalid role value is not DB-rejected.
- Nullable `users.tenant_id` supports platform identities but M0 does not encode an explicit database discriminator between a platform identity and malformed tenant staff.
- The local M0 MySQL run used MySQL 8.4.11, `REPEATABLE-READ`, and 31 InnoDB tables on task-owned port 33427; XAMPP/MariaDB was not used.
- The demo seed contains no guardian, child, ticket, session or payment data and refuses to run outside local/testing.

## 7. Automated test review

| Check | Result |
|---|---|
| M0 SQLite gate | PASS — 7 tests / 49 assertions |
| Full SQLite regression | PASS — 314 total / 311 passed / 3 skipped / 2,659 assertions |
| M0 MySQL 8.4 gate | PASS — 7 tests / 49 assertions; fresh 21 migrations / 31 InnoDB tables |
| Seed rerun/environment guard | PASS — stable counts and production refusal covered |
| Composer configuration | PASS — lock refreshed and strict validation passed through local Composer phar; shell PATH installation remains documented |
| Pint / Vite / docs / whitespace | PASS; Vite emits only the existing optional `fontaine` notice |
| Hosted CI | PENDING — workflow exists but has not run from the local worktree |

## 8. Browser test results

`NOT_APPLICABLE` for M0 closure: M0 defines no user-facing workflow. A health HTTP response alone would not be browser acceptance. Authenticated role/UI journeys begin with M1 and require isolated synthetic seed data first.

## 9. UX/UI audit

The shared visual direction—porcelain surfaces, graphite text, petrol-teal actions—fits a bright reception environment and should remain. The structural problem is inconsistent use of desktop space.

The approved implementation contract for later repair is:

- At `>=1024px`: full-height workspace, fixed 248px sidebar, fixed 64px top bar, 24–32px content gutters, and up to 1440px for readable content; operational tables may consume all remaining width.
- Desktop uses comparison tables, compact 48px/standard 56px rows, aligned end actions and one dominant primary action. It must not switch to tablet cards at 1366px.
- Tablet/card conversion should use one shared threshold near 900px; phone becomes single-column below 640px.
- RTL/LTR uses logical properties and `<bdi dir="ltr">` for money, IDs and phones.
- No new design-system package or dependency. Reuse the current shell and semantic tokens.

## 10. Accessibility and responsive findings

Existing foundation includes `dir`, skip link, visible focus, reduced-motion handling and 44px controls. Missing evidence includes keyboard walkthrough, 200% zoom/reflow, live-region consistency, and unified 360/768/1366 browser checks. Focus CSS is duplicated; Vite loads Instrument Sans while the CSS/Design contract specifies Inter and IBM Plex Sans Arabic.

## 11. Missing requirements

1. First hosted execution of the new M0 workflow.
2. Workstation-level Composer PATH installation where it is absent; the repository cannot safely install a global prerequisite itself.

## 12. Functional bugs

- `M0-003` resolved: the documented seeder now creates the deterministic M0 tenant/branch/role scenario and reruns without duplication.
- `M0-004` resolved: setup validates the SQLite path or exact explicit MySQL target before migration.
- A full later-milestone MySQL regression exposed two M4 assertion mismatches and one M4 concurrency-test query error. They are not M0 failures and were not changed under this bounded review; the M0 MySQL gate passes.

## 13. Security and privacy risks

- The hosted workflow must run before automatic regression blocking is proven in GitHub.
- The setup guard rejects default/ambiguous MySQL targets before Laravel boots or migrates.
- No real child, guardian or payment data was used in this audit; future seeds must remain unmistakably synthetic.
- MariaDB/XAMPP must not be counted as MySQL 8.4 evidence.

## 14. Root UX/UI problems

- Page templates independently choose widths from `max-w-4xl` through `max-w-7xl`.
- Tickets use a page-specific 1599px card breakpoint, so desktop behaves like tablet.
- Repeated inline utilities substitute for a small shared page/table vocabulary.
- Typeface loading and focus-state rules conflict or duplicate.

## 15. Proposed radical improvements

First establish one shared desktop page canvas and table/action contract. Then, during each milestone review, rebuild screens around the task sequence: context → primary action → comparable data → secondary/destructive actions. Do not perform a global cosmetic rewrite before the functionality and role matrix of that milestone is verified.

## 16. Prioritized backlog

| Issue | Severity / priority | Proposed fix | Acceptance |
|---|---|---|---|
| M0-001 CI absent | S1 / P1 | Implemented; run hosted workflow | Local equivalent gates pass; hosted execution pending. |
| M0-002 Composer unavailable | S1 / P1 | Documented prerequisite and CI provisioning | Strict local phar validation passes; workstation PATH remains external. |
| M0-003 demo seed missing | S1 / P1 | Implemented | Rerun-safe two-tenant role fixtures pass. |
| M0-004 unsafe setup order | S1 / P1 | Implemented | Unsafe/default targets fail before migration. |
| M0-005 MySQL proof absent | S1 / P1 | Implemented | Isolated MySQL 8.4.11 M0 gate passes. |
| M0-006 toolchain drift | S2 / P2 | Implemented | PHP, PHPUnit, lock, README and CI agree. |
| DS-001 desktop canvas inconsistency | S2 / P2 | Shared full-screen desktop page and table rules | 1366px remains desktop and uses available width. |
| DS-002 font/focus duplication | S3 / P3 | One typography load and one focus rule | Arabic/English and keyboard focus render consistently. |

## 17. Acceptance criteria

- PASS locally: setup refuses ambiguous/shared targets and defaults to project-local SQLite.
- PENDING externally: hosted CI must pass fresh MySQL 8.4 migrations, M0 tests, full SQLite regression, Pint, Vite and docs.
- PASS: synthetic seed provides two tenants, four branches and Owner/Manager/Reception/Cashier and is rerun-safe.
- PASS: MySQL rejects cross-tenant assignment and the authorized path passes.
- PASS: toolchain versions agree across configuration, lock, CI and documentation.
- PASS as M0 contract: desktop rules are canonical; visual implementation/proof stays milestone-scoped.

## 18. Regression plan

1. Fresh SQLite and MySQL 8.4 migrations.
2. Run the seed twice; assert stable tenant/branch/user/assignment counts.
3. Run `TenantBranchTest` on both engines plus the direct composite-FK negative.
4. Run the M0 tests on MySQL plus the full regression on SQLite, Pint, Vite and docs in CI.
5. After later UI approval, capture 360/768/1366 LTR/RTL evidence, keyboard focus and 200% zoom for the screens in that milestone.

## 19. Final closure recommendation

**M0 is locally accepted.** M0-001 through M0-006 are implemented and their local equivalents pass. The only remaining closure item is a successful hosted run of `.github/workflows/ci.yml`; no push or remote execution was authorized in this review.

Stop here. Do not start M1 review or redesign M1–M3 screens until the owner accepts this report and the hosted M0 gate is green.
