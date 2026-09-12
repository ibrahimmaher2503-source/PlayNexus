# Current Milestone

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
