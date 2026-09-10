# Current Milestone

## Milestone

M1: Staff authentication and branch-aware access foundation.

## Goal

Provide staff-only session access from `users.tenant_id`, active assigned-branch selection, tenant/branch isolation, and a minimal bilingual operational shell. MySQL migrations and database-backed runtime are still unverified; no customer, session, pricing, payment, or reporting module is in scope.

## Next three actions

1. Provision or select an isolated MySQL 8.4 database without changing shared services, then verify the migration and database-session runtime path.
2. Implement the next approved M1 policy/gate slice for fixed tenant/branch roles, retaining the current deny-by-default tenant and assignment checks.
3. Add only the approved M1 locale/timezone/currency configuration or audit skeleton after its acceptance criteria are selected.

## 2026-09-10 M1 foundation slice

Tenant/branch context and assignment-scoped access are implemented and verified with the focused SQLite suite. MySQL migration/runtime verification remains blocked by the unavailable environment.

## 2026-09-10 update

Initial G1 baseline choices and OQ-08/OQ-12/OQ-16 decisions were approved and synchronized across the canonical contract documents. T03 scaffold is integrated from commit `7866de3b7c04386621217adb8b9c43c46c588cae`.

## 2026-09-10 T05 update

T05 adds staff session authentication, login throttling, session-backed active branch selection, stale-context clearing, and a minimal English/Arabic shell. SQLite feature tests and frontend build pass; MySQL migration/runtime remains `BLOCKED_BY_ENVIRONMENT`.
