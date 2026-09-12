# PlayNexus Agent Plan

## 2026-09-12 M2 T33-T35 first family-registry wave

The user authorized M2 start with three Luna/xhigh workers. Fixed contract: one visible flow at `/app/families` and `/app/families/create`; search by trimmed/capped child name or normalized guardian phone; only current-tenant safe results; create one guardian, one child and one active guardian-child link in one transaction. Same-tenant normalized phone match creates nothing and returns the existing-family path; cross-tenant matches stay undisclosed. No phone uniqueness constraint, consent event, safety note/photo, editing, visit history, check-in or speculative permission key in this wave.

- **T33 data:** migration, Guardian/Child models and factories, relations/casts/masked-phone helper, and database-integrity tests only.
- **T34 backend:** family authorization for active owner or fixed eligible branch roles, E.164 normalization, controller/routes, transaction/audit, duplicate guard, and backend security tests only.
- **T35 UI:** bilingual accessible search/create/results/empty/error/success views, navigation entry and UI feature tests only.

**Accepted result:** T33-T35 are integrated after coordinator corrections for pivot naming, actor evidence, phone formats, query/view contracts, child-name search, allowed relationship values, duplicate recovery, and transaction rollback. Focused tests pass 19 / 148; full PHP 8.5 regression passes 169 / 1,328; Pint and Vite pass. Browser creation/search passed and synthetic data was removed; duplicate visual verification was interrupted by browser unavailability and remains covered automatically. The new migration still needs isolated MySQL acceptance. M2 remains in progress, not complete.

## 2026-09-12 custom roles and staff administration accepted

Three requested Luna/xhigh workers split custom roles, staff search, and direct staff creation. Coordinator reviewed and tightened the result: `/app/roles` creates tenant roles and toggles the enforced `branches.view` permission; eligible roles are assignable at `/app/assignments`; staff search is tenant-scoped by name/email; `/app/staff/create` creates an active account with a generated unknown password for password recovery. Built-in role maps, owner transfer, email delivery, and speculative permission keys remain out of scope.

## 2026-09-12 M1 closed

T31 PHP 8.5 compatibility is DONE: the full suite passes on PHP 8.5.8. Final coordinator review added the shared authenticated shell, polished tenant settings, semantic state tokens, audit labels/filters for branch and tenant settings, and a missing branch-settings translation fix. Final M1 acceptance: 142 tests / 1,096 assertions on SQLite under PHP 8.4.21 and PHP 8.5.8; 142 / 1,096 after fresh migrations on isolated MySQL 8.4.11/InnoDB; focused UI/audit 33 / 217; Pint, Vite, Composer audit, npm audit, documentation validation, whitespace, real Arabic RTL/English LTR browser, and console checks PASS. M1 DONE; M2 subsequently started above; no main merge or push.

## 2026-09-12 T30 and T32 final acceptance integrated

Three requested Luna/xhigh workers completed MySQL execution, browser execution, and independent review. T30 `3c2d6fd` is merged by `385b58d`; isolated MySQL 8.4.11/InnoDB passed migrations, 46 tests / 389 assertions focused and 138 / 1,059 full. T32 `108ca47` is merged after it fixed safe same-page locale switching and browser-verified `/app/branches/1/settings` in Arabic RTL and English LTR. Post-merge SQLite verification passed 54 / 444 focused and 141 / 1,077 full; Pint and diff checks pass. T31 PHP 8.5 remains outstanding. No main merge or push.

## 2026-09-12 T21-T23 delivery accepted

Three Luna/xhigh feature workers delivered complete vertical slices: T21 staff invitation `4c7df3b`, T22 branch lifecycle `29aa88e`, and T23 tenant audit viewer `77745f0`. Coordinator reviewed actual diffs/tests, wired the feature route files and owner navigation, corrected invitation test assertions/session setup, and accepted the combined result after 23/188 focused and 103/691 full tests plus Pint/build/docs/diff checks. Browser remains deferred; MySQL/PHP 8.5 and broader platform/owner-transfer scope remain incomplete. No main merge or push.

## 2026-09-12 T18-T20 integrated feature wave

Three requested Luna/xhigh workers delivered complete features in isolated worktrees from `bb5f883`. Integrated T18 `ee9a35b` (owner all-active-branch access), T19 `7c6e171` plus `401a235` (existing non-owner staff status and per-row validation correction), T20 `3066663` (fixed branch assignment management). T20 worker later amended its commit to `95e99b6` solely for SQL-null assertion handling; coordinator applied that correction directly after the original commit was integrated. Shared audit migration, route composition, owner navigation and canonical documentation are coordinator-owned.

Delivered: active owners list/select/read all active own-tenant branches; ownership revocation clears selected context unless an independent permitted staff assignment remains. Owners manage existing non-owner staff status at `/app/staff` and fixed branch roles/access at `/app/assignments`. All owner accounts remain protected. Administration requires reason codes, expected-state conflict checks, scoped transaction locks and atomic successful-change audit. No user creation, invitation delivery, owner assignment/transfer, platform access or arbitrary permission maps.

Review found and corrected Query Builder misuse, query callback type mismatch, JSON audit serialization, per-row old-input contamination, and generic HTML409 presentation. Added a localized conflict page. Tests were inspected as code as well as executed; a stale-branch regression's setup used the wrong query and was corrected before acceptance.

Verification in isolated integration checkout: focused `php artisan test --filter='OwnerBranchAccessTest|StaffManagementTest|BranchAssignmentManagementTest'` PASS 28 tests / 187 assertions after three initial failures were resolved. Full `php artisan test` PASS 80 tests / 503 assertions. Process-local SQLite `:memory:`, empty DB_URL, array sessions, SESSION_CONNECTION unset. `php vendor/bin/pint --test` PASS after scoped formatting; `npm run build` PASS (optional fontaine notice); documentation validator PASS 0 errors / 2 existing warnings; `git diff --check` PASS.

Status: DONE for local implementation and automated acceptance. Browser DEFERRED_BY_USER. MySQL migration/row-lock concurrency and PHP8.5 remain unverified; SQLite transaction rollback/expected-state tests are not evidence of MySQL concurrent behavior. Successful admin-change audit is delivered, not the complete denied/security audit program. Full M1/production acceptance remain incomplete. No shared service changes, main merge or push.

## 2026-09-12 T14-T17 delivery accepted

| Task | Worker | Delivery / outcome |
| --- | --- | --- |
| T14 owner-read contract | Coordinator | DONE: explicit tenant_owners, tenant-global read only; canonical docs synchronized |
| T15 backend | owner_backend_luna, Luna/xhigh | DONE: bb6df9e reviewed and integrated |
| T16 UI | owner_ui_luna, Luna/xhigh | DONE: a451bca reviewed and integrated |
| T17 tests | owner_tests_luna, Luna/xhigh | DONE: 96fbe14 reviewed and integrated |

Workers reported exact base/SHA/owned files, executed static checks and missing runtime verification. Coordinator verified actual diffs, fixed findings through worker feedback, and ran combined tests (52/315), Pint, build and docs/diff checks successfully. This is local code and automated acceptance; browser DEFERRED_BY_USER, new MySQL migration and PHP 8.5 unverified. No main merge or push. Future owner-wide branch scope must get its own fixed contract before another implementation wave; full M1 is not complete.

## 2026-09-12 user-directed browser deferral and next proposed wave

The user explicitly deferred browser testing for now. T13 local code integration and automated checks are complete; browser acceptance is DEFERRED_BY_USER, not PASS, and does not block planning the next wave. MySQL coverage of this wave remains unverified.

Proposed sequence (not dispatched): T14 coordinator defines and records the tenant-owner representation and the read-only route/data contract, reconciling the ERD/architecture conflict. Recommended initial scope is own-tenant profile and staff list, with explicit tenant-level ownership; a branch role must never imply ownership. Owner access to all branches and platform access stay separate.

After T14, three workers can run concurrently: T15 backend owns the approved migration, owner relation, policy, read controller and route; T16 UI owns the tenant profile/staff-list Blade view and its English/Arabic strings against the fixed view contract; T17 security tests owns one focused feature test file for owner allow, staff deny, foreign scope, inactive states and revocation against the same contract. Each uses a separate worktree; coordinator owns shared status files and integration. T17's executable verification depends on integrating T15/T16, so interim test failures are not acceptance failures. One final integration/review/check round follows. These are proposed tasks, not started work or full M1 completion.

## 2026-09-12 combined integration checkpoint

Recovered local integration at `6dafe21`: T10 `c3b2065` and T11 `c61f9e8` are merged into `codex/first`; T12 report `4093e8d` is integrated. Reviewed status enforcement, forward migration/backfill, mass-assignment exclusion, policy checks, locale middleware/controller and the failed-login interaction. Retained the pending focused fix that restores the validated locale after failed-login session invalidation; its regression checks guest state and the following Arabic RTL page.

Combined verification: `php artisan test` PASS (37 tests / 247 assertions), process-local SQLite `:memory:` and array sessions; `php vendor/bin/pint --test` PASS; `npm run build` PASS (optional fontaine notice); documentation validator PASS (0 errors / 2 existing placeholder warnings); `git diff --check` PASS.

Three Terra review agents were attempted (`staff_review_now`, `locale_review_now`, `owner_review_now`); all ended with usage-limit errors, so no independent agent review is claimed. Coordinator performed the code review. T12 is accepted as investigation only: owner representation and owner branch scope remain unresolved; no owner/platform implementation is authorized by that report.

Status: code integrated and automated checks PASS; T13 runtime acceptance remains PARTIAL. No combined T10/T11 browser or MySQL acceptance is claimed; historical T07/T09 evidence does not cover this wave. Next bounded acceptance is active login -> locale switch/invalid-login persistence -> select branch -> suspend user -> protected reload clears authentication/context. Then close T13 and define owner representation before dispatching the next implementation wave. Full M1, PHP 8.5 and production remain incomplete. No main merge, push or shared service change.

## Parallel M1 wave — 2026-09-10

Current goal: complete independent M1 gaps in parallel, then one coordinated review/integration round. Base application commit 0e360eb; start workers from the planning commit containing this section. User requested three Terra subagents; coordinator dispatched /root/staff_status (A), /root/locale_switch (B), /root/owner_contract (C), all from 38fa251 in separate worktrees. Coordinator monitors, reviews and integrates accepted results locally.

| Task | Owner | Status | Dependency / acceptance |
| --- | --- | --- | --- |
| T10 staff status enforcement | A | INTEGRATED; RUNTIME_PENDING | T09 integrated; invited/active/suspended/disabled statuses, deny non-active login and revoke existing access on next request |
| T11 session locale switch | B | INTEGRATED; RUNTIME_PENDING | T09 integrated; en/ar switch persists, LTR/RTL and validation localization work without server reconfiguration |
| T12 owner/platform authorization contract investigation | C | DONE (investigation only) | Read-only code/spec analysis; identify authoritative scope representation, contradictions and smallest next vertical slice |
| T13 combined review/integration | Orchestrator | PARTIAL | Review A/B independently as delivered; merge accepted A then B, combine runtime journey; C informs next wave |

Ownership: A owns User/status migration and factory/seeder adjustments, existing auth controller/tenant middleware/TenantContext/BranchPolicy only as necessary, new StaffStatusTest and directly affected existing tests. B owns routes/web.php, bootstrap/app.php locale registration, new locale controller/middleware, lang files, existing Blade views/CSS only as needed, new LocaleSwitchTest. C owns only docs/owner-platform-contract-review.md; no implementation or approval edits. A/B return check evidence in their final reports; orchestrator alone updates shared .ai status/test records and this ledger at integration. Do not edit each other's tests or shared configuration. All workers use isolated data/ports/cookies; no shared DB/server/.env changes.

T10 contract: follow users.status values and invited default from ERD; add a forward migration preserving deployed schema and backfill existing users active for compatibility. New normal users default invited; test factories explicitly create active staff. Status is not a request-writable mass-assignment field. Enforce current DB status at the trust boundary and policy access, not stale loaded relationships. Non-active login gives existing generic failure; current non-active sessions lose authentication/branch context on the next protected request. Browser protected requests go to login; JSON gets 401 after revocation. Logout remains available. Existing inactive-tenant behavior/404 scope remains unchanged; no admin status editor, invite sending, owner scope or password reset. Record that physical deletion of every session row is not claimed; next-request enforcement is this slice's guarantee. Use scoped automated checks, existing suite, and an isolated browser active-login -> DB status suspension -> protected refresh -> logged-out check. Migration must be tested against fresh and existing rows; do not replace historical migrations.

T11 contract: session-based en/ar preference only (no users.locale migration). Add POST /locale with validated locale allowlist, CSRF and safe redirect to fixed local login or app route based on auth. No client-provided redirect target. Middleware runs after session starts and before rendering/validation; fallback en. Visible language control on login and app. Translate login required/invalid/type validation messages used by the slice; no broad translation dependency. Keep auth/policy decisions untouched. Focused tests cover persistence, invalid locale, safe redirect and guest/auth rendering; browser keyboard switch on login/app and Arabic validation on same runtime. Session locale after logout may reset to configured default; do not weaken session invalidation to preserve it.

T12: inspect permission matrix, ERD, decisions and real schema/code; distinguish explicit approvals from proposals. No invented tenant-owner assignment from branch_user role, no blanket super-admin bypass. Report competing models already documented, contradictions needing decisions, recommended minimal native representation with migration/validation/isolation tests, exact owned files and dependencies for the next implementation prompt. No implementation tasks are marked ready until the coordinator reviews it.

One review round: accept/reject precise diffs, fix only confirmed findings, integrate accepted code without a separate user prompt for each mechanical step under the user's accelerated coordination request. No main merge/push. Run each worker's scoped checks once, one combined suite after merge, and browser evidence only for changed flows. No repetitive broad QA or speculative fourth task. Full M1 remains incomplete until its actual remaining requirements are closed.

## 2026-09-10 T09 integration accepted

T09 integrated locally into codex/first from accepted 0c93d50, preserving coordinator 27ade78. The only conflict was .ai/TEST_RESULTS.md; both evidence sections were retained. Combined tests PASS: 26 tests/156 assertions; Pint PASS; docs validator PASS (0 errors/2 known warnings); diff check PASS. Application/tests/dependencies match accepted worker code. No additional build, MySQL or browser run was needed for this documentation-only conflict resolution. T09 integration DONE; full M1, owner/platform permissions, PHP 8.5 and production remain incomplete. No main merge or push.

This entry supersedes earlier T09 pending-review/not-integrated statements below.

## Orchestrator acceptance of T08 — 2026-09-10

T08 is DONE after review of 7a3963e48011f230a8d27ce91320fc8e1f942395. This acceptance supersedes pending-review statements below. The canonical coordination baseline is now this tracked ledger on codex/first in the t08-integration worktree; the untracked main-checkout ledger is historical. The bounded auth/branch slice is integrated and accepted. Full M1, PHP 8.5 and production acceptance remain incomplete.

Verified accepted-target ancestry, four-file documentation-only diff, retained worker evidence and all substantive coordinator review entries. The omitted old statement that no scaffold/tests exist is obsolete here. Main and QA refs/status and existing stash were preserved. Independently reran 21 tests/81 assertions, Pint, build, documentation validator (0 errors, 2 status-marker warnings) and diff checks successfully. No service restart, main merge or push.

T09 is accepted on worker branch at 0c93d5073960ae8dc6c93c86d22b1c176fb09bcd. Next is local integration and combined verification; no integration performed by this review.

## T09 review — 2026-09-10

- Reviewed nine-file diff against 92aca44, all policy call sites, fresh pivot lookup, scoped 403/404 behavior and regression tests. No confirmed correctness findings in the bounded contract.
- Independently reran full suite: 26 tests/156 assertions PASS with SQLite :memory:/array sessions; Pint PASS; docs validator PASS (0 errors, 2 known marker warnings); diff check PASS. Worker tree clean. No frontend/dependency/schema changes, so no additional build or MySQL migration run. T07 MySQL evidence predates this policy; T09 validation is SQLite only.
- Reviewed original worker browser outputs in task 01a08944-c762-7bb0-8945-a89e0044ac79: 8194/app as T09 Operator, permitted branch selection/reload, role revocation removes context, direct /branches/1 renders 403. No independent live browser rerun or screenshot-render claim. Port 8194 not listening at review.
- T09 DONE on worker branch, not integrated. Fixed tenant-owner/platform access, staff lifecycle, full M1, PHP 8.5 and production remain incomplete. Per-branch policy queries are acceptable for this bounded selector; optimize only if measured branch scale warrants it.
- Next integration must merge accepted worker 0c93d50 into codex/first while preserving this coordinator review commit and worker evidence. The coordinator review advances codex/first separately, so recheck ancestry and use a normal local merge if fast-forward is no longer possible. No force/rebase, main merge or push. Run combined tests and docs/diff checks before acceptance.

## T09 execution contract — DONE on worker branch

- Base: accepted codex/first at 84c5b00 plus this planning-only commit. User launches one isolated worker branch codex/t09-branch-view-policy; no implementation dispatched by the orchestrator.
- Objective: existing /app branch list, POST /branch-context/{branch}, GET /branches/{branch}, and stored branch-context revalidation enforce the same branches.view decision. Active membership alone must not grant access for arbitrary role strings.
- Supported branch roles for this slice: branch_manager, reception_staff, cashier. Existing stored reception is an explicit compatibility alias for reception_staff; do not mass-rewrite data or remove coverage for the alias. Unknown, empty, future and misplaced tenant_owner/super_admin pivot values deny by default. This does not define actual tenant-owner/platform access; no authoritative tenant-level ownership representation exists in current User/schema. Implement that separately before claiming full M1.
- Reuse TenantContext and activeBranches scope, plus Laravel-native BranchPolicy view and Gate authorization. Preserve 404 for foreign/unassigned/inactive scope; return 403 for an active in-scope assignment whose role lacks branches.view, following permission-matrix section 13. Filter denied branches from the selector and clear denied selected context after role revocation/change. Missing permissions and stale loaded relations must not preserve access.
- Role mapping remains server-side fixed code, no package/tables/custom permission editor. Only branches.view is delivered; do not pre-grant future operations or add an update/settings endpoint merely to exercise a second permission.
- Owner: one worker owns policy, any minimal shared role predicate, integration points in User/branch controllers/middleware/provider/routes as actually needed, focused tests, and .ai/PROGRESS.md, .ai/TEST_RESULTS.md, .ai/CURRENT_MILESTONE.md. It may add a clearly scoped implementation note to docs/08-Permission-Matrix.md without changing the business matrix. Coordinator alone edits this ledger. No concurrent workers on these files.
- Verification: direct policy negatives and real HTTP list/select/read negatives for unsupported role, role from a different branch, inactive tenant/branch/assignment and cross-tenant records; canonical and legacy reception success; role revocation clears selected context; auth/logout/throttle regressions unchanged. Run focused checks then full suite, Pint and docs/diff checks. Perform a bounded real-browser assigned-list/select/reload role-revocation check using isolated synthetic data; report exact engine and limitations. Do not restart shared services.
- Delivery: focused commit(s), exact checks/results, changed files, browser evidence and remaining scope; REVIEW_REQUIRED until orchestrator review. Depends on T08 DONE. Integration order: review T09 -> accepted correction if needed -> integrate -> combined verification. Owner/platform scope, staff lifecycle and further permissions remain deferred tasks needing separate contracts.

## Current startup snapshot — 2026-09-10

This snapshot supersedes the current-state and readiness statements in the historical ledger below. Earlier review results remain historical evidence, not tests rerun during this startup.

### Current Goal

T07B evidence accepted at `37b4f6b`; the bounded auth/branch-entry slice and its target-engine acceptance are DONE. T08 local integration has been performed in the isolated `codex/first` checkout at `C:/Users/N/.codex/worktrees/t08-integration/PlayNexus`; the coordinator baseline is versioned there and is pending orchestrator review. No main merge or push was performed; this is not full M1 or production acceptance.

### Current Repository State

- Active source checkout: `C:/Users/N/OneDrive/Documents/ChatGPT/PlayNexus`, branch `main`, HEAD `b6d09e1`; its pre-existing dirty/untracked coordinator state is preserved. Active integration checkout: `C:/Users/N/.codex/worktrees/t08-integration/PlayNexus`, branch `codex/first`, fast-forwarded from `03108c6` to accepted `37b4f6b`.
- Existing untracked items: `.codex/`, `bootstrap/` (generated cache PHP files), and this ledger. Preserve them; cache artifacts alone do not constitute an application.
- `codex/first` now points to the T08 coordination commit on top of accepted `37b4f6b`; its application and dependency tree is unchanged relative to `37b4f6b`. `codex/t04-tenant-branch-foundation` remains at `03108c6`.
- Worker worktree `C:/Users/N/.codex/worktrees/344d/PlayNexus`: `codex/t03-scaffold-review` at `7866de3`, with an untracked `docs/agent-plan.md`.
- Worker worktree `C:/Users/N/.codex/worktrees/3284/PlayNexus`: detached at `b6d09e1`, clean at inspection.
- Existing stash: `orchestrator-docs-before-t03-merge`; leave intact.
- Delivered worker worktree: `C:/Users/N/.codex/worktrees/t05-auth-branch-access/PlayNexus`, branch `codex/t05-auth-branch-access`, clean at `c94a0461b1a2412c163ae22ea8d434a02b1a82ac`, parent `03108c6`. Its 21-file diff contains auth/branch UI and tests, with no dependency or migration change. It is not integrated into `main` or `codex/first`.
- Naming reconciliation: the worker calls this slice T05; in this ledger it corresponds to T06 (auth implementation). T05 remains the independent review responsibility. Refer to branch/commit as well as task ID to avoid duplicate work.
- Follow-up `9056439dc341aea76040dd4723fab7e13e536f0f` is now the clean auth worktree HEAD, directly after `c94a046`; five scoped files changed. Neither auth commit has been integrated into `codex/first` or `main`.
- QA worktree `C:/Users/N/.codex/worktrees/t07-runtime-acceptance/PlayNexus` is clean at `835346d8dbfad3b668813b0e93737c5767b6d371`, branch `codex/t07-runtime-acceptance`, parent `9056439`. Only `.ai/TEST_RESULTS.md` changed (22 added lines).
- Latest QA HEAD is `37b4f6b782c0020984567bb774d982b42428d3cb`, parent 835346d; only `.ai/TEST_RESULTS.md` changed (43 added lines). Tracked tree is clean; pre-existing untracked `ibrahim.err` remains untouched. Earlier clean/HEAD statements above are historical.
- `codex/first` contains approved-decision entries and canonical documentation changes absent from `main`. Its blocker text claims receipt/pricing/verification synchronization while its decision register still lists those items as open; resolve this discrepancy before dependent planning.

### Architecture Context

One Laravel modular monolith; Blade with selective Livewire; shared-schema MySQL tenancy; authenticated identity supplies tenant context; active branch assignment and permission checks deny by default. Money uses integer minor units plus ISO currency; timestamps persist in UTC and render in branch timezone. `docs/` owns specifications; `DESIGN.md` owns the petrol-teal/cool-porcelain UI, keyboard/touch and Arabic/English behavior. OpenAPI, permission matrix and ERD are contracts to inspect before splitting work.

### Task Graph and Status

| ID | Task | Status | Evidence / next gate |
| --- | --- | --- | --- |
| T00 | Startup inspection and ledger reconciliation | DONE | Branches, worktrees, commit history, guidance, architecture, milestones and test source inspected |
| T01 | Historical decision-impact investigation | REVIEW_REQUIRED | Earlier acceptance preserved below; report not re-reviewed in this startup |
| T02 | Approved decision / canonical contract synchronization | REVIEW_REQUIRED | Branch-specific docs exist; contradictory approval statements require reconciliation |
| T03 | Laravel scaffold | REVIEW_REQUIRED | Commit exists and is in `codex/first`; prior review preserved, no fresh runtime verification |
| T04 | Tenant/branch foundation slice | REVIEW_REQUIRED | Implementation and five focused test methods exist on `codex/first`; earlier SQLite results not rerun |
| T05 | Independent code review and SQLite verification of delivered foundation/auth slice | DONE | 9056439 reviewed; both confirmed defects closed, existing suite and independent probes pass; excludes runtime acceptance |
| T06 | Staff login/logout and authorized branch-entry vertical slice | DONE | Bounded slice accepted through 37b4f6b; not integrated; does not include complete M1 RBAC/staff lifecycle |
| T07 | Complete target-engine runtime acceptance | DONE | T07A and T07B evidence reviewed and accepted |
| T07A | SQLite database-session and English/Arabic browser fallback | DONE | Reviewed 835346d and worker interaction history; explicitly excludes MySQL |
| T07B | Provision isolated MySQL 8.4 and execute remaining acceptance | DONE | 37b4f6b: MySQL runtime, InnoDB constraints, tests and browser/database-session evidence accepted |
| T08 | Local integration and versioned coordinator baseline | DONE | Fast-forwarded codex/first `03108c6` -> `37b4f6b`; coordinator files and accepted worker evidence are preserved in a focused documentation commit; orchestrator review remains pending |

`REVIEW_REQUIRED` here is the current orchestration evidence status, not rejection of previously accepted code. Supported statuses include TODO, READY, IN_PROGRESS, BLOCKED, DONE, REVIEW_REQUIRED, CHANGES_REQUIRED and REJECTED.

### Dependencies and Execution Waves

- Correction wave complete: follow-up 9056439 accepted for the two reported defects after independent checks.
- Current wave: T08 local integration executed in the isolated checkout. The accepted history was fast-forwarded, the main coordinator ledger was imported and reconciled with worker evidence, and scoped checks were run before a focused coordination-document commit. Preserve main dirty/untracked state and worker ibrahim.err. No service restart, product changes, main merge or push. Fixed-role policies/gates planning follows only after orchestrator review of this baseline.
- T02 business-contract reconciliation gates affected pricing/receipt/guardian slices; it does not automatically block a bounded M1 authentication slice.
- Before the next major decision, re-read this ledger, Git status/worktrees/history, relevant docs, implementation and tests on the selected branch.
- Review T02 contracts before dependent business slices; preserve T03 -> T04 history and schedule T05 against the actual integrated result.
- No parallel implementation wave is scheduled during runtime acceptance.

### Agent Ownership and Conflict Map

- T08 prompt issued: one integration worker imported this ledger into an isolated codex/first checkout, updated factual integration status there, and combined coordinator review entries with worker `.ai/TEST_RESULTS.md` history. This was a scoped exception to earlier ledger-write exclusions; the original main-checkout ledger and all unrelated state stayed untouched. T08 is now REVIEW_REQUIRED pending orchestrator acceptance.
- Orchestrator owns this canonical ledger, task graph, prompts, review classification and integration recommendations.
- User manages the existing auth worker session. Correction ownership: `routes/web.php`, `app/Providers/AppServiceProvider.php`, focused auth tests, and the existing three worker-owned `.ai` status files. Coordinator alone owns this ledger and review records in the main checkout. No additional worker launched by the orchestrator.
- Next proposed QA worker owns only isolated runtime/test data, reproducible QA evidence and `.ai/TEST_RESULTS.md` updates on its own branch. No application fixes, shared service changes or coordinator-ledger edits; report findings before a correction wave.
- Shared/high-risk: `.ai/*`, `docs/contracts/openapi.yaml`, permission/ERD docs, routes, migrations, dependencies and `AGENTS.md`. Assign one owner per shared file before any wave.
- Worker worktrees must receive a versioned common coordination baseline; the current ledger is untracked and will not follow a branch checkout automatically.

### Integration Order

1. Select the baseline appropriate to the user's objective and reconcile branch-specific decisions/contracts.
2. Review existing commits and acceptance evidence before recommending reuse or integration; do not rebuild an existing scaffold.
3. Integrate accepted changes in dependency order, preserving approved docs and the coordinator ledger.
4. Verify the integrated journey and relevant negative cases before marking the wave DONE.

### Risks and Decisions

- Current `main` documentation and historical ledger describe different stages. Always name the branch and commit when reporting progress.
- Application test source on `codex/first` covers identity-derived tenant context, branch assignment/active-state denial, indexes and cross-tenant foreign-key rejection. Source inspection is not a passing execution result.
- T07 MySQL 8.4/InnoDB migration, browser and database-session evidence is accepted for the bounded slice; T08 did not rerun that runtime. PHP 8.5, full M1 and production readiness remain unverified. Historical SQLite checks alone do not prove MySQL readiness.
- Preserve unrelated files, worker ledgers and stash contents. No implementation changes in this startup.
- Worker completion requires actual diff/commit and acceptance review, exact verification results, remaining risks and dependencies; this T08 commit is coordination-only and does not establish full M1, PHP 8.5 validation or production readiness.

## T08 integration result — 2026-09-10

- Created the existing `codex/first` branch checkout at `C:/Users/N/.codex/worktrees/t08-integration/PlayNexus` without overwriting an existing path, verified its starting `03108c6` ref and ancestry, then ran `git merge --ff-only 37b4f6b782c0020984567bb774d982b42428d3cb` to reach accepted `37b4f6b`.
- Imported `docs/agent-plan.md` from the main checkout and reconciled the coordinator's unique T07 review entries into `.ai/TEST_RESULTS.md` while retaining the accepted worker evidence. The source ledger hash was `E7F78F42DB8F2A308D4F2B5E54725E1BF211FE6CF4C8E0E8F4F888787E1654CF` before and after import.
- Integration rerun explicitly used process-local SQLite `:memory:` and array sessions; the tracked PHPUnit configuration was not changed and no inherited MySQL QA override was left active in the test process. The required integrated checks and their exact outcomes are recorded in `.ai/TEST_RESULTS.md`.
- Result: `REVIEW_REQUIRED` pending orchestrator review. The accepted bounded auth/branch slice and T07 MySQL 8.4/InnoDB acceptance remain intact; full M1, PHP 8.5 validation, production readiness and the policies/gates slice remain incomplete and were not started.

## T07B acceptance review — 37b4f6b, 2026-09-10

- Verified exact one-file commit, ancestry from 9056439 and fast-forward relationship from codex/first; application code unchanged since accepted correction. Worker runtime stopped; current check found no listeners on 33407/8190/8191.
- Reviewed original task execution evidence in task `01a08944-c762-7bb0-8945-a89e0044ac79` (local transcript used because app summary omitted tool outputs in the final completed turn). Runtime output shows MySQL 8.4.11, loopback 33407, 12/12 InnoDB tables and four migration rows; direct invalid cross-tenant insert returns ERROR 1452 on the expected composite FK.
- Reviewed worker test commands with process-local MySQL connection/database/port and session overrides and output: focused 19 tests/78 assertions, full 21/81 PASS. No force=true in tracked PHPUnit env entries; overrides are not a tracked config change. Reviewed database-session logout counts (0 authenticated rows, 0 branch-context rows) and Arabic DOM output (ar/rtl), together with the committed browser flow evidence. Coordinator did not restart runtime, rerun browser or independently render screenshots in this review.
- Previous environment blocker is closed for this bounded slice. PHP 8.5 target, full role policies/staff lifecycle, broader M1 and production readiness remain outside this acceptance. Do not label the entire milestone complete.
- Reproduction note for next QA run: initialize a fresh private datadir before starting a newly extracted ZIP; the worker transcript includes initialization but the short committed recipe omits that step. Existing initialized data is retained; no installation or cleanup required now.
- T08 is a recommendation, not a merge already executed. Integration must preserve accepted history c94a046 -> 9056439 -> 835346d -> 37b4f6b and the coordinator ledger, then validate the final combined state.

## T07 fallback review — 835346d, 2026-09-10

- Reviewed exact single-file commit and clean worker status. No application diff since accepted 9056439, so the unchanged full suite was not rerun by the coordinator in this turn.
- Inspected task `Implement staff authentication`, ID `01a08871-17c7-7752-8b7b-68d5d29fb433`, T07 turn `01a08915-4357-7822-b52e-6972e0d3e2ce`: completed browser calls cover English login/select/reload/logout, invalid credentials, empty assignments, foreign/unassigned navigation, revoked context, suspended-tenant logout, Arabic locale inspection and Arabic select/reload/logout. One incorrect Arabic selector was corrected in a later successful call. Review used action history and runtime output; screenshots were not independently rendered by the coordinator.
- Retained SQLite evidence exists at the recorded temporary path; read-only inspection found expected migrated tables, 0 foreign-key-check issues and 3 session rows. Row count alone is not evidence of authentication state; the worker's logout-state assertion is recorded separately.
- Worker command output confirms 21 tests / 81 assertions and formatting/docs checks. Current listener inspection found no listeners at 3306-3308 or 8187-8188; no docker/mysqld command found on PATH. These limited checks do not prove absence of every possible installation. Worker discovery identified only XAMPP MariaDB 10.4.32, not the required MySQL 8.4.
- Accepted T07A as SQLite fallback only. T07 stays blocked until T07B provisions the real target engine and verifies migrations, constraints, access denials and database-session browser flows. No application defect newly confirmed. No merge/push or shared service changes.
- T07B must prove resolved MySQL test connection rather than rely on tracked SQLite/array-session PHPUnit settings. Record exact server version, InnoDB tables, isolated host/port/schema, command results and browser evidence references. Stop only task-owned processes after verification and preserve non-secret reproduction instructions.

## Auth correction accepted — 9056439, 2026-09-10

- Verified five-file diff, parent c94a046, clean worktree; logout is auth-only with operational tenant guards intact; non-string rate-limit email input maps to empty-email/IP bucket and normal string normalization is preserved.
- Independently executed `php artisan test`: 21 tests / 81 assertions PASS; Pint PASS; documentation validator PASS (29 files, 0 errors/warnings); diff check PASS.
- Independent temporary regression probes: 2 tests / 4 assertions PASS. Logout returned 302 with authentication cleared; array email returned JSON validation failure. The probe now uses normal exception rendering for validation instead of disabling it.
- Build not repeated: correction touches no frontend or build dependency; successful c94a046 build remains applicable. No MySQL/browser/PHP 8.5 claim.
- T07 acceptance requires actual MySQL 8.4/InnoDB identity, migrations and cross-tenant constraints, and real HTTP/browser login -> branch select -> logout using database sessions, plus negative access, stale context and RTL/LTR checks. Explicitly verify resolved test configuration: tracked phpunit.xml forces SQLite/array sessions, so an ordinary green suite is insufficient MySQL evidence. Use an isolated temporary configuration; no shared DB or tracked defaults changed.
- Integration recommendation after T07 review: preserve 03108c6 -> c94a046 -> 9056439 history (then any accepted follow-ups), protect coordinator docs and unrelated untracked files, and rerun integrated checks. No merge performed now.

## Auth commit review — c94a046, 2026-09-10 (findings closed by 9056439)

- P2: `routes/web.php:18` registers logout inside `tenant.access`. After the logged-in user's tenant becomes inactive, POST `/logout` returns 404 and `auth()->check()` remains true. Move logout to an auth-only boundary while retaining CSRF and session invalidation; keep protected operational routes tenant-guarded.
- P2: `app/Providers/AppServiceProvider.php:27` casts unvalidated email input to string in throttle middleware. POST `/login` with `email` as an array raises `Array to string conversion` before `LoginRequest` can return validation errors. Safely normalize only string input for the rate key and retain normal validation/throttling.
- Independently rerun: `php artisan test` PASS (19 tests / 72 assertions); `php vendor/bin/pint --test` PASS; `npm run build` PASS (optional fontaine notice); documentation validator PASS (29 files, 0 errors/warnings); `git diff --check` PASS; worker tracked tree remains clean.
- Two additional isolated regression probes in `%TEMP%/PlayNexusAuthReviewTest.php`: 1 failed assertion (logout 404, still authenticated), 1 exception (array email at provider line 27). They use SQLite in memory and do not modify application files. Command: `php vendor/bin/phpunit --configuration phpunit.xml <probe-path>` from worker root.
- MySQL/database sessions, real-browser RTL/LTR and target PHP 8.5 remain unverified. No new full-M1 or integration acceptance claim.
- Follow-up acceptance: suspended-tenant logout succeeds, clears authentication/context and invalidates session; malformed login input receives validation failure rather than server exception; valid credentials, throttling and all existing tenant/branch denials remain covered. Worker returns files, commit, exact checks/results and remaining risks. Review corrected commit before integration.

## Historical ledger — preserved, superseded where inconsistent above

## Current Goal

Advance Milestone M0 by closing the repository's implementation-blocking Gate G1 decisions before Laravel schema/API freeze and scaffold work.

## Current Repository State

- Branch: `main`; HEAD `b6d09e1` (`docs: establish PlayNexus implementation baseline`)
- Worktrees: main checkout plus worker worktree `C:/Users/N/.codex/worktrees/344d/PlayNexus` at the same baseline commit
- Main working tree: untracked `.codex/` runtime metadata and this orchestration ledger; no implementation files changed
- Repository stage: audited documentation baseline; no Laravel application, migrations, routes, or application tests exist
- Canonical specification: `docs/`; visual baseline: `DESIGN.md`
- Milestone: M0 — decisions and scaffold
- Documentation QA is recorded as passing in `.ai/TEST_RESULTS.md`; those checks do not prove runtime behavior
- Worker report exists in the worker worktree at `deliverables/g1-decision-impact-report.md`; it is ignored by the repository's `deliverables/` rule and is not part of the main checkout

## Architecture Context

- Target: one Laravel modular monolith with Blade and selective Livewire
- Database target: MySQL; tenant-owned records require explicit tenant scoping
- Money: integer minor units plus ISO currency; timestamps stored in UTC
- Safety boundaries: authorization, guardian verification, auditability, and transactional checkout/payment/refund flows
- Unresolved decisions must not be silently implemented in schema, API, permissions, migrations, or UI

## Task Graph

- T00 — Startup repository and documentation inspection — DONE
- T01 — Reconcile M0/G1 decision register and implementation impacts — DONE (reviewed worker report)
- T02 — Synchronize approved G1 decisions in canonical docs and `.ai/DECISIONS.md` — IN_PROGRESS; baseline decisions recorded, contract sync pending
- T03 — Select exact supported Laravel/tool versions and scaffold baseline — BLOCKED; depends on T02 and remaining launch constraints
- T04 — Implement M1 foundation vertical slice and prove tenant isolation — BLOCKED; depends on T03
- T05 — Independent review and integration verification — BLOCKED; depends on T04 and subsequent slice work

## Dependencies

- T02 depends on the reviewed T01 impact matrix and now-approved baseline decisions; it still requires canonical contract synchronization
- T03 depends on T02; do not scaffold a schema/API from unresolved G1 assumptions
- T04 depends on T03
- T05 depends on integrated implementation and real verification results

## Execution Waves

### Wave 1 — Complete

- T01 — Decision-impact investigation completed; all 19 requested OQs mapped to owners, affected contracts, gate impact, minimum decisions, checks, and contradictions

### Wave 2 — In progress

- T02 — Synchronize approved decisions across canonical docs and `.ai/` records; resolve remaining contract references

### Wave 3 — Waiting

- T03 — Laravel scaffold and exact command capture

### Wave 4 — Waiting

- T04 — M1 access/branch foundation vertical slice

### Wave 5 — Waiting

- T05 — Independent review, integration, and tenant-isolation verification

## Agent Ownership

- Orchestrator: task graph, worker prompts, integration order, review, and verification
- T01: completed by investigation worker; report reviewed
- T02–T05: unassigned until their dependencies clear

## Status

- T00: DONE
- T01: DONE
- T02: IN_PROGRESS; baseline decisions approved, remaining decisions still open
- T03–T05: BLOCKED or waiting on documented dependencies
- No implementation worker is ready; documentation synchronization is the active orchestrator task

## Integration Order

1. Obtain and record authorized G1 decisions in canonical docs and `.ai/DECISIONS.md`.
2. Choose supported versions and scaffold Laravel; capture exact commands in `AGENTS.md`/README as required.
3. Implement M1 foundation with focused tenancy/authorization tests.
4. Run independent review and whole-system verification before advancing to M2.

## Risks / Decisions

- The worker report claimed `docs/agent-plan.md` was missing, but that was a stale worker-worktree observation; the file exists in the main checkout and is now reconciled.
- The worker report is evidence of analysis only, not approval of any product/legal/finance/safety choice.
- Do not invent values for payments, currencies/tax/receipts, pricing, guardian verification, child data, duplicate handling, checkout topology, incident scope, or cashier close.
- Shared files (`routes/*`, migrations, OpenAPI, permission docs, `.ai/*`) require one owner per wave.

## Decisions

- T01 is accepted as DONE after reviewing the report and validating its conclusions against the current main checkout.
- The initial baseline decisions are authorized and recorded; implementation remains blocked until affected contracts are synchronized and receipt, pricing, and guardian-verification decisions are closed.

## 2026-09-10 update

Initial MVP baseline decisions are approved and recorded. Canonical contract synchronization remains the next documentation task before Laravel scaffolding. Receipt numbering/content, guardian verification, pricing, and dependent workflow decisions remain blocked.


## 2026-09-10 final decision sync

T02 is DONE. T03 is READY to scaffold the approved Laravel baseline. Do not add online payments, offline writes, pause pricing, or unsanctioned receipt/legal integrations.


## T03 review — 2026-09-10 (supersedes earlier readiness claims)

- T03: CHANGES_REQUIRED. Scaffold exists only in C:/Users/N/.codex/worktrees/344d/PlayNexus, detached at b6d09e1, uncommitted. Not integrated.
- Reviewed composer.json, config/app.php, phpunit.xml, README.md, ignore rules, worker ledger and reported verification. Tests/build are worker-reported, not independently rerun in this review.
- Confirmed findings: application timezone Africa/Cairo contradicts UTC persistence; README install requires ignored .codex/composer.phar without provisioning instructions; file currently exists despite worker cleanup claim; worker ledger is a five-line replacement missing task graph/ownership/status.
- Root cause of missing coordination context: coordinator changes including docs/agent-plan.md remain uncommitted and were never included in the worker starting commit. This is not merely a stale observation by the worker.
- Fix wave: same T03 worker corrects UTC configuration and reproducible setup documentation, retains bounded scaffold scope, reruns targeted verification, and delivers a focused commit excluding docs/agent-plan.md and runtime/secrets. Coordinator owns the canonical ledger.
- T04: BLOCKED pending T03 review/integration and a versioned common documentation baseline. MySQL migration/runtime remains unverified; missing CLI alone does not prove no database service exists.
- T02: REVIEW_REQUIRED. Prior append-only amendments and OpenAPI extension did not reconcile existing paths/schemas or contradictory pause rules; earlier DONE/synchronization PASS claims were too strong. This must be resolved before dependent business implementation.
- Actual coordinator branch: codex/first. Do not replace current approved decisions with worker baseline .ai files during integration.


## T03 review result — 2026-09-10

- T03: REVIEW_REQUIRED pending integration. Worker commit `7866de3b7c04386621217adb8b9c43c46c588cae` on `codex/t03-scaffold-review` contains the Laravel 13.31.0 scaffold and requested corrections.
- Reviewed report: UTC application timezone, reproducible Composer prerequisite, guarded environment setup, accurate status records, and excluded orchestrator ledger/runtime artifacts.
- Worker-reported checks passed: 2 PHPUnit tests/2 assertions, Vite build, UTC config resolution, documentation validator, and diff check. MySQL migration/runtime and PHP 8.5 remain unverified/blocking.
- Integration rule: preserve main-branch approved decision docs and canonical `docs/agent-plan.md`; integrate only the worker commit's scaffold/status files after a clean diff review. T04 remains blocked until integration and test rerun.

## T04 review result — 2026-09-10

- T04: DONE for the SQLite-verified M1 slice after follow-up commit `03108c6a7c07698bb9271a82d32d8ab4ceb1bea7`.
- Integrated into `codex/first` by fast-forward from `739df0e` to `03108c6`.
- Tenant context is user-derived; branch assignment is active/tenant-scoped; composite foreign keys reject cross-tenant pivot rows; focused suite reports 5 tests/15 assertions and full suite 7 tests/17 assertions.
- MySQL migration/runtime remains BLOCKED_BY_ENVIRONMENT and must be verified before production readiness, but does not block planning the next isolated M1 slice.
- T05 review/integration for this slice is complete enough to advance; next objective is the smallest approved M1 access/branch UI or authentication boundary slice, selected from the repository after inspecting current gaps.

## 2026-09-12 T14 approved implementation contract

Under the user's authorization to execute the proposed three-worker owner-read wave, the coordinator selects the narrow explicit equivalent allowed by T12: `tenant_owners` contains non-null unsigned-bigint `tenant_id`, `user_id`, timestamps; composite primary key `(tenant_id,user_id)` and composite foreign key to `users(tenant_id,id)` with cascade delete. No backfill/inference from branch roles, no generic RBAC tables or assignment endpoint. This is an implementation decision for this slice, not approval of the entire draft matrix. Existing users remain non-owners until explicitly provisioned outside this UI.

Native `TenantPolicy::view(User,Tenant)` grants only a freshly queried active user whose persisted tenant matches the active target tenant and whose exact tenant_owners row exists. Unknown/null/platform identities and branch-only owner strings deny. Policy foreign/inactive scope is denyAsNotFound (404); in-scope missing owner assignment is 403. Existing tenant middleware enforces account revocation and inactive tenant behavior. Do not grant new branch access.

GET `/app/tenant`, route `tenant.show`, existing `auth` + `tenant.access`, resolves tenant from a freshly queried authenticated user (never request IDs), authorizes view, renders `tenant.show`. View data: `$tenant` (Tenant), `$staff` (LengthAwarePaginator of only id, name, email, status; tenant-filtered users, order by id, 25/page). User query/body tenant identifiers are ignored and never switch scope. No API/JSON expansion. UI navigation from dashboard appears only for policy-authorized owner; page has own tenant name, read-only staff name/email/localized status, empty state, pagination, locale controls and return-to-dashboard. Existing locale POST redirect remains unchanged.

T15 owns new migration, TenantPolicy, TenantReadController, provider policy registration, routes only (User model only if essential). T16 owns resources/views/tenant/show.blade.php, dashboard Blade navigation, lang/en/tenant.php and lang/ar/tenant.php only. T17 owns tests/Feature/TenantOwnerReadTest.php only. Each commits in its isolated branch; no shared .ai edits. Tests cover constraints, owner without branch assignment, staff and spoofed-role denials, fresh revocation, inactive account/tenant, foreign policy 404, request-ID isolation, scoped pagination and en/ar rendering. Run executable combined tests only after dependencies integrate; missing peer implementation is not PASS. Browser explicitly deferred. Coordinator reviews actual commits, integrates locally on codex/first and records one combined verification. No main merge or push.

## 2026-09-12 T18-T20 parallel feature contract

User authorized three Luna/xhigh agents for the next feature wave. Each delivers a complete feature (backend, UI, focused tests) in its own worktree from this contract commit. Browser remains DEFERRED_BY_USER. Coordinator alone edits shared docs/status, web route composition, provider registration and tenant-profile navigation; no main merge/push/shared DB. Workers do not install dependencies: parent executes focused feature suites and one combined suite after integration using existing vendor. Worker delivery must list exact base/SHA/files, implemented acceptance behaviors, actual checks, missing checks and REVIEW_REQUIRED. No result is accepted solely from a progress claim.

### T18 owner branch access (A)

Explicit active tenant_owners grants view/list/select of ALL active branches in the same active tenant without branch_user rows. Staff retain current assignment/role rules and 404 scope/403 permission semantics. Fresh user, tenant, branch and ownership state must be used. Remove owner privilege on next request when ownership is revoked; selected context survives only if remaining staff assignment independently permits it. Foreign/inactive branches stay 404. No branch writes, no new staff permissions. Own User.php only if needed (keep activeBranches assignment semantics; add clearly named accessibleBranches query if needed), BranchPolicy, BranchContextController, EnsureBranchAccess, EnsureTenantAccess only for branch-context validation, dashboard branch copy if needed, new OwnerBranchAccessTest and adjust only obsolete owner-no-branch expectation in TenantOwnerReadTest. No TenantPolicy/route/provider/audit edits. Test owner list/select/direct read/reload, inactive/foreign denial, revocation, staff fallback and existing staff regressions. Existing dashboard is the UI; no redundant page.

### Shared owner administration contract for B/C

Fresh active owner of own active tenant only; authorize via existing TenantPolicy::view as the exact owner predicate (do not broaden it). Owner assignment management and platform identities are excluded. All targets resolve within authenticated tenant BEFORE mutation; foreign/null tenant IDs return404, same-tenant non-owner403. Deny managing any user with tenant_owners row, including self (403), so owner transfer/last-owner semantics cannot be bypassed. No request-writable tenant_id, owner flag, password or arbitrary role map. New pages are under existing auth + tenant.access route group.

Writes run in DB::transaction. Lock active tenant row first, then refresh/authorize actor and owner assignment, then lock target user; C then locks/rechecks branch and pivot. All these admin writes lock the same tenant row to serialize this bounded low-volume operation. Revalidate actor status, tenant ownership and target scope INSIDE transaction. Check explicit expected state and return409 if stale; invalid form422 JSON / standard redirect+errors HTML. Require reason_code in staffing_change/access_review/correction. Append successful state-changing audit row in SAME transaction; no-op does not claim/change/audit a mutation. Audit failure rolls back mutation. Generate UUID request_id server-side; no client-supplied correlation trust. audit_logs schema is preprovided. Snapshots only status or branch_id/role/is_active, no names/emails/passwords. actor_type=user, outcome=success, occurred_at UTC; no audit update/delete route. This is successful-admin-mutation audit only, not complete denial/security audit coverage.

### T19 existing staff account status (B)

GET `/app/staff` staff.index, PATCH `/app/staff/{user}/status` staff.status. New routes/staff.php loaded by parent inside auth+tenant.access. Own StaffStatusController, new views/staff/index.blade.php, lang/en/staff.php lang/ar/staff.php, tests/Feature/StaffManagementTest.php and routes/staff.php only. Tenant-filtered list 25/page, display name/email/status, skip mutation controls on self/owner rows. Update to active/suspended/disabled only, expected_status required one of invited/active/suspended/disabled. No user creation/email/password reset/invitation sending. Action staff.status.changed; subject_type=user, subject_id target ID, branch_id null, before_json/after_json {status}. Inline localized reason/status controls, validation/success feedback, fixed route redirect and accessible page navigation. Tests owner success, nonowner/owner target/foreign deny, invalid input, stale409, audit contents/rollback, and target's already-authenticated session denied on next protected request. User.status remains outside mass assignment.

### T20 fixed branch-role assignments (C)

GET `/app/assignments` assignments.index; PUT `/app/assignments/{user}/{branch}` assignments.update. New routes/assignments.php loaded by parent inside auth+tenant.access. Own BranchAssignmentController, views/assignments/index.blade.php, lang/en/assignments.php lang/ar/assignments.php, tests/Feature/BranchAssignmentManagementTest.php and routes/assignments.php only. Read page selects one same-tenant non-owner staff member via optional user_id; paginate staff picker25 to avoid loading all users; show active branches with current pivot role/state using scoped queries. Missing choice shows useful selection state. Updates may assign/change/reactivate/revoke pivot; do not delete rows. Allow only new role branch_manager/reception_staff/cashier, is_active boolean; legacy reception remains readable but not a new grant. Required expected_role nullable string(max50) and expected_is_active nullable boolean: both null means no previous pivot, otherwise match current role/state or409. Target account must be active, branch active/same tenant (inactive branch404; inactive target409); no owner targets. Action staff.branch_assignment.changed; subject_type=user subject_id target, branch_id branch; snapshots absent=null or {branch_id,role,is_active}. Test grant/change/revoke, no permission via arbitrary roles, foreign user/branch404, nonowner/owner target deny, inactive state, stale write409, audit/rollback, revoked role/context denied next request. Do not edit User, BranchPolicy or existing middleware; A owns those.

### Coordinator acceptance

Inspect each exact diff and tests, return only concrete defects to owner, merge independently delivered commits (A then B then C). Register route files and owner navigation centrally. Run focused new tests and full suite, Pint/build/docs/diff once; rerun only checks affected by fixes. Record actual evidence and clear incomplete scope. No manual/browser/MySQL acceptance claims.
