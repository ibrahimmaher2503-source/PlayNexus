# M1 Audit Report — Access and Branch Foundation

**Audit date:** 2026-09-14  
**Reviewed branch:** `codex/m4-checkout-contract`  
**Reviewed commit:** `92a3648`  
**Closure:** **NOT READY FOR CLOSURE**

> Historical snapshot: this report records the 2026-09-14 M1 audit. Later milestone work superseded its audit-log immutability finding with database update/delete triggers in migration `2026_09_15_000024_make_audit_logs_append_only.php`; current acceptance evidence lives in `.ai/TEST_RESULTS.md`.

## 1. Executive summary

M1 has a strong tenant/branch data foundation and its focused suite passes identically on SQLite and isolated MySQL 8.4: **152 tests / 1,214 assertions**. Owner and Platform Admin paths, tenant-derived context, session revocation, branch isolation, optimistic locking, and successful administrative audit writes are materially implemented.

The milestone is not closable. Real Microsoft Edge execution exposed a broken locale control that silently submits no locale. The canonical permission matrix also grants Branch Manager several branch-scoped administrative and audit actions that the application currently blocks with `403`. Authentication security events are not appended to the audit log, audit tamper detection is absent, and the assignments screen overflows at 360px. These include open P1 requirements, so the historical `M1 closed` ledger entry is not current acceptance evidence.

No production code was changed during this audit.

## 2. Scope and exclusions

Reviewed:

- tenant and Platform Admin context;
- branch listing, selection, switching, and direct access;
- staff login, password recovery, revocation, and logout;
- owner/staff administration and fixed/custom roles;
- branch and tenant settings, Egypt locale/timezone/currency contract;
- audit schema, writes, browsing, scope, and redaction;
- English/LTR and Arabic/RTL UI at 1366px, 768px, and 360px;
- owner, manager, reception, cashier, guest, and Platform Admin access.

Excluded:

- M2 family/guardian behavior except where its links appear in the shared shell;
- M3 pricing/tickets/check-in behavior;
- M4 checkout preparation and all M5 payment/receipt/refund behavior;
- production deployment, email-provider delivery, and hosted CI;
- final security timing/retention values still governed by open OQ-22.

CodeGraph MCP was unavailable in this session. The audit therefore used direct route → middleware → controller → policy → model/table → test tracing. This limitation does not convert an unverified flow into a pass.

## 3. Requirement Traceability Matrix

Status vocabulary follows the audit contract.

| Requirement | Requirement summary / source | Status | Implementation and route/screen | Data / authorization | Evidence, tests, and gap |
|---|---|---|---|---|---|
| PRD-MVP-002 | Branch setup and branch-level settings; `docs/01-PRD-Baseline.md` | Partially implemented | `BranchAdminController`, `BranchSettingsController`; `/app/branches/manage`, `/app/branches/{branch}/settings` | `branches`, `branch_opening_hours`; owner gate | Owner path verified; canonical Branch Manager capabilities are blocked. |
| PRD-MVP-003 | Staff users and role-based permissions; PRD | Partially implemented | staff/create/status, assignments, roles screens | `users`, `branch_user`, custom-role tables; `TenantPolicy`, `BranchPolicy` | Owner flows tested; manager-scoped staff administration missing. |
| PRD-XCUT-001 | Mandatory tenant isolation; PRD | Implemented and verified | all protected M1 routes | explicit tenant IDs, composite FKs, tenant-derived context | SQLite/MySQL suites plus owner/staff foreign-scope browser checks pass. |
| PRD-XCUT-002 | RBAC and audit where applicable; PRD | Partially implemented | policies and audit page | fixed/custom roles and `audit_logs` | permission/audit matrix is incomplete; auth events and required scoped audit views missing. |
| FR-AUT-001 | Active staff login and bound session; SRS | Implemented and verified | `/login`, `AuthenticatedSessionController@store` | active user + active tenant | automated positive/negative tests and Edge login pass. |
| FR-AUT-002 | Deny suspended user/inactive tenant generically; SRS | Implemented and verified | login + `tenant.access` middleware | fresh user/tenant status | automated status combinations pass. |
| FR-AUT-003 | Logout terminates current session; SRS | Implemented and verified | `POST /logout` | session invalidation and CSRF regeneration | automated replay checks and Edge logout-to-login pass. |
| FR-AUT-004 | Generic, expiring, single-use password reset; SRS | Implemented but partially verified | forgot/reset screens and `PasswordResetController` | password-reset token table; generic response | automated token/replay/tamper coverage passes; external email delivery is excluded. |
| FR-AUT-005 | Revoke on user/tenant suspension or security reset; SRS | Implemented but partially verified | `EnsureTenantAccess`, password reset | `auth_version` | next-request revocation passes; release interval remains unapproved under OQ-22. |
| FR-TEN-001 | Platform Admin creates tenant and initial owner invitation; SRS | Implemented and verified locally | `/platform/tenants` | tenant provisioning key, owner user, platform policy/middleware | MySQL feature tests and Edge EN/AR screen pass; no real email transport claim. |
| FR-TEN-002 | Platform Admin activates/suspends tenant with reason and audit; SRS | Implemented and verified locally | platform tenant list/status action | tenant lock/version and platform audit | focused automated lifecycle/revocation tests pass. |
| FR-TEN-003 | Owner views/updates tenant profile; SRS | Implemented and verified | `/app/tenant`, `/app/tenant/settings` | `TenantPolicy::view`, tenant lock/version | persistence, scope, validation, audit tests and browser render pass. |
| FR-TEN-004 | Owner creates/updates own-tenant branches; SRS | Implemented and verified for owner | branch manage/settings | tenant-scoped unique keys and optimistic lock | owner, duplicate, cross-tenant, rollback tests pass. |
| FR-TEN-005 | Complete branch settings; SRS | Implemented and verified for owner | branch settings form | branch + seven hours rows | validation, transaction, stale-state, audit tests pass on both DBs. |
| FR-TEN-006 | Authorized branch activation/deactivation, retained history, audited reason; SRS | Partially implemented | branch management | owner-only gate; `access_review` audit | owner path passes; required Branch Manager path is blocked. Historical-session behavior belongs to later milestones. |
| FR-TEN-007 | Branch-local time from UTC; SRS | Implemented but partially verified | shell/settings/audit formatting | UTC storage, branch timezone | timezone values and localized rendering exist; complete DST/boundary matrix is not present in M1 tests. |
| FR-TEN-008 | Owner sees all branches; scoped roles assigned branches only; SRS | Implemented and verified | `/app`, `/branch-context/{branch}`, `/branches/{branch}` | `accessibleBranches`, `BranchPolicy` | Edge: own branch `200`, foreign branch `404`; tests cover revocation/inactive scope. |
| FR-TEN-009 | Prevent currency change after posted finance unless migrated; SRS | Deferred by approved market constraint | branch settings | validator permits only `EGP` | Current Egypt scope structurally prevents changing ISO currency; broaden only with an approved market/migration process. |
| FR-RBAC-001 | Authorized user creates staff and approved scopes; SRS | Partially implemented | `/app/staff/create` then assignments | owner-only tenant policy | owner direct-create path passes; matrix-required manager scoped creation/management is absent. |
| FR-RBAC-002 | Authorized role/scope/status changes take effect and audit; SRS | Partially implemented | staff status and assignments | tenant row serialization, expected state, audit | owner paths pass; Branch Manager scoped path absent; release cache interval is open. |
| FR-RBAC-003 | Server-side tenant, branch, permission enforcement; SRS | Implemented but partially verified | all protected M1 routes | auth + tenant/branch middleware and policies | direct foreign/same-tenant denial proven; missing allowed manager permissions make enforcement incomplete rather than bypassable. |
| FR-RBAC-004 | Named enforced keys for every in-scope protected action; SRS | Partially implemented | policies/controllers | custom roles expose only `branches.view` | bounded custom-role slice is safe, but M1 settings/staff/audit actions are not represented as named enforceable permissions. |
| FR-RBAC-005 | Requester/approver retention where approval applies; SRS | Out of scope | no M1 approval flow | — | Applies to later controlled actions. |
| FR-RBAC-006 | Suspension preserves historical attribution; SRS | Implemented and verified | staff status | user row retained; audit FK restricts deletion | automated status/audit tests pass. |
| FR-RBAC-007 | Platform support denied unless approved and audited; SRS | Implemented but partially verified | isolated platform routes | platform-admin membership | tenant users denied; no general support impersonation/data-access feature exists. Privileged auth events remain unaudited. |
| FR-RBAC-008 | Owner custom roles only over enforced keys; SRS | Implemented and verified for bounded slice | `/app/roles` | role/permission/grant tables; only `branches.view` | create/update/grant/revoke/stale/scope tests pass. UX overstates breadth of permissions. |
| FR-AUD-001 | Append required operational and authentication security events; SRS | Partially implemented | admin mutations; audit page | `audit_logs` | successful M1 mutations log atomically; login/logout/reset and relevant denied attempts do not. |
| FR-AUD-002 | Complete immutable, UTC, scoped, redacted event fields; SRS | Implemented but partially verified | audit writers/schema | required columns and restricted FKs | sample events and rollback/redaction tests pass; immutability itself is not DB-enforced. |
| FR-AUD-003 | Users cannot alter audit; integrity monitoring detects direct tampering; SRS | Missing | no audit update/delete route | ordinary table permits direct update/delete | app route surface is append-only, but no trigger/signature/monitor or controlled tamper test exists. |
| FR-AUD-004 | Authorized scoped search without secrets; SRS | Partially implemented | `/app/audit` | owner-only `TenantPolicy::view` | owner scope/redaction/filter tests pass; manager branch scope and frontline own-action view required by matrix are blocked. |
| FR-AUD-005 | Shared correlation ID for multi-step commands; SRS | Out of scope for M1 | later check-in/checkout/payment flows | M1 uses per-event UUID | Must be audited with the applicable later milestone. |
| LOC-001 | Translation keys and EN/AR resources; SRS | Implemented but partially verified | all M1 views | locale middleware/resources | rendered copies are translated; user-facing locale control is broken. |
| LOC-002 | LTR/RTL without clipping/control loss; SRS | Incorrect implementation | shared shell and assignments | CSS logical layout | forced-session EN/AR renders work except assignments overflow at 360px; ordinary locale switch fails. |
| LOC-003 | Arabic/Latin names, addresses, notes, search; SRS | Implemented but partially verified | tenant/branch/staff screens | Unicode DB columns | rendering/search exercised; full create/search mixed-text matrix is incomplete. |
| LOC-004 | Localized branch-time display with UTC storage; SRS | Implemented but partially verified | settings/audit/shell | UTC timestamps + branch timezone | basic evidence exists; edge-boundary matrix incomplete. |
| LOC-005 | ISO currency and locale display without value mutation; SRS | Implemented and verified for Egypt constraint | branch settings | EGP fixed; integer money is later scope | both locales render EGP; currency cannot change. |
| LOC-006 | Market-configured tax/receipt/phone/legal text; SRS | Implemented but partially verified | tenant/branch settings | Egypt/EGP/Africa-Cairo contract | tax/receipt config exists; phone/legal controls belong to later flows. |

## 4. Routes and screens inventory

| Surface | Main routes | Actors | Audit result |
|---|---|---|---|
| Guest authentication | `/login`, `/forgot-password`, `/reset-password/{token}`, `/locale` | guest/staff | screens render; locale action is functionally broken by client JS. |
| Operational shell | `/app`, `/branch-context/{branch}`, `/branches/{branch}` | owner and assigned staff | context and switching work; foreign branch is hidden with `404`. |
| Tenant profile/settings | `/app/tenant`, `/app/tenant/settings` | owner | works; profile duplicates staff information architecture. |
| Staff | `/app/staff`, `/app/staff/create`, status mutation | owner; manager required in bounded scope | owner works; manager incorrectly receives `403`. |
| Assignments | `/app/assignments`, assignment update | owner; manager required in bounded scope | owner works; manager blocked; result cards overflow at 360px. |
| Roles | `/app/roles`, create/update/grants | owner; manager should at least view definitions | owner works; manager blocked; only one permission exists. |
| Branch administration | `/app/branches/manage`, branch settings/status/create | owner; manager required for assigned branches | owner works; manager blocked. |
| Audit | `/app/audit` | owner; scoped manager/frontline views required | owner works; all staff roles blocked. |
| Platform administration | `/platform/login`, `/platform/tenants`, tenant status | Platform Admin | automated and 12 Edge render states pass locally. |

## 5. Role × Action Matrix

`✅` verified allowed, `🚫` verified denied as intended, `❌` denied but required by canonical matrix, `—` not applicable.

| Action | Owner | Branch Manager | Reception | Cashier | Platform Admin |
|---|---:|---:|---:|---:|---:|
| View/switch accessible branches | ✅ all active own tenant | ✅ assigned active | ✅ assigned active | ✅ assigned active | — |
| Direct foreign branch access | 🚫 `404` | 🚫 `404` | 🚫 `404` | 🚫 `404` | — |
| Tenant profile | ✅ full | ❌ required masked view | 🚫 | 🚫 | — |
| Tenant settings | ✅ | 🚫 | 🚫 | 🚫 | — |
| Assigned-branch settings/capacity/hours/tax | ✅ | ❌ | 🚫 | 🚫 | — |
| Assigned-branch activation/status | ✅ | ❌ | 🚫 | 🚫 | — |
| View staff in managed branch | ✅ | ❌ | own identity only not exposed | own identity only not exposed | — |
| Manage Reception/Cashier in managed branch | ✅ | ❌ | 🚫 | 🚫 | — |
| Assign/remove permitted branch access | ✅ | ❌ | 🚫 | 🚫 | — |
| View role definitions | ✅ | ❌ | 🚫 | 🚫 | — |
| Create tenant-specific role | ✅ | 🚫 | 🚫 | 🚫 | — |
| View audit | ✅ tenant | ❌ required branch scope | ❌ required own actions | ❌ required own actions | separate platform audit only |
| Provision/suspend tenant | 🚫 | 🚫 | 🚫 | 🚫 | ✅ |

Direct HTTP requests were used for denial checks; hidden navigation is not treated as authorization.

## 6. Database and security review

Verified strengths:

- tenant-owned high-risk rows carry `tenant_id`, with composite references on user/branch relationships;
- cross-tenant pivot construction is rejected by DB constraints;
- owner and branch access are reloaded from persistent state on protected requests;
- branch/tenant/settings and staff/assignment writes use transactions, row locks, expected-state checks, and atomic audit insertion;
- foreign and unassigned branches return `404`; active in-scope users lacking a permission receive `403`;
- successful audit snapshots omit passwords/tokens and unnecessary staff PII;
- currency is fixed to EGP, preventing historical reinterpretation in the current market.

Gaps:

- `audit_logs` has no database-level update/delete prevention or tamper evidence;
- authentication success/failure/logout/reset is not audited;
- final inactivity, absolute-session, revocation-cache, password-policy, rate-limit, and audit-retention values remain open under OQ-22;
- required manager/frontline audit scoping is not implemented.

No tenant escape, branch escape, exposed credential, or data corruption was found in the reviewed M1 paths.

## 7. Automated test review

| Environment | Command scope | Result |
|---|---|---|
| SQLite | 17 focused M1 feature files | **PASS — 152 tests / 1,214 assertions** |
| MySQL 8.4.11 / InnoDB | fresh migrations + same 17 files on task-owned `127.0.0.1:33427` | **PASS — 152 tests / 1,214 assertions** |

The isolated MySQL service was shut down after the run. Existing services were not changed.

Coverage is strong for owner flows, cross-tenant constraints, revocation, optimistic conflicts, rollback on audit failure, validation, and bilingual server rendering. Missing regression coverage mirrors the functional gaps: ordinary browser locale submission, allowed Branch Manager administration, scoped audit views, authentication audit events, tamper detection, and 360px overflow.

## 8. Browser test results

Actual Microsoft Edge (headless) was used against an isolated SQLite seed on `127.0.0.1:8218`; no Browser/CUA availability claim is made because the interactive browser controller returned `User unavailable`.

- **85 screenshots** total: 72 main M1 captures, 12 Platform Admin captures, and 1 dedicated locale-failure capture.
- Main coverage: 12 guest/owner screens × English/Arabic × 1366/768/360.
- Platform coverage: login and tenant administration × English/Arabic × 1366/768/360.
- All allowed screens returned `200`, had one main landmark/H1, no console/page errors, and no unnamed visible controls.
- Search, no-results, selecting the second branch, and logout were exercised successfully.
- Manager/Reception/Cashier: own assigned branch `200`; foreign branch `404`; owner administration pages `403`.
- Failures: ordinary locale change stays LTR; assignments overflows at 360px in both languages.

Evidence: `deliverables/qa/m1-audit/manifest.json`, `platform-manifest.json`, `locale-switch-ui-bug.png`, and the `screenshots/` directory.

## 9. UX/UI audit

| Screen | Operational objective | Main finding |
|---|---|---|
| Login/recovery | enter or recover securely | visually focused and clear; locale control fails silently. |
| Branch context | choose work location | clean cards, but no operational summary or clear next task after selection. |
| Staff | find/manage staff quickly | readable desktop table; row actions are vertically stacked and visually dominate each row. |
| Assignments | find employee then manage branch access | search + result cards + selected-user stage feels duplicated; empty initial state consumes a large area; mobile overflows. |
| Roles | understand/create permissions | title promises broad permissions while only `branches.view` exists; split empty list/form wastes desktop space. |
| Branch management | create/change branch | create control is a large collapsed strip with a detached small plus; row actions lack clear hierarchy. |
| Branch settings | configure operational branch rules | grouping and sticky save are good; owner-only gating contradicts manager task flow. |
| Tenant profile/settings | understand/edit institution | profile repeats the staff list and separates summary/settings without a strong workflow reason. |
| Audit | investigate changes | readable data, but actor filter requires numeric ID and staff roles cannot see their permitted scope. |
| Platform tenants | provision/control institutions | full-width desktop form/table is clear; locale/logout controls are visually fragmented. |

## 10. Accessibility and responsive findings

- Positive: logical direction changes work when locale is seeded server-side; inputs have labels; visible controls are named; primary targets meet the 44px minimum; keyboard-focused forms and semantic tables are present.
- P1: the locale selector disables itself before native form submission, so its named value is omitted. There is no visible error.
- P2: assignment result anchors render at 387px inside a 360px viewport because the action label is `shrink-0` beside long identity text; document width becomes 403px.
- Desktop screens often retain tablet-like stacked controls and broad empty cards, reducing scan speed and information density.
- Loading/network/server-error states were not observable as dedicated UI states in this server-rendered slice; generic Laravel handling remains the fallback.

## 11. Missing requirements

1. Branch Manager scoped branch settings, lifecycle, capacity, staff, assignments, and role-definition visibility.
2. Branch-scoped manager audit view and own-action audit view for Reception/Cashier.
3. Authentication security event audit coverage.
4. Audit integrity/tamper detection.
5. Approved OQ-22 release values and enforcement evidence.
6. Regression tests for the real locale-control JS behavior and 360px overflow.

## 12. Functional bugs

### M1-001 — Locale selector silently submits no locale

- **Milestone / requirement:** M1; LOC-001, LOC-002.
- **Route / screen / role:** `POST /locale`; every guest/app/platform locale control; all roles.
- **Environment:** Microsoft Edge, English page; reproduced at 1366px and applies to all sizes.
- **Steps:** open login; choose Arabic using the visible selector.
- **Expected:** request contains `locale=ar`, redirects safely, page renders `lang=ar dir=rtl`.
- **Actual:** page returns to login and remains `dir=ltr` without an error.
- **Evidence:** `deliverables/qa/m1-audit/screenshots/locale-switch-ui-bug.png`.
- **Root cause / files:** `resources/js/app.js:21-24` disables the select before `requestSubmit()`; disabled controls are omitted from successful form controls. Shared locale forms are affected.
- **Severity / priority / status:** Major / **P1** / Open.
- **Proposed solution:** submit first and disable only after serializing, or remove the unnecessary disable entirely.
- **Acceptance / tests:** visible selector changes both directions on guest, tenant, and platform screens; POST payload includes locale; one browser regression test.

### M1-002 — Branch Manager is denied required branch administration

- **Milestone / requirement:** M1; PRD-MVP-002, FR-TEN-005–006, FR-RBAC-002–004.
- **Route / screen / role:** branch manage/settings/status; assigned Branch Manager.
- **Environment:** SQLite/MySQL code path and direct Edge requests.
- **Steps:** log in as active assigned manager; request `/app/branches/1/settings` or `/app/branches/manage`.
- **Expected:** manage assigned branch settings/status/capacity under the canonical permission matrix.
- **Actual:** `403`.
- **Evidence:** browser role checks in `manifest.json`; `BranchSettingsController::authorizedContext`; `TenantPolicy::view`.
- **Root cause / files:** tenant-owner policy is reused as the administration gate; no branch-scoped administration ability exists.
- **Severity / priority / status:** Major authorization contract mismatch / **P1** / Open.
- **Proposed solution:** add explicit fixed abilities per action and scope them through fresh branch assignment checks; keep tenant settings owner-only.
- **Acceptance / tests:** allowed assigned manager succeeds; unassigned/foreign/inactive manager remains `404`; reception/cashier remain `403`; mutation is atomic/audited.

### M1-003 — Branch Manager is denied required scoped staff/access work

- **Milestone / requirement:** M1; PRD-MVP-003, FR-RBAC-001–004.
- **Route / screen / role:** staff, staff create/status, assignments, role definitions; Branch Manager.
- **Environment:** direct Edge requests and controller/policy trace.
- **Steps:** log in as manager and open owner staff/access routes.
- **Expected:** view staff and manage Reception/Cashier access inside managed branches; view role definitions.
- **Actual:** all routes return `403`.
- **Evidence:** role checks in `manifest.json`; owner-only authorized-context methods.
- **Root cause / files:** one owner-only `TenantPolicy::view` guard covers resources whose intended scope differs.
- **Severity / priority / status:** Major missing workflow / **P1** / Open.
- **Proposed solution:** explicit scoped abilities and queries; never broaden owner or cross-branch access.
- **Acceptance / tests:** role/action matrix positive and negative cases, HTTP tampering, owner-target protection, branch-scope filtering, stale writes and audit rollback.

### M1-004 — Required scoped audit views are blocked

- **Milestone / requirement:** M1; FR-AUD-004, FR-RBAC-003–004.
- **Route / screen / role:** `/app/audit`; Branch Manager, Reception, Cashier.
- **Environment:** direct Edge request and automated non-owner denial test.
- **Steps:** request audit page as any staff role.
- **Expected:** manager sees managed-branch events; frontline sees own actions only, redacted.
- **Actual:** `403` for all non-owners.
- **Evidence:** `AuditLogController::authorizedContext`, `AuditLogViewTest::test_non_owner_cannot_view_audit_logs`, browser role checks.
- **Root cause / files:** audit query is tenant-owner-only and has no role-aware scope builder.
- **Severity / priority / status:** Major accountability gap / **P1** / Open.
- **Proposed solution:** one query scope derived from fixed audit-view ability and accessible branch/actor scope.
- **Acceptance / tests:** owner tenant view, manager assigned-branch view, frontline own events, foreign/unassigned secrecy, redaction and filter scope.

### M1-005 — Authentication security events are not audited

- **Milestone / requirement:** M1; FR-AUD-001–002.
- **Route / screen / role:** login, failed login, logout, password reset; guest/staff/platform admin.
- **Environment:** static trace plus automated suite.
- **Steps:** perform each authentication transition; inspect `audit_logs`/platform audit.
- **Expected:** safe security event with actor/subject where known, outcome, UTC time and request ID.
- **Actual:** controllers update sessions/credentials but append no audit event.
- **Evidence:** `AuthenticatedSessionController`, `PasswordResetController`, `PlatformSessionController`; no matching action codes/tests.
- **Root cause / files:** authentication was implemented outside the administrative audit writer pattern.
- **Severity / priority / status:** Security/accountability gap / **P1** / Open.
- **Proposed solution:** minimal shared append operation invoked at successful and relevant denied transitions, with no password/token/email leakage.
- **Acceptance / tests:** matrix tests for success/failure/logout/reset/revocation and rollback-safe redaction.

### M1-006 — Audit rows have no tamper-evidence control

- **Milestone / requirement:** M1; FR-AUD-003.
- **Route / screen / role:** database and audit subsystem; all application roles.
- **Environment:** schema/static review.
- **Steps:** update/delete a row through a privileged DB connection in a controlled test.
- **Expected:** update/delete prevented or integrity monitor detects it.
- **Actual:** ordinary table permits mutation; no detector exists.
- **Evidence:** `2026_09_12_000006_create_audit_logs_table.php`; no trigger/signature/monitor test.
- **Root cause / files:** append-only is an application convention only.
- **Severity / priority / status:** Security control gap / **P2** / Open.
- **Proposed solution:** choose the smallest MySQL-compatible DB privilege/trigger or hash-chain monitoring contract after deciding operational ownership.
- **Acceptance / tests:** application mutation denied and controlled direct tamper is detected without blocking valid inserts.

### M1-007 — Assignment search results overflow at 360px

- **Milestone / requirement:** M1; LOC-002 and responsive DoD.
- **Route / screen / role:** `/app/assignments`; owner; EN/AR.
- **Environment:** Edge 360×800.
- **Steps:** open assignment selection with seeded staff results.
- **Expected:** no horizontal document scroll.
- **Actual:** document width 403px; result card width 387px.
- **Evidence:** `assignments__en__mobile.png`, `assignments__ar__mobile.png`, `diagnose-overflow.cjs` output.
- **Root cause / files:** assignment result link keeps a `shrink-0` action beside long name/email.
- **Severity / priority / status:** Responsive UX / **P2** / Open.
- **Proposed solution:** allow action wrap/stack at small width and ensure the identity block has `min-w-0`.
- **Acceptance / tests:** no overflow at 320/360 in EN/AR with long name/email fixtures.

## 13. Security and privacy risks

- **High:** missing allowed manager abilities may drive unsafe future workarounds or excessive owner credential sharing.
- **High:** absence of authentication audit events weakens investigation of account attacks and revocation.
- **Medium:** audit rows are mutable to a DB-capable actor without detectable integrity evidence.
- **Medium:** OQ-22 leaves release-critical timeout, password, throttling, and retention expectations undefined.
- **Low:** numeric actor-ID filtering is hard to use but remains tenant-validated and does not expose foreign actor existence.

No P0 tenant isolation, child safety, secret exposure, or money-integrity defect was found in M1.

## 14. Root UX/UI problems

1. **Information architecture follows implementation slices, not jobs.** Tenant profile, settings, staff, assignments, roles, branches, and audit are separate destinations even when one manager task spans several.
2. **Desktop density is too low.** Wide cards and stacked row actions feel tablet-first and increase scanning distance.
3. **Capability labels overpromise.** “Roles and permissions” suggests full control while only one permission is enforceable.
4. **Context and next action are weak.** Branch selection and empty states explain state but do not lead directly to the most likely task.
5. **System status is occasionally silent.** The locale failure produces neither change nor error.

## 15. Proposed radical improvements

After functional permission fixes, reshape M1 around three jobs rather than adding more pages:

1. **Workspace:** branch context, current status, and role-relevant next actions in one full-width desktop dashboard.
2. **Team & access:** merge staff search, staff detail, branch assignments, and role visibility into a master-detail workspace. Owners retain role creation; managers see only manageable people/branches.
3. **Organization:** combine tenant summary/settings and branch list/settings through clear sub-navigation, while keeping Platform Admin isolated.

Keep Audit as a separate investigation workspace, but replace numeric actor ID with a searchable staff picker and apply role-aware scope automatically. Use compact desktop action groups, one primary action per page, row-level overflow menus for secondary actions, and stacked cards only below the table breakpoint.

## 16. Prioritized backlog

| Priority | IDs | Smallest safe repair group |
|---|---|---|
| P1-A | M1-001 | remove the locale-submit defect and add a real-browser regression. |
| P1-B | M1-002 | introduce explicit assigned-branch settings/lifecycle abilities and tests. |
| P1-C | M1-003 | implement manager-scoped staff/access reads and allowed mutations; protect owner and other managers. |
| P1-D | M1-004 | add role-scoped audit querying and redaction tests. |
| P1-E | M1-005 | append safe authentication audit events. |
| Decision gate | OQ-22 | approve release timings, password, rate limit, and retention values. |
| P2-A | M1-007 | repair 360px assignment result wrapping. |
| P2-B | M1-006 | approve and implement audit integrity mechanism. |
| P2-C | UX-IA | consolidate Team & access and Organization desktop flows. |
| P3 | visual polish | compact table actions, clearer create disclosures, consistent locale/account controls. |

## 17. Acceptance criteria

M1 may close only when:

- M1-001 through M1-005 are fixed and all P1 regressions pass on SQLite and MySQL;
- the canonical role matrix is executable: owner, manager, reception, cashier and Platform Admin positive/negative cases are proven;
- ordinary visible locale controls work in both directions on guest, operational and platform shells;
- manager/frontline audit scopes cannot reveal foreign tenant/branch/user data;
- authentication events are safe, traceable, and do not record credentials or reset tokens;
- no critical screen overflows at 360, 768 or 1366 in EN/AR;
- OQ-22 release blockers are approved or explicitly excluded from local milestone closure with a release gate;
- before/after browser evidence and updated traceability are recorded;
- no P0/P1 remains open.

## 18. Regression plan

For each repair group:

1. add the smallest failing automated or Edge check that reproduces the defect;
2. apply one root-cause change in the shared policy/query/JS path;
3. run the focused tests on SQLite;
4. run affected M1 tests on isolated MySQL 8.4;
5. execute the exact EN/AR role journey in Edge at 1366/768/360 where visual behavior changed;
6. run the full suite, formatter, asset build, documentation validator, and diff check after the final M1 group;
7. update this report with before/after evidence and close only verified issue IDs.

## 19. Final closure recommendation

**Recommendation: NOT READY FOR CLOSURE.**

Proven: tenant/branch isolation, owner and platform foundations, session revocation, branch switching, transactional M1 mutations, SQLite/MySQL parity, and broad bilingual server rendering.

Not proven or incorrect: the visible locale interaction, canonical Branch Manager permissions, scoped audit access, authentication-event audit, tamper detection, and one mobile layout.

The safest next step is repair group **P1-A / M1-001 only**. It is isolated, has a clear browser reproduction, and does not alter authorization. Stop after its focused regression and report update, then request approval before the manager-permission group.
