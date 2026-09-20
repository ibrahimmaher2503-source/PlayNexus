# Checkpoint 02 — Tenant, Branches & Configuration

**Date:** 2026-09-17  
**Final status:** `PARTIAL`  
**Production ready:** No.

## Scope and Requirements Covered

Tenant business profile and branch identity, settings, readiness, lifecycle, selector, scoped authorization, audit, and integration with capacity, tickets, pricing, payments, receipts and reports. Covers `FR-TEN-003` through `FR-TEN-009`, `US-TEN-003/004`, UC-01/UC-13, applicable `FR-RBAC-*`, isolation and regional-data requirements. Platform Administration and OQ-04 remain separate checkpoints; subscription limits were verified through the existing authoritative service only.

## Initial State / Inventory

- `routes/tenant-settings.php`, `TenantSettingsController`, `TenantPolicy`, `tenant/settings`: Owner-scoped profile with display/legal name, locale, timezone, EGP, validation, optimistic locking and transactional audit. No general business telephone/email or seller-tax-ID field is currently exposed; owner contact is a separate onboarding identity, not a business-contact setting.
- `routes/branches-admin.php`, `BranchAdminController`, `branches/manage`: create, list and audited status control. Creation previously made an incomplete name-only branch active.
- `routes/branch-settings.php`, `BranchSettingsController`, `BranchPolicy`, `branches/settings`: code, name, address, IANA timezone, capacity, EGP, tax basis points/mode, receipt prefix, cash method and seven weekday rows, optimistic locking and transactional audit. Settings/status were incorrectly Owner-only despite assigned-Manager grants in `docs/08-Permission-Matrix.md`.
- `Branch`, Query Builder-managed `branch_opening_hours`, tenant/branch foundation and operational-settings migrations: tenant ownership, unique tenant/code and weekday keys, composite ownership foreign keys. No schema changes were needed.
- `BranchContextController`, `User::accessibleBranches`, branch/tenant middleware: active authorized selector with request-time revocation/stale-context checks.

## Defects Found / Changes Made

### Functional and configuration

Branch creation now produces an inactive draft. Activation/reactivation validates code/name, positive capacity, valid IANA timezone, launch EGP currency, valid tax settings, receipt prefix, cash configuration and all seven structurally valid weekday rows. Closed days retain null times; no unapproved requirement for at least one open day was introduced. Existing settings validation rejects overnight ranges; no overnight feature was added.

### Security / RBAC

Added `BranchPolicy::updateSettings` and `changeStatus`. Owner scope remains tenant-wide; assigned active Branch Managers can configure and change state for their own branches, including reactivation of an inactive assigned branch. They cannot create branches. Authorization is repeated after transaction locks; foreign/unassigned IDs are concealed, Reception/Cashier are denied. Manager list/navigation exposes assigned branches only and hides creation.

### UI / UX

EN/AR creation copy explains the draft/configure/activate flow. The deactivation menu displays current active-session count and explicitly warns that no session is automatically completed/deleted. Existing settings tabs, labels, validation summary, safe old input, save feedback and sensitive-action confirmations were retained; no aesthetic redesign was needed. The impeccable skill guided this conservative review of hierarchy, RTL, error and consequence states.

### Audit

Existing `tenant.settings.updated`, `branch.created`, `branch.settings.updated`, `branch.status.changed` records preserve actor, tenant/branch, before/after, reason code, UTC time and request identifier. Settings/audit failure rolls back the transaction. Routine status reason remains server-derived `access_review`, per the approved decision; it is not a client-selected reason. Invalid currency payload is rejected before mutation and produces no successful settings-change event.

## Tenant Profile Verification

`TenantSettingsTest` proves valid changes, validation, scope/tampering, role denial, conflict/no-op and audit. The target tenant comes from the authenticated actor, not submitted `tenant_id`. Real Edge exercised valid save and invalid IANA timezone, preserved safe values and success feedback. Current editable fields are name/legal name and regional defaults, not a full contact or fiscal identity editor.

## Branch Verification / RBAC / Isolation

`BranchAdministrationTest`, `BranchSettingsTest`, `BranchViewAuthorizationTest`, `TenantBranchTest`, `OwnerBranchAccessTest`, `NavigationUiTest` and `TenantSubscriptionEnforcementTest` cover scoped lists, creation/idempotency, invalid input, readiness, settings, lifecycle audit, foreign IDs, stale state, selector revocation and effective plan limits. Inactive branches block new operational work through existing active-branch guards. No data is deleted. Owner historical reports remain available for inactive branches. Reactivation changes no staff assignment or individual status.

## Capacity / Timezone / Currency / Tax / Receipts / Payment

- `PlaySessionCheckInTest::test_active_or_paused_child_conflict_and_capacity_denial_do_not_consume_ticket` exercises the transactional capacity guard; current Branch capacity is authoritative. Configuration rejects capacity below one.
- UTC storage and branch-local dates are consumed by tickets and reports, not globally hard-coded Cairo. `TicketLifecycleTest` covers service/closing boundaries; `M6ReportsTest::test_revenue_reconciles_refunds_and_uses_branch_local_half_open_dates` covers branch-local date windows, including DST-capable fixtures in the report suite.
- Egypt V1 accepts EGP only. Added `BranchSettingsTest::test_currency_change_is_rejected_after_financial_records_are_posted_and_history_is_unchanged`: posted order/payment history remains EGP when USD settings are rejected. No authorized currency-migration workflow exists.
- Tax basis points are integer, validated 0–10000, inclusive/exclusive. Pricing rule/session/order snapshots preserve historical calculations. `SessionQuoteCalculatorTest`, pricing-version tests and POS/payment tests exercise actual calculations and immutable snapshots; no historical recalculation was added.
- `SettlePendingSession` and `ProcessOrdinaryPosOrder` consume receipt prefix when initializing the locked branch/year sequence; the prefix is frozen for that sequence. Added issuance-timezone snapshots to new receipts and branch-local issue-time presentation. Existing receipts without that snapshot use current branch timezone without rewriting stored history. `ReceiptTest` verifies retrieval/reprint without additional financial rows and a New York DST/midnight fixture whose display stays on the original local day after the branch changes to Cairo.
- Both settlement actions check branch cash enablement. Unsupported payload methods fail closed; posted payment methods remain unchanged. Online capture remains out of scope.
- Opening hours are not uniformly enforced on every operational action. Ticket issue uses local closing/service-date rules; no claim is made that POS/check-in universally reject work outside opening hours. No unambiguous universal-hours enforcement requirement was found.

## Browser Verification

Actual Microsoft Edge 153 CDP against disposable, migrated/seeded SQLite at `http://127.0.0.1:8821`, using `tools/browser-checkpoint02.mjs`. Evidence: `deliverables/qa/checkpoint-02-tenant-branch/browser-results.json` and screenshots.

Exercised Owner login; EN profile invalid/valid save; draft creation; invalid/valid branch settings; activation; actual branch-selector button; deactivation and historical revenue page; reactivation; manager scoped list without create; Reception direct URL HTTP 403; Arabic authenticated management/profile/settings pages and overflow measurement, including 1024px tablet settings. Forms are browser DOM-submitted; status automation bypasses the native confirmation dialog, so confirmation-click acceptance is not claimed. Expected authorization probes log 403; zero unexpected console errors were recorded. The new receipt timestamp has HTTP/bilingual regression coverage, not a separately exercised browser receipt journey.

## Tests

Exact final commands/results are recorded in `.ai/TEST_RESULTS.md`. Baseline: 37 passed / 332 assertions. Affected branch/check-in/ticket/POS/receipt/report run: 89 passed / 859 assertions. Final focused tenant/branch/limit/navigation run: 37 passed / 324 assertions. Full suite and format/build/view/docs/whitespace results are recorded separately, not inferred. No MySQL verification was run in this checkpoint.

## Remaining Gaps

1. **Operations/Product decision:** UC-13 explicitly requires operational handling of already-active sessions before intentional deactivation during hours, but does not approve whether to block deactivation or provide a controlled handoff/continued checkout path. Current inactive-branch guards deny operational access; they do not auto-complete or delete sessions. The count/warning is implemented, but choosing that safety policy is not engineering authority. Owner: Product Owner + Operations/Safety. This prevents unconditional checkpoint closure.
2. Full business-contact/fiscal seller metadata is not currently editable; production receipt wording/tax identity must be validated against the approved Finance/Legal launch requirements, not invented here.
3. Native confirmation-dialog click, full keyboard/screen-reader acceptance, named operational UAT and production-MySQL/deployment rehearsal remain unverified. Local browser/SQLite evidence is not Production readiness.

## Final Status

`PARTIAL`

Core profile, branch configuration, readiness, assigned-manager authorization and downstream integrations work locally. The active-session deactivation procedure remains a real unresolved safety policy boundary. `READY TO CHECK IN MASTER HTML: NO` for unconditional completion; verified individual controls may be recorded with this gap visible. No commit, push or production deployment.
