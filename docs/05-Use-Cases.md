# PlayNexus MVP Use Cases

## 2026-09-15 UC-10/UC-11 implemented boundary

UC-10 now has a scoped bilingual desktop surface for revenue reconciliation, attendance, session facts and staff audit activity, plus Owner/Manager CSV using the identical validated filter query. UC-11 now persists and asynchronously processes eligible receipt/session-ending intents through a deterministic database transport. Missing destinations become terminal without affecting core records; duplicate jobs do not add attempts or regress state; provider delivery remains unclaimed and external channels/callbacks remain gated.

## 2026-09-13 UC-05/M4 implemented boundary

UC-04 is implemented through ticket-backed check-in and the live board: current authorized scope and family safety are revalidated, one ticket/session commits atomically and idempotently, hard branch capacity and tenant-wide duplicate-child state are enforced, and rejected attempts retain privacy-safe scan evidence without business mutation. The UC-05 board now derives exact grace/overtime/tax values from immutable facts without persistence, offers fixed 30-minute extensions, exposes due/overdue labels, and supports reasoned manager/owner additive adjustments and cancellation. Pause/resume is not part of the Egypt MVP. The first UC-06/OQ-19 preparation boundary verifies the guardian (or audited manager override), freezes the quote, and hands the session to the Cashier as `pending_payment`; payment, receipt, release, refund, notifications, and shift completion remain outside this boundary.

**Document version:** 1.1
**Status:** Draft operational-flow baseline
**Source:** PlayNexus PRD v1.0, `01-PRD-Baseline.md` v1.1, `02-BRD.md` v1.1, `03-SRS.md` v1.1, and `04-User-Stories.md` v1.1
**Release:** Phase 1 / MVP

## 1. Purpose and notation

This document specifies critical end-to-end interactions, including actor responsibilities, preconditions, success guarantees, alternative routes, failure handling, business rules, and traceability. It complements—not replaces—the atomic SRS requirements and permission matrix.

Use cases are a draft operational decomposition. A use case labeled **Proposed/Conditional** is not an approved MVP flow merely because detailed steps are present.

Terms:

- **Primary actor:** initiates the business goal.
- **Supporting actor/system:** participates in the flow.
- **Success postcondition:** must be true after success.
- **Minimum guarantee:** must remain true even when the use case fails.
- **Extension:** a valid alternative route.
- **Exception:** an error/failure route that must preserve invariants.

Global constraints for all use cases:

1. The server derives and enforces tenant, branch, object, and permission scope.
2. Client-supplied totals, timestamps, prices, tenant IDs, branch permissions, and state are never authoritative.
3. Material commands are transactional/idempotent and create the required audit evidence.
4. Cross-tenant access is never a valid alternative flow.
5. Errors expose a safe message and correlation ID, preserve the last valid business state, and do not leak secrets or out-of-scope records.

## 2. Use-case catalog

| ID | Use case | Primary actor | Outcome |
|---|---|---|---|
| UC-01 | Onboard a tenant and first branch | Super Admin, Tenant Owner | Isolated, operable customer workspace |
| UC-02 | Provision and control staff access | Tenant Owner / Branch Manager | Correct role and branch permissions |
| UC-03 | Find or register a family | Reception Staff | Reusable guardian-child record with consent/safety data |
| UC-04 | Issue/validate a ticket and check a child in | Reception Staff / Cashier | One valid ticket consumption and one Active session |
| UC-05 | Operate a timed session | Reception Staff / Branch Manager | Accurate pause, resume, extension, alert, adjustment, or cancellation |
| UC-06 | Checkout, collect payment, and issue receipt | Reception Staff / Cashier | Paid, verified, Completed session and reproducible receipt |
| UC-07 | Authorize an exceptional guardian checkout | Branch Manager | Controlled child-release override with evidence |
| UC-08 | Complete a non-session POS sale | Cashier | Posted sale, payment record, and receipt |
| UC-09 | Refund a posted payment | Branch Manager | Policy-valid linked refund without destructive edit |
| UC-10 | Run and reconcile core reports | Tenant Owner / Branch Manager | Scoped operational and financial insight |
| UC-11 | Dispatch an operational notification | Scheduler / Queue Worker | Truthful, non-blocking attempt history |
| UC-12 | Record and follow up a safety incident (**Proposed/Conditional OQ-20**) | Authorized Staff / Manager | Searchable, append-only incident history if approved |
| UC-13 | Suspend a tenant or deactivate a branch | Super Admin / Tenant Owner | New work is blocked while history is retained |
| UC-14 | Deny a cross-scope or unauthorized action | Any staff or malicious client | No protected action/data disclosure; security evidence retained as applicable |

### 2.1 Source requirement and rule coverage

| Source trace | Primary use cases | Disposition |
|---|---|---|
| FR-001, FR-002 | UC-01, UC-13, UC-14 | Included |
| FR-003 | UC-02, UC-14 | Included |
| FR-004; BR-001 | UC-03 | Included |
| FR-005; BR-002–005, BR-009 | UC-04–07, UC-13 | Included |
| FR-006 | UC-05–06 | Included; algorithm detail awaits OQ-16 |
| FR-007 | UC-04 | Included as QR-first; source permits QR or barcode |
| FR-008, FR-009; BR-006–007 | UC-06, UC-08, UC-09 | Included; payment/refund detail awaits OQ-01/OQ-09 |
| FR-010 | UC-10 | Included |
| PRD-MVP-011 | UC-11 | Included for session-ending and receipt messages only |
| BR-008 | Global constraint and UC-14 | Included |
| BR-010 | UC-11 | Marketing sending Deferred; consent/opt-out constraint retained |
| FR-011, FR-012 | None | Explicitly future/Deferred |

## 3. UC-01 — Onboard a tenant and first branch

**Goal:** Create an isolated customer workspace, initial owner access, business profile, and first operable branch.
**Primary actors:** Super Admin; Tenant Owner
**Supporting systems:** Authentication/invitation service; audit service
**Trigger:** A commercially approved customer is ready for product onboarding.
**Frequency:** Per new tenant; branch configuration repeats as the customer grows.

**Preconditions**

- Super Admin is authenticated and permitted to create tenants.
- Required commercial/legal onboarding data and minimum branch configuration are approved.
- Initial owner destination is valid for the selected invitation channel.

**Success postconditions**

- Exactly one tenant exists with an initial owner invitation/account.
- Business profile and first branch belong to that tenant.
- The branch has valid time zone, currency, tax, hours, capacity, receipt, payment, and status configuration.
- Owner can sign in and sees only the new tenant and permitted branches.
- Creation/configuration events are auditable.

**Minimum guarantee**

- Retries do not create duplicate tenants, invitations, or branches.
- Failure does not expose another tenant or leave an apparently active but invalid branch.

**Main success flow**

1. Super Admin opens tenant provisioning and enters the business name, status/plan reference, and initial owner identity.
2. The system validates required fields, uniqueness/idempotency, and Super Admin permission.
3. The system creates the tenant and initial owner invitation inside one controlled provisioning operation.
4. The system records the tenant-created and invitation-created audit events.
5. The owner accepts the invitation, establishes credentials, and signs in.
6. The owner completes permitted business profile fields.
7. The owner creates a branch with name/location, opening hours, capacity, time zone, currency, tax, receipt, and payment settings.
8. The system validates that branch configuration is internally consistent and belongs to the current tenant.
9. The owner activates the branch.
10. The system shows the active branch in the owner's branch list and allows downstream staff/catalog/pricing configuration.

**Extensions**

- **3a — Provisioning retry:** If the same idempotency key/payload is retried, the system returns the existing tenant result.
- **5a — Invitation expired:** Authorized Super Admin/owner reissues an invitation according to policy; old token remains invalid and the event is audited.
- **7a — Add more branches:** Repeat steps 7–10; every branch retains the same tenant and independent operational configuration.
- **9a — Save inactive:** Owner may retain an Inactive configured branch; it remains unavailable for new sessions/orders.

**Exceptions**

- **2e — Invalid data:** No tenant is created; field errors are returned.
- **3e — Partial provisioning failure:** The transaction/compensation leaves neither duplicate nor orphan invitation and returns a correlation ID.
- **8e — Invalid currency/time zone/tax/hours:** Branch cannot activate until corrected.
- **10e — Cross-tenant identifier injection:** Request is denied; no target details are exposed.

**Rules:** BR-008, BR-009, BR-017, BR-018.
**Requirement links:** FR-TEN-001–009, FR-AUT-001–005, DATA-TEN-001–002, SEC-TEN-001, LOC-004–006.
**Story links:** US-TEN-001, US-TEN-002, US-TEN-003, US-AUT-001.

## 4. UC-02 — Provision and control staff access

**Goal:** Give each staff member the minimum approved role and branch scope, and revoke it when no longer required.
**Primary actor:** Tenant Owner; permitted Branch Manager
**Supporting actor:** Invited staff member
**Trigger:** A staff member joins, changes duties/branch, or leaves.
**Frequency:** As required per tenant.

**Preconditions**

- Managing actor is authenticated and has staff-management permission in the target scope.
- Target role and branches exist in the same tenant.
- The approved permission matrix defines role capabilities and approval rules.

**Success postconditions**

- User identity belongs to the tenant and has the intended role/branch assignments.
- Effective authorization changes within the approved interval.
- Assignment/status changes retain actor, time, and before/after scope.

**Minimum guarantee**

- No cross-tenant or unmanaged-branch assignment is created.
- Historical attribution survives suspension or assignment changes.

**Main success flow**

1. Manager searches for or enters the staff identity.
2. Manager selects an approved role and one or more branches within their manageable scope.
3. The system displays the resulting effective scope and sensitive permissions.
4. Manager confirms.
5. The system validates identity uniqueness, tenant ownership, manager authority, role, branch scope, and self-management policy.
6. The system creates/invites the staff account and assignments atomically.
7. The system records audit events and sends/queues the invitation without rolling back the account if delivery fails.
8. Staff accepts the invitation, sets credentials, and signs in.
9. The system shows only authorized landing pages, branches, objects, and actions.

**Extensions**

- **1a — Existing tenant user:** Manager updates assignments instead of creating a duplicate account.
- **4a — Sensitive role:** A second authorized approval is required if the approved matrix specifies it.
- **6a — Change duties:** Manager updates role/branch scope; prior assignments remain auditable.
- **6b — Suspend:** Manager supplies any required reason; user sessions are revoked and new access is denied.
- **6c — Reactivate:** Authorized manager restores approved assignments and access; action is audited.

**Exceptions**

- **5e — Manager lacks target scope:** Request is denied even if UI was manipulated.
- **5f — Cross-tenant branch/user:** Request is denied without disclosing the object.
- **7e — Invitation delivery fails:** Account/invitation remains in a truthful retryable state; access is not silently activated.
- **9e — Stale authorization:** Server re-evaluates current permissions and rejects revoked actions.

**Rules:** BR-004, BR-008, BR-019.
**Requirement links:** FR-RBAC-001–007, FR-AUT-001–005, SEC-RBAC-001, SEC-AUT-001–002, SEC-AUD-001.
**Story links:** US-RBAC-001, US-RBAC-002, US-RBAC-003, US-RBAC-004, US-AUT-002.

## 5. UC-03 — Find or register a family

**Goal:** Select the correct existing family or create a valid guardian-child relationship quickly and safely.
**Primary actor:** Reception Staff
**Supporting actor:** Parent/Guardian
**Trigger:** A family arrives and no confirmed reusable record is selected.
**Frequency:** Frequent; often immediately before check-in.

**Preconditions**

- Reception Staff is authenticated, assigned to the operating branch, and permitted to access family records.
- The approved Egypt M2 fields, Arabic-first privacy notice, legal-guardian child-data consent, phone normalization, and optional child DOB/age representation are configured.

**Success postconditions**

- The correct current-tenant guardian and child are selected, or new records are created.
- Every child has at least one active same-tenant guardian relationship.
- Versioned legal-guardian child-data consent evidence, required emergency contact, and any optional restricted safety note are retained.
- Potential duplicate handling follows the approved policy.

**Minimum guarantee**

- No orphan child, cross-tenant link, uncontrolled duplicate, or partial consent record is created.
- Sensitive fields remain visible only to permitted roles.

**Main success flow — existing family**

1. Reception asks for the guardian's phone or child name and enters a search.
2. The system normalizes the search value and returns safe current-tenant matches only.
3. Reception confirms the family using permitted identifying context.
4. The system displays linked children and active guardians, current consent status, and restricted safety information within permission. Visit history appears only after M3 session records exist.
5. Reception confirms or updates permitted contact, emergency, consent, and safety information.
6. Reception selects the child for the visit.

**Extension A — new family**

1. **At step 2**, no appropriate match exists; Reception starts guardian registration.
2. Reception presents the Arabic-first notice, records acknowledgment for necessary service processing, and captures explicit written/electronic child-data consent from an active legal guardian; optional marketing consent is separate and unchecked.
3. The system performs normalized-phone duplicate detection again before commit.
4. Reception enters child name, approved date-of-birth/age fields, optional approved photo/notes, and relationship.
5. The system atomically creates the guardian, child, active relationship, and consent evidence.
6. The system returns the new family for check-in.

**Other extensions**

- **2a — Potential duplicate:** A normalized-phone match opens the existing current-tenant family. Create-anyway and automated merge do not exist in MVP; foreign-tenant matches remain undisclosed.
- **4a — Additional guardian:** Reception links another same-tenant guardian with relationship type and active state.
- **5a — Consent change:** A new versioned grant/withdrawal event is appended; prior evidence remains. Withdrawal stops consent-based processing without erasing records subject to a documented hold.
- **5b — Optional child photo:** Approved image is privately stored; registration can continue without a photo.

**Exceptions**

- **2e — Match exists only elsewhere:** It is not returned or disclosed.
- **3e — Ambiguous family identity:** Reception does not guess; create/selection follows approved verification and duplicate policy.
- **5e — Remove final guardian:** Request is rejected until another valid active guardian is linked.
- **A5e — Validation/storage failure:** No partial guardian/child/link/consent aggregate is exposed.

**Rules:** BR-001, BR-008.
**Requirement links:** FR-CUS-001–009, DATA-REL-001, DATA-PII-001–002, SEC-PII-001–002, LOC-003, OQ-15, OQ-17.
**Story links:** US-CUS-001, US-CUS-002, US-CUS-003, US-CUS-004, US-CUS-005.

## 6. UC-04 — Issue/validate a ticket and check a child in

**Goal:** Create exactly one Active session for an eligible child using a valid ticket or pricing rule.
**Primary actor:** Reception Staff
**Supporting actors:** Cashier where ticket sale is separated; Parent/Guardian
**Supporting systems:** QR/scanner/browser-print interface; audit service
**Trigger:** A registered child is ready to enter the play area.
**Frequency:** Very high during arrival peaks.

**Preconditions**

- Tenant, branch, staff assignment, guardian-child link, and required pricing/ticket configuration are active; a branch-local service date is selected.
- Child has no Active session in the tenant.
- If payment before entry is required, the ticket/order is eligible under that policy.

**Success postconditions**

- Exactly one Active session exists with tenant, branch, child, actor, server start time, and snapshotted pricing/ticket reference.
- If a ticket was used, it is Consumed exactly once and linked to the session.
- The active board and audit history reflect check-in.

**Minimum guarantee**

- Invalid or concurrent attempts create neither duplicate session nor orphan ticket consumption.
- Scans are recorded with truthful, privacy-safe results.

**Main success flow — issue then scan**

1. Reception selects the registered child and confirms the active branch.
2. Reception/Cashier selects an active ticket type/pricing rule.
3. The system calculates/displays the ticket price and validity using server-held configuration.
4. Staff confirms issue/sale according to payment policy.
5. The system issues one tenant/branch-scoped ticket for the selected branch-local service date with a non-guessable QR payload.
6. The system displays/prints the QR using supported browser hardware.
7. Reception scans or selects the ticket for check-in.
8. The system validates tenant/branch, service date/operating window, validity, Issued state, entitlement, child eligibility, branch state, and staff permission.
9. Reception confirms check-in.
10. In one transaction, the system locks holder/child assignment, consumes the ticket, creates the Active session at server time, snapshots the rule, and appends ticket/session/audit events.
11. The system returns the Active session and current expected timing/alert information.

**Extensions**

- **2a — No pre-issued ticket required:** Reception selects an active pricing rule directly; check-in creates the session without ticket consumption if approved venue policy permits.
- **5a — Reprint:** Authorized staff reprints the same QR; identity/state/price/validity do not change and reprint is audited.
- **7a — Manual QR entry:** Authorized UI accepts the resolved ticket identifier if scanner input fails, subject to the same validation.
- **5b — Pre-scan correction:** Authorized staff may correct/reassign an unused Issued ticket before any successful scan; the change is audited and cannot alter tenant, branch, service date, price, or QR identity.
- **8a — Valid ticket sold earlier:** Existing Issued ticket can be used only in its assigned branch and branch-local service date while still unused.

**Exceptions**

- **3e — Client price tampering:** Server rejects/recalculates; tampered amount does not post.
- **8e — Expired/cancelled/consumed/wrong-branch/wrong-service-date/unknown ticket:** Check-in is blocked with a staff-safe reason and validation attempt logged; a failed scan never locks transfer.
- **8f — Inactive branch/rule or existing active session:** Check-in is blocked and ticket remains unconsumed.
- **10e — Concurrent scan/check-in:** One request succeeds; others receive the existing result or a conflict; at most one session/consumption exists.
- **10f — Transaction failure:** Ticket and session return/remain in their prior valid states.

**Rules:** BR-002, BR-008, BR-009, BR-011, BR-013, BR-015.
**Requirement links:** FR-TKT-001–008, FR-SES-001–002, FR-SES-012–014, DATA-INT-001, INT-HW-001–002.
**Story links:** US-TKT-001, US-TKT-002, US-TKT-003, US-SES-001, US-SES-002.

## 7. UC-05 — Operate a timed session

**Goal:** Monitor and change a live session without losing billing accuracy or auditability.
**Primary actor:** Reception Staff; Branch Manager for controlled actions
**Supporting system:** Time engine; scheduler
**Trigger:** Staff views or acts on an Active session.
**Frequency:** High throughout operating hours.

**Preconditions**

- Session is Active or Paused in the actor's tenant/branch scope.
- Session references an immutable pricing configuration/snapshot.
- Actor has the permission required for the selected operation.

**Success postconditions**

- Exactly one valid state/extension/adjustment event is applied.
- Current timing, estimated charge, and alert due time reflect authoritative history.
- Original timestamps/configuration/history are preserved.

**Minimum guarantee**

- Invalid/repeated/concurrent commands do not corrupt pause intervals, totals, state, or alert schedules.

**Main success flow — monitor**

1. Staff opens the active-session board or finds a session by QR, guardian phone, or child name.
2. The system returns current state, server-derived elapsed time, billable time, pause/extension summary, estimated charge, and alert status.
3. Staff selects an available operation: pause, resume, extend, request adjustment, cancel, or proceed to checkout.
4. The system displays only permitted commands but still validates permission and current state on submission.
5. Staff confirms the action and required reason/approval.
6. The system locks/checks the current session version and applies one valid transition/event.
7. The time engine recalculates the estimate and the scheduler replaces any stale alert schedule.
8. The system appends the operational and audit events and refreshes the current session.

**Extensions**

- **3a — Pause:** Active becomes Paused; an allowed pause type/reason opens one interval at server time.
- **3b — Resume:** Paused becomes Active; the one open interval closes at server time; rule controls billability.
- **3c — Extend:** Staff chooses an approved extension; incremental time/price is previewed and then stored once.
- **3d — Manual adjustment:** Dedicated permission, non-empty reason, old/new values, and configured approval are required; adjustment is additive evidence, not an invisible timestamp edit.
- **3e — Cancel:** Authorized reason/approval closes an open pause safely, sets terminal Cancelled state, and invalidates alerts.
- **3f — Checkout:** Continue with UC-06.

**Exceptions**

- **4e — Permission revoked:** Server denies the command and refreshes available actions.
- **5e — Approval missing/invalid/self-approval forbidden:** Action remains uncommitted.
- **6e — Stale/concurrent session version:** One valid command commits; the other receives a conflict/current state.
- **6f — Invalid transition:** Pause/resume is deferred; terminal sessions reject all operational transitions.
- **7e — Scheduler/provider failure:** Session change remains committed; scheduling/delivery failure is recorded and retried/alerted separately.

**Rules:** BR-003, BR-004, BR-011, BR-012, BR-013, BR-014, BR-019.
**Requirement links:** FR-SES-003–006, FR-SES-011–014, FR-TIM-001–009, FR-RBAC-005, FR-NOT-001.
**Story links:** US-TIM-001, US-TIM-002, US-SES-003, US-SES-004, US-SES-005, US-SES-006.

## 8. UC-06 — Checkout, collect payment, and issue receipt

**Goal:** Calculate the final session charge, settle any amount due, verify the collecting guardian, complete the session once, and issue a consistent receipt.
**Primary actors:** Reception Staff; Cashier
**Supporting actors:** Parent/Guardian; Branch Manager for approvals/overrides
**Supporting systems:** Time engine; POS; receipt and notification services
**Trigger:** Parent/guardian requests the child's checkout.
**Frequency:** Very high.

**Preconditions**

- Session is Active or Paused and belongs to the operating scope.
- Current user(s) have checkout/POS permissions according to the approved station handoff in OQ-19.
- Pricing snapshot and required guardian links exist.

**Success postconditions**

- Session is Completed once with server end time and immutable final calculation.
- Required amount due is settled by one posted payment, unless an explicitly approved exception exists.
- Checkout stores valid guardian verification or a manager override.
- Receipt has one unique number and reproduces the posted commercial facts.
- Receipt notification is queued/logged independently of completion success.

**Minimum guarantee**

- No child-release completion occurs without verification/override.
- No duplicate payment, receipt, or completion occurs under retries/concurrency.
- Provider failure cannot roll back an already posted payment/completed session.

**Main success flow**

1. Staff opens checkout for the Active session.
2. If Paused, the system calculates through the checkout time and handles the open interval according to the approved rule/completion operation.
3. The time engine produces a quote containing elapsed, pause treatment, extensions, rounding, base/overage, adjustments, discount/tax where applicable, payments, and amount due.
4. Staff and guardian review the explanation.
5. Staff selects an active guardian linked to the child and performs the approved verification method.
6. The system records a pending checkout verification with guardian, method, verifier, and server time, bound to this checkout attempt.
7. If amount is due, Cashier opens/uses the linked order and confirms its server-calculated lines, discounts, tax, total, permitted payment method, and non-sensitive reference.
8. The system validates current session/order versions, permission, approval thresholds, currency, method, amount, and idempotency.
9. The system posts the immutable payment and issues/allocates one receipt result atomically with the order.
10. Staff confirms the current checkout quote.
11. The system revalidates current state, payment settlement, and guardian verification.
12. The system atomically stores end time, final calculation snapshot, verification, and Completed state.
13. The system appends session/financial/verification/audit events and requests receipt notification.
14. The UI displays Completed status and the original receipt for display/print/resend.

**Extensions**

- **3a — No amount due:** Payment steps 7–9 are skipped; a zero-value receipt/session document follows approved receipt policy.
- **4a — Permitted discount:** Within-threshold discount applies; above threshold invokes UC-02 approval behavior before payment.
- **5a — Normal verification impossible:** Invoke UC-07; completion remains blocked until approved override exists.
- **7a — Reception/cashier handoff:** Checkout remains pending and current; Cashier settles linked order; Reception refreshes before completing.
- **14a — Resend/reprint:** The same receipt number/facts are used and only a delivery/reprint event is appended.

**Exceptions**

- **3e — Calculation mismatch:** Staff does not override silently; authorized adjustment follows UC-05 then a new quote is generated.
- **8e — Invalid/tampered/disallowed payment:** No payment posts; session remains Active and safe for retry.
- **9e — Payment transaction failure:** Order/payment/receipt remain in prior valid state; retry uses idempotency.
- **10e — Stale quote/session changed:** Completion conflicts; system shows current quote and requires reconfirmation.
- **11e — Amount remains due or verification absent:** Completion is denied.
- **12e — Concurrent checkout:** One completion succeeds; duplicate returns the original result/conflict without new payment/receipt.
- **13e — Notification provider unavailable:** Completion/payment/receipt stay successful; attempt becomes failed/retryable without claiming delivery.

**Rules:** BR-004, BR-005, BR-006, BR-013, BR-014, BR-016, BR-017, BR-018, BR-019.
**Requirement links:** FR-SES-007–011, FR-SES-014, FR-TIM-003–007, FR-POS-002–009, FR-SAF-001–002, FR-NOT-002–006, DATA-FIN-001, DATA-NUM-001, DATA-INT-001.
**Story links:** US-SES-007, US-SES-008, US-SES-010, US-POS-002, US-POS-003, US-POS-004, US-NOT-002.

**M4/OQ-19 implemented preparation boundary:** Reception verifies the active checkout-capable guardian with registered-phone last four digits, or a Branch Manager records a permission-checked, reasoned override. The server freezes the exact quote and moves the session to `pending_payment`; Cashier only receives the handoff and cannot prepare it. Identical retries return the original preparation, while changed retries, stale versions, ineligible/foreign guardians, and terminal sessions do not mutate state. M5 payment posting, receipt issuance, child release, refunds, and shifts are excluded.

## 9. UC-07 — Authorize an exceptional guardian checkout

**Goal:** Permit a rare child checkout when normal guardian verification cannot be completed, without weakening the normal safety control.
**Primary actor:** Branch Manager
**Supporting actor:** Reception Staff
**Trigger:** UC-06 cannot complete normal guardian verification.
**Frequency:** Exceptional; monitored as a safety KPI.

**Preconditions**

- Session is eligible for checkout and remains not Completed.
- Reception has documented the normal-verification problem.
- Manager is authenticated, assigned to the branch, and has dedicated override permission.

**Success postconditions**

- A checkout-specific override contains requester, approver, child/session, server time, and non-empty reason.
- UC-06 may continue only for that current checkout/session version.
- Override is prominent and searchable in session/audit reports.

**Minimum guarantee**

- Missing/invalid approval cannot release/complete the session.
- An override for another session, stale session version, or unauthorized branch cannot be reused.

**Main success flow**

1. Reception selects “Request checkout override” and enters the operational reason/context.
2. The system identifies the exact tenant, branch, child, session, requester, and current session version.
3. Branch Manager reviews the request and minimum necessary family/session context.
4. The system verifies manager permission, branch scope, and self-approval policy.
5. Manager explicitly approves and confirms the non-empty reason.
6. The system creates the immutable checkout-override evidence and audit event.
7. Reception refreshes checkout; the system confirms the override still matches the current session/version.
8. UC-06 continues through settlement and atomic completion.

**Extensions**

- **5a — Reject:** Manager rejects with reason; checkout remains blocked and venue safety procedure continues outside the system.
- **7a — Session changed after approval:** Override is treated as stale; a new review is required if policy requires version binding.

**Exceptions**

- **4e — Manager lacks scope/permission:** Approval is denied and logged as policy requires.
- **5e — Empty reason:** Approval cannot commit.
- **6e — Storage/concurrency failure:** No partial override is accepted; session remains uncompleted.

**Rules:** BR-004, BR-005, BR-019.
**Requirement links:** FR-SES-008, FR-SAF-001–002, FR-RBAC-005, FR-AUD-001–004, OQ-12.
**Story links:** US-SES-008, US-SES-009, US-SAF-003.

## 10. UC-08 — Complete a non-session POS sale

**Goal:** Sell approved ticket, F&B, merchandise, or add-on items and record one payment/receipt without requiring a child session.
**Primary actor:** Cashier
**Supporting actor:** Branch Manager for controlled discount
**Trigger:** A customer requests a supported product sale.
**Frequency:** High.

**Preconditions**

- Branch and Cashier assignment are active.
- Catalog, tax, receipt, currency, payment methods, and discount policy are configured.
- Selected items are active and branch-available.

**Success postconditions**

- One Paid order and immutable payment record exist in branch currency.
- One unique receipt reproduces server-calculated lines, discount, tax, total, method, and cashier.
- Sale appears in revenue/staff activity reporting.

**Minimum guarantee**

- Client tampering, duplicate submission, or posting failure cannot create a partial/duplicate financial result.

**Main success flow**

1. Cashier opens a new branch order.
2. Cashier adds active products and quantities from the permitted catalog.
3. The system calculates server-authoritative line subtotal, tax, and order total.
4. Cashier optionally links a guardian; ordinary permitted retail sale may remain unlinked.
5. Cashier applies any within-policy discount/reason.
6. Cashier selects a permitted payment method and confirms the exact amount/currency.
7. The system revalidates catalog availability, current prices/tax, permission, discount/approval, total, method, and idempotency.
8. The system atomically posts the payment, marks the order Paid, creates the immutable financial record, and issues one receipt number/content.
9. The system creates audit/reporting records and queues receipt notification where an eligible destination exists.
10. Cashier displays/prints/resends the original receipt.

**Extensions**

- **5a — Above-threshold discount:** Finalization waits for a valid manager approval through UC-02 approval behavior.
- **9a — No recipient destination:** Sale/receipt succeeds; notification is logged as not sendable.
- **10a — Reprint/resend later:** Same receipt number/content; reprint/delivery event only.

**Exceptions**

- **2e — Inactive/out-of-scope product:** It cannot be added/posted.
- **7e — Price/tax changed or client tampered:** Server rejects stale confirmation and returns recalculated values for explicit reconfirmation.
- **7f — Split/partial/gateway request:** Under ASM-02/ASM-08/ASM-10, the draft baseline rejects the operation; OQ-01/OQ-09 may change this behavior only through approved scope change.
- **8e — Duplicate/concurrent submit:** One result; retries return original or safe conflict.
- **8f — Posting failure:** No partial Paid/payment/receipt aggregate is visible.

**Rules:** BR-006, BR-013, BR-016, BR-017, BR-018, BR-019.
**Requirement links:** FR-POS-001–009, FR-POS-012–013, INT-PAY-001, DATA-FIN-001, DATA-NUM-001, DATA-INT-001.
**Story links:** US-POS-001, US-POS-002, US-POS-003, US-POS-004, US-POS-006.

## 11. UC-09 — Refund a posted payment

**Goal:** Record the approved OQ-09 full cash refund at the original branch on the same branch-local business date while preserving original financial evidence. For a ticket-linked payment, OQ-18 additionally requires the ticket to remain unused with no successful scan, consumption or session.
**Primary actor:** Branch Manager or other refund-authorized user
**Supporting actor:** Cashier/requester where approval separation applies
**Trigger:** An eligible posted sale requires correction/refund.
**Frequency:** Low but financially critical.

**Preconditions**

- Original payment is Posted, in actor's scope, not already fully refunded, and eligible under approved OQ-09 policy; any linked ticket also passes the approved unused-ticket rule.
- Actor has refund permission and any required approval.

**Success postconditions**

- One immutable refund is linked to the original payment/order with actor, approver if required, reason, amount, method reference-safe data, and server time.
- Original payment/order/receipt facts remain available; status reflects refund.
- Revenue report deducts the refund according to approved posting-date policy.

**Minimum guarantee**

- Refunded total cannot exceed refundable balance.
- Retry/concurrency cannot duplicate the same refund command or exceed refundable balance.
- Original posted records cannot be deleted or rewritten.

**Main success flow**

1. Authorized user retrieves the original order/payment by receipt or transaction reference.
2. The system displays permitted original facts, refundable balance, and current refund status.
3. User selects a refund permitted by the approved OQ-09 policy, chooses/enters required reason, and requests approval if policy requires. Under ASM-10 this is a full refund.
4. The system validates permission, scope, eligibility/window, refundable balance, approval, currency, and idempotency.
5. User confirms the consequence.
6. The system atomically creates a linked refund/reversal record and updates derived order/receipt refund status without overwriting original facts.
7. The system appends audit events and makes the refund available to the revenue report under the approved posting policy.
8. The system displays the refund reference and updated receipt/order status.

**Extensions**

- **3a — Approval required:** Requester and approver are retained; forbidden self-approval is rejected.
- **8a — Send refund confirmation:** Only if the selected provider/template is approved; notification history is separate from refund success.

**Exceptions**

- **2e — Already refunded/no balance:** Request is rejected with current safe status.
- **4e — Ineligible date/method or missing permission/reason/approval:** No refund record is created.
- **6e — Concurrent retries:** At most one result for the same refund command commits; other requests receive the original result/conflict and cumulative refunds never exceed balance.
- **6f — Failure during posting:** Original payment remains Posted/not-refunded and no partial negative record appears.

**Rules:** BR-004, BR-007, BR-016, BR-018, BR-019.
**Requirement links:** FR-POS-010–013, FR-RBAC-005, FR-RPT-001, DATA-FIN-001, DATA-INT-001, OQ-09.
**Story links:** US-POS-005, US-RBAC-003, US-RPT-001.

## 12. UC-10 — Run and reconcile core reports

**Goal:** Produce scoped, explainable revenue, attendance, session, and staff activity information that reconciles to source records.
**Primary actor:** Tenant Owner; Branch Manager; other explicitly authorized report user
**Trigger:** User needs operational/financial oversight or investigation.
**Frequency:** Daily and on demand.

**Preconditions**

- Actor is authenticated and has the specific report permission.
- Branch/time-zone/currency/posting-date policies are configured.
- Requested date range/page size is within approved bounds.

**Success postconditions**

- Report displays effective filters, branch scope, time zone, currency, and generated-at time.
- Totals/detail reconcile to the same source population.
- No out-of-scope rows or aggregate inference is returned.

**Minimum guarantee**

- Invalid or over-broad requests fail/cap predictably without exposing data or exhausting resources.

**Main success flow — revenue**

1. User chooses Revenue and enters date range and permitted branch filters.
2. The system derives effective tenant/branch scope and validates range/grouping.
3. The system queries posted orders/payments/refunds using approved branch-local/posting-date semantics.
4. The system calculates gross sales, discounts, tax, refunds, and net revenue, with requested available dimensions.
5. The system returns server-paginated/aggregated results and applied filters/time-zone/currency/generated-at metadata.
6. User drills/filters by branch, cashier, item/ticket type, or payment method as permitted.
7. The system retains the same source/rounding semantics so group totals reconcile.

**Extensions**

- **1a — Attendance:** Count eligible visits/sessions by date/hour/branch/age band; missing age is explicitly Unknown.
- **1b — Session history:** Filter by state, child, guardian phone, ticket, actor, and dates; show timing/pause/extension/calculation/payment/verification permitted fields.
- **1c — Staff activity:** Filter immutable audit events by date, branch, actor, category, subject, and outcome with safe redaction.
- **1d — Tenant consolidation:** Tenant Owner selects multiple permitted branches; each monetary result retains currency context and cross-currency summing follows approved policy (baseline assumes compatible/same reporting context).

**Exceptions**

- **2e — Unassigned branch filter:** Server ignores/rejects unauthorized scope and returns no inferred totals.
- **2f — Excessive range/page size:** Request is capped or rejected with stable guidance.
- **3e — Query timeout/failure:** No partial result is presented as complete; correlation ID is returned and failure is monitored.
- **7e — Reconciliation variance:** Treated as a defect/investigation; values are not manually patched in the report layer.

**Rules:** BR-007, BR-008, BR-013, BR-016, BR-018.
**Requirement links:** FR-RPT-001–008, FR-SES-012, FR-AUD-004, NFR-PERF-002–003, DATA-MNY-001, DATA-AUD-001.
**Story links:** US-RPT-001, US-RPT-002, US-RPT-003, US-RPT-004.

## 13. UC-11 — Dispatch an operational notification

**Goal:** Send session-ending alerts and receipt notifications asynchronously, truthfully recording every attempt without blocking core operation.
**Primary actor:** Scheduler / Queue Worker
**Supporting systems:** Notification provider; provider callback endpoint
**Interested human actors:** Parent/Guardian; Branch Manager/support user
**Trigger:** Eligible session alert due or receipt issued/resend requested.
**Frequency:** High and event-driven.

**Preconditions**

- Approved channel/provider, sender, operational template, destination policy, retry policy, and locale behavior are configured.
- Domain event contains explicit tenant and subject context.
- Eligible guardian destination exists, or the system can record “not sendable.”

**Success postconditions**

- One notification request and its attempts are retained with truthful state.
- Provider `Sent` is not represented as `Delivered` without confirmation.
- Core session/payment/receipt state is independent of provider outcome.

**Minimum guarantee**

- No secret or unnecessary sensitive data is logged.
- Stale schedule, duplicate job, callback replay, or provider retry cannot generate uncontrolled duplicate messages/state regressions.

**Main success flow — session ending**

1. Session creation/change produces or updates an alert schedule using its rule snapshot and current state.
2. At due time, scheduler loads the session using explicit tenant context.
3. Scheduler revalidates that the schedule is current and the session is eligible.
4. The system renders the approved operational template using recipient locale and authoritative timing.
5. The system creates/claims an idempotent notification request and attempt.
6. Channel adapter sends to the provider using protected credentials.
7. Provider accepts the request and returns a provider reference.
8. System marks the attempt/request Sent, records safe metadata, and makes it visible to authorized users.
9. If a verified delivery callback arrives, system idempotently updates state to Delivered without regressing a later/final state.

**Extensions**

- **1a — Receipt:** Receipt issuance creates a request referencing the original receipt; resend uses the same receipt facts.
- **3a — Session paused/extended:** Due time is recalculated; obsolete schedule is Cancelled/Stale.
- **3b — Session completed/cancelled:** No ending notification is sent from the stale schedule.
- **5a — Missing destination:** Request becomes NotSendable/FailedPermanent according to state model; core transaction remains successful.
- **7a — Retryable failure:** Attempt is marked FailedRetryable and requeued using bounded backoff.
- **7b — Permanent failure:** Attempt is marked FailedPermanent and no indefinite retry occurs.

**Exceptions**

- **2e — Missing/wrong tenant context:** Job fails safely and raises operational monitoring; it does not search globally for a match.
- **6e — Provider outage/timeout:** No core transaction is rolled back; retry/alert policy applies.
- **9e — Invalid callback signature:** Callback is rejected and security/technical event recorded without state change.
- **9f — Duplicate/out-of-order callback:** Idempotent state logic prevents duplicate event or status regression.

**Rules:** BR-008, BR-010, BR-020.
**Requirement links:** FR-NOT-001–007, FR-TIM-008, INT-NOT-001–003, NFR-AVL-002, SEC-SEC-001, LOC-006.
**Story links:** US-NOT-001, US-NOT-002, US-NOT-003, US-SES-003.

## 14. UC-12 — Record and follow up a safety incident

**Approval status:** **Proposed/Conditional on OQ-20.** The PRD describes incident records in a safety module and role responsibility, but does not list them in the Phase 1 MVP capabilities. This use case is not a release gate unless Product/Safety approves it.
**Goal:** Capture and retrieve basic operational incident facts and append follow-up without presenting PlayNexus as an emergency service.
**Primary actor:** Authorized staff member
**Supporting actor:** Branch Manager / safety lead
**Trigger:** A venue/child safety incident is observed or reported.
**Frequency:** Exceptional but safety-critical.

**Preconditions**

- Actor is authenticated and has incident-create permission in the branch.
- Venue follows its external immediate-emergency/escalation procedure; PlayNexus recording is not a substitute.
- Approved categories, severity, visibility, state, and retention policy are configured under OQ-20.

**Success postconditions**

- One tenant/branch-scoped incident contains required facts, reporter, occurred/reported times, and current state.
- Later notes/transitions append actor/time history without overwriting original facts.
- Authorized search can find it by child, date, branch, reporter, severity, and state.

**Minimum guarantee**

- Invalid/cross-scope records are not created or disclosed.
- Failure cannot erase prior incident facts or follow-up.

**Main success flow**

1. Staff follows the venue's immediate safety/emergency procedure outside the application as necessary.
2. Staff opens incident creation for the current branch.
3. Staff enters approved category, occurred time, description, severity, applicable child/session, and required contextual fields.
4. The system validates permission, tenant/branch/child/session consistency, required fields, and time values.
5. Staff confirms.
6. The system creates the Open incident with reporter/reported time and an audit event.
7. Authorized Manager reviews the incident and appends a follow-up note or permitted state transition.
8. The system retains original facts and appends the reviewer/action/time event.
9. Authorized user retrieves it using one or more approved search filters.

**Extensions**

- **3a — No specific child/session:** Venue-level incident may be recorded if approved category permits it.
- **7a — Under review/close/reopen:** Transitions follow the approved OQ-20 state policy; every transition is appended/audited.

**Exceptions**

- **4e — Cross-tenant/branch child or session:** Request is rejected without disclosure.
- **4f — Invalid required facts:** Field errors are returned; no partial incident is created.
- **7e — User lacks note/state permission:** Update is denied and original record is unchanged.
- **9e — Search outside scope:** No protected incident or aggregate details are returned.

**Rules:** BR-008, BR-019.
**Requirement links:** FR-SAF-003–006, FR-AUD-001–004, DATA-TEN-002, DATA-PII-001, SEC-PII-001, OQ-20.
**Story links:** US-SAF-001, US-SAF-002, US-AUD-001.

## 15. UC-13 — Suspend a tenant or deactivate a branch

**Goal:** Stop new operations promptly without deleting business history.
**Primary actors:** Super Admin for tenant; Tenant Owner for branch
**Trigger:** Commercial, security, legal, closure, maintenance, or operational decision.
**Frequency:** Infrequent.

**Preconditions**

- Actor is authenticated with the specific status-control permission.
- Actor has reviewed the consequence and provides a reason.
- Operational handling of already active sessions is decided before a branch is intentionally deactivated during hours.

**Success postconditions**

- Tenant suspension denies/revokes staff access according to policy; branch deactivation denies new sessions/orders.
- Historical records and attribution remain retained and reportable to authorized actors.
- Status, actor, time, reason, and before/after values are audited.

**Minimum guarantee**

- Status control never physically deletes tenant, branch, family, session, financial, receipt, incident, or audit history.

**Main success flow — branch**

1. Tenant Owner selects an Active branch and chooses Deactivate.
2. The system displays impact, including new check-in/POS denial and any current Active sessions.
3. Owner enters reason and confirms.
4. The system checks permission/current version and sets branch Inactive.
5. The system rejects subsequent new sessions/orders while keeping history accessible.
6. The system appends an audit event.

**Extensions**

- **1a — Tenant suspension:** Super Admin selects tenant, reviews impact, enters reason, confirms; system sets Suspended and revokes/denies staff access.
- **4a — Reactivate branch/tenant:** Authorized actor confirms valid configuration/status dependencies; system restores permitted access and audits it.
- **2a — Active sessions exist:** System follows the approved operational policy—block deactivation, require completion/cancellation, or permit managed wind-down. This policy must be resolved before implementation acceptance.

**Exceptions**

- **3e — Missing reason/permission:** No status change.
- **4e — Concurrent status/config change:** One valid update commits; actor receives current state/conflict.
- **5e — Cached authorization/status:** Server-side current status prevents new action within approved revocation interval.

**Rules:** BR-008, BR-009, BR-016.
**Requirement links:** FR-TEN-002, FR-TEN-006, FR-RBAC-003, FR-AUT-002, FR-AUT-005, FR-RBAC-006, FR-AUD-001–003.
**Story links:** US-TEN-001, US-TEN-004, US-RBAC-002.

## 16. UC-14 — Deny a cross-scope or unauthorized action

**Goal:** Ensure a user/client cannot read or mutate a tenant, branch, object, action, report, job, cache, or file outside effective authorization.
**Primary actor:** Any authenticated staff user or malicious/erroneous client
**Supporting systems:** Authorization, tenant scoping, audit/security monitoring
**Trigger:** Request lacks required permission, scope, or trusted context.
**Frequency:** Continuous; covers normal mistakes and hostile attempts.

**Preconditions**

- Protected endpoint/action/object exists or is guessed.
- Current authentication context has known tenant, assignments, status, and permissions, or is unauthenticated.

**Success postconditions**

- Protected data/action is not returned or changed.
- Response does not expose sensitive existence or contents.
- Relevant denied-sensitive/security event is recorded and rate/alert controls apply according to policy.

**Minimum guarantee**

- Authorization denial itself does not leak secrets, another tenant's metadata, aggregate count, signed file URL, cached result, or job output.

**Main success flow**

1. Client submits a protected request.
2. System authenticates the session and verifies active user/tenant status.
3. System derives current tenant and branch scope from trusted assignments.
4. System checks the required permission/action policy.
5. System loads/queries the object only through enforced tenant/branch scope or performs equivalent object authorization.
6. If any check fails, system denies with safe unauthenticated/forbidden/not-found semantics per API policy.
7. System records correlation and the appropriate technical/security/audit event without storing secrets or unnecessary target data.
8. No protected state changes; the client may request only an authorized object/action.

**Extensions / attack variants**

- **1a — URL/body identifier substitution:** Same denial applies even when the identifier is valid for another scope.
- **1b — Report filter manipulation:** Server derives allowed branches and excludes/denies unauthorized filters before aggregation.
- **1c — Direct API call bypassing UI:** Same policies/business guards apply.
- **1d — File/child photo URL guessing:** Private storage authorization denies direct access.
- **1e — Background job missing/wrong tenant:** Job fails safely and alerts; it never falls back to an unscoped query.
- **1f — Cache/search collision:** Tenant key/scope prevents another tenant's cached/indexed result.
- **1g — Suspended account/tenant with old session:** Revocation/status check denies access.
- **1h — Super Admin support access:** Default denial applies unless OQ-14-approved function and privileged audit requirements are met.

**Exceptions**

- **7e — Audit/log sink unavailable:** Security design follows fail-safe policy for the operation and raises monitoring; critical privileged mutations must not silently proceed without required evidence.

**Rules:** BR-004, BR-008.
**Requirement links:** FR-RBAC-003–007, SEC-TEN-001, SEC-RBAC-001, DATA-TEN-001–002, DATA-ID-001, FR-AUD-001–004, NFR-OBS-001–002.
**Story links:** US-RBAC-004, US-TEN-001, US-RPT-001, US-SAF-002, US-AUD-001.

## 17. Cross-use-case traceability matrix

| Capability | Primary use cases | Key stories | Key SRS families |
|---|---|---|---|
| Tenant/branch setup | UC-01, UC-13, UC-14 | US-TEN-001–004 | FR-TEN, SEC-TEN, DATA-TEN |
| Staff/RBAC | UC-02, UC-14 | US-AUT-001–002, US-RBAC-001–004 | FR-AUT, FR-RBAC, SEC-AUT, SEC-RBAC |
| Family registration | UC-03, UC-14 | US-CUS-001–005 | FR-CUS, DATA-REL, SEC-PII |
| Ticket/check-in | UC-04 | US-TKT-001–003, US-SES-001–002 | FR-TKT, FR-SES |
| Time engine/session operation | UC-05 | US-TIM-001–002, US-SES-003–006 | FR-TIM, FR-SES |
| Checkout/safety | UC-06, UC-07 | US-SES-007–010, US-SAF-003 | FR-SES, FR-SAF |
| POS/payment/receipt/refund | UC-06, UC-08, UC-09 | US-POS-001–006 | FR-POS, DATA-FIN, DATA-NUM |
| Reports | UC-10 | US-RPT-001–004 | FR-RPT, FR-AUD |
| Notifications | UC-11 | US-NOT-001–003 | FR-NOT, INT-NOT |
| Audit and Conditional incidents | UC-07, UC-14; UC-12 only if OQ-20 approves | US-SAF-003, US-AUD-001; US-SAF-001–002 Conditional | FR-AUD, SEC-AUD; FR-SAF-003–006 Conditional |

## 18. End-to-end pilot scenario

The minimum pilot UAT shall execute the following as one traceable journey:

1. UC-01 creates/configures the pilot tenant and active branch.
2. UC-02 provisions Reception, Cashier, and Manager users and proves one denial per role.
3. UC-03 registers a guardian with two children and required consent/safety data, then proves duplicate warning.
4. UC-04 sells/issues a QR ticket and checks one child in, then proves repeat ticket use and duplicate active session are blocked.
5. UC-05 pauses/resumes, extends, triggers/reschedules an ending alert, and proves calculation against an approved fixture.
6. UC-06 shows the final quote, records payment, verifies guardian, completes once, and issues/resends the same receipt.
7. UC-07 is executed on separate controlled test data to prove unauthorized override fails and approved override is reportable.
8. UC-08 posts an ordinary retail sale.
9. UC-09 records a policy-valid refund (full under ASM-10) and preserves the original.
10. UC-10 reconciles revenue, attendance, session history, and staff activity to the scenario data.
11. UC-11 proves sent/delivered distinction, provider failure isolation, bounded retry, and stale alert suppression.
12. **Only if OQ-20 approves incident recording for MVP**, UC-12 records and follows up a test incident with proper field/scope protection; otherwise this step is Deferred and is not counted as a failed MVP criterion.
13. UC-13 deactivates a test branch, proves new check-in/order denial, and proves history remains.
14. UC-14 attempts cross-tenant/branch/UI/API/report/file access and proves zero disclosure/change.

## 19. Decision impact crosswalk

This table is a historical impact crosswalk, not a list of currently open OQs. As of 2026-09-15, OQ-01 through OQ-19 and OQ-21 through OQ-23 have approved baselines; OQ-20 and OQ-24 are explicitly deferred. Remaining provider/procurement and security/retention sub-decisions are registered separately in `.ai/DECISION_REGISTER.md`.

| Decision | Affected use cases | Required outcome |
|---|---|---|
| OQ-01 online capture vs recording | UC-06, UC-08, UC-09 | **Resolved:** in-person payment recording only; online capture deferred. |
| OQ-02 mandatory hardware | UC-04 | **Resolved:** keyboard-input scanner baseline; proprietary device SDKs deferred. |
| OQ-03 offline mode | All front-desk use cases | **Resolved:** online-only with manual/read-only continuity; offline writes deferred. |
| OQ-04 subscription plans/limits | UC-01, UC-02 | **Resolved:** approved V1 plan catalog, limits, trial/grace and manual SaaS billing; recurring provider billing deferred. |
| OQ-05/OQ-13 provider/channel/alert policy | UC-06, UC-11 | **Resolved baseline:** WhatsApp, SMS fallback, email receipt/admin; 10-minute lead and bounded 2/5-minute retries. Provider/sender/template/callback/cost inputs remain separate procurement decisions. |
| OQ-06/OQ-07 launch markets/localization/privacy | UC-01, UC-03, UC-06, UC-08, UC-11, UC-12 if approved | **Resolved engineering baseline:** Egypt/EGP/Cairo/Arabic-English and minimum-data/consent/three-year retention; production Finance/Legal/DPO approval remains a release gate. |
| OQ-08 receipt numbering/content | UC-01, UC-06, UC-08 | **Resolved:** immutable branch/year sequence and approved receipt facts; fiscal validation remains a release gate. |
| OQ-09 refund policy | UC-09 | **Resolved:** one full same-branch, same-local-business-day cash refund with reason and separate Manager/Owner approval. |
| OQ-10 multi-branch roles | UC-02, UC-10, UC-14 | **Resolved:** multiple branch-scoped assignments with default-deny authorization. |
| OQ-11 capacity enforcement | UC-04 | **Resolved for Egypt MVP:** hard non-overridable block; Active sessions count; transactional concurrency behavior. |
| OQ-12 guardian verification | UC-06, UC-07 | **Resolved:** QR plus guardian phone last four/handoff evidence; reasoned audited manager override. |
| OQ-14 Super Admin support access | UC-14 | **Resolved:** tenant-specific, least-privilege, reasoned, visible, revocable, audited, maximum 60-minute access; implementation remains partial. |
| OQ-15/OQ-17 family age/duplicate policy | UC-03, UC-10 | **Resolved:** optional DOB/minimum precision and tenant-unique normalized guardian phone with hard reuse. |
| OQ-16 pricing patterns | UC-04, UC-05, UC-06 | **Resolved:** immutable fixed-duration/grace/overtime/tax arithmetic. |
| OQ-18 ticket transfer/scope/refund | UC-04, UC-09 | **Resolved:** branch/service-date scope, pre-scan correction, post-scan lock and unused-only refund eligibility. |
| OQ-19 station/payment handoff | UC-06 | **Resolved:** Reception/Manager prepares; Cashier/authorized transaction actor posts exact payment and completion atomically. |
| OQ-20 incident policy | UC-12 | **Deferred:** no incident module in Egypt V1; reopening requires a new Safety/Legal/Product decision. |
| OQ-21 load/volume definitions | All high-volume/search/report use cases | **Resolved targets:** approved p95/availability/bounded-query baseline; environment workload counts are release-test evidence. |
| OQ-22 security/session configuration | UC-01, UC-02, UC-14 and all authenticated flows | **Resolved baseline:** approved idle/password/revocation/MFA/secrets/debug controls; exact absolute timeout, rates and retention remain separately registered. |
| OQ-23 branded vs white-label | UC-01 and scope governance | **Resolved:** branded SaaS only; white-label deferred. |
| OQ-24 minimal cashier shift close | UC-08, UC-10 | **Deferred:** no shift/drawer workflow in the Egypt MVP. |
