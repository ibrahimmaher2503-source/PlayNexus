# Current Milestone

## 2026-09-14 M0 repair LOCALLY ACCEPTED; hosted CI pending

The bounded M0 audit repairs are implemented: safe project-local SQLite setup with an explicit MySQL target guard, deterministic local/testing-only two-tenant role fixtures, aligned PHP 8.4+/PHPUnit 12 tooling, and a minimal hosted gate for MySQL 8.4 M0 tests plus full SQLite regression, Pint, Vite and documentation. Local SQLite passes 314 total / 311 passed / 3 skipped / 2,659 assertions; the focused isolated MySQL 8.4.11 gate passes 7 tests / 49 assertions after all 21 migrations. Composer validation, Pint, build, docs and whitespace pass.

The first hosted workflow run remains external and pending. A diagnostic full MySQL run also exposed three later-M4 test portability issues; they are recorded but not changed under the M0-only boundary. Do not start M1 review until the owner accepts this M0 report and the hosted gate is green.

## 2026-09-14 M4 implementation and browser runtime accepted; MySQL evidence pending

OQ-19 is implemented as the approved Reception-to-Cashier handoff: guardian last-four verification or audited Manager/Owner override freezes an immutable quote and transitions only the active session to `pending_payment`. The same bounded M4 slice adds server-derived on-time/due/overdue state, fixed 30-minute extensions priced from the immutable snapshot, append-only reasoned Manager/Owner adjustments, and reasoned terminal cancellation. An extension moves the overtime boundary as well as adding its frozen unit charge, so it cannot be charged twice. Every mutation uses tenant/branch scope, actor/reason/audit evidence, optimistic versioning and UUID idempotency; active HTML forms redirect safely to the scoped board.

Focused M4 tests pass on process-local SQLite: 26 passed, 1 explicit MySQL-concurrency skip, 197 assertions. Scoped Pint, Blade cache, routes, Vite build, documentation validation and whitespace checks pass. An isolated, authenticated desktop browser run at `127.0.0.1:8215` passes with a synthetic Owner: a 30-minute extension updates the stored expected end and total from `256.50` to `342.00 EGP`, guardian last-four verification freezes that total and sends the session to `pending_payment`, and the English LTR and Arabic RTL screens both render correctly. Payment, receipt, refund, child release, shift, pause/resume and provider notifications remain deliberately excluded for M5/later. The only remaining runtime gate is an isolated MySQL 8.4/InnoDB run; the former private listener is not running.

## 2026-09-13 M4 checkout-preparation slice LOCALLY ACCEPTED; M5 NOT STARTED

OQ-19 is resolved as a reception-to-cashier handoff. Reception/assigned Manager verifies an active checkout-capable guardian by registered-phone last four digits, or a permission-checked manager override with a non-empty reason, then the server calculates and freezes the exact quote and moves the session to `pending_payment`. Cashier cannot prepare checkout; M5 will post one matching payment and atomically complete the session. UUID replays are idempotent, changed replays and stale versions conflict, terminal/ineligible/foreign cases fail safely, and successful preparation is event/audit recorded.

Focused `PlaySessionCheckoutPreparationTest` passes 8 tests / 66 assertions on process-local SQLite. Central integration also passes full process-local SQLite `php artisan test --compact`: 293 total / 291 passed / 2 skipped / 2,494 assertions; `npm run build`, scoped Pint, Blade cache/compilation, and `git diff --check` pass (build has the existing optional `fontaine` notice). Full global Pint only exposed a pre-existing `ordered_imports` issue in `StaffStatusController`, outside M4 and not modified. This is not full M4 or production acceptance: PHP 8.5, isolated MySQL, browser, payment, refund, receipt, child release, shift, pause/extension/adjustment gates remain pending.

## 2026-09-13 M3 LOCALLY ACCEPTED; M4 NOT STARTED

M3 now includes a deterministic read-only estimate for each Active session from its immutable pricing snapshot and one server clock. Fixed duration plus grace, rounded-up overtime units, and branch-snapshotted inclusive/exclusive tax use integer minor units and half-up rounding. The bilingual surface labels the value as an as-of estimate and never persists it or presents checkout/payment as complete. Malformed snapshots fail closed.

Three `gpt-5.6-luna` / `xhigh` workers delivered calculator, boundary tests, and bilingual copy; coordinator review integrated the view and corrected fail-closed handling. Focused quote/session checks pass 18 / 177. Full PHP 8.4 and 8.5 SQLite pass 279 of 281 / 2,387 with two MySQL-only skips; MySQL 8.4.11 passes 281 / 2,434. Authenticated Edge QA passes Arabic/English, Owner/Cashier, mobile/tablet/desktop, exact 14% display fixture, no overflow, and no browser errors.

M3 is locally accepted, not production-released. Per the product owner's stop instruction, M4 has not started. OQ-12 guardian verification is already approved; OQ-19 station/payment ownership remains open. Final checkout, guardian release, payment, receipt, pause/extension/adjustment, alerts, and refund execution remain unimplemented.

## 2026-09-13 check-in/session stage CLOSED

IMPLEMENTED / LOCALLY ACCEPTED: atomic idempotent ticket-backed check-in, one Active play session with immutable pricing/time facts, tenant-wide Active/Paused child uniqueness, hard branch capacity, append-only session/scan/audit evidence and a bilingual masked live board. Owner/assigned Manager/Reception may check in; Cashier is read-only. Full MySQL passes 272 / 2,385, SQLite and PHP 8.5 pass 270 of 272 / 2,338 with two MySQL-only skips, true concurrency passes 2 / 47, and authenticated responsive Edge QA passes. Existing port 8206 was untouched.

This closes the bounded check-in/session stage, not production readiness or checkout/finance. M4 did not start. If the owner explicitly resumes it, first freeze the smallest checkout/time-and-charge contract and OQ-19 station ownership before implementing any release verification, final charge, payment or refund behavior.

## 2026-09-13 historical ticket-only engineering acceptance

IMPLEMENTED / M3 PARTIAL: three user-requested Luna/xhigh workers delivered UI, focused tests and independent review, followed by coordinator integration. The slice includes immutable branch ticket types, dated issuance with frozen integer money/time facts, encrypted opaque QR, idempotent validation, permanent holder lock after the first accepted scan, audited pre-scan correction, manager/owner unused-ticket cancellation and immutable reprint. Full MySQL passes 262 / 2,233; SQLite passes 261 / 2,210 with one MySQL-only skip. Scoped authenticated headless Edge visual/QR/print acceptance passes. See `.ai/TEST_RESULTS.md` for exact evidence.

At this ticket-only checkpoint, validation was not check-in, consumption or session creation; the current section above records the later accepted implementation. Cancellation still does not post a refund. OQ-09 refund execution and approved tax-total examples remain separate gates. The existing port 8206 runtime/database was not migrated or restarted; apply both current M3 migrations to the explicitly selected runtime database before using these slices there. Task-local QA is not production approval.

## 2026-09-13 M3 ticket decision closure

OQ-18 is approved for Egypt. The next bounded M3 implementation may add branch/service-date-scoped ticket types and issuance, QR validation, pre-scan assignment correction, permanent transfer lock on first successful scan, unused-ticket cancellation/refund eligibility with manager/owner approval, and atomic idempotent ticket consumption plus check-in. OQ-09 still governs payment method, refund window, and reversal execution, while approved finance tax examples remain required before calculator/checkout acceptance.

## 2026-09-13 Egypt M2 engineering closure

The approved Egypt family contract is implemented and has full SQLite/MySQL regression evidence at 241 tests / 1,997 assertions. The visible flow includes explicit Arabic-first child-data consent, a separate optional unchecked marketing choice, emergency contact, restricted encrypted safety notes, and verified relationship lifecycle controls. Tenant uniqueness, hard reuse, role/scope denials, append-only evidence, withdrawal behavior, and the final-guardian invariant are enforced in code and tests.

M2 is **IMPLEMENTED / RELEASE PARTIAL**: real browser acceptance is still unavailable, and production remains gated on Legal/DPO approval of the deployed privacy notice and processor/transfer/licensing details. Visit history and retention execution wait for M3 session/last-visit data under the approved dependency waiver. These gates do not reopen the product decisions or authorize unrelated M3 expansion.

## 2026-09-12 M2 remediation checkpoint

M1 remains closed. The approved M2 family slices are hardened: family permissions are action-specific, Cashier responses are server-masked and read-only after initial registration, search excludes inactive guardians/children, and the responsive application shell/assignment workflow has been improved without adding a new design-system dependency. Branch creation is retry-safe through a tenant-scoped idempotency key, and JSON failures expose a stable request ID.

Automated acceptance is current on both SQLite and isolated MySQL 8.4.11/InnoDB at 234 tests / 1,943 assertions. The Egypt M2 product decisions are now approved: tenant-unique normalized phone with hard reuse, versioned legal-guardian child-data consent, separate optional marketing consent, three-year default retention, required emergency contact, restricted encrypted safety notes, verified relationship lifecycle/last-guardian invariant, visit-history dependency waiver, and incident deferral. M2 stays **IN PROGRESS** until this new contract is implemented/tested and browser acceptance plus production Legal/DPO review are complete. No additional M3 behavior is authorized.

## 2026-09-12 M2 started — family registry first slice

M1 remains closed. M2 starts with a bounded staff-assisted family registry: current-tenant search by normalized guardian phone or child name, and atomic creation of one guardian, one child, and one active relationship. Same-tenant phone matches block a second record and return the existing-family path until OQ-17 chooses merge/create-with-approval behavior; cross-tenant matches are never disclosed. Consent events, safety notes/photos, family editing, history, check-in, and M2 closure remain outside this first wave until their approved contracts are ready.

T33 schema/models, T34 authorization/controller/routes, and T35 bilingual UI are integrated and centrally reviewed. Next: obtain isolated MySQL evidence for the new migration, close OQ-17 and legal consent wording/version/retention, then plan the next bounded M2 slice.

## 2026-09-12 M2 second slice — family profile maintenance

Implement a tenant-scoped family detail page, basic guardian/child edits with optimistic conflict checks, and adding one new child plus active relationship to an existing guardian. Audit successful changes without copying phone, email, DOB, or names into audit JSON. Consent, safety/emergency data, relationship revocation, guardian merge, visit history, tickets, and check-in remain outside this slice.

T36 backend authorization/commands, T37 bilingual family-profile UI, and T38 adversarial review are integrated and centrally corrected. Automated, build, format, and route gates pass. Browser acceptance remains blocked by `User unavailable`; isolated MySQL evidence and the OQ-17/legal-consent decisions remain open.

## 2026-09-12 M3 first slice — fixed-duration pricing configuration

Create and list immutable branch pricing rules using the approved OQ-16 shape: integer EGP amounts, fixed package duration, 600-second grace, 1,800-second rounded-up overtime units, and selected-branch tax snapshot. No default business prices are seeded. This historical slice excluded ticket types/QR; OQ-18 was subsequently approved in the top current-milestone entry. Calculation, ticket implementation, check-in, sessions, extensions, retirement, and M3 closure remain later.

T39 pricing data/integrity, T40 scoped backend/audit, and T41 bilingual UI are integrated and centrally reviewed. Automated security and money-conversion checks pass. Browser acceptance is blocked by `User unavailable`; fresh isolated MySQL evidence and later pricing version/retirement/calculation work remain open. Ticket types stay blocked by OQ-18.

## 2026-09-12 M3 second slice — immutable pricing version replacement

Allow an authorized owner/branch manager to replace one active rule by atomically retiring it and creating version +1 with the same tenant, branch, and code. The new version takes new package prices/duration and the current locked branch tax snapshot; the old row remains unchanged except status. Stale/replayed forms conflict and no in-place money/duration update is exposed.

T42 version command/security, T43 bilingual replacement UI, and T44 adversarial immutability review are integrated and centrally reviewed. Focused and full automated gates pass. Browser acceptance is blocked by `User unavailable`, and fresh isolated MySQL evidence remains outstanding. Calculator/tax totals, tickets, QR, check-in, sessions, and M3 closure remain excluded.

## 2026-09-12 M1 closed

M1 access and branch foundation is DONE on `codex/first`. PHP 8.5 compatibility (T31) is closed; its full suite passes on PHP 8.4.21 and 8.5.8, and its migrations plus suite passed on isolated MySQL 8.4.11/InnoDB. M2 is now in progress through the family-registry first slice; this is not M2 closure or production readiness. No main merge or push.

## 2026-09-12 T30 and T32 runtime acceptance

T30 MySQL and T32 browser acceptance are DONE and integrated on `codex/first`. MySQL 8.4.11/InnoDB passed the current migrations and full suite; real browser checks covered platform provisioning/lifecycle, bilingual platform pages, and branch-settings locale switching that remains on the same safe local page. Post-merge regression passed 141 tests / 1,077 assertions. PHP 8.5 (T31) remains the next runtime gap. No main merge or push.

## 2026-09-12 T27-T29 platform administration wave

Platform Super Admin tenant administration is locally integrated on `codex/first`: isolated platform login/session enforcement, tenant provisioning with an invited initial owner and idempotent replay, tenant lifecycle activation/suspension, a separate bilingual platform shell, and platform audit records. Tenant users are forbidden from platform management and suspended tenants cannot continue protected operations.

Focused security acceptance passed 17 tests / 178 assertions; the full suite passed 138 tests / 1,059 assertions. All Blade views compile; Pint, frontend build, documentation validation and whitespace checks pass. Browser remains deferred by user. MySQL validation for migrations added after T07B and PHP 8.5 remain outstanding. No main merge or push.

Next work should close M1 runtime evidence: isolated MySQL 8.4 migration/full-suite verification for the current schema, PHP 8.5 compatibility, then the deferred browser journeys for platform and account/settings flows.

## 2026-09-12 T24-T26 integrated feature wave

Password recovery, tenant business-profile settings, and full branch operational settings are locally integrated on `codex/first`. Password resets are generic, time-limited and single-use, and increment `auth_version` to revoke older authenticated sessions. Tenant owners can update their own bilingual profile and configure own-tenant branches, including inactive branches, with optimistic conflict checks, transactions and atomic audit records. Branch configuration covers code, address, timezone, capacity, EGP currency, tax mode/rate, receipt prefix, cash payment and all seven opening-hours rows.

Focused acceptance passed 18 tests / 190 assertions; the full suite passed 121 tests / 881 assertions. Pint, frontend build, documentation validation and diff checks passed. Browser remains deferred by user. The three new migrations and locking behavior remain unverified on MySQL, and PHP 8.5 remains unverified. No main merge or push.

Next bounded M1 work: platform-super-admin provisioning and authorization, followed by an isolated MySQL migration/locking acceptance pass for the post-T07 schema. Email transport delivery and browser journeys remain separate runtime acceptance work.

## 2026-09-12 T21-T23 integrated feature wave

Staff invitation, owner branch lifecycle, and tenant audit viewing are locally integrated on `codex/first`. Focused verification passed 23 tests / 188 assertions; the full suite passed 103 tests / 691 assertions. Pint, frontend build, documentation validation, and whitespace checks passed. Browser remains deferred by user; MySQL/PHP 8.5 remain unverified. No main merge or push.

## 2026-09-12 T18-T20 integrated feature wave

Three requested Luna/xhigh workers delivered complete features in isolated worktrees from `bb5f883`. Integrated T18 `ee9a35b` (owner all-active-branch access), T19 `7c6e171` plus `401a235` (existing non-owner staff status and per-row validation correction), T20 `3066663` (fixed branch assignment management). T20 worker later amended its commit to `95e99b6` solely for SQL-null assertion handling; coordinator applied that correction directly after the original commit was integrated. Shared audit migration, route composition, owner navigation and canonical documentation are coordinator-owned.

Delivered: active owners list/select/read all active own-tenant branches; ownership revocation clears selected context unless an independent permitted staff assignment remains. Owners manage existing non-owner staff status at `/app/staff` and fixed branch roles/access at `/app/assignments`. All owner accounts remain protected. Administration requires reason codes, expected-state conflict checks, scoped transaction locks and atomic successful-change audit. No user creation, invitation delivery, owner assignment/transfer, platform access or arbitrary permission maps.

Review found and corrected Query Builder misuse, query callback type mismatch, JSON audit serialization, per-row old-input contamination, and generic HTML409 presentation. Added a localized conflict page. Tests were inspected as code as well as executed; a stale-branch regression's setup used the wrong query and was corrected before acceptance.

Verification in isolated integration checkout: focused `php artisan test --filter='OwnerBranchAccessTest|StaffManagementTest|BranchAssignmentManagementTest'` PASS 28 tests / 187 assertions after three initial failures were resolved. Full `php artisan test` PASS 80 tests / 503 assertions. Process-local SQLite `:memory:`, empty DB_URL, array sessions, SESSION_CONNECTION unset. `php vendor/bin/pint --test` PASS after scoped formatting; `npm run build` PASS (optional fontaine notice); documentation validator PASS 0 errors / 2 existing warnings; `git diff --check` PASS.

Status: DONE for local implementation and automated acceptance. Browser DEFERRED_BY_USER. MySQL migration/row-lock concurrency and PHP8.5 remain unverified; SQLite transaction rollback/expected-state tests are not evidence of MySQL concurrent behavior. Successful admin-change audit is delivered, not the complete denied/security audit program. Full M1/production acceptance remain incomplete. No shared service changes, main merge or push.

## 2026-09-12 owner-read slice integrated

T14 contract, T15 backend, T16 bilingual UI and T17 security tests are DONE for local code/automated acceptance on `codex/first`. An explicitly provisioned active tenant owner can read `/app/tenant` without branch assignment; staff see no owner navigation and receive 403. The server resolves tenant scope; no request tenant ID can select another institution. Read-only staff list is paginated, localized and limited to name/email/status plus internal id.

Combined verification PASS: 52 tests / 315 assertions, Pint, build and docs/diff checks. Evidence is in TEST_RESULTS.md. Browser is DEFERRED_BY_USER, MySQL for the new migration and PHP 8.5 remain unverified. This supersedes T14 representation-blocked statements for this slice only. Owner-wide branch operations, platform authorization, assignment management UI and full M1 remain incomplete.

## 2026-09-12 combined integration checkpoint

Recovered local integration at `6dafe21`: T10 `c3b2065` and T11 `c61f9e8` are merged into `codex/first`; T12 report `4093e8d` is integrated. Reviewed status enforcement, forward migration/backfill, mass-assignment exclusion, policy checks, locale middleware/controller and the failed-login interaction. Retained the pending focused fix that restores the validated locale after failed-login session invalidation; its regression checks guest state and the following Arabic RTL page.

Combined verification: `php artisan test` PASS (37 tests / 247 assertions), process-local SQLite `:memory:` and array sessions; `php vendor/bin/pint --test` PASS; `npm run build` PASS (optional fontaine notice); documentation validator PASS (0 errors / 2 existing placeholder warnings); `git diff --check` PASS.

Three Terra review agents were attempted (`staff_review_now`, `locale_review_now`, `owner_review_now`); all ended with usage-limit errors, so no independent agent review is claimed. Coordinator performed the code review. T12 is accepted as investigation only: owner representation and owner branch scope remain unresolved; no owner/platform implementation is authorized by that report.

Status: code integrated and automated checks PASS; T13 runtime acceptance remains PARTIAL. No combined T10/T11 browser or MySQL acceptance is claimed; historical T07/T09 evidence does not cover this wave. Next bounded acceptance is active login -> locale switch/invalid-login persistence -> select branch -> suspend user -> protected reload clears authentication/context. Then close T13 and define owner representation before dispatching the next implementation wave. Full M1, PHP 8.5 and production remain incomplete. No main merge, push or shared service change.

## 2026-09-10 T09 integration accepted

T09 integrated locally into codex/first from accepted 0c93d50, preserving coordinator 27ade78. The only conflict was .ai/TEST_RESULTS.md; both evidence sections were retained. Combined tests PASS: 26 tests/156 assertions; Pint PASS; docs validator PASS (0 errors/2 known warnings); diff check PASS. Application/tests/dependencies match accepted worker code. No additional build, MySQL or browser run was needed for this documentation-only conflict resolution. T09 integration DONE; full M1, owner/platform permissions, PHP 8.5 and production remain incomplete. No main merge or push.

This entry supersedes earlier T09 pending-review/not-integrated statements below.

## 2026-09-10 orchestrator acceptance

T08 is DONE following independent review of 7a3963e and passing integration checks. This supersedes pending-review statements below. The accepted auth/branch baseline is on codex/first; next is scoped M1 policy/gate planning, not implementation yet. Full M1, PHP 8.5 and production readiness remain incomplete.

## 2026-09-10 T09 branch view authorization

Implemented fixed `branches.view` authorization through Laravel `BranchPolicy` and Gate across branch listing, selection, direct reads and stored-context revalidation. `branch_manager`, `reception_staff`, `cashier`, and legacy `reception` are allowed only on active assigned branches; unsupported or misplaced roles deny by default. T09 is `REVIEW_REQUIRED` pending orchestrator review. The policies/gates scope remains limited to this permission, and full M1, PHP 8.5 and production readiness remain incomplete.

## Milestone

M1: Staff authentication and branch-aware access foundation.

## Goal

Provide staff-only session access from `users.tenant_id`, active assigned-branch selection, tenant/branch isolation, a minimal bilingual operational shell, and fixed branch `branches.view` authorization. The bounded auth/branch slice and T07 MySQL 8.4/InnoDB runtime acceptance are accepted; T09 is implemented on `codex/t09-branch-view-policy` and remains `REVIEW_REQUIRED` pending orchestrator review. No customer, session, pricing, payment, reporting, tenant-owner, platform, or broader M1 module is in scope.

## Next three actions

1. Complete orchestrator review of the T09 BranchPolicy/Gate implementation and evidence.
2. If accepted, integrate the focused T09 branch into `codex/first`; do not begin another permission or policy slice here.
3. Track PHP 8.5 validation, full M1 completion and production readiness as separate incomplete dependencies.

## 2026-09-10 M1 foundation slice

Tenant/branch context and assignment-scoped access are implemented and verified with the focused SQLite suite. MySQL migration/runtime verification remains blocked by the unavailable environment.

## 2026-09-10 T08 integration

`codex/first` was checked out at `C:\Users\N\.codex\worktrees\t08-integration\PlayNexus` and fast-forwarded from `03108c6` to accepted `37b4f6b`. The coordinator ledger was imported from the main checkout and the accepted worker and coordinator evidence were reconciled in a focused documentation commit. T08 is `REVIEW_REQUIRED`; full M1, PHP 8.5 validation and production readiness remain incomplete.

## 2026-09-10 update

Initial G1 baseline choices and OQ-08/OQ-12/OQ-16 decisions were approved and synchronized across the canonical contract documents. T03 scaffold is integrated from commit `7866de3b7c04386621217adb8b9c43c46c588cae`.

## 2026-09-10 T05 update

T05 adds staff session authentication, login throttling, session-backed active branch selection, stale-context clearing, and a minimal English/Arabic shell. SQLite feature tests and frontend build pass; MySQL migration/runtime remains `BLOCKED_BY_ENVIRONMENT`.
