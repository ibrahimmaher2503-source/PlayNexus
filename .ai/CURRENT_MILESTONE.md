# Current Milestone

## 2026-09-10 orchestrator acceptance

T08 is DONE following independent review of 7a3963e and passing integration checks. This supersedes pending-review statements below. The accepted auth/branch baseline is on codex/first; next is scoped M1 policy/gate planning, not implementation yet. Full M1, PHP 8.5 and production readiness remain incomplete.

## Milestone

M1: Staff authentication and branch-aware access foundation.

## Goal

Provide staff-only session access from `users.tenant_id`, active assigned-branch selection, tenant/branch isolation, and a minimal bilingual operational shell. The bounded auth/branch slice and T07 MySQL 8.4/InnoDB runtime acceptance are accepted and integrated locally on `codex/first`; T08 remains `REVIEW_REQUIRED` pending orchestrator review. No customer, session, pricing, payment, or reporting module is in scope.

## Next three actions

1. Complete orchestrator review of the versioned T08 integration baseline.
2. Only after review, select the next approved M1 policy/gate slice; do not broaden the accepted auth/branch scope.
3. Track PHP 8.5 validation, full M1 completion and production readiness as separate incomplete dependencies.

## 2026-09-10 M1 foundation slice

Tenant/branch context and assignment-scoped access are implemented and verified with the focused SQLite suite. MySQL migration/runtime verification remains blocked by the unavailable environment.

## 2026-09-10 T08 integration

`codex/first` was checked out at `C:\Users\N\.codex\worktrees\t08-integration\PlayNexus` and fast-forwarded from `03108c6` to accepted `37b4f6b`. The coordinator ledger was imported from the main checkout and the accepted worker and coordinator evidence were reconciled in a focused documentation commit. T08 is `REVIEW_REQUIRED`; full M1, PHP 8.5 validation and production readiness remain incomplete.

## 2026-09-10 update

Initial G1 baseline choices and OQ-08/OQ-12/OQ-16 decisions were approved and synchronized across the canonical contract documents. T03 scaffold is integrated from commit `7866de3b7c04386621217adb8b9c43c46c588cae`.

## 2026-09-10 T05 update

T05 adds staff session authentication, login throttling, session-backed active branch selection, stale-context clearing, and a minimal English/Arabic shell. SQLite feature tests and frontend build pass; MySQL migration/runtime remains `BLOCKED_BY_ENVIRONMENT`.
