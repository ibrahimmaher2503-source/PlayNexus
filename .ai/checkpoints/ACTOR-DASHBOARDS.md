# Actor Dashboards

## Scope

This checkpoint covers the authenticated landing experiences for Platform Administrator, Tenant Owner, Branch Manager, Reception, and Cashier. It does not add a Guardian portal, cashier shifts, online payments, or unrestricted Platform access to tenant operational data.

## Current Dashboard Inventory

| Actor | Landing Route | Initial State | Final State |
|---|---|---|---|
| Super Admin | `/platform` | Authentication landed on the tenant list; no dedicated platform overview. | Safe platform overview with tenant-state attention, recent onboarding, active support grants, and approved administrative actions. |
| Tenant Owner | `/app` | Generic branch-oriented landing with weak cross-branch context. | Tenant-scoped business overview with cross-branch operational summaries, setup/commercial warnings, and owner actions. |
| Branch Manager | `/app` | Shared generic page; role emphasis and assigned-branch context were weak. | Assigned-branch operations dashboard, scoped branch switching, operational warnings, and manager actions. |
| Reception | `/app` | Shared generic page made the front-desk path indirect. | Front-desk dashboard led by family search, tickets, and live sessions/check-in. |
| Cashier | `/app` | Shared generic page did not foreground settlement. | Cashier workspace led by POS, pending settlement, receipts/cash totals, and transaction history. |

## Defects Found

### Super Admin

- No dedicated platform landing dashboard.
- Post-login and MFA completion landed on the tenant list rather than an overview.
- Switching locale from exact `/platform` incorrectly fell back into the tenant app and invalidated the platform session.

### Tenant Owner

- The former landing page did not answer the cross-branch business question.
- Branch-local dates and current-day finance/attendance context were not composed into a useful overview.
- Empty/setup/commercial warnings were not prominent enough.

### Branch Manager

- The generic dashboard did not make assigned scope, capacity, sessions, due/overdue state, and local business day explicit.
- A selected branch could outlive an assignment unless it was re-authorized on the next request.

### Reception

- The highest-frequency family/ticket/check-in actions were not the primary landing workflow.
- Finance and administration emphasis was not sufficiently separated from the front-desk role.

### Cashier

- POS and settlement were not the dominant landing actions.
- Pending payment, receipt count, and net cash sales lacked a focused role presentation.

### Shared UI/UX

- One generic information hierarchy was being reused for distinct actors.
- Role-specific empty states, warnings, and quick-action copy were incomplete.
- Explicit role and branch context was weak on the landing page.

### Security

- Dashboard composition needed one server-authoritative place that ignores client role, tenant, and branch query parameters.
- Branch eligibility needed a bounded server query that includes only active approved assignments or custom roles with `branches.view`.

### Accessibility

- The generic dashboard did not give each actor a meaningful page/section heading hierarchy and clearly labelled primary action.

## Changes Made

- Added `ActorDashboard`, the single read-only tenant-dashboard composition service. It derives tenant, actor, eligible branches, permissions, KPIs, alerts, and actions from the authenticated server context.
- Replaced the generic tenant dashboard with role-aware owner, manager, reception, cashier, and fail-closed unassigned-staff compositions.
- Added bounded aggregate queries for sessions, local-day attendance, tickets, receipts, posted cash payments, and executed refunds; the owner branch rail is paginated to 12 records.
- Added `PlatformDashboardController` and `/platform`, and changed successful Platform login/MFA completion to land there.
- Added English and Arabic actor/platform dashboard copy and fixed exact-root Platform locale switching.
- Added responsive, accessible dashboard markup and a focused Edge CDP acceptance runner with screenshots.
- Updated regression expectations for the intentional platform landing and owner inactive-branch overview behavior.

## Final Dashboard Specification

### Super Admin

- **KPIs:** total, active, pending, and suspended tenants.
- **Quick actions:** create tenant, tenant list, plans, subscriptions, support access, platform audit.
- **Alerts:** suspended/pending tenants and active time-bound support grants.
- **Scope:** platform aggregates and administrative tenant identity only; no guardian, child, session, or POS detail.

### Tenant Owner

- **KPIs:** active branches, active staff, active sessions, pending payments; per-branch attendance, sessions, pending payments, capacity context, tickets, receipts, and net cash sales.
- **Quick actions:** branches, staff, reports, business settings; families/tickets only when a selected authorized branch makes those actions valid.
- **Alerts:** incomplete/no-branch setup, inactive branches, account/subscription phase, branch due/overdue/capacity conditions.
- **Scope:** authenticated tenant only; branch figures use each branch's timezone and business date.

### Branch Manager

- **KPIs:** active and pending sessions, attendance/check-ins, tickets, capacity, due/overdue sessions, receipts, and net cash sales where authorized.
- **Quick actions:** sessions/check-in, tickets, pending payments, POS, branch-scoped reports, and branch settings only when policy permits.
- **Alerts:** inactive branch, due/overdue sessions, near/full capacity, and no authorized assignment.
- **Scope:** active assigned branches only; single branch is selected automatically, multiple branches use the authorized selector.

### Reception

- **KPIs:** active sessions/children, check-ins today, tickets today, capacity, and due/overdue sessions.
- **Quick actions:** family search/create, tickets, and active sessions/check-in.
- **Alerts:** inactive branch, capacity, and due/overdue sessions.
- **Scope:** current active assigned branch; no finance, tenant settings, staff, or platform controls.

### Cashier

- **KPIs:** pending payments, receipts today, net posted cash sales after executed refunds, and active operational context.
- **Quick actions:** POS, pending payments, and transactions/receipts history.
- **Alerts:** unresolved pending settlements and inactive branch.
- **Scope:** current active assigned branch and policy-authorized financial data; no shifts/drawers, staff, branch configuration, tenant settings, or platform controls.

## Browser Journeys

Real Microsoft Edge CDP automation used isolated seeded SQLite data at `127.0.0.1:8841`, with screenshots in `deliverables/qa/actor-dashboards/`.

- Platform Administrator: login/MFA, `/platform`, tenants action, support-access action, locale retention.
- Tenant Owner: login, cross-branch overview, branches, staff, reports, settings.
- Branch Manager: assigned dashboard, query-string tampering ignored, foreign branch direct request returned 404, sessions action.
- Reception: front desk dashboard, family, ticket, and session actions; staff administration returned 403.
- Cashier: cashier dashboard, POS, pending-payment, and transaction actions; staff administration returned 403.
- Result: 53 checks and 30 screenshots; no root horizontal overflow, console errors, or HTTP 5xx responses.

## Responsive Verification

Every actor dashboard was opened at 390, 820, and 1440 CSS pixels in English and Arabic. Cards stack at mobile width, primary actions remain prominent, branch context/warnings remain visible, and the desktop compositions retain useful density. No root overflow was detected in any of the 30 viewport/locale captures.

## Arabic / English Verification

All five dashboards were exercised as English/LTR and Arabic/RTL. Headings, KPI labels, actions, warnings, empty states, status labels, money/date context, and direction changed without changing business data. The Platform-root locale regression is covered separately.

## Security / Scope Verification

- Client `tenant_id`, `branch_id`, and actor/role query substitutions do not choose dashboard scope.
- Owner data is constrained to the authenticated tenant.
- Manager, Reception, and Cashier branch eligibility comes from active tenant-owned assignments and recognized permission semantics.
- Revoking an assignment invalidates an already selected branch on the next request.
- Tenant users cannot enter `/platform`; Platform administrators cannot enter `/app` without an approved tenant identity.
- Reception/Cashier administration attempts return 403; foreign manager branch access returns 404.
- Quick actions are emitted only when the corresponding route and policy capability are valid.

## Tests

| Command | Result |
|---|---|
| `php artisan test tests/Feature/ActorDashboardTest.php tests/Feature/PlatformDashboardTest.php tests/Feature/NavigationUiTest.php tests/Feature/LocaleSwitchTest.php tests/Feature/AuthenticationTest.php tests/Feature/AccountMfaTest.php tests/Feature/PlatformAdministrationTest.php tests/Feature/OwnerBranchAccessTest.php tests/Feature/BranchViewAuthorizationTest.php` | PASS — 80 tests, 785 assertions. |
| `php artisan test` | PASS — 483 total, 479 passed, 4 intentionally skipped MySQL/concurrency cases, 3,930 assertions. |
| `php artisan test tests/Feature/ActorDashboardTest.php tests/Feature/PlatformDashboardTest.php tests/Feature/BranchAdministrationTest.php tests/Feature/SupportAccessSecurityTest.php tests/Feature/TenantRouteBindingTest.php tests/Feature/NavigationUiTest.php tests/Feature/LocaleSwitchTest.php` | PASS after formatting — 48 tests, 374 assertions. |
| `node tools/browser-actor-dashboards.mjs` | PASS — 53 checks, 30 screenshots. |
| `vendor/bin/pint --test` | PASS. |
| `npm run build` | PASS — 31 modules; optional Fontaine fallback notice only. |
| `php artisan view:cache` | PASS. |
| `py tools/validate_documentation.py` | PASS — 42 Markdown files, 0 errors, 2 existing review-marker warnings. |
| `git diff --check` | PASS; line-ending warnings only. |

## Existing Master HTML Mapping

| Key | Existing task text | Status | Evidence |
|---|---|---|---|
| 23-1 | Executive dashboard: revenue/attendance/active sessions/pending payment/refunds. | منفذ | Owner/Manager branch-local dashboard metrics use authoritative sessions, posted cash payments, executed refunds, and pending-payment state. |
| 33-1 | Global navigation منطقي حسب role. | منفذ | Dashboard actions/navigation are role- and policy-derived; negative direct-route tests pass. |
| 33-2 | Role-based landing/dashboard. | منفذ | Five distinct server-derived landing experiences pass tests and real-browser acceptance. |
| 33-3 | Consistent spacing/typography/components. | منفذ | Dashboards reuse `DESIGN.md` tokens and the existing card/action/status vocabulary. |
| 33-7 | Empty states. | منفذ | Role-relevant zero/setup/no-assignment states guide the next valid action. |
| 33-14 | Responsive desktop/tablet/mobile as applicable. | منفذ | 30 EN/AR captures across 390/820/1440; no root overflow. |
| 33-15 | Reception flow minimizes clicks. | منفذ | Family search is the primary action; tickets and sessions are direct actions. |
| 33-16 | Cashier flow touch-friendly. | منفذ | Large primary POS action and direct pending-payment/transaction actions at all widths. |
| 33-17 | Visual hierarchy prioritizes child safety and money actions. | منفذ | Reception foregrounds active/due/overdue/capacity state; Cashier foregrounds settlement and cash figures. |

The authoritative HTML has general role-dashboard coverage but does not identify acceptance for each actor separately.

## Proposed Master Checklist Additions — Actor Dashboards

These are proposed additions, not claims about existing checklist rows:

1. Super Admin landing shows safe Platform aggregates, attention items, support-access state, and approved administrative actions without tenant operational PII.
2. Tenant Owner landing shows tenant-wide and per-branch current-day operational context, setup/account warnings, and owner quick actions.
3. Branch Manager landing is restricted to active assigned branches and foregrounds current branch capacity, sessions, due/overdue work, and permitted operations.
4. Reception landing foregrounds family search, ticket handling, and check-in/active sessions without finance or administration leakage.
5. Cashier landing foregrounds POS and settlement using actual posted/pending financial state without configuration, staff administration, or deferred shift workflows.

## Remaining Production Gates

- Formal accessibility audit/certification and assistive-technology acceptance.
- Production-like MySQL dashboard acceptance and performance/load profiling at approved scale.
- Security review/sign-off, staging/UAT, operational monitoring acceptance, and final release go/no-go.

These are Production gates, not missing dashboard functionality.

## Final Status

- Super Admin: **IMPLEMENTED**
- Tenant Owner: **IMPLEMENTED**
- Branch Manager: **IMPLEMENTED**
- Reception: **IMPLEMENTED**
- Cashier: **IMPLEMENTED**
- **ACTOR_DASHBOARDS_STATUS: IMPLEMENTED**
