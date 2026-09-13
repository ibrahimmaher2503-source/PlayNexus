# PlayNexus Software Requirements Specification (SRS)

## 2026-09-13 implemented M3 subset

The current Laravel implementation satisfies the bounded arrival portion of FR-SES-001, FR-SES-002 and FR-SES-014 plus the read-only display portion of FR-TIM-001/003/007. It also implements the first OQ-19 checkout-preparation boundary: authorized Reception/Manager staff verify an eligible guardian or audited manager override, calculate from immutable session facts, freeze the quote, and move the session to `pending_payment` for the Cashier queue with idempotent replay. Pause/resume/extend/adjust, payment posting, receipt/refund execution, child release, and final Completed checkout remain unimplemented.

**Document version:** 1.1  
**Status:** Draft for technical and product approval  
**Product release:** Phase 1 / MVP  
**Source:** PlayNexus PRD v1.0, `01-PRD-Baseline.md` v1.1, and `02-BRD.md` v1.1  
**Intended implementation:** Web-based multi-tenant SaaS; Laravel implementation is planned but this specification remains technology-verifiable

## 1. Purpose

This SRS defines the externally observable behavior and quality constraints of the PlayNexus MVP. Each requirement has a stable, unique identifier and a verification statement. These identifiers are referenced by user stories, use cases, architecture, database, API, permission, UI/UX, and test documents.

Unresolved product choices are referenced as `OQ-nn` from the BRD. A requirement marked **Conditional** is not authorized for MVP implementation until its named decision is approved.

## 2. Scope baseline

### 2.1 Included subsystems

- Authentication, tenant provisioning, branch configuration, staff users, roles, and scoped permissions.
- Guardian and child registration, guardian relationships, consent, emergency and safety notes, and search.
- Check-in, session lifecycle, smart timing, configurable pricing, extensions, checkout, and guardian verification.
- QR ticket issuance, validation, consumption, cancellation, reprint, and scan history.
- Basic catalog, POS order, discount, tax, payment recording, refund recording, and digital receipts.
- Revenue, attendance, session-history, and staff-activity reports.
- Session-ending and receipt notifications with attempt logging.
- Audit events and manual overrides; basic incident records are **Conditional/Proposed pending OQ-20**.
- English/Arabic-ready localization, regional currency/tax/time-zone configuration, security, availability, backup, and observability.

### 2.2 Excluded subsystems

Native apps; parent login/self-service; online booking and birthday packages; memberships, loyalty, wallet, and gift cards; campaigns and advanced CRM; game/queue/slot management; inventory and HR; split tender; offline synchronization; advanced BI/export; online gateway capture unless OQ-01 is approved; proprietary wristband/kiosk integration unless OQ-02 is approved; marketplace, franchise, AI, and white-label delivery.

## 3. Requirement conventions

### 3.1 Identifier families

| Prefix | Requirement family |
|---|---|
| FR-AUT | Authentication and account session behavior |
| FR-TEN | Tenant and branch management |
| FR-RBAC | Staff, roles, scope, and approvals |
| FR-CUS | Guardian and child records |
| FR-SES | Check-in, session lifecycle, and checkout |
| FR-TIM | Pricing and time engine |
| FR-TKT | Ticketing and QR |
| FR-POS | Catalog, sale, payment, refund, and receipt |
| FR-RPT | Reports |
| FR-NOT | Notifications |
| FR-SAF | Safety and incidents |
| FR-AUD | Audit and accountability |
| NFR-* | Performance, availability, scalability, usability, recovery, maintainability, compatibility, accessibility, observability |
| SEC-* | Security and privacy |
| DATA-* | Data integrity, ownership, retention, numbering, and time/money handling |
| INT-* | Integration interfaces |
| LOC-* | Localization and regional behavior |

### 3.2 Priorities

- **Must:** required for MVP acceptance.
- **Should:** expected for MVP unless the Product Owner explicitly defers it before sprint commitment.
- **Could:** not an MVP release gate.
- **Conditional:** enters scope only when the referenced open question is approved with its delivery impact.

Priority is the proposed delivery criticality after a requirement is approved; it is not evidence that a derived interpretation has already received business approval.

### 3.3 Normative terms

“Shall” denotes a testable requirement. “Server time” means the application-authoritative time source stored in UTC. “Current tenant” and “current branch scope” are derived from the authenticated user and approved assignment, never trusted solely from client input.

### 3.4 Source and approval provenance

| Detailed family | Source trace | Provenance |
|---|---|---|
| FR-TEN; SEC-TEN; DATA-TEN | Source FR-001, FR-002, BR-008 | Source-mapped decomposition |
| FR-AUT; FR-RBAC; SEC-AUT; SEC-RBAC | Source FR-003 and Security NFR | Source-mapped plus derived security controls |
| FR-CUS; DATA-REL; relevant SEC-PII | Source FR-004, BR-001, registration/safety module | Source-mapped decomposition |
| FR-SES; FR-SAF-001–002 | Source FR-005, BR-002–005 | Source-mapped decomposition |
| FR-TIM | Source FR-006 and Smart Time module | Source-mapped decomposition; exact algorithms remain OQ-16 |
| FR-TKT | Source FR-007 and Ticketing module | Source-mapped; QR-first satisfies the source “QR or barcode” choice under ASM-03 |
| FR-POS | Source FR-008, FR-009, BR-006–007 | Source-mapped; tender/refund detail remains OQ-01/OQ-09 |
| FR-RPT | Source FR-010, PRD-MVP-010, reporting section | Core reports source-mapped; extra dimensions are Proposed where noted in the PRD baseline |
| FR-NOT | PRD-MVP-011 and Notifications module | Source-mapped for session-ending/receipt only; campaigns are Deferred |
| FR-AUD | PRD-MVP-010, Security/Safety NFR/modules | Source-mapped for staff activity and sensitive-action accountability; detailed event schema is Derived |
| FR-SAF-003–006 | Safety module and Game Operator role, but absent from the Phase 1 MVP list | **Conditional/Proposed pending OQ-20** |
| NFR/SEC/DATA/INT/LOC detail | Source NFR categories and API/data overview | Source-mapped where the source is explicit; measurable/control detail is Derived and approved at G1/G2 |

Source FR-011 (memberships/loyalty) and FR-012 (parent mobile app/marketplace) are future requirements and have no MVP detailed FR family.

### 3.5 Proposed interpretation register

| Proposed item | Draft position | Approval dependency |
|---|---|---|
| PROP-SRS-001 | Staff password reset is included as a normal secure account-recovery control. | Product/Security approval; FR-AUT-004–005 |
| PROP-SRS-002 | Payment capture is recording-only, with no gateway or raw card data. | ASM-02; OQ-01 |
| PROP-SRS-003 | One payment settles a basic order; split/partial tender is deferred. | ASM-08 |
| PROP-SRS-004 | Refund design is full-only for planning; refund policy is not approved. | ASM-10; OQ-09 |
| PROP-SRS-005 | Incident records enter MVP only if Product/Safety approves them. | ASM-11; OQ-20 |
| PROP-SRS-006 | The 2-second/5-second source targets are measured at the 95th percentile. | OQ-21; NFR-PERF-001–002 |
| PROP-SRS-007 | Accessibility criteria, idempotency, atomicity, append-only finance/audit, and correlation IDs are derived quality/safety controls. | Architecture, Security, QA approval at G2 |
| PROP-SRS-008 | No cashier-shift feature is specified in the current SRS; the PRD POS module's “daily shift close” is unresolved. | OQ-24; add requirements/stories/API/data/tests only if approved |

### 3.6 Source business-rule crosswalk

| Source rule | Detailed enforcement |
|---|---|
| BR-001 | FR-CUS-004, FR-CUS-005, FR-CUS-007; DATA-REL-001 |
| BR-002 | FR-SES-001–002; session creation invariant |
| BR-003 | Session state model §6.4; FR-SES-003–006, FR-SES-010–011 |
| BR-004 | FR-RBAC-003–005; FR-SES-003–006; FR-TIM-007; FR-POS-010 |
| BR-005 | FR-SES-008–010; FR-SAF-001–002 |
| BR-006 | FR-POS-005; FR-RBAC-005 |
| BR-007 | FR-POS-010–012; FR-RPT-001; FR-AUD-001–004 |
| BR-008 | FR-RBAC-003; SEC-TEN-001; DATA-TEN-001–002 |
| BR-009 | FR-TEN-006; FR-SES-002 |
| BR-010 | FR-NOT-007; SEC-PII-002. Marketing sending remains Deferred from MVP. |

## 4. Actors and access scopes

| Actor | Scope | Summary |
|---|---|---|
| Super Admin | Platform | Provision/suspend tenants, platform configuration, limited support access as approved under OQ-14 |
| Tenant Owner | Own tenant, all assigned branches | Business/branch settings, staff and roles, consolidated reports |
| Branch Manager | Own tenant, assigned branch(es) | Branch operation, staff, reports, approvals, refunds, overrides |
| Reception Staff | Own tenant, assigned branch(es) | Family records, tickets, check-in/out, allowed session actions |
| Cashier | Own tenant, assigned branch(es) | Catalog lookup, orders, permitted discounts, payments, receipts |
| Parent / Guardian | No authenticated MVP role | Data subject and notification recipient; all MVP actions are staff-assisted |
| Scheduler / Queue Worker | System service with explicit tenant context | Alerts, receipt delivery, retry, expiry, and report-supporting jobs |

Game Operator is retained in the long-term role model but has no game-management MVP subsystem. Only if OQ-20 approves incident recording and assigns it to this role does it use the same branch-scoped permission rules as other staff.

## 5. Functional requirements

### 5.1 Authentication

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-AUT-001 | The system shall authenticate an active staff user using the approved login identifier and password and establish an authenticated session bound to that user. | Must | Valid credentials for an active user reach the authorized landing page; invalid credentials return a generic failure and create no session. |
| FR-AUT-002 | The system shall refuse login for suspended/deactivated users and for users whose tenant is inactive or suspended. | Must | Each status combination is tested; access is denied without exposing whether the user or tenant caused the denial. |
| FR-AUT-003 | The system shall terminate the current authenticated session on logout and reject reuse of its credential/session token. | Must | After logout, a replay of the previous authenticated request returns unauthenticated. |
| FR-AUT-004 | The system shall support a time-limited, single-use staff password-reset flow without revealing whether an account exists. | Must | Valid token resets the password once; expired/used/tampered token fails; request responses are indistinguishable for known and unknown identifiers. |
| FR-AUT-005 | The system shall revoke active sessions when the user is suspended, the tenant is suspended, or credentials are security-reset. | Must | A previously authenticated session is denied no later than the documented revocation interval, which must be approved before release. |

### 5.2 Tenant and branch management

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-TEN-001 | A Super Admin shall be able to create a tenant with a unique internal identifier, business name, status, plan reference, and initial owner invitation. | Must | Creation produces one tenant and invitation; a duplicate idempotency key does not produce a second tenant. |
| FR-TEN-002 | A Super Admin shall be able to activate or suspend a tenant and record a reason. | Must | Suspended tenants cannot establish or continue staff access; status change, actor, time, and reason appear in audit. |
| FR-TEN-003 | A Tenant Owner shall be able to view and update the tenant business profile within the fields permitted by policy. | Must | Saved values are returned on reload, are tenant-scoped, validated, and audited when sensitive. |
| FR-TEN-004 | A Tenant Owner shall be able to create and update branches belonging to the tenant. | Must | Every branch stores the current tenant identifier; cross-tenant identifiers are rejected; required fields are validated. |
| FR-TEN-005 | Branch configuration shall include name, address/location text, active status, opening hours, capacity, time zone, currency, tax settings, receipt settings, and permitted payment methods. | Must | A complete valid configuration can be saved; invalid time-zone/currency/tax values are rejected with field-level errors. |
| FR-TEN-006 | Authorized users shall activate/deactivate a branch with an audited reason code. The M1 administration UI derives that code from the action instead of asking the owner to choose it. Deactivation shall prevent new sessions and new POS orders but shall not delete historical data. | Must | New check-in/order is denied for an inactive branch; historical records/reports remain readable to authorized users; action is audited with `access_review`. |
| FR-TEN-007 | The system shall show branch-local operational dates/times derived from stored UTC timestamps and the configured branch time zone. | Must | Known UTC boundary examples, including date changes and daylight-offset changes where applicable, render correctly. |
| FR-TEN-008 | A Tenant Owner shall be able to view all branches and their active status; a branch-scoped user shall see only assigned branches. | Must | Matrix tests prove all-tenant visibility for owner and assigned-only visibility for scoped roles. |
| FR-TEN-009 | The system shall prevent changing a branch currency after posted financial records exist unless an explicitly authorized migration process is approved. | Must | Direct update is rejected after a posted transaction; no historical amount is reinterpreted. |

### 5.3 Staff, roles, permissions, and approvals

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-RBAC-001 | An authorized user shall create a staff user inside the current tenant and assign one or more approved role/branch scopes. | Must | Created staff is active and tenant-owned, receives a generated unknown password for later reset, invalid/cross-tenant branch assignment fails, and creation is logged. |
| FR-RBAC-002 | An authorized user shall change role/branch assignments and suspend/reactivate staff within permitted scope. | Must | Changes take effect within the documented authorization-cache interval and are audited with before/after assignments. |
| FR-RBAC-003 | Every protected UI route, API action, background job, report, and file access shall enforce authenticated tenant scope, branch scope, and required permission server-side. | Must | Positive and negative permission tests cover each protected operation; URL/body identifier tampering cannot widen access. |
| FR-RBAC-004 | The MVP shall provide named permission keys at least for tenant settings, branch settings, staff management, family records, check-in, pause/resume, session cancellation, time adjustment, checkout, checkout override, ticket issue/cancel/reprint/scan, POS sale, discount, discount approval, payment, refund, reports, and audit view; incident keys apply only if OQ-20 approves that module. | Must | The approved matrix maps every in-scope protected action to a permission key and automated tests demonstrate enforcement. |
| FR-RBAC-005 | Where policy requires approval, the requesting actor and approving actor shall both be retained; self-approval shall be allowed or denied according to the approved permission matrix. | Must | Threshold/override tests require an approver; the result stores both identities and fails if policy forbids self-approval. |
| FR-RBAC-006 | Suspension of a staff user shall not delete or anonymize that user's historical operational or audit attribution. | Must | Historical records continue to show a stable user reference/display label after suspension. |
| FR-RBAC-007 | Super Admin support access to tenant data shall be denied by default except for explicitly approved functions under OQ-14 and shall always be audited. | Must | Unapproved support access returns forbidden; any approved support action emits a privileged-access event. |
| FR-RBAC-008 | A Tenant Owner may create tenant-specific roles and control only permission keys already enforced by the implemented application. | Must | The current slice exposes `branches.view` only; tenant isolation, stale-write conflict handling, audit, assignment, grant and revocation are covered by automated tests. |

### 5.4 Guardian and child registration

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-CUS-001 | Authorized staff shall create a guardian with name, normalized phone, optional email, emergency contact details where required, consent fields, and tenant ownership. | Must | Valid record is created in current tenant; required/format failures are shown; another tenant cannot access it. |
| FR-CUS-002 | Before creating a guardian, the system shall search for normalized phone matches within the current tenant and warn about potential duplicates. | Must | Formatting variants of the same test phone generate a warning; a matching phone in another tenant is neither shown nor disclosed. |
| FR-CUS-003 | Authorized staff shall search current-tenant guardians by normalized phone and children by name, with branch access not unnecessarily hiding reusable tenant customer records. | Must | Exact phone and partial child-name tests return only current-tenant records within the approved policy. |
| FR-CUS-004 | Authorized staff shall create a child with name, date of birth or age representation approved by OQ-15, optional photo, notes, and tenant ownership, linked to at least one guardian in the same tenant. | Must | Creation without a valid guardian link fails; cross-tenant link fails; valid creation can be retrieved. |
| FR-CUS-005 | The system shall support multiple guardians per child and multiple children per guardian and shall store the relationship type and active status. | Must | Test data can create both cardinalities; duplicate active link is rejected; inactive link remains historical. |
| FR-CUS-006 | Authorized staff shall update guardian contact/consent data and child details/safety notes, while recording sensitive changes in audit. | Must | Reload returns the change; consent and safety-note modifications include actor/time and do not leak across tenants. |
| FR-CUS-007 | The system shall prevent removal/deactivation of the last active guardian link for a child. | Must | Attempt to deactivate the final link is rejected; deactivating one of two links succeeds. |
| FR-CUS-008 | The family record shall show visit/session history accessible only to authorized users and scoped to the current tenant. | Must | History includes the child's sessions in date order and excludes all other tenants. |
| FR-CUS-009 | If child photos are enabled, uploads shall be optional, restricted to approved image types/sizes, privately stored, and served only through authorized access. | Should | Invalid type/oversize input fails; direct unauthenticated file access fails; authorized current-tenant view succeeds. |

### 5.5 Check-in, session lifecycle, and checkout

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-SES-001 | Authorized reception staff shall create an Active session for one child at one active branch using either a valid ticket or a selected active pricing rule. | Must | Successful check-in stores tenant, branch, child, initiator, rule/ticket, server start time, and Active status atomically. |
| FR-SES-002 | The system shall reject check-in when required data is missing, the branch is inactive, the rule is inactive, the ticket is invalid, or the child already has an Active/Paused session in the tenant. | Must | One negative test per condition returns a conflict/validation result and creates no partial session/ticket consumption. |
| FR-SES-003 | Authorized staff shall pause an Active session using an allowed pause type and reason; the system shall start a pause interval at server time. | Must | Active becomes Paused; one open pause interval exists; action/actor/type/reason/time are retained. |
| FR-SES-004 | Authorized staff shall resume a Paused session; the system shall close the open pause interval using server time. | Must | Paused becomes Active; pause end is at/after start; a second resume fails without changing data. |
| FR-SES-005 | Authorized staff shall extend an Active or Paused session using an allowed extension option or approved manual value. | Must | Extension and incremental price are previewed, accepted once, stored, and included in the calculation/audit. |
| FR-SES-006 | Authorized staff shall cancel an Active or Paused session only with a reason and, where configured, manager approval. | Must | Session becomes Cancelled; open pause is safely closed; reason/approval are retained; terminal state rejects later operational transitions. |
| FR-SES-007 | Authorized staff shall request a checkout quote that shows elapsed time, included/excluded pause time, extensions, rounding, base/overage charge, discounts/tax where applicable, payments, and amount due. | Must | Worked examples approved in DEP-01 reproduce the displayed breakdown and total. |
| FR-SES-008 | Checkout shall require selection/verification of an active guardian linked to the child or an authorized manager override with reason. | Must | Checkout completion is rejected until one of the two records exists; saved verification identifies guardian/method/actor/time or approver/reason. |
| FR-SES-009 | The system shall not mark a session Completed while a required amount remains due unless an authorized payment exception policy is explicitly configured. | Must | With amount due, completion fails; after a matching posted payment it succeeds; exception behavior is absent unless approved. |
| FR-SES-010 | Completing checkout shall atomically store server end time, final immutable calculation snapshot, guardian verification/override, and Completed state. | Must | Failure rolls back all completion changes; retry is idempotent and returns the already-completed result rather than duplicating financial/receipt records. |
| FR-SES-011 | Completed and Cancelled sessions shall be terminal. Corrections shall be represented by authorized adjustment records rather than rewriting historical events. | Must | Transition/update attempts fail; an approved adjustment preserves original and revised values, actor, approver, reason, and time. |
| FR-SES-012 | Authorized staff shall view/search sessions by branch, state, date/time, ticket/QR, parent phone, or child name within their permitted scope. | Must | Each filter returns correct scoped results; combinations and pagination are deterministic. |
| FR-SES-013 | The active-session view shall show current billable elapsed time, expected end/alert status, and current estimated charge calculated from authoritative stored data. | Must | Refresh after a controlled clock advance shows correct values and does not mutate the final financial snapshot. |
| FR-SES-014 | Session-changing commands shall use concurrency control so two requests cannot create duplicate transitions, pauses, payments, or checkouts. | Must | Parallel request tests result in one accepted transition and a conflict/idempotent response for the other. |

**M4/OQ-19 bounded preparation contract — 2026-09-13:** Reception (or an authorized Branch Manager) may prepare checkout only for an Active, in-scope session. The server verifies an active same-tenant, checkout-capable, verified guardian by registered-phone last four digits, or accepts a permission-checked manager override with a non-empty reason. It then calculates from immutable session facts, stores the frozen quote and verification evidence, increments the session lock version, appends an audit/event pair, and transitions to `pending_payment`. Cashier cannot prepare checkout; M5 will post one matching payment and atomically complete the session. Identical UUID replays return the original preparation; changed replays, stale locks, ineligible/foreign guardians, and terminal states conflict or fail safely without mutation. Payment, refund, receipt, shift, and child-release execution are outside this slice.

### 5.6 Smart time and pricing engine

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-TIM-001 | Authorized users shall configure branch pricing rules with name, active dates/status, base duration/price or rate, overage/extension rules, rounding mode/increment, tax treatment, permitted pause behavior, and alert lead time as applicable. | Must | Valid rule saves; incomplete/inconsistent rule is rejected; activation is audited. |
| FR-TIM-002 | A pricing rule change shall create a new effective version or snapshot and shall not change the calculation semantics of an existing session/order. | Must | A session created before the change retains its original worked result; a later session uses the new version. |
| FR-TIM-003 | The time engine shall calculate total elapsed duration from server start/end (or calculation time), total recorded pause duration, excluded approved pause duration, and billable duration. | Must | Boundary tests for no pause, one pause, multiple pauses, open pause, and non-excludable pause match approved examples. |
| FR-TIM-004 | The time engine shall calculate base, extension, overage, rounding, discount/tax inputs, and final session charge according to the selected versioned rule. | Must | Approved worked examples, including exact boundaries and one-unit-over boundaries, match within currency precision. |
| FR-TIM-005 | The same stored inputs and rule version shall always produce the same calculation output independent of UI locale. | Must | Recalculation in English/Arabic and on separate application instances produces the same numeric result and component codes. |
| FR-TIM-006 | The system shall store a structured calculation breakdown and rule-version reference whenever a quote is accepted or a session is completed. | Must | Stored snapshot can reproduce every displayed line and does not change after later configuration edits. |
| FR-TIM-007 | Manual time or price adjustment shall require explicit permission, reason, old/new values, and any configured approval; it shall never edit source timestamps invisibly. | Must | Unauthorized/no-reason request fails; authorized adjustment is shown in quote/report/audit. |
| FR-TIM-008 | The system shall determine the session-ending alert due time from the active rule, extension, and applicable pause behavior, and shall reschedule it after relevant session changes. | Must | Controlled-clock tests prove one current alert schedule; stale schedules do not send duplicate alerts after pause/extension/cancel/complete. |
| FR-TIM-009 | Time calculations shall use an integer duration unit and monetary minor units or an equivalent decimal representation that avoids binary floating-point rounding errors. | Must | Repeated arithmetic and boundary test dataset shows no fractional-cent drift. |

### 5.7 Ticketing and QR

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-TKT-001 | Authorized staff shall issue a ticket belonging to the current tenant/branch and a branch-local service date with type, validity, price/rule reference, holder/child binding where used, status, and a non-guessable unique QR payload. | Must | Ticket and QR are created once; branch/service-date bounds are immutable; collision/duplicate identifier test fails safely; payload does not reveal sensitive child data. |
| FR-TKT-002 | The system shall render the QR for on-screen display and browser-compatible printing. | Must | A supported scanner/camera reads the printed and displayed test QR and resolves it through the validation interface. |
| FR-TKT-003 | Authorized staff shall validate/scan a ticket and receive a result of valid or a specific staff-safe reason such as expired, cancelled, consumed, wrong branch, wrong service date/policy, or not found. The first successful scan permanently locks holder/child assignment; failed scans do not. | Must | State/scope/date tests produce the expected safe result; post-scan reassignment is rejected without cross-tenant disclosure. |
| FR-TKT-004 | Every scan/validation attempt shall record tenant, branch, ticket when identifiable, actor/device context available to the application, time, and result. | Must | Scan history contains successful and failed known-ticket attempts; unknown payload logging is privacy-safe. |
| FR-TKT-005 | When a ticket authorizes check-in, its consumption and session creation shall occur atomically and idempotently. | Must | Concurrent scan/check-in attempts create at most one session and one consumption event. |
| FR-TKT-006 | Authorized staff shall cancel an unused ticket with a reason; expired, cancelled, or consumed tickets shall not authorize check-in. A ticket is refund-eligible only while Issued with no successful scan, consumption, or linked session, and refund requires in-scope Branch Manager/Tenant Owner approval and audit. | Must | State tests deny check-in/refund after use; cancellation is audited; any paid refund creates a linked immutable reversal under OQ-09 rather than rewriting the ticket or payment. |
| FR-TKT-007 | Authorized staff shall reprint a ticket without changing its identity, validity, price, consumption state, or QR payload, and the reprint shall be audited. | Must | Before/after comparison is identical except reprint audit/metadata. |
| FR-TKT-008 | A scheduled process or validation read shall treat a ticket past its validity end as Expired even if physical status maintenance is delayed. | Must | A controlled-clock test rejects a past-validity ticket without needing a prior batch job. |

### 5.8 Basic POS, payments, refunds, and receipts

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-POS-001 | Authorized users shall manage a branch-available catalog of ticket, F&B, merchandise, add-on, and extension items with SKU/code, name, type, price, tax classification, and active status. | Must | Valid products are selectable at assigned branches; inactive or out-of-scope products cannot be added to a new order. |
| FR-POS-002 | A Cashier shall create a branch order and add/remove/change quantities of available items before posting. | Must | Line subtotal and order subtotal update deterministically; invalid quantity/price tampering is rejected server-side. |
| FR-POS-003 | The system shall support associating an order with a guardian and/or session where relevant without requiring association for ordinary retail sales unless policy says otherwise. | Must | Linked order appears in authorized family/session history; unlinked permitted retail sale can complete. |
| FR-POS-004 | The system shall calculate line subtotal, allowed discount, tax, and order total from server-held catalog/configuration values and show a breakdown before payment. | Must | Approved tax/discount examples reconcile exactly; client-modified price/tax is ignored/rejected. |
| FR-POS-005 | A Cashier shall apply discounts only within assigned permission/threshold; higher discounts shall require an authorized approval and reason. | Must | Below-threshold case succeeds; above-threshold case remains unpostable until valid approval; both are audited. |
| FR-POS-006 | A Cashier shall record one payment against an order using a branch-permitted method, reference where applicable, amount, currency, and server timestamp. | Must | Matching payment posts the order once; disallowed method/currency, negative amount, or duplicate idempotency key fails safely. |
| FR-POS-007 | Payment posting shall atomically create the immutable financial transaction, mark the order Paid, allocate it to the related session if any, and request receipt issuance. | Must | Injected failure creates neither a partial posting nor duplicate; retry returns the original payment/receipt result. |
| FR-POS-008 | The system shall issue a unique digital receipt containing required seller/branch, receipt number, date/time, cashier, line, subtotal, discount, tax, total, payment method/reference-safe details, and refund status according to OQ-08. | Must | Receipt matches source order/payment and retains the same number/content on reload or resend. |
| FR-POS-009 | Authorized users shall retrieve and resend/reprint an existing receipt without creating a new payment or receipt number. | Must | Repeated action preserves commercial values/count and creates only delivery/reprint audit events. |
| FR-POS-010 | An authorized user shall record a refund against an eligible posted payment with reason and configured approval. Refund amount/type/window/method shall follow the approved OQ-09 policy; ASM-10 assumes full-only for planning. | Must with OQ-09 | Refund cannot exceed refundable balance; a policy-valid refund creates a linked negative/reversal record and does not delete the original. |
| FR-POS-011 | Refunded amounts shall be visible on the order/receipt status and deducted in revenue reporting for the refund posting period according to the approved accounting policy. | Must with OQ-09 | The approved full/partial fixture reconciles order, refund, receipt status, and report for identical filters. |
| FR-POS-012 | Posted orders, payments, receipts, and refunds shall not be physically deleted or freely edited through the application. | Must | Delete/update attempts are forbidden; correction uses void/refund/adjustment behavior with audit. |
| FR-POS-013 | **Assumption pending OQ-01/OQ-09:** split payments, partial payments, partial refunds, cash-drawer balancing, inventory depletion, and gateway capture shall not be presented as available MVP functions unless separately approved. | Open Decision | Under ASM-02/ASM-08/ASM-10, UI/API do not advertise or accept these operations; acceptance must be revised if the decisions change. |

### 5.9 Reports

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-RPT-001 | Authorized users shall run a revenue report for a date range and permitted branch scope, showing gross sales, discounts, tax, refunds, and net recorded revenue, with breakdowns by branch, cashier, item/product type, ticket type, and payment method where data exists. | Must | Each aggregate reconciles to posted source records under the documented posting-date policy. |
| FR-RPT-002 | Authorized users shall run an attendance report by date range and branch with session/child visit counts and breakdown by hour and child age band where age data exists. | Must | Controlled dataset produces exact totals; missing age is grouped as Unknown rather than excluded silently. |
| FR-RPT-003 | Authorized users shall search session history by date range, branch, state, child, guardian phone, ticket, and staff actor and view duration, pauses, extensions, final charge, and verification outcome. | Must | Filters/pagination return the controlled dataset and only permitted scope. |
| FR-RPT-004 | Authorized users shall view staff activity/audit reporting filtered by date, branch, actor, action category, and subject. | Must | Known actions are returned once with immutable timestamp and subject linkage. |
| FR-RPT-005 | Tenant Owners shall be able to consolidate permitted branches; branch-scoped roles shall not request or infer unassigned branch results. | Must | Scope matrix tests prove totals and row data cannot include unassigned branches. |
| FR-RPT-006 | Every report shall display the applied date/time-zone, branch, currency, state, and other material filters and a generated-at timestamp. | Must | Screenshot/API response shows all effective filters; changing a filter changes the query result deterministically. |
| FR-RPT-007 | On-screen reports shall paginate or aggregate large result sets server-side and shall not require loading all matching detail rows into the browser. | Must | Performance test verifies bounded page response and memory under the approved standard range. |
| FR-RPT-008 | PDF/Excel export and advanced analytics are deferred; any CSV export implemented opportunistically shall enforce the same scope and redaction controls. | Could | No release dependency; if exposed, access/security tests match on-screen report. |

### 5.10 Notifications

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-NOT-001 | The system shall create an operational notification request when a session reaches the configured ending-alert due time and remains eligible. | Must | Controlled-clock test creates one request for an eligible session and none for completed/cancelled/stale schedules. |
| FR-NOT-002 | The system shall create a receipt notification request after receipt issuance when the guardian has an approved destination/channel. | Must | A paid order with eligible destination creates one request; missing destination is logged as not sendable without failing payment. |
| FR-NOT-003 | Notification processing shall be asynchronous and shall not block successful check-in, checkout, payment posting, or receipt creation on provider availability. | Must | Simulated provider outage leaves core transaction successful and creates a failed/retryable notification attempt. |
| FR-NOT-004 | Every request/attempt shall retain tenant, event/type, template/version, recipient reference with protected destination, channel/provider, attempt time, result, provider reference where available, and error category. | Must | Log inspection contains required metadata and redacts sensitive provider content from normal staff views. |
| FR-NOT-005 | Retryable failures shall follow a configurable bounded retry policy; permanent failures shall not retry indefinitely. | Must | Provider test doubles prove exact attempt cap/backoff configuration and final state. |
| FR-NOT-006 | Receipt and ending-alert templates shall support locale variables without changing financial or timing values. | Must | English/Arabic-ready template rendering produces correct direction/text and identical receipt amount/session times. |
| FR-NOT-007 | Marketing campaigns shall not be available in MVP. Stored marketing consent shall not be interpreted as permission to send any campaign from the MVP. | Must | No campaign endpoint/UI/job is exposed; operational templates are allowlisted. |

### 5.11 Safety and incidents

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-SAF-001 | The checkout interface shall present active guardians linked to the child and the approved verification method(s) without exposing unnecessary sensitive data. | Must | Only active same-tenant links appear; masked contact values follow the approved UI policy. |
| FR-SAF-002 | A checkout override shall require a dedicated permission, manager approval if configured, and a non-empty reason; it shall be highlighted in session history and audit reporting. | Must | Unauthorized/no-reason override fails; authorized case completes and is discoverable by an override filter. |
| FR-SAF-003 | **Proposed pending OQ-20:** Authorized staff shall create an incident record with branch, child when applicable, session when applicable, category, occurred/reported times, description, severity, reporter, and status. | Conditional | If incident recording is approved for MVP, required fields validate and the saved record is tenant/branch-scoped and audit-attributed. |
| FR-SAF-004 | **Proposed pending OQ-20:** Authorized staff shall update incident status and add follow-up notes without replacing prior notes or original reported facts. | Conditional | If approved, update appends actor/time note and state transition; original reporter/time/description remain available. |
| FR-SAF-005 | **Proposed pending OQ-20:** Authorized users shall search incidents by child, date range, branch, staff reporter, severity, and status. | Conditional | If approved, filter tests return only permitted records and preserve chronological event history. |
| FR-SAF-006 | **Proposed pending OQ-20:** The incident module shall not claim emergency dispatch, medical advice, or regulatory case-management capability. | Conditional | If approved, UI/help text does not represent the feature as an emergency service; operational escalation remains a venue procedure. |

### 5.12 Audit and accountability

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| FR-AUD-001 | The system shall append an audit event for authentication security events, tenant/branch status/settings, role/scope changes, family consent/safety changes, session transitions/adjustments, ticket lifecycle, discounts/approvals, payments/refunds, checkout verification/override, incidents, and privileged support access. | Must | Event matrix tests create one expected event per successful action and relevant denied sensitive attempts. |
| FR-AUD-002 | Each audit event shall contain immutable event ID, UTC time, tenant, branch if applicable, actor type/ID, action code, subject type/ID, request/correlation ID, outcome, reason/approval reference where relevant, and safe before/after data where relevant. | Must | Schema validation and sample event inspection prove presence, immutability, and secret redaction. |
| FR-AUD-003 | Application users shall not update or delete audit events; retention/archival shall occur only through an approved administrative policy. | Must | All normal-role update/delete attempts fail; integrity-monitoring test detects direct tampering in the controlled environment. |
| FR-AUD-004 | Authorized users shall search audit events within their tenant/branch scope without access to password, token, full payment credential, or unnecessary child-sensitive content. | Must | Scope/redaction tests pass for each role; no secret fields appear in UI/API/log export. |
| FR-AUD-005 | Operational records and their audit events shall share a trace/correlation identifier for multi-step commands such as check-in, checkout, payment, refund, and notification dispatch. | Should | A test transaction can be followed across command, domain records, queue request, and audit entries using a stable reference. |

## 6. State models and invariants

### 6.1 Tenant

| Current | Allowed next | Guard/effect |
|---|---|---|
| Pending | Active, Suspended | Activation requires minimum profile/owner; reason required for suspension. |
| Active | Suspended | New/current access revoked per FR-AUT-005; data retained. |
| Suspended | Active | Authorized Super Admin only; audit required. |

No physical deletion behavior is specified for MVP.

### 6.2 Branch

| Current | Allowed next | Guard/effect |
|---|---|---|
| Inactive | Active | Minimum operational configuration is valid. |
| Active | Inactive | Authorized reason; no new sessions/orders; existing/historical records retained. |

### 6.3 Staff user

| Current | Allowed next | Guard/effect |
|---|---|---|
| Invited | Active, Suspended | Activation follows credential setup; invitation expiry policy is configuration. |
| Active | Suspended | Sessions revoked; attribution retained. |
| Suspended | Active | Authorized reactivation. |

### 6.4 Session

```text
             pause                 resume
Active ----------------> Paused ----------------> Active
   |                        |
   | complete               | complete (after closing pause)
   v                        v
Completed                Completed
   ^ terminal                ^ terminal

Active -------- cancel ------> Cancelled <------ cancel -------- Paused
                                  terminal
```

Invariants:

- A child has at most one Active/Paused session in a tenant.
- Exactly one open pause interval exists only while state is Paused.
- Completed and Cancelled are terminal.
- Completion includes a final calculation snapshot and checkout verification/override.
- A failed multi-record operation leaves the prior valid state intact.

### 6.5 Ticket

| Current | Allowed next | Guard/effect |
|---|---|---|
| Issued | Consumed, Cancelled, Expired | Consumption occurs with check-in; cancellation requires permission/reason; expiration is effective when validity ends. |
| Consumed | None | Terminal for entry use; refund does not silently make it reusable. |
| Cancelled | None | Terminal. |
| Expired | None | Terminal for MVP. |

Reprint is an event, not a state transition.

### 6.6 POS order and financial records

The refund transition below is a **planning assumption** under ASM-10, not a closed finance decision. OQ-09 may add partial-refund states and allocations.

| Aggregate | Current | Allowed next | Notes |
|---|---|---|---|
| Order | Draft | Paid, Voided | Line edits only in Draft; Void requires reason where retained. |
| Order | Paid | Refunded | Draft baseline supports a full linked refund only; subject to OQ-09. |
| Payment | Posted | Refunded | Original payment remains immutable. |
| Receipt | Issued | Refunded annotation/status | Number and original commercial facts remain reproducible. |

### 6.7 Notification

```text
`queued` -> `sending` -> `sent` -> `delivered` (when provider supplies verified delivery status)
                    \-> `failed_retryable` -> `queued` (bounded)
                    \-> `failed_permanent`
`queued` -------------------------------> `stale`
```

UI labels may render `sending` as “Processing” and `stale` as “Cancelled/Stale”; persistence, API, jobs, and tests use the canonical codes above.

Provider status availability is channel-specific. `Sent` must not be presented as `Delivered` unless confirmed.

### 6.8 Incident

**Proposed pending OQ-20:** draft states are `Open`, `UnderReview`, and `Closed`; categories, transition permissions, any `Resolved` state, and reopen policy require operational approval. State names may be localized, but approved stored codes must remain stable.

## 7. Data requirements

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| DATA-TEN-001 | Every tenant-owned table/aggregate, file, cache entry, search record, queued job, and report result shall carry or derive an unambiguous tenant identifier. | Must | Architecture/data review and automated isolation tests cover every persistence/processing mechanism. |
| DATA-TEN-002 | Branch-owned records shall carry a branch identifier belonging to the same tenant; cross-tenant foreign relationships shall be impossible or rejected. | Must | Constraint/application tests reject mismatched tenant/branch relationships. |
| DATA-ID-001 | Public identifiers used in URLs/QRs shall be non-sequential or otherwise resistant to enumeration; internal keys shall never grant authorization by possession. | Must | Enumeration/tampering security tests return no cross-scope data. |
| DATA-TIME-001 | Event timestamps shall be stored in UTC with sufficient precision and rendered using branch/user locale; business-effective dates shall retain the applicable time-zone context. | Must | Round-trip/boundary tests prove UTC storage and correct branch-local display/filter behavior. |
| DATA-MNY-001 | Monetary amounts shall store currency code and exact minor-unit/decimal value without binary floating-point arithmetic. | Must | Arithmetic/reconciliation test dataset has no rounding drift. |
| DATA-VER-001 | Pricing, tax, discount, and receipt configuration used by a posted session/order shall be retained as a version reference and/or immutable snapshot. | Must | Later configuration change does not alter historic quote/receipt/report reconstruction. |
| DATA-FIN-001 | Posted orders, payments, refunds, and receipt facts shall be append-only from normal application workflows. | Must | Update/delete API/database policy tests fail; correction records link to originals. |
| DATA-REL-001 | Guardian-child links shall be many-to-many, tenant-scoped, stateful, and historically traceable. | Must | Cardinality, final-active-link guard, and history tests pass. |
| DATA-AUD-001 | Audit events shall be append-only and independently queryable by tenant, branch, actor, subject, action, outcome, and time. | Must | Query and immutability tests pass on representative volume. |
| DATA-PII-001 | The schema shall separate contact, child-sensitive, consent, operational, and audit data sufficiently to support least-privilege access and future retention actions. | Must | Data/permission review maps fields to access and retention classifications without ambiguous shared blobs for critical fields. |
| DATA-PII-002 | Required fields, retention periods, deletion/anonymization exceptions, and lawful basis shall be configured/documented after OQ-07 and before production data entry. | Must | Approved data inventory and retention schedule exist at Gate G3; implementation matches it. |
| DATA-NUM-001 | Receipt numbers shall be unique and concurrency-safe within the legal scope approved through OQ-08; gaps shall not be silently reused. | Must | Parallel issuance test has no duplicates; failed issue behavior follows approved numbering policy. |
| DATA-INT-001 | Multi-record check-in, ticket consumption, checkout, payment, refund, and approval operations shall be transactional or compensating/idempotent so partial business state is not exposed. | Must | Injected-failure and retry tests prove the documented invariant for every listed operation. |

## 8. External and internal interface requirements

### 8.1 User interface surfaces

| Interface | Users | Required behavior |
|---|---|---|
| Sign-in / reset | All staff | Clear generic authentication errors, accessible form, locale-ready text. |
| Tenant / branch setup | Super Admin, Tenant Owner | Validated forms, explicit active/inactive consequences, audit confirmation. |
| Staff / role assignments | Tenant Owner, Branch Manager where permitted | Show effective role and branch scope before save. |
| Family lookup / registration | Reception | Phone-first lookup, child-name search, duplicate warning, rapid guardian/child creation. |
| Active sessions board | Reception, Manager | Branch-scoped states, elapsed/billable time, alert state, quick authorized actions. |
| Ticket / QR | Reception, Cashier | Issue, display/print, scan/validate, state/result, reprint/cancel controls. |
| POS | Cashier | Touch-friendly catalog/cart, server-calculated totals, approval handoff, payment confirmation. |
| Checkout | Reception/Cashier according to matrix | Explainable time/charge, amount due, guardian verification, explicit final confirmation. |
| Reports | Owner/Manager/authorized Finance users | Visible filters/time zone/currency, branch-scope enforcement, drill-through where specified. |
| Audit / Conditional incidents | Authorized managers/safety users | Audit search and immutable chronology; incident surfaces/protected fields only if OQ-20 approves the module. |

### 8.2 Application/API interface

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| INT-API-001 | The web client and any public/internal API shall use authenticated HTTPS requests and enforce the same authorization/business rules irrespective of client. | Must | Direct API tests cannot bypass UI restrictions; plaintext production endpoints are unavailable. |
| INT-API-002 | API errors shall return a stable machine code, safe user message, correlation ID, and field errors where relevant without stack traces or secret data. | Must | Validation, unauthorized, forbidden, not-found, conflict, rate-limit, and server-error samples match the contract. |
| INT-API-003 | Mutating operations vulnerable to retries—tenant creation, check-in, ticket issue/consume, checkout, payment, refund, receipt issue, notification request—shall accept or internally enforce idempotency. | Must | Same actor/key/payload returns the same result; changed payload with reused key conflicts; concurrent retries create no duplicate. |
| INT-API-004 | List/search interfaces shall support bounded pagination, deterministic sorting, filters, and maximum date-range/page-size controls. | Must | Boundary requests are capped/rejected predictably and do not return unbounded datasets. |
| INT-API-005 | API versioning and backward-compatibility policy shall be documented before any external client is authorized. | Should | API specification states version scheme, deprecation process, and compatibility rules. |

### 8.3 Notification provider

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| INT-NOT-001 | Notification providers shall be accessed through a channel adapter that maps provider outcomes to stable internal states. | Must | Provider test double and chosen provider both map success, retryable failure, permanent failure, and available delivery callback. |
| INT-NOT-002 | Provider credentials shall be stored outside source code, logs, audit payloads, and client responses, with environment-specific configuration. | Must | Repository/response/log scan finds no credential; rotation procedure is tested before production. |
| INT-NOT-003 | Incoming delivery-status callbacks, if used, shall authenticate/verify the provider request, be idempotent, and tolerate out-of-order duplicate events. | Must | Invalid signature fails; duplicate/out-of-order fixture does not regress final state or duplicate notifications. |

### 8.4 Payment gateway

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| INT-PAY-001 | Baseline MVP shall record payments but shall not handle raw card data or claim gateway capture. | Must | No raw PAN/CVV fields, logs, or endpoints exist; payment method/reference fields are non-sensitive. |
| INT-PAY-002 | If OQ-01 approves a gateway, its detailed authorization, callback, reconciliation, failure, refund, security, and certification requirements require an approved SRS/API change before implementation. | Conditional | Change request and approved specification exist before code is released. |

### 8.5 QR/scanner/printer hardware

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| INT-HW-001 | Baseline QR input shall support keyboard-emulating scanners and/or approved browser camera scanning; QR output shall support browser display/print. | Must | Approved pilot devices complete issue, scan, and reprint tests. |
| INT-HW-002 | The application shall not depend on proprietary wristband, kiosk, or fiscal-printer drivers unless OQ-02/OQ-08 explicitly add them. | Must | Baseline journey works with supported browser/standard printer only. |

## 9. Non-functional requirements

### 9.1 Performance and capacity

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| NFR-PERF-001 | Source target: standard interactive actions complete within 2 seconds under normal load. **Proposed measurement PROP-SRS-006:** evaluate the 95th percentile of server response time, excluding provider delivery and client network latency. | Must / measurement open | Repeatable load test meets the approved percentile/load definition for login, search, check-in, session action, cart, payment record, and receipt retrieval. |
| NFR-PERF-002 | Source target: standard-range dashboard/core reports complete within 5 seconds. **Proposed measurement PROP-SRS-006:** evaluate the 95th percentile under normal load. | Must / measurement open | Revenue, attendance, sessions, and staff-activity datasets meet the approved percentile/range definition. |
| NFR-PERF-003 | The Product Owner and Architecture Owner shall approve quantified normal/peak concurrency, tenant, branch, customer, session, transaction, report-range, and notification-volume profiles before Gate G2. | Must | Signed capacity profile exists; tests use at least normal and agreed peak scenarios. |
| NFR-PERF-004 | Check-in, ticket validation, checkout, and payment commands shall provide a visible in-progress state and prevent accidental repeat submission. | Must | Slow-response UI test shows progress, disables/reconciles repeat action, and creates one result. |

### 9.2 Availability, backup, and recovery

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| NFR-AVL-001 | The production service shall target 99.9% monthly availability for core operations, with exclusions and measurement source defined in the approved SLA. | Must | Monitoring/SLA configuration calculates the same result from a controlled outage sample. |
| NFR-AVL-002 | Failure of a notification provider shall not make core operational or financial transactions unavailable. | Must | Provider outage test passes core journey and queues/logs notification outcome. |
| NFR-DR-001 | Production data shall be backed up automatically using encrypted backups and documented retention. | Must | Backup jobs and alerts are visible; an approved sample backup completes. |
| NFR-DR-002 | Recovery point objective, recovery time objective, backup retention, region, and restore authority shall be approved before Gate G2. | Must | Decision record and runbook contain numeric values and owners. |
| NFR-DR-003 | A full restore test into an isolated environment shall be completed before production, verifying tenant, session, financial, receipt, and audit integrity. | Must | Signed restore report meets approved RPO/RTO and reconciliation checks. |

### 9.3 Scalability and maintainability

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| NFR-SCA-001 | Application services, queues, and scheduled jobs shall scale without relying on process-local session, lock, or timing state. | Must | Two-instance test executes login, session transition, scheduled alert, and idempotent command correctly. |
| NFR-SCA-002 | Tenant and branch growth shall not require separate application code deployments per customer. | Must | A new configured tenant/branch operates using the common release artifact. |
| NFR-MNT-001 | Business rules for authorization, session transitions, time calculation, financial posting, and tenant scoping shall have automated unit/integration coverage and a single authoritative implementation per interface. | Must | Traceable test suite covers requirement IDs; UI and API yield identical rule outcomes. |
| NFR-MNT-002 | Database migrations and deployment steps shall support forward deployment and documented rollback/roll-forward for each release. | Must | Staging rehearsal and runbook demonstrate the chosen recovery path. |

### 9.4 Usability, accessibility, and compatibility

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| NFR-USA-001 | Reception and cashier critical tasks shall be optimized for desktop/tablet and shall not require re-entering data already available in the current workflow. | Must | Moderated pilot task test records no unnecessary duplicate entry; issues are dispositioned before G4. |
| NFR-USA-002 | Destructive/sensitive actions shall clearly show subject and consequence, require explicit confirmation/reason where specified, and return a visible outcome. | Must | Cancel, refund, deactivate, adjustment, and override usability tests meet the pattern. |
| NFR-USA-003 | Forms shall preserve safe input after recoverable validation errors and identify each failing field in plain language. | Must | Representative form tests retain non-sensitive values and link error text to fields. |
| NFR-ACC-001 | Critical workflows shall be operable by keyboard, provide visible focus, programmatic labels, logical reading order, adequate contrast, and non-color-only states. | Must | Automated accessibility scan plus manual keyboard/screen-reader smoke test passes approved criteria; exceptions are documented. |
| NFR-CMP-001 | Supported browser, operating system, tablet, scanner, and printer versions shall be approved before Gate G2 and verified before pilot. | Must | Compatibility matrix contains results for every approved environment. |

### 9.5 Observability

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| NFR-OBS-001 | The platform shall emit structured operational logs, metrics, and error traces with correlation IDs while excluding credentials and unnecessary personal/child data. | Must | A critical journey is traceable across services/jobs; automated/log review finds no forbidden sample secrets. |
| NFR-OBS-002 | Monitoring shall alert on core endpoint availability, error rate, queue backlog/failure, scheduler health, notification failures, database health, backup failures, and resource saturation. | Must | Each alert is exercised in staging and routes to a named owner/runbook. |
| NFR-OBS-003 | Application audit events and technical logs shall be distinguishable: audit provides business accountability, while logs support operations and may follow different retention/access policies. | Must | Data inventory and access tests show separate stores/views or clearly separated policies. |

## 10. Security and privacy requirements

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| SEC-TEN-001 | The system shall enforce tenant isolation at query, object authorization, cache, queue, file, report, and search boundaries. | Must | Automated negative tests with colliding identifiers and malicious tenant/branch input return no data or inference. |
| SEC-AUT-001 | Passwords shall be stored only using the framework's approved adaptive password hash; plaintext or reversible password storage is prohibited. | Must | Storage inspection shows supported hashes; password values never appear in logs/events. |
| SEC-AUT-002 | Authentication/session cookies or tokens shall use secure production settings, rotation/revocation behavior, CSRF protection where applicable, and inactivity/absolute lifetimes approved by Security. | Must | Configuration and dynamic tests verify attributes, expiry, CSRF, rotation, and logout/revocation. |
| SEC-RBAC-001 | Authorization shall default deny and be evaluated server-side for every protected object and action. | Must | Missing permission/scope always denies; object identifier substitution cannot bypass policy. |
| SEC-TRN-001 | All production user, API, and provider traffic shall be encrypted in transit using currently approved TLS configuration. | Must | Security scan finds no plaintext endpoint or deprecated configured protocol approved for exclusion. |
| SEC-ENC-001 | Backups and provider/application storage containing personal or sensitive data shall be encrypted at rest with controlled key access. | Must | Architecture/configuration evidence and restore test confirm encryption and authorized access. |
| SEC-INP-001 | All external input shall be validated and output encoded/parameterized as appropriate to prevent injection, cross-site scripting, unsafe upload, path traversal, and mass assignment. | Must | Static/dynamic security tests and malicious fixtures are rejected without execution or data leakage. |
| SEC-RATE-001 | Authentication, password reset, search, QR validation, and other abuse-sensitive interfaces shall have risk-appropriate throttling without causing cross-tenant denial through a shared weak key. | Must | Rate tests trigger controlled limits and recovery; one actor cannot exhaust another tenant's allowance under the approved design. |
| SEC-SEC-001 | Secrets shall be managed outside source code and rotated using a documented process with least-privilege environment access. | Must | Repository/build/log scan finds no production secret; a non-production rotation exercise succeeds. |
| SEC-AUD-001 | Privileged, denied-sensitive, approval, override, refund, and data-access actions identified in FR-AUD shall produce tamper-resistant audit evidence. | Must | Audit event matrix passes; ordinary users cannot alter the records. |
| SEC-PII-001 | Staff interfaces shall display only personal and child data necessary for the user's task and role; contact/identifier masking shall follow the approved permission matrix. | Must | Role-based field visibility test passes for guardian, child, receipt, report, incident, and audit surfaces. |
| SEC-PII-002 | Consent and privacy notice version, response, capture time, and capturing actor/channel shall be retained as structured data according to OQ-07. | Must | Creating/updating consent stores a versioned event and does not erase prior evidence. |
| SEC-PII-003 | Production retention, correction, access, deletion/anonymization, legal-hold, and breach-response procedures shall be approved for each launch jurisdiction before G3. | Must | Signed procedure maps to implemented controls and exceptions; a dry-run request is evidenced. |
| SEC-UPL-001 | Child photo uploads, if enabled, shall be private, malware/content-type checked according to approved infrastructure capability, renamed server-side, and inaccessible by predictable public paths. | Should | Upload security fixtures and direct URL tests pass. |
| SEC-VUL-001 | Before production, the release shall pass dependency, secret, static, dynamic, access-control, tenant-isolation, and manual critical-flow security testing, with no unresolved Critical/High issue unless formally accepted at G4. | Must | Security report lists scope/results; findings have remediation or signed risk acceptance. |

## 11. Localization requirements

| ID | Requirement | Priority | Verification / acceptance |
|---|---|---|---|
| LOC-001 | User-facing text shall use translation keys rather than hard-coded business copy, supporting approved English and Arabic resources. | Must | Locale switch or test locale identifies no untranslated critical-flow key except approved gaps. |
| LOC-002 | Layout and components shall support both left-to-right and right-to-left direction without loss of controls, clipping, or reversed numeric meaning. | Must | Visual/functional QA passes all critical surfaces in English and Arabic-ready RTL. |
| LOC-003 | Names, addresses, notes, and search shall accept and correctly render Arabic and Latin Unicode text. | Must | Create/search/display fixtures in Arabic, English, and mixed text round-trip correctly. |
| LOC-004 | Dates/times shall display in approved locale format and branch time zone while APIs/storage use unambiguous values. | Must | UTC/branch boundary fixtures render and filter correctly in each supported locale. |
| LOC-005 | Currency values shall display the configured ISO currency and locale-appropriate formatting without changing the stored amount or calculation. | Must | Locale switch changes presentation only; numeric reconciliation remains identical. |
| LOC-006 | Tax labels, receipt wording, phone normalization, and required legal text shall be configurable by launch market according to OQ-06/OQ-08. | Must | Approved market fixture renders the required fields/text and phone matching behavior. |

## 12. Error handling and concurrency

- Validation errors shall not create partial domain records.
- Unauthorized and cross-scope object requests shall not disclose sensitive object existence; response semantics will follow the API security design.
- State conflicts shall return the current safe state and a stable conflict code so the UI can refresh.
- Business commands shall use database transactions and/or durable idempotency with uniqueness constraints where required by `DATA-INT-001`.
- Provider timeout or notification failure shall be recorded and retried according to policy without rolling back an already committed core transaction.
- User-visible errors shall include a correlation/reference ID for support, while technical details remain in protected logs.

## 13. Minimum audit event catalog

| Category | Required event examples |
|---|---|
| Authentication | login succeeded/failed as policy permits, logout, reset requested/completed, session revoked |
| Tenant/branch | tenant created/status changed, branch created/config changed/status changed |
| Access | user invited/status changed, role/scope assigned/revoked, privileged support access, sensitive denial |
| Family | guardian/child created, consent changed, safety note changed, guardian link activated/deactivated |
| Session | checked in, paused, resumed, extended, cancelled, quote accepted, checkout verified/overridden, completed, adjusted |
| Ticket | issued, validation/scan result, consumed, cancelled, reprinted, expired when materialized |
| Finance | order posted/voided, discount applied/approved, payment posted, receipt issued/retrieved/resent, refund posted |
| Safety | checkout verification/override; incident events only if OQ-20 approves the incident module |
| Notification | request queued, attempt sent/failed, callback accepted/rejected, delivered when confirmed |

## 14. Verification strategy and traceability

Requirement verification uses:

- **Automated unit tests:** pure time/pricing calculations, state guards, phone/money/time normalization.
- **Integration tests:** database constraints, tenant scoping, permission policies, transaction/idempotency, queue/provider adapters.
- **API/feature tests:** end-to-end request/response behavior and report reconciliation.
- **UI tests:** critical role journeys, validation, repeat submission, RTL, keyboard/accessibility.
- **Performance/resilience tests:** approved load profile, concurrency, provider/database failure, restore.
- **Security tests:** tenant/branch ID tampering, privilege escalation, injection/upload, secret scanning, session controls.
- **UAT:** real pilot staff execute the linked user stories and use cases using approved fixtures and devices.

Every Source-Must and approved derived-Must requirement shall map to at least one test case before Gate G3. Conditional/Open-Decision requirements do not become release gates until the decision is recorded; if approved, they receive the same traceability and evidence.

## 15. Open specification items

The BRD decision log OQ-01 through OQ-24 is authoritative. OQ-15 through OQ-22 originated as SRS clarification and are repeated below for requirement-level impact; repetition is not approval.

| ID | Clarification needed | Affected requirements |
|---|---|---|
| OQ-15 | Store child date of birth, declared age, year/month only, or a jurisdiction-dependent combination? Define age-band calculation and correction policy. | FR-CUS-004, FR-RPT-002, DATA-PII-001 |
| OQ-16 | **Resolved for the read-only estimate:** fixed-duration snapshot; 600-second included grace; first later second rounds up to one 1,800-second overtime unit; integer minor-unit half-up tax using the snapshotted inclusive/exclusive mode. Checkout/discount/extension/payment fixtures remain later gates. | FR-TIM-001 through FR-TIM-009 |
| OQ-17 | **Resolved:** tenant-unique normalized phone; use existing family on match; no create-anyway or automated merge in MVP. | FR-CUS-002 |
| OQ-18 | **Resolved for Egypt MVP:** ticket is branch-specific and branch-local-service-date-bound; audited holder correction is allowed only before the first successful scan, after which assignment is immutable; refund eligibility ends at the first successful scan/use and requires in-scope manager/owner approval, reason, audit, and linked reversal when paid. OQ-09 retains refund window/method/execution. | FR-TKT-001, FR-TKT-003, FR-TKT-006 |
| OQ-19 | Define whether checkout and payment are one station/role or reception and cashier handoff; define unpaid exception policy. | FR-SES-007 through FR-SES-010, FR-POS-006 |
| OQ-20 | **Deferred:** no incident-management module in M2; restricted encrypted child safety notes remain in scope. | FR-SAF-003 through FR-SAF-006 |
| OQ-21 | Define normal and peak load, standard report range, API pagination/date caps, and data-volume horizon. | NFR-PERF-001 through NFR-PERF-003, INT-API-004 |
| OQ-22 | Define session inactivity/absolute timeout, credential policy, authorization cache/revocation interval, audit/log retention, and rate limits. | FR-AUT, SEC-AUT, SEC-RATE, DATA-AUD |
| OQ-23 | Confirm branded SaaS only for MVP or approve any white-label capability. | Scope only; no white-label MVP requirements exist unless an approved change is issued. |
| OQ-24 | Confirm whether basic POS includes minimal cashier shift open/close/daily close. | No current detailed SRS requirement; if approved, add FR-POS requirements and linked finance/report/permission behavior. |

## 16. SRS acceptance checklist

- [ ] Product Owner approves all Source-Must and proposed derived-Must behavior and explicit exclusions; no Conditional item is assumed approved.
- [ ] Operations/Safety approves session states, guardian verification, overrides, and either approves or defers the Conditional incident baseline under OQ-20.
- [ ] Finance approves pricing examples, discounts, tax, payment, refund, posting-date, and receipt rules.
- [ ] Security/Privacy approves tenant isolation, access, audit, child data, consent, and retention requirements.
- [ ] Architecture approves capacity, availability, recovery, integration, and concurrency requirements.
- [ ] QA confirms every Must requirement is testable and linked to the test strategy.
- [ ] All Gate G1 questions are decided or formally deferred with accepted impact.

## Approved MVP decision amendment — 2026-09-10

- OQ-08: receipts use a unique branch-scoped display number in the form `BRANCH-YYYY-000001`; numbers are never reused, and voiding preserves the record and reason. Receipt content includes seller/branch, timestamp, receipt number, service, quantity, prices, discount, tax, total, payment method, actor, and verification QR.
- OQ-12: checkout verification uses the session/ticket QR plus confirmation of the registered guardian phone last four digits or a handoff code; failed verification blocks checkout. Manager override is reason-required, permission-checked, single-use, and audited.
- OQ-16: fixed-duration packages are the MVP pricing model; 10-minute grace; overtime rounds up in 30-minute units; pause is deferred; extension is a package or 30-minute unit; money is integer piastres; tax is branch-configurable; checkout snapshots inputs and calculation lines.

## Approved Egypt M2 decision amendment — 2026-09-12

- OQ-07/M2 consent: an Arabic-first privacy notice is distinct from consent. Child-data processing requires explicit written/electronic consent by an active legal guardian for every child under 18; choices are unbundled and never preselected. Every grant/withdrawal is append-only, versioned, attributable, UTC-timestamped, and correlated to tenant/branch/request. Marketing consent is optional, separate, and immediately withdrawable.
- Retention: operational family data remains while active and for three years after the last visit or closure, then is anonymized/deleted unless a documented legal/financial/safety/complaint/litigation hold applies. The production notice must state the actual period/criteria, controller/DPO contacts, rights, recipients/processors, transfers, and complaint route.
- OQ-17: normalized phone is unique per tenant. A match reuses the existing family; no duplicate override or automated merge exists in MVP. Cross-tenant matches are never disclosed.
- Emergency/safety: an active child has an emergency-contact name and normalized phone; optional safety notes are capped, encrypted, need-to-know, and excluded from ordinary logs/exports. Photos are deferred.
- Relationships: `mother`, `father`, and `legal_guardian` may consent; `authorized_pickup` and `other` may not. Checkout capability is explicit. Link/reactivate/revoke is verified, transactional, and audited, and the final active checkout-capable legal guardian cannot be revoked.
- OQ-18/M3 tickets: every ticket is tenant/branch scoped and tied to a service date interpreted in the branch time zone. Only a successful scan locks its holder/child binding. Refund eligibility requires an unused Issued ticket, manager/owner approval, reason, and audit; OQ-09 still determines refund timing and financial execution.
- Visit history: contract is read-only, tenant-scoped, newest-first, 25 per page for Owner/Manager/Reception, with operational timing/status and masked references only. Delivery waits for M3 session records and is an accepted M2 dependency waiver.
- OQ-20: the incident module is deferred; this does not defer the M2 emergency contact or restricted safety notes.
