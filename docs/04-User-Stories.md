# PlayNexus MVP User Stories

**Document version:** 1.1  
**Status:** Draft backlog baseline  
**Source:** PlayNexus PRD v1.0, `01-PRD-Baseline.md` v1.1, `02-BRD.md` v1.1, and `03-SRS.md` v1.1  
**Release:** Phase 1 / MVP

## 1. How to use this backlog

These stories express user value and executable acceptance examples. They do not replace the complete SRS requirements, security constraints, data invariants, permission matrix, or test strategy. Each story links to the controlling SRS IDs; all linked Must requirements remain applicable even when an acceptance scenario does not restate them.

Stories are a draft delivery decomposition, not evidence of stakeholder approval. **Source-mapped** stories implement a PRD MVP requirement or business rule. **Proposed/Conditional** stories are explicitly labeled and must not enter an MVP sprint until their open decision is closed.

Priority meanings:

- **Must:** required for MVP release.
- **Should:** expected in MVP unless explicitly deferred by the Product Owner.
- **Conditional:** not ready for implementation until the referenced BRD/SRS open question is approved.

Global acceptance conditions for every story:

- The actor is authenticated unless the story explicitly concerns authentication.
- The server enforces tenant, branch, object, and permission scope; hiding a control in the UI is not sufficient.
- Successful sensitive actions create the required audit events.
- Validation/conflict failure creates no partial business state and returns a safe, actionable message with a correlation ID.
- Dates/times, money, locale, privacy, accessibility, idempotency, and performance follow the linked SRS requirements.

## 2. Epic overview

| Epic | Outcome | Stories | Source trace / status |
|---|---|---|---|
| EP-01 SaaS Onboarding | A customer and its venues can be configured safely. | US-TEN-001–004 | FR-001–002; PRD-MVP-001–002 |
| EP-02 Staff Access | Staff can authenticate and act only within approved scope. | US-AUT-001–002, US-RBAC-001–004 | FR-003; secure reset detail is Proposed PROP-SRS-001 |
| EP-03 Family Registration | Reception can quickly find or register guardians and children. | US-CUS-001–005 | FR-004; BR-001 |
| EP-04 Tickets and Check-In | Staff can issue/validate entry and start one traceable child session. | US-TKT-001–003, US-SES-001–002 | FR-005, FR-007; BR-002, BR-009 |
| EP-05 Time Operations | Staff can monitor and control sessions using consistent pricing/time rules. | US-TIM-001–002, US-SES-003–006 | FR-006; BR-003–004 |
| EP-06 Safe Checkout | Charges and guardian verification are complete before release. | US-SES-007–010 | FR-005–006; BR-005 |
| EP-07 POS and Receipts | Sales, payments, receipts, discounts, and refunds reconcile. | US-POS-001–006 | FR-008–009; BR-006–007; refund detail OQ-09 |
| EP-08 Operational Insight | Owners and managers can reconcile core activity. | US-RPT-001–004 | FR-010; PRD-MVP-010 |
| EP-09 Notifications | Parents receive operational alerts/receipts without blocking venue work. | US-NOT-001–003 | PRD-MVP-011; marketing remains Deferred under BR-010 |
| EP-10 Safety and Accountability | Checkout safety and sensitive actions are traceable. Incident recording is Conditional. | US-SAF-001–003, US-AUD-001 | BR-005, Security/Safety NFR; US-SAF-001–002 Conditional on OQ-20 |

Source FR-011 and FR-012 are explicitly future requirements; no MVP membership, loyalty, parent-app, or marketplace story is authorized. No cashier-shift story is approved until OQ-24 resolves whether the POS module's daily shift close belongs in basic MVP POS.

### 2.1 Source business-rule coverage

| Source rule | Primary stories |
|---|---|
| BR-001 | US-CUS-003 |
| BR-002 | US-SES-001 |
| BR-003 | US-SES-004, US-SES-006, US-SES-010 |
| BR-004 | US-RBAC-003, US-SES-004–006, US-POS-005 |
| BR-005 | US-SES-008–010 |
| BR-006 | US-POS-002 |
| BR-007 | US-POS-005, US-RPT-001, US-RPT-004 |
| BR-008 | Global acceptance condition; US-RBAC-004 |
| BR-009 | US-TEN-004, US-SES-001 |
| BR-010 | US-NOT-001–003 cover operational messages only; marketing remains Deferred and consent-aware. |

## 3. EP-01 — SaaS onboarding

### US-TEN-001 — Provision a tenant

**As a** Super Admin, **I want** to provision and control a tenant with an initial owner **so that** a new business can be onboarded into an isolated workspace.  
**Priority:** Must  
**Requirement links:** FR-TEN-001, FR-TEN-002, FR-AUT-002, SEC-TEN-001, DATA-TEN-001, FR-AUD-001

**Acceptance criteria**

1. **Given** valid business and owner invitation details and a new idempotency key, **when** I create the tenant, **then** one Pending/Active-as-approved tenant and one initial owner invitation are created with stable identifiers and an audit event.
2. **Given** the same idempotency key and payload was already accepted, **when** I retry the request, **then** the original result is returned and no duplicate tenant or invitation exists.
3. **Given** I suspend an active tenant with a reason, **when** its staff next access the service, **then** access is denied/revoked while historical records remain intact.
4. **Given** any tenant-owned record, **when** a different tenant attempts to access it by changing an identifier, **then** no record contents or existence-sensitive details are disclosed.

### US-TEN-002 — Maintain the business profile

**As a** Tenant Owner, **I want** to maintain my business profile **so that** branch operations and receipts use approved business information.  
**Priority:** Must  
**Requirement links:** FR-TEN-003, FR-RBAC-003, LOC-003, SEC-PII-001

**Acceptance criteria**

1. **Given** I am an active Tenant Owner, **when** I save valid permitted profile fields, **then** the values persist only in my tenant and sensitive changes are audited.
2. **Given** a required or formatted value is invalid, **when** I save, **then** the relevant fields are identified and no invalid change is committed.
3. **Given** I alter a tenant identifier in the request, **when** the server processes it, **then** the update is rejected and no other tenant changes.

### US-TEN-003 — Configure a branch

**As a** Tenant Owner, **I want** to configure each branch's operating context **so that** transactions and sessions follow venue policy.  
**Priority:** Must  
**Requirement links:** FR-TEN-004, FR-TEN-005, FR-TEN-007, FR-TEN-009, LOC-004, LOC-005

**Acceptance criteria**

1. **Given** valid name, location, hours, capacity, time zone, currency, tax, receipt, and payment settings, **when** I create a branch, **then** it belongs to my tenant and can be activated after minimum configuration passes.
2. **Given** invalid currency, time zone, tax, or opening-hour values, **when** I save, **then** field-level errors identify the problem and the invalid configuration is not activated.
3. **Given** the branch has posted financial records, **when** I attempt to replace its currency directly, **then** the change is rejected and historical values remain unchanged.
4. **Given** events stored in UTC, **when** staff view the branch day, **then** dates and times use the configured branch time zone.

### US-TEN-004 — Deactivate or reactivate a branch

**As a** Tenant Owner, **I want** to control branch status **so that** closed venues cannot create new operational records.  
**Priority:** Must  
**Requirement links:** FR-TEN-006, FR-TEN-008, BR-009, FR-AUD-001

**Acceptance criteria**

1. **Given** an active branch and a valid reason, **when** I deactivate it, **then** new sessions and POS orders are denied and the status change is audited.
2. **Given** a branch is inactive, **when** an authorized user views historical sessions or reports, **then** retained history remains available within their scope.
3. **Given** an inactive branch has valid configuration, **when** I reactivate it, **then** authorized staff may start new work and the action is audited.

## 4. EP-02 — Staff access

### US-AUT-001 — Sign in and sign out securely

**As a** staff member, **I want** to sign in and end my session **so that** I can use PlayNexus without leaving access open to others.  
**Priority:** Must  
**Requirement links:** FR-AUT-001, FR-AUT-002, FR-AUT-003, SEC-AUT-001, SEC-AUT-002

**Acceptance criteria**

1. **Given** valid credentials for an active user and tenant, **when** I sign in, **then** I reach only an authorized landing area.
2. **Given** invalid credentials or a suspended user/tenant, **when** sign-in is attempted, **then** a generic failure appears and no authenticated session is created.
3. **Given** I am signed in, **when** I sign out, **then** the prior session cannot be replayed.

### US-AUT-002 — Recover a staff password

**As a** staff member, **I want** to reset a forgotten password **so that** I can regain access safely.  
**Priority:** Must  
**Requirement links:** FR-AUT-004, FR-AUT-005, SEC-RATE-001

**Acceptance criteria**

1. **Given** a known or unknown login identifier, **when** I request a reset, **then** the visible response does not disclose whether the account exists.
2. **Given** a valid, unused, unexpired reset token, **when** I choose an acceptable password, **then** the password changes, the token becomes unusable, and existing sessions are revoked as configured.
3. **Given** an expired, used, or tampered token, **when** I submit it, **then** reset fails without changing credentials.

### US-RBAC-001 — Invite and assign staff

**As a** Tenant Owner or permitted Branch Manager, **I want** to invite staff and assign role/branch scope **so that** each person has the access needed for their job.  
**Priority:** Must  
**Requirement links:** FR-RBAC-001, FR-RBAC-003, FR-RBAC-004, FR-TEN-008

**Acceptance criteria**

1. **Given** a valid staff identity, approved role, and branches within my management scope, **when** I invite the user, **then** one tenant-owned invitation/assignment is created and audited.
2. **Given** an unassigned or other-tenant branch, **when** I attempt an assignment, **then** the server rejects it without disclosing other-tenant details.
3. **Given** the invitation is accepted, **when** the user signs in, **then** they see only screens, branches, and actions allowed by effective permissions.

### US-RBAC-002 — Change or suspend staff access

**As a** permitted manager, **I want** to change assignments or suspend a staff member **so that** access follows current employment and duties.  
**Priority:** Must  
**Requirement links:** FR-RBAC-002, FR-RBAC-006, FR-AUT-005

**Acceptance criteria**

1. **Given** I may manage the target user, **when** I change their role/branch assignment, **then** new authorization takes effect within the approved interval and before/after scope is audited.
2. **Given** I suspend the user, **when** they make a subsequent request, **then** active access is revoked/denied.
3. **Given** the user is suspended, **when** historical activity is viewed, **then** their stable attribution remains visible.

### US-RBAC-003 — Request and record manager approval

**As a** frontline staff member, **I want** to obtain manager approval for a controlled action **so that** exceptional work remains authorized and traceable.  
**Priority:** Must  
**Requirement links:** FR-RBAC-005, FR-TIM-007, FR-POS-005, FR-SAF-002, FR-AUD-002

**Acceptance criteria**

1. **Given** an action crosses a configured threshold or requires override, **when** I try to finalize it, **then** the system blocks finalization and identifies that approval and reason are required.
2. **Given** an authorized approver confirms the exact action, **when** approval succeeds, **then** requester, approver, reason, subject, old/new values, and time are retained.
3. **Given** the policy forbids self-approval or the approver lacks scope, **when** approval is attempted, **then** it is rejected and the protected action remains uncommitted.

### US-RBAC-004 — Protect every action by scope

**As a** Tenant Owner, **I want** authorization applied consistently **so that** a user cannot bypass their role or branch through a URL or API request.  
**Priority:** Must  
**Requirement links:** FR-RBAC-003, SEC-RBAC-001, SEC-TEN-001, DATA-ID-001

**Acceptance criteria**

1. **Given** a user lacks an action permission, **when** they call the UI-backed or direct API operation, **then** both are denied.
2. **Given** a user has the action but not the target branch/object scope, **when** they substitute an identifier, **then** the request is denied without returning protected data.
3. **Given** a scheduled/background action, **when** it runs, **then** it has an explicit tenant context and cannot process another tenant accidentally.

## 5. EP-03 — Family registration

### US-CUS-001 — Find an existing family

**As a** Reception Staff member, **I want** to search by guardian phone or child name **so that** I can reuse the correct family record quickly.  
**Priority:** Must  
**Requirement links:** FR-CUS-002, FR-CUS-003, SEC-RATE-001, LOC-003

**Acceptance criteria**

1. **Given** an existing guardian phone in alternate supported formatting, **when** I search, **then** normalized matching returns the current-tenant family.
2. **Given** part of a child's Arabic or English name, **when** I search, **then** matching current-tenant children are returned with sufficient safe context to select the family.
3. **Given** a match exists only in another tenant, **when** I search, **then** it is neither returned nor disclosed.

### US-CUS-002 — Register a guardian without uncontrolled duplication

**As a** Reception Staff member, **I want** to register a guardian with duplicate warning **so that** contact and consent data remain usable.  
**Priority:** Must  
**Requirement links:** FR-CUS-001, FR-CUS-002, FR-CUS-006, SEC-PII-002, OQ-17

**Acceptance criteria**

1. **Given** no normalized-phone match, **when** I enter valid required data and consent response, **then** one guardian is created in my tenant and the consent version/evidence is recorded.
2. **Given** a normalized-phone match, **when** I attempt creation, **then** the system presents the approved duplicate policy before any new record is created.
3. **Given** invalid required contact data, **when** I submit, **then** field errors appear and no partial guardian exists.

### US-CUS-003 — Add a child and guardian relationship

**As a** Reception Staff member, **I want** to add a child to one or more guardians **so that** check-in and release use an explicit relationship.  
**Priority:** Must  
**Requirement links:** FR-CUS-004, FR-CUS-005, FR-CUS-007, DATA-REL-001, OQ-15

**Acceptance criteria**

1. **Given** a valid guardian in my tenant, **when** I enter the approved child age/date fields, name, relationship, and optional notes, **then** the child and active guardian link are created together.
2. **Given** no valid guardian link, **when** I attempt child creation, **then** it is rejected and no orphan child is created.
3. **Given** a child has two guardians, **when** an authorized user deactivates one link, **then** the other remains and history is retained.
4. **Given** only one active link remains, **when** deactivation is attempted, **then** it is rejected until another valid guardian is linked.

### US-CUS-004 — Maintain consent and safety details

**As a** Reception Staff member, **I want** to update contact, consent, emergency, and child safety notes **so that** current information is available during the visit.  
**Priority:** Must  
**Requirement links:** FR-CUS-006, SEC-PII-001, SEC-PII-002, FR-AUD-001

**Acceptance criteria**

1. **Given** I have permission, **when** I update contact or safety fields, **then** valid values persist and sensitive changes record actor/time and safe before/after evidence.
2. **Given** consent changes, **when** I save, **then** prior consent evidence is retained and the new notice version/response/time/actor is recorded.
3. **Given** a user lacks sensitive-field permission, **when** they view or update the family, **then** restricted fields are masked/absent and writes are denied server-side.

### US-CUS-005 — Review a child's visit history

**As an** authorized Reception Staff member or Manager, **I want** to see a child's prior sessions **so that** I can assist the family and investigate questions.  
**Priority:** Must  
**Requirement links:** FR-CUS-008, FR-SES-012, SEC-PII-001

**Acceptance criteria**

1. **Given** the selected child is in my tenant and scope, **when** I open visit history, **then** sessions are shown in deterministic date order with permitted status/timing details.
2. **Given** another tenant's child identifier, **when** I request history, **then** no protected record is returned or inferred.
3. **Given** many visits, **when** I page/filter history, **then** no visit is duplicated or skipped under stable data.

## 6. EP-04 — Tickets and check-in

### US-TKT-001 — Issue and print a QR ticket

**As a** Reception Staff member or Cashier, **I want** to issue a QR ticket **so that** entry entitlement can be identified and validated.  
**Priority:** Must  
**Requirement links:** FR-TKT-001, FR-TKT-002, DATA-ID-001, INT-HW-001

**Acceptance criteria**

1. **Given** an active branch and valid ticket type/rule, **when** I issue the ticket, **then** one Issued ticket with non-guessable QR, validity, price/rule, and tenant/branch scope is created.
2. **Given** the QR is displayed or printed on an approved pilot device, **when** it is scanned, **then** the validation interface resolves the correct ticket without child-sensitive data in the payload.
3. **Given** the same issue command is retried, **when** idempotency matches, **then** no second ticket is created.

### US-TKT-002 — Validate and consume a ticket

**As a** Reception Staff member, **I want** to scan a ticket at check-in **so that** only valid, unused entitlement starts a session.  
**Priority:** Must  
**Requirement links:** FR-TKT-003, FR-TKT-004, FR-TKT-005, FR-TKT-008, FR-SES-002

**Acceptance criteria**

1. **Given** a currently valid Issued ticket for the allowed scope, **when** I scan it, **then** a valid result appears and the scan attempt is logged.
2. **Given** an expired, cancelled, consumed, unknown, or disallowed-scope ticket, **when** scanned, **then** check-in is blocked with a staff-safe reason and the attempt is logged without cross-tenant disclosure.
3. **Given** two concurrent check-in attempts use one ticket, **when** processed, **then** at most one session and one consumption event are committed.

### US-TKT-003 — Cancel or reprint an unused ticket

**As an** authorized staff member, **I want** to cancel or reprint a ticket **so that** operational exceptions are controlled without losing history.  
**Priority:** Must  
**Requirement links:** FR-TKT-006, FR-TKT-007, FR-AUD-001

**Acceptance criteria**

1. **Given** an unused Issued ticket and a valid reason, **when** I cancel it, **then** its state becomes Cancelled, it cannot authorize check-in, and the action is audited.
2. **Given** an authorized reprint, **when** I reprint, **then** identifier, QR, validity, price, and state remain unchanged and a reprint event is audited.
3. **Given** a consumed/cancelled/expired ticket, **when** an invalid transition is attempted, **then** it is rejected without changing history.

### US-SES-001 — Check a child in

**As a** Reception Staff member, **I want** to start a child session **so that** attendance, timing, ticket use, and billing begin reliably.  
**Priority:** Must  
**Requirement links:** FR-SES-001, FR-SES-002, FR-SES-014, FR-TKT-005, BR-002, BR-011

**Acceptance criteria**

1. **Given** an active branch, eligible child, staff actor, and valid ticket or active pricing rule, **when** I confirm check-in, **then** one Active session is atomically created with the server start time and required references.
2. **Given** the child already has an Active/Paused tenant session or any required input is invalid, **when** I confirm, **then** check-in fails and no ticket or partial session is consumed/created.
3. **Given** the submission is repeated or concurrent, **when** the server processes it, **then** one accepted session exists and the UI resolves to that current state.

### US-SES-002 — Find an active session quickly

**As a** Reception Staff member, **I want** to find sessions by family or ticket information **so that** I can serve the right child without delay.  
**Priority:** Must  
**Requirement links:** FR-SES-012, FR-SES-013, NFR-PERF-001

**Acceptance criteria**

1. **Given** a scoped active session, **when** I search by ticket/QR, guardian phone, or child name, **then** the matching session appears with status and safe identifying context.
2. **Given** branch/date/state filters, **when** I apply them, **then** only permitted matching sessions are returned in deterministic pages.
3. **Given** a record outside my scope, **when** I search or change identifiers, **then** it is not disclosed.

## 7. EP-05 — Time operations

### US-TIM-001 — Configure a pricing rule

**As a** Branch Manager, **I want** to configure approved time and pricing behavior **so that** session charges follow venue policy.  
**Priority:** Must  
**Requirement links:** FR-TIM-001, FR-TIM-002, FR-TIM-009, DATA-VER-001, OQ-16

**Acceptance criteria**

1. **Given** an approved pricing example and valid configuration, **when** I save/activate the rule, **then** it becomes selectable in its effective scope and the action is audited.
2. **Given** incomplete or internally inconsistent duration/rate/rounding/pause/tax settings, **when** I save, **then** activation is rejected with specific validation.
3. **Given** sessions already reference a rule, **when** I change it, **then** a new effective version/snapshot applies only as approved and old session calculations remain reproducible.

### US-TIM-002 — Trust the calculated charge

**As a** Branch Manager, **I want** an explainable deterministic calculation **so that** staff and parents can understand the bill.  
**Priority:** Must  
**Requirement links:** FR-TIM-003, FR-TIM-004, FR-TIM-005, FR-TIM-006, DATA-MNY-001

**Acceptance criteria**

1. **Given** an approved fixture containing start/end, pauses, extension, rounding, price, and tax, **when** the engine calculates it, **then** every component and final amount matches the approved example exactly within currency precision.
2. **Given** the same stored inputs/rule version in English and Arabic-ready locales, **when** recalculated, **then** numeric results and component codes are identical.
3. **Given** configuration changes after completion, **when** I reopen the completed session, **then** its stored calculation breakdown remains unchanged and reproducible.

### US-SES-003 — Monitor active timing and alerts

**As a** Reception Staff member, **I want** to see live session timing and alert state **so that** I can manage endings proactively.  
**Priority:** Must  
**Requirement links:** FR-SES-013, FR-TIM-008, FR-NOT-001

**Acceptance criteria**

1. **Given** an Active/Paused session, **when** I view the active board, **then** current state, elapsed/billable time, expected ending/alert information, and estimated charge reflect authoritative data.
2. **Given** a pause or extension changes the due time, **when** the command succeeds, **then** the current alert schedule is recalculated and stale schedules cannot send duplicates.
3. **Given** a session completes or cancels before alert due time, **when** the scheduler runs, **then** no ending alert is sent for that stale schedule.

### US-SES-004 — Pause and resume a session

**As an** authorized staff member, **I want** to pause/resume using an approved reason/type **so that** billable time follows policy.  
**Priority:** Must  
**Requirement links:** FR-SES-003, FR-SES-004, FR-TIM-003, BR-012, BR-014

**Acceptance criteria**

1. **Given** an Active session and permitted pause type, **when** I pause with required reason, **then** it becomes Paused with one open server-timed interval and an audit event.
2. **Given** a Paused session, **when** I resume, **then** the interval closes, state becomes Active, and its billable treatment follows the snapshotted rule.
3. **Given** an invalid state, missing permission, or duplicate concurrent command, **when** pause/resume is attempted, **then** only the valid transition can commit.

### US-SES-005 — Extend or adjust a session

**As an** authorized staff member, **I want** to extend a session and request controlled adjustment **so that** changes are priced and traceable.  
**Priority:** Must  
**Requirement links:** FR-SES-005, FR-TIM-007, FR-RBAC-005

**Acceptance criteria**

1. **Given** an Active/Paused session and allowed extension, **when** I select it, **then** incremental time/price is previewed and committed once after confirmation.
2. **Given** a manual time/price change, **when** I lack permission, reason, or required approval, **then** it cannot be finalized.
3. **Given** an approved adjustment, **when** saved, **then** original values remain available and new values, requester, approver, reason, and effect appear in calculation/audit.

### US-SES-006 — Cancel a session

**As an** authorized staff member, **I want** to cancel an exceptional session **so that** it no longer continues timing while history is preserved.  
**Priority:** Must  
**Requirement links:** FR-SES-006, FR-SES-011, FR-TIM-008

**Acceptance criteria**

1. **Given** an Active/Paused session and required permission/reason/approval, **when** I cancel, **then** any open pause closes safely, state becomes Cancelled, alerts are invalidated, and history is retained.
2. **Given** missing authorization or reason, **when** I attempt cancellation, **then** state is unchanged.
3. **Given** a Cancelled/Completed session, **when** another operational transition is attempted, **then** the terminal state rejects it.

## 8. EP-06 — Safe checkout

### US-SES-007 — Review a checkout quote

**As a** Reception Staff member or Cashier, **I want** to review a transparent charge breakdown **so that** I can explain and collect the correct amount.  
**Priority:** Must  
**Requirement links:** FR-SES-007, FR-TIM-003, FR-TIM-004, FR-TIM-006

**Acceptance criteria**

1. **Given** an Active/Paused session, **when** I request checkout, **then** elapsed, included/excluded pauses, extension, rounding, base/overage, adjustments, tax/discount where relevant, payments, and amount due are displayed.
2. **Given** an approved worked example, **when** the quote is produced, **then** its components and total match exactly within currency precision.
3. **Given** the underlying session changes before final confirmation, **when** I submit a stale quote, **then** the server reports a conflict and requires refresh rather than completing with stale totals.

### US-SES-008 — Verify the collecting guardian

**As a** Reception Staff member, **I want** to verify an active guardian at checkout **so that** the child leaves with an authorized person.  
**Priority:** Must  
**Requirement links:** FR-SES-008, FR-SAF-001, BR-005, OQ-12

**Acceptance criteria**

1. **Given** active guardians linked to the child, **when** checkout begins, **then** only those current-tenant links appear with the approved minimum verification context.
2. **Given** a linked guardian passes an approved verification method, **when** I confirm it, **then** guardian, method, verifier, and server time are retained.
3. **Given** no verification or override, **when** I try to complete checkout, **then** release/completion is blocked.

### US-SES-009 — Override guardian verification exceptionally

**As a** Branch Manager, **I want** to approve an exceptional checkout **so that** an operational exception is handled without hiding safety risk.  
**Priority:** Must  
**Requirement links:** FR-SAF-002, FR-SES-008, FR-RBAC-005, FR-AUD-001

**Acceptance criteria**

1. **Given** normal verification cannot be completed, **when** an unauthorized user or user without reason tries to override, **then** checkout remains blocked.
2. **Given** an authorized manager approves the specific checkout with a non-empty reason, **when** it is committed, **then** requester, approver, reason, child/session, and time are retained.
3. **Given** the session later appears in history/reporting, **when** viewed by authorized users, **then** the override is clearly distinguishable and searchable.

### US-SES-010 — Complete checkout once

**As a** Reception Staff member, **I want** to finalize an eligible session exactly once **so that** time, charge, payment, verification, and history agree.  
**Priority:** Must  
**Requirement links:** FR-SES-009, FR-SES-010, FR-SES-011, FR-SES-014, DATA-INT-001

**Acceptance criteria**

1. **Given** the quote is current, required amount is settled, and guardian verification/override exists, **when** I confirm, **then** end time, immutable calculation, verification, and Completed state commit atomically.
2. **Given** money remains due with no approved exception, **when** I confirm, **then** completion is rejected and the session remains operationally valid.
3. **Given** confirmation is retried or submitted concurrently, **when** processed, **then** one completion/financial result exists and the existing result is returned or a safe conflict shown.

## 9. EP-07 — POS and receipts

### US-POS-001 — Maintain and sell catalog items

**As a** Cashier, **I want** one catalog/cart for tickets, F&B, merchandise, add-ons, and extensions **so that** venue sales are recorded consistently.  
**Priority:** Must  
**Requirement links:** FR-POS-001, FR-POS-002, FR-POS-003, FR-POS-004

**Acceptance criteria**

1. **Given** active products assigned to my branch, **when** I add permitted quantities, **then** server-held price/tax values calculate the line and order breakdown.
2. **Given** an inactive/out-of-scope item or tampered client price, **when** I submit the order, **then** it is rejected or recalculated safely and cannot post the tampered value.
3. **Given** the order relates to a family/session, **when** I link it, **then** it appears in authorized history; a permitted ordinary retail order may remain unlinked.

### US-POS-002 — Apply a controlled discount

**As a** Cashier, **I want** to apply discounts within policy and seek approval above threshold **so that** promotions do not create uncontrolled leakage.  
**Priority:** Must  
**Requirement links:** FR-POS-005, FR-RBAC-005, BR-006

**Acceptance criteria**

1. **Given** a discount within my permitted threshold, **when** I apply it with any required reason, **then** the revised total is shown and the discount is attributed.
2. **Given** a discount above threshold, **when** no valid approval exists, **then** payment posting is blocked.
3. **Given** an authorized approver confirms it, **when** the sale posts, **then** requester, approver, reason, threshold context, and amount are audited.

### US-POS-003 — Record a payment atomically

**As a** Cashier, **I want** to record a permitted payment **so that** the order and any session balance are settled exactly once.  
**Priority:** Must  
**Requirement links:** FR-POS-006, FR-POS-007, INT-PAY-001, DATA-FIN-001, DATA-INT-001

**Acceptance criteria**

1. **Given** an eligible Draft order, permitted method, correct currency/amount, and unique idempotency key, **when** I confirm payment, **then** one immutable payment posts, the order becomes Paid, and receipt issuance is requested atomically.
2. **Given** an invalid method/currency/amount or server-side total changed, **when** I submit, **then** posting is rejected and no partial payment/order state exists.
3. **Given** the same payment submission is retried or concurrent, **when** processed, **then** no duplicate payment or receipt number is created.
4. **Given** baseline MVP, **when** I use the payment interface, **then** it records only non-sensitive method/reference data and never asks for raw card details.

### US-POS-004 — Issue and resend a digital receipt

**As a** Cashier, **I want** to show, print, or resend the original receipt **so that** the parent has consistent proof of payment.  
**Priority:** Must  
**Requirement links:** FR-POS-008, FR-POS-009, DATA-NUM-001, LOC-006

**Acceptance criteria**

1. **Given** a posted payment, **when** receipt issuance completes, **then** one concurrency-safe number and the approved seller/branch, date, cashier, lines, subtotal, discount, tax, total, method, and status are stored/rendered.
2. **Given** I retrieve, reprint, or resend it, **when** the action completes, **then** the same number and commercial facts appear and no payment or second receipt is created.
3. **Given** another branch/tenant receipt identifier outside my scope, **when** requested, **then** protected content is not disclosed.

### US-POS-005 — Record an approved refund

**As an** authorized Manager, **I want** to record an eligible refund with a reason **so that** corrections remain controlled and reports reconcile.  
**Priority:** Must capability; refund type/window/method are Open Decision OQ-09  
**Requirement links:** FR-POS-010, FR-POS-011, FR-POS-012, BR-007, OQ-09

**Acceptance criteria**

1. **Given** an eligible posted payment, an approved OQ-09 policy fixture, and valid approval/reason, **when** I confirm the policy-valid refund, **then** one linked immutable refund record is created and the original payment remains intact. Under planning assumption ASM-10 this fixture is full-only.
2. **Given** the request exceeds refundable balance, repeats an already refunded payment, or lacks authority/reason, **when** submitted, **then** it fails without changing financial state.
3. **Given** the refund is posted, **when** order/receipt/report is viewed, **then** status and negative/reversal financial effect reconcile under the approved posting-date policy.

### US-POS-006 — Keep advanced payment scope out of MVP

**As a** Product Owner, **I want** the POS limited to approved basics **so that** delivery is not delayed by hidden payment complexity.  
**Priority:** Scope guard; payment/refund detail remains Open Decision  
**Requirement links:** FR-POS-013, INT-PAY-001, INT-PAY-002, OQ-01, OQ-09, ASM-08, ASM-10

**Acceptance criteria**

1. **Given** working assumptions ASM-02, ASM-08, and ASM-10 remain in force, **when** users or API clients inspect POS, **then** split tender, partial payment/refund, gateway capture, and inventory depletion are not presented or accepted.
2. **Given** online gateway capture is approved, **when** implementation is planned, **then** an approved SRS/API/security change exists before release code is accepted.

## 10. EP-08 — Operational insight

### US-RPT-001 — Reconcile revenue

**As a** Tenant Owner or authorized Manager, **I want** a filtered revenue report **so that** I can reconcile recorded venue revenue.  
**Priority:** Must  
**Requirement links:** FR-RPT-001, FR-RPT-005, FR-RPT-006, NFR-PERF-002

**Acceptance criteria**

1. **Given** a date range and permitted branches, **when** I run the report, **then** gross sales, discounts, tax, refunds, and net revenue reconcile to posted source records under displayed filters/time-zone/currency.
2. **Given** available dimensions, **when** I group/filter by branch, cashier, product/ticket type, or payment method, **then** components sum to the same controlled total.
3. **Given** an unassigned branch, **when** a scoped manager tampers with filters, **then** neither details nor aggregate inference includes it.

### US-RPT-002 — Review attendance and peak periods

**As a** Branch Manager, **I want** attendance by date/hour/age band **so that** I can understand venue usage.  
**Priority:** Must  
**Requirement links:** FR-RPT-002, FR-RPT-005, FR-RPT-006, OQ-15

**Acceptance criteria**

1. **Given** a controlled session dataset and date/branch filter, **when** I run attendance, **then** visit counts match eligible session records and hourly grouping uses the displayed branch time zone.
2. **Given** missing child age data, **when** age-band grouping is shown, **then** those visits appear as Unknown rather than disappearing.
3. **Given** only branch-scoped access, **when** I run the report, **then** only assigned branches contribute.

### US-RPT-003 — Investigate session history

**As a** Manager, **I want** a detailed session history **so that** I can resolve timing, cancellation, charge, and checkout questions.  
**Priority:** Must  
**Requirement links:** FR-RPT-003, FR-SES-012, FR-SAF-002

**Acceptance criteria**

1. **Given** authorized filters for date, branch, state, child, phone, ticket, or actor, **when** I search, **then** matching sessions show allowed timing, pause, extension, calculation, payment, verification, and override information.
2. **Given** a terminal session, **when** I view it after configuration changes, **then** its historic calculation and state remain reproducible.
3. **Given** large results, **when** I paginate, **then** ordering is deterministic and access remains scoped.

### US-RPT-004 — Review staff activity

**As a** Tenant Owner or authorized Manager, **I want** staff activity filters **so that** approvals and sensitive operations are accountable.  
**Priority:** Must  
**Requirement links:** FR-RPT-004, FR-AUD-004, DATA-AUD-001

**Acceptance criteria**

1. **Given** known auditable actions, **when** I filter by date, branch, actor, category, or subject, **then** each matching event appears once in immutable chronological context.
2. **Given** my role has limited scope/field visibility, **when** I view activity, **then** other branches and secrets/unnecessary child-sensitive fields are absent or masked.
3. **Given** a suspended former staff member, **when** I search history, **then** their stable attribution remains available.

## 11. EP-09 — Notifications

### US-NOT-001 — Notify before a session ends

**As a** Parent/Guardian, **I want** an operational ending alert **so that** I can prepare for checkout.  
**Priority:** Must  
**Requirement links:** FR-NOT-001, FR-NOT-003, FR-TIM-008, OQ-05, OQ-13

**Acceptance criteria**

1. **Given** an eligible active session reaches its configured alert due time, **when** the scheduler processes it, **then** one current notification request is queued for an approved destination/channel.
2. **Given** the session was paused, extended, completed, or cancelled and an old schedule is stale, **when** it fires, **then** it sends no duplicate/obsolete alert.
3. **Given** the provider is unavailable, **when** delivery is attempted, **then** the core session remains valid and the failure is logged/retried according to bounded policy.

### US-NOT-002 — Receive a receipt notification

**As a** Parent/Guardian, **I want** the issued receipt sent to my approved destination **so that** I retain proof of payment.  
**Priority:** Must  
**Requirement links:** FR-NOT-002, FR-NOT-003, FR-POS-009

**Acceptance criteria**

1. **Given** a receipt and approved destination/channel, **when** issuance completes, **then** one notification request references the original receipt.
2. **Given** no usable destination, **when** payment posts, **then** payment/receipt still succeed and the notification is logged as not sendable.
3. **Given** a resend, **when** processed, **then** it references the same receipt number and creates no new payment/receipt.

### US-NOT-003 — Monitor delivery attempts honestly

**As a** Branch Manager or support user with permission, **I want** to see notification outcomes **so that** I can distinguish sent, delivered, failed, and pending messages.  
**Priority:** Must  
**Requirement links:** FR-NOT-004, FR-NOT-005, INT-NOT-001, INT-NOT-003

**Acceptance criteria**

1. **Given** a provider attempt, **when** I view its log, **then** event/template, protected recipient reference, channel, times, provider reference, and current truthful state are shown within scope.
2. **Given** only provider acceptance exists, **when** no delivery confirmation was received, **then** the UI says Sent rather than Delivered.
3. **Given** retryable and permanent failures, **when** processing continues, **then** retries stop exactly at configured bounds and never duplicate the core business event.

## 12. EP-10 — Safety and accountability

### US-SAF-001 — Record a safety incident (proposed)

**As an** authorized staff member, **I want** to record a child/venue incident **so that** facts and follow-up are traceable.  
**Priority:** Conditional on OQ-20; not an approved MVP story  
**Requirement links:** FR-SAF-003, FR-SAF-004, FR-SAF-006, FR-AUD-001, OQ-20

**Acceptance criteria**

1. **Given** required branch, category, times, description, severity, and applicable child/session, **when** I submit, **then** one tenant/branch-scoped Open incident is created with reporter and audit evidence.
2. **Given** follow-up or a permitted state change, **when** an authorized user saves it, **then** a new attributed note/event is appended and original reported facts remain available.
3. **Given** the feature is displayed, **when** staff read its guidance, **then** it does not represent itself as emergency dispatch, medical advice, or regulatory case management.

### US-SAF-002 — Search incidents (proposed)

**As an** authorized Manager or safety user, **I want** to find incidents by operational dimensions **so that** I can review follow-up and patterns.  
**Priority:** Conditional on OQ-20; not an approved MVP story  
**Requirement links:** FR-SAF-005, SEC-PII-001, DATA-TEN-002

**Acceptance criteria**

1. **Given** incidents in permitted scope, **when** I filter by child, date, branch, reporter, severity, or status, **then** matching records and chronological history are returned.
2. **Given** incidents outside my tenant/branch scope, **when** identifiers or filters are tampered with, **then** no protected details or aggregate inference are returned.
3. **Given** limited field permission, **when** I open an incident, **then** unnecessary sensitive child/contact data is masked or omitted.

### US-SAF-003 — See and investigate checkout overrides

**As a** Tenant Owner or safety lead, **I want** guardian-checkout exceptions to be visible **so that** release controls can be monitored.  
**Priority:** Must  
**Requirement links:** FR-SAF-002, FR-RPT-003, FR-RPT-004, FR-SES-010

**Acceptance criteria**

1. **Given** a manager-approved override occurred, **when** I filter session/audit history, **then** the child/session, requester, approver, reason, and time are discoverable within my scope.
2. **Given** a Completed checkout has neither verification nor override, **when** integrity reporting/tests run, **then** it is flagged as a critical invariant violation; normal application flow must make this count zero.

### US-AUD-001 — Trace a critical transaction

**As an** authorized support, audit, or management user, **I want** to trace a business transaction through its events **so that** failures and disputes can be investigated.  
**Priority:** Should  
**Requirement links:** FR-AUD-001, FR-AUD-002, FR-AUD-003, FR-AUD-004, FR-AUD-005, NFR-OBS-001

**Acceptance criteria**

1. **Given** a check-in, checkout, payment, refund, or notification flow, **when** I search using its subject/correlation reference, **then** permitted business/audit events can be followed in time order.
2. **Given** an audit event, **when** an application user attempts to edit/delete it, **then** the action is denied.
3. **Given** audit/log data, **when** it is displayed or searched, **then** credentials, tokens, full payment credentials, and unnecessary child-sensitive values are absent.

## 13. Conditional stories held outside the baseline

These are decision placeholders, not sprint-ready MVP stories.

### US-INT-001 — Capture an online payment

**As a** Product Owner, **I want** an approved regional payment gateway **so that** PlayNexus can capture rather than merely record eligible payments.  
**Priority:** Conditional on OQ-01  
**Requirement links:** INT-PAY-002

**Acceptance gate:** An approved gateway, jurisdiction/security assessment, detailed payment/refund/reconciliation state model, API/webhook specification, failure/idempotency scenarios, estimates, and change control are complete.

### US-INT-002 — Operate proprietary wristband/kiosk hardware

**As an** Operations Owner, **I want** approved wristband/kiosk integration **so that** the venue can use selected hardware beyond browser QR.  
**Priority:** Conditional on OQ-02  
**Requirement links:** INT-HW-002

**Acceptance gate:** Supported hardware/SDK, ownership, security, failure fallback, device matrix, field-test plan, estimates, and change control are approved.

### US-OPS-001 — Work offline

**As a** Branch Manager, **I want** continuity during internet loss **so that** essential venue operations can continue.  
**Priority:** Conditional on OQ-03  
**Source/decision links:** ASM-04, OQ-03

**Acceptance gate:** Offline data authority, identity, numbering, timing, payment safety, synchronization/conflict, security, recovery, and operational limits are specified and approved. Until then the MVP uses a documented manual continuity procedure.

## 14. Backlog readiness and completion rules

### 14.1 Definition of Ready

A story may enter implementation only when:

- its requirement links exist and no blocking open question remains;
- designs, permission keys, states, validations, errors, and analytics/audit events are identified;
- representative Arabic/English, time, money, role, branch, and failure fixtures exist as relevant;
- API/data changes and dependencies are identified;
- acceptance criteria are testable and estimated by engineering/QA.

### 14.2 Definition of Done

A story is Done only when:

- all acceptance scenarios and linked Source-Must/approved derived-Must requirements pass at appropriate test layers; a Conditional story cannot be Done until its decision is approved;
- authorization and tenant/branch negative tests pass;
- audit, idempotency, error, accessibility, localization, and observability behavior is verified where relevant;
- code review, automated test, migration, security scan, and documentation checks pass;
- Product Owner/role-based UAT accepts the story in the integrated critical journey;
- no unresolved Critical/High defect remains without formal release-risk acceptance.
