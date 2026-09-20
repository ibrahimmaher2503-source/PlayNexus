# Decisions

## 2026-09-15: Approve the Production V1 commercial and operational defaults

**Authority:** The product owner replied `Approved as recommended. Proceed`, authorized implementation, and requested editable demo plan prices.

**OQ-04 subscriptions:** Launch editable Starter (1 branch/5 users), Growth (3/15), Professional (10/50), and Enterprise (custom) demo plans. Plans have editable EGP monthly/yearly prices and annual discount, sale-active state, and quantitative limits only. Subscriptions use `trialing`, `active`, `past_due`, `grace_period`, `suspended`, `cancelled`, and `expired`; the trial is 14 days and grace is 7 days. Grace permits normal work. After grace, tenants become restricted/read-only: no new sessions, sales, branches, or staff, while Tenant Owners retain historical reports. Existing data is never deleted for expiry. Super Admin may change plan/custom limits, extend trial, suspend/reactivate/cancel, record effective dates and reasoned temporary/permanent commercial overrides, and view audit history.

**Commercial billing boundary:** Store manual SaaS invoice/reference, integer-minor-unit amount, currency, billing period, payment date/method, and notes separately from venue POS payments. Online recurring billing is a later provider-specific integration; no speculative gateway abstraction is approved.

**OQ-05/OQ-13 notifications:** Customer-channel priority is WhatsApp, then SMS fallback, with email for receipts/administration. Session-end alerts start 10 minutes before expected end, retry after 2 minutes and then 5 minutes on technical failure, stop at three attempts, and do not resend after provider-confirmed delivery. Every attempt/callback is audited. Actual vendor adapters, sender identities, production credentials and approved bilingual templates remain procurement/deployment inputs, not invented code.

**GAP-07 support access:** Super Admin has no tenant-content access by default. A support session is tenant-specific, reason-required, least-privilege, revocable, fully audited, visible to Tenant Owners, automatically expires, and is capped at 60 minutes. Permanent impersonation is prohibited. Security/Legal validation remains a production gate, not a reason to keep the approved local workflow absent.

**GAP-08 retention:** Family/operational data is eligible three years after last activity or relationship closure, subject to financial/audit statutory retention and documented legal holds. Prefer anonymization/pseudonymization where required. Provide hold/release and eligibility/dry-run administration; destructive production execution stays disabled until Legal/DPO approves the final schedule.

**OQ-21/OQ-22 production defaults:** Acceptance targets are p95 under 500 ms for normal UI/API, under 1 second for critical transactions, under 3 seconds for standard reports, and 99.9% availability. Large lists require pagination and bounded queries. Staff idle timeout is 30 minutes; Super Admin/support-sensitive idle timeout is 15 minutes; passwords require at least 12 characters; disable/suspend revokes access immediately; Super Admin MFA is mandatory and Tenant Owner MFA is enforceable; audit is immutable, secrets remain outside the repository, and production debug is off. The environment-specific load profile and deployed availability evidence remain go-live evidence, not an open product behavior.

**Explicit V1 exclusions and release gate:** No current external `/api/v1` consumer is identified, so the web release is not blocked on implementing it. Incident management, cashier shifts/drawer reconciliation, and pause/resume remain outside Egypt V1. Separate staging/production, CI/CD, safe migrations, secrets, HTTPS, monitoring, health/error/queue/scheduler/database visibility, backup/restore, rollback, smoke tests and role-based UAT are mandatory before go-live. Human role assignees and vendor credentials must be named in the deployment record; engineering must not invent identities.

## 2026-09-15: Bounded M5 review-remediation engineering contract

The owner's request to fix all review findings authorizes implementation against the existing permission matrix, not Cashier-only permissions that contradict it. Tenant Owner and assigned Branch Manager/Cashier may record cash payment and request/execute an approved refund; approval still requires another authorized person. Tenant-global product writes are Owner-only because a branch Manager must not mutate other branches' shared catalog.

Use dedicated domain records already introduced for the cash pilot instead of scaffolding a speculative generic approval framework: discount approvals carry a reviewable server-derived locked cart snapshot and are consumed only atomically by payment; refund request/approval/execution is represented in the dedicated refund state machine. Canonical ERD/API must describe these bounded records. No empty-line legacy bypass is approved. Original receipt seller/cashier/commercial facts remain immutable and current refund state is an explicit annotation, not a replacement receipt.

## 2026-09-14: Approve the Egypt M5 cash, refund, discount and shift baseline

**Authority:** The product owner explicitly approved the four recommended M5 decisions and authorized implementation.

**Payment and completion:** M5 records one exact in-person cash payment in EGP against the frozen `pending_payment` quote. Posting locks and commits the linked order, immutable payment, branch/year receipt facts and verified session completion atomically. Identical idempotent replay returns the original result; changed replay conflicts. No gateway, split tender, partial payment or raw card data.

**OQ-09 refund:** One full cash refund only, executed at the original branch during the same branch-local business date. It requires a non-empty reason and separate Branch Manager or Tenant Owner approval; the Cashier may execute but cannot approve. Ticket-linked refunds additionally require the approved unused/no-successful-scan/no-session OQ-18 eligibility. No partial refund, store credit, exchange, destructive edit or provider call.

**OQ-24 shift:** Cashier shift open/close, drawer balancing and variance are deferred. Each payment still records tenant, branch, cashier and server timestamp for later daily reconciliation.

**Discount and zero total:** The branch discount threshold starts at zero basis points, so every positive discount requires a current, payload-bound, single-use Manager/Owner approval and reason. Discounts are fixed minor-unit amounts, never change stored unit prices and cannot reduce the order total to zero. Zero-total checkout is not enabled in the first pilot.

**Receipt/provider boundary:** Receipt identity/content follows OQ-08 and is reproducible for browser display/print. Email/SMS/WhatsApp delivery and provider callbacks remain M6 behind OQ-05/OQ-13.

## 2026-09-13: Close the Egypt M4 no-pause and notification UI boundary

**Authority:** The product owner resumed the bounded M4 implementation and requested the remaining work be distributed to Luna agents.

**Decision:** Egypt MVP sessions do not support pause/resume. Active sessions may be extended only in fixed 30-minute units. Manager/Owner correction is additive, reasoned, and version-guarded; cancellation is reasoned, terminal, audited, and never a financial refund. The `pending_payment` screen shows the frozen server subtotal, tax, and total, while payment, receipt, refund, shift, child-release, and provider-notification controls remain outside this slice.

**Boundary:** This closes the UI representation of the approved time/session preparation behavior only. Final runtime/browser acceptance and M5 matching payment/completion remain required; notification providers and alert policy remain open for a later decision.

## 2026-09-13: Resolve OQ-19 as reception-to-cashier checkout handoff

**Authority:** The product owner approved the bounded station decision so M4 preparation could proceed without expanding into Finance.
**Decision:** Reception verifies the active checkout-capable guardian using the registered-phone last four digits (or a configured handoff code when that later contract is implemented), calculates from immutable session facts, freezes the exact quote, and transitions the session to `pending_payment` in the Cashier queue. Cashier cannot prepare or alter that handoff; M5 matching payment posting will atomically complete the session. Identical UUID retries return the original preparation, while changed replays conflict. A Branch Manager/Owner may use the dedicated manager override only with permission, a non-empty reason, and audit evidence; recovery is idempotent and bound to the session version.
**Boundary:** This slice covers preparation, guardian verification, frozen quote, queue state, concurrency/idempotency and audit only. It does not authorize payment, refund, receipt, child-release, shift, pause/extension/adjustment, or final Completed behavior.

## 2026-09-13: Freeze read-only session estimate arithmetic

**Authority:** The product owner authorized closing the remaining Egypt defaults so delivery could continue; this closes the bounded OQ-16 worked examples for an estimate, not Checkout or Finance approval.
**Decision:** Base ticket price covers the snapshotted base duration plus 600-second grace. Overtime begins on the first later second and rounds up in snapshotted 1,800-second units. Exclusive tax is rounded half up once on base plus overtime and added; inclusive tax keeps that configured total and extracts its rounded included-tax portion. All math uses integer minor units, server UTC time, and the session's immutable snapshot. A zero rate is valid.
**Examples:** With base 15,000, base duration 3,600, grace 600, overtime unit 1,800, overtime price 7,500, and a 1,400 bps fixture: elapsed 4,200 -> exclusive total 17,100; 4,201 -> one unit and total 25,650; 6,001 -> two units and total 34,200. Inclusive at 4,201 keeps total 22,500, with extracted tax 2,763 and net 19,737.
**Tax boundary:** Egypt's VAT Law No. 67/2016 states the standard rate as 14%, but PlayNexus does not decide whether a specific operator/activity is taxable, exempt, registered, or differently treated. The application never seeds 14%; the branch-approved snapshotted rate/mode governs. Venue legal/accounting approval remains required.
**Non-goals:** The estimate is read-only and non-final. No persisted quote, discount, extension, pause, checkout, guardian release, payment, amount-due, refund, receipt or tax filing behavior is authorized.

## 2026-09-13: Hard branch capacity for Egypt MVP check-in

**Authority:** The product owner authorized the coordinator to close the remaining Egypt decisions with a safe default so delivery could continue.
**Decision:** OQ-11 uses a hard, non-overridable check-in block for the MVP. Active and Paused sessions both occupy capacity. The final capacity check and ticket consumption/session creation run under the same tenant transaction lock; concurrent attempts cannot over-commit a branch. A rejected capacity attempt records privacy-safe scan evidence and changes no ticket/session state.
**Boundary:** No manager override, waitlist, reservation, capacity warning notification or automatic retry is introduced. A later policy change requires a new explicit decision, audit/approval model and concurrency tests.

## 2026-09-13: Keep ticket-type price/version independent of later pricing replacement

**Implementation clarification:** The approved immutable ticket type captures a current active pricing version when created. Replacing that pricing rule preserves its historical facts and does not silently disable or reprice an active ticket type; later issuance snapshots the type's unchanged price/source version. A new price needs a manager-created new type/code. No type update/retirement or automatic rebinding command is introduced by this bounded ticket slice. Validation and unused-ticket cancellation are not session creation or financial refund execution.

## 2026-09-13: Approve the Egypt MVP ticket scope, transfer, date, and refund eligibility

**Authority:** The product owner approved the proposed Egypt default and closed OQ-18 for M3 implementation.
**Decision:** Every MVP ticket belongs to exactly one tenant and one branch and carries a `service_date` interpreted in that branch's configured time zone. It is valid only for that branch and the configured operating window of that service day. A successful first scan/consumption permanently locks its holder/child binding; no transfer or reassignment is allowed afterward. An unused Issued ticket may be corrected/reassigned before the first successful scan by authorized staff with audit evidence.
**Refund eligibility:** Only an unused Issued ticket with no successful scan, consumption, or linked session may be refunded. Refund requires Branch Manager or Tenant Owner authority in scope, a non-empty reason, an action-bound approval, an immutable linked financial reversal when a posted payment exists, and audit evidence. Consumed tickets are never refundable. This closes ticket eligibility only; OQ-09 still owns payment method, refund window, and execution mechanics.
**Safety and concurrency:** Wrong-branch, wrong-service-day, expired, cancelled, or consumed scans fail with staff-safe results. First successful scan, ticket consumption, and session creation remain atomic/idempotent. Failed scans do not lock transfer. No implementation or release claim is made by this decision entry.

## 2026-09-12: Approve the Egypt M2 privacy, family, and safety baseline

**Authority:** The product owner authorized closing the remaining M2 decisions using a conservative Egypt-appropriate baseline so implementation can continue.
**Regulatory basis:** Egypt Personal Data Protection Law 151/2020, Executive Regulations 816/2025, and the January 2026 PDPC consent/privacy-notice guidance. This is an engineering/product baseline and does not replace the controller's production legal/DPO sign-off or required PDPC licensing/registration assessment.

- **OQ-17 duplicate policy:** normalized guardian phone is unique within a tenant for MVP. A match always opens the existing family; there is no create-anyway or automated merge. Corrections update the existing profile. The database/application must reject concurrent duplicates without disclosing cross-tenant matches.
- **Privacy notice and lawful basis:** show a concise Arabic-first privacy notice with optional English translation. Operational guardian contact data uses the documented service/contract lawful basis and notice acknowledgment; it is not presented as freely withdrawable consent when service processing is necessary.
- **Child-data consent:** before activating a child record, capture written/electronic, explicit, unbundled consent from an active legal guardian. For the conservative MVP, guardian consent is required for every person under 18. `authorized_pickup` and `other` cannot grant child-data consent. Store append-only grant/withdrawal evidence: tenant, child, consenting guardian, notice/policy version, purpose/data categories, locale, method, staff actor, UTC time, branch/request correlation, and withdrawal time.
- **Marketing:** separate optional unchecked consent. Refusal never blocks service. Withdrawal is free and immediate. Consent/opt-out evidence is retained for at least three years; no marketing send is authorized until provider/licensing requirements are separately approved.
- **Retention:** active family operational data is retained while the relationship is active and for three years after the last visit or closure, then anonymized/deleted unless a documented legal, financial, safety, complaint, or litigation hold applies. Consent/audit evidence follows the longer applicable hold. The privacy notice states the period/criteria and data-subject rights.
- **Emergency and safety:** an active child must have an emergency contact name and normalized phone; the primary guardian may be reused rather than duplicating data. Safety notes are optional, capped, encrypted at rest, excluded from normal logs/exports, and available only to Owner/Branch Manager/Reception on a need-to-know basis. Child photos are deferred from M2.
- **Relationship lifecycle:** allow `mother`, `father`, `legal_guardian`, `authorized_pickup`, and `other`. Only the first three can provide child-data consent. Checkout authority is explicit and requires verified active relationship evidence. Linking/reactivation/revocation is transactional and audited; the final active checkout-capable legal-guardian relationship can never be revoked.
- **Visit history:** freeze a read-only, tenant-scoped, newest-first contract for Owner/Branch Manager/Reception with 25-item pagination. It exposes visit date, branch, status, start/end/duration, and masked ticket/receipt references only. Implementation is deferred until M3 supplies session records and does not block release of the M2 registry/profile aggregate.
- **OQ-20 incident module:** defer the conditional incident-management module beyond M2. Safety notes remain M2 data; incident categories/workflow are not invented by this decision.

**Implementation gate:** migrations, APIs, UI, masking, withdrawal, authorization, negative tests, and Arabic/English copy must implement this contract before M2 is marked complete. Production deployment additionally requires the operator's Legal/DPO approval of the actual notice text, retention schedule, controller/DPO contact details, processor/cross-border disclosures, and licensing obligations.

## 2026-09-12: Continue M2 with basic family-profile maintenance

**Authority:** The product owner asked to continue remaining milestones with another Luna/xhigh worker wave.
**Decision:** Deliver family detail, basic guardian/child correction, and adding one child to an existing guardian before consent or downstream visit flows. Use current-tenant route binding/query scope, existing fixed-role policy eligibility, allowlisted relationship types, optimistic `lock_version` checks, transactions, and audit events containing record IDs and changed field names only.
**Boundary:** No guardian merge/create-anyway path, consent evidence, emergency/safety notes, relationship revocation, visit history, ticket, check-in, or new permission key. Those require their own approved contract or later milestone.
**Rationale:** This advances BRQ-004 and US-CUS-003/004 without inventing OQ-17 or legal/privacy behavior and reuses the schema and UI vocabulary already accepted in T33-T35.

## 2026-09-12: Start M2 with a conservative family-registry slice

**Authority:** The product owner asked to begin M2 after confirming current readiness and requested three Luna/xhigh workers with coordinator review.
**Decision:** Build current-tenant family search and atomic guardian + child + active relationship creation first. Normalize Egyptian/local phone formats to E.164 where valid. If the same normalized phone already exists in the tenant, stop creation and guide staff to the existing record; a match in another tenant is neither returned nor treated as a local duplicate.
**Boundary:** This temporary safe behavior does not decide OQ-17's eventual merge or supervised duplicate flow. Consent events, policy wording, safety notes/photos, editing, visit history, tickets and check-in remain later slices. No unique phone constraint is added.

## 2026-09-12: Close M1 at the access-and-branch boundary

**Decision:** Mark M1 DONE after PHP 8.4/8.5, SQLite/MySQL 8.4.11, focused security, build/format/audit, documentation, and real bilingual browser gates pass.
**Rationale:** The milestone definition is fully represented by tenant/branch context, authentication, fixed policies, owner/platform administration, locale/timezone/currency settings, audit history, and the responsive shell. Guardian/child and later operational modules belong to M2–M6 and are not pulled into this closure.
**Boundary:** This is local milestone acceptance, not production readiness or authorization to start M2 without its approved contracts.

## 2026-08-24: Documentation-first Laravel baseline

**Decision:** Use an implementation-ready documentation pack and UI-first milestones before scaffolding application code.  
**Rationale:** Critical rules for money, time, tenant isolation, safety, and permissions must align before migrations and workflows are expensive to change.  
**Alternative:** Scaffold immediately from the PRD; rejected because unresolved rules would be embedded as accidental behavior.

## 2026-08-24: Modular monolith for MVP

**Decision:** Begin with one Laravel application and relational database.  
**Rationale:** It is the fastest operationally simple approach and preserves transactional integrity across sessions, POS, and audit.  
**Alternative:** Microservices; deferred until measured scale or independent release needs justify them.

## 2026-08-24: Shared-schema tenancy

**Decision:** Use shared-schema tenancy with explicit tenant scoping and constraints for MVP.  
**Rationale:** Lowest setup and operational cost while supporting the target model.  
**Alternative:** Database per tenant; revisit for contractual isolation, very large tenants, or regional data residency.

## 2026-08-24: Verified PRD source baseline

**Decision:** Treat the re-attached PRD as the same source baseline; both inputs are 27,198 bytes and have SHA-256 `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2`.  
**Rationale:** Prevents unnecessary document divergence while preserving a reproducible source check.

## 2026-08-24: Documentation formats

**Decision:** Deliver the project pack as Markdown, OpenAPI YAML, and standalone HTML only; do not generate Word/DOCX.  
**Rationale:** This matches the user's requested working format and keeps specifications diffable and implementation-friendly.

## 2026-08-24: Proposed fast implementation stack

**Decision:** Recommend PHP 8.5, Laravel 13, Blade/Livewire 4, Tailwind CSS 4, MySQL 8.4 LTS/InnoDB, and Pest 5, using Laravel-native auth, policies, queues, cache, scheduling, notifications, and storage. Confirm supported patch versions when scaffolding.  
**Rationale:** One conventional modular monolith is the shortest safe path to the pilot and preserves transactional correctness.  
**Alternative:** SPA, microservices, tenancy/permission frameworks, Redis, and broad infrastructure packages; defer until a measured need exists.

## 2026-08-24: Conditional module boundaries

**Decision:** Keep games/queues, parent self-service, marketing campaigns, cashier shifts, and other later-phase features out of MVP. Keep basic incident routes/tables/permissions/UI disabled unless OQ-20 is approved; keep cashier close out unless OQ-24 is approved.  
**Rationale:** These behaviors appear outside or ambiguously within the PRD's explicit MVP inventory and must not become hidden scope.

## 2026-08-24: Single canonical documentation directory

**Decision:** Keep every final specification and implementation guide directly under `docs/`, with supporting OpenAPI and wireframe assets in `docs/contracts/` and `docs/wireframes/`; remove the former nested documentation layer and its summary duplicates.  
**Rationale:** One canonical path prevents drift and makes the repository ready for day-to-day implementation work.  
**Alternative:** Maintain short summaries plus a detailed project pack; rejected because it created two sources for the same topics.

## 2026-08-25: Cool mineral operational visual baseline

**Decision:** Use a light, restrained product interface with cool porcelain surfaces, graphite text, and deep petrol teal reserved for primary actions, focus, and selection. Purple and cream are explicitly excluded. `DESIGN.md` is the canonical reusable visual and interaction baseline.  
**Rationale:** Reception and cashier users work in bright, busy environments and need a calm, high-contrast interface that feels modern without becoming an arcade theme.  
**Alternative:** Purple/cream styling, neon/playful entertainment styling, or a dark dashboard; rejected because they do not match the requested direction or reduce clarity and trust in safety and money workflows.

## 2026-09-10: Initial MVP decisions approved by product owner

The following initial decisions are approved for the MVP baseline:

- **OQ-23:** PlayNexus launches as branded SaaS only. White-label capability is deferred.
- **OQ-03:** MVP is online-only. Offline writes and conflict synchronization are deferred; operational outage fallback is a visible failure/read-only procedure.
- **OQ-06:** Initial market is Egypt; branch currency is EGP; default timezone is Africa/Cairo; Arabic and English are supported; tax behavior is configurable pending legal/accounting confirmation.
- **OQ-07:** Collect only the minimum data required for operations and safety; record consent/version events; support masking and deletion/anonymization where legally permitted; retain mandatory financial, safety, and audit evidence.
- **OQ-10:** Staff may be assigned to multiple branches with branch-scoped roles and deny-by-default authorization.
- **OQ-14:** Super Admin support access is denied by default and requires least-privilege, time-bound, reason-required, audited access.
- **OQ-01:** MVP records in-person payments only. Online gateway capture is deferred.
- **OQ-02:** QR/barcode input uses browser keyboard-input scanners. Proprietary wristband/scanner SDKs are deferred.
- **OQ-15:** Child date of birth is optional; the system may use an age/family-band value where needed, without requiring more precision than the approved purpose.

**Historical status at approval time:** OQ-08 receipt numbering/content, OQ-16 pricing, OQ-12 guardian verification, and remaining operational decisions were still open here. OQ-08, OQ-12, and OQ-16 were subsequently approved and synchronized in the canonical documents; later unresolved questions remain gated separately.

## 2026-09-12: Start M3 with immutable fixed-duration pricing configuration

**Authority:** The product owner asked to continue subsequent milestones with another Luna/xhigh worker wave.
**Decision:** Implement current-tenant/current-branch pricing-rule list and creation first. Rules are immutable versioned configuration: fixed duration, integer EGP base price, fixed 600-second grace, fixed 1,800-second overtime unit, integer EGP overtime price, and a snapshot of the selected branch tax rate/mode. No in-place update, calculation/checkout, or seeded business price.
**Authorization:** Active Tenant Owners manage active branches in their tenant. Active branch managers manage only their active assignments. Reception and cashier may view active rules only in their active assigned branches; they cannot create them. Custom `branches.view` alone grants neither pricing permission.
**Boundary:** Ticket types/issuance/QR remain blocked by OQ-18. Check-in, live sessions, tax-total calculation, extensions, retirement/version cloning, and M3 closure remain later slices.
**Rationale:** OQ-16 fixes the pricing shape, while OQ-18 still leaves ticket behavior unresolved. An immutable configuration slice advances M3 without inventing ticket or Finance examples.

## 2026-09-12: Replace pricing through immutable versions

**Decision:** A manager change retires the current active rule and creates the next numeric version in one transaction. Tenant, branch, and code are immutable; duration and integer EGP prices are re-entered, while fixed grace/overtime terms and current branch tax settings are snapshotted again. A stale or repeated submission returns conflict and creates neither another version nor another audit event.
**Boundary:** No edit-in-place, delete, backdating, scheduled activation, calculator, ticket, session, or tax-total claim.

## 2026-09-12: Narrow tenant-owner read representation

**Authority:** User authorized execution of the proposed three-worker owner-read wave; coordinator selected the narrow representation recommended as an option by T12.
**Decision:** Use explicit `tenant_owners(tenant_id,user_id)` with a composite primary key and tenant-aware user FK. No inference/backfill from branch roles. Initially authorize only own-tenant profile and staff-list reads via a native policy with fresh user, tenant and ownership state. Do not expand branch-owner or platform access. Full draft RBAC/schema remains a future target.
**Rationale:** Represent the required tenant-level ownership without building a general permission registry for one read operation. Exact route/view and worker contracts are in [the execution ledger](../docs/agent-plan.md).

## 2026-09-12: Owner branch access and bounded staff administration

User authorized the next three-feature wave. Explicit active tenant ownership now grants own-tenant active-branch read/selection without branch assignment. Owner-only management covers existing non-owner staff status and fixed branch-role assignments, not account creation/invitations or ownership transfer. Every mutation requires expected-state checks, a reason code, tenant-serialized transaction and atomic audit. Any owner account is excluded from management to protect ownership. Detailed contracts and file ownership: docs/agent-plan.md T18-T20. Remaining matrix permissions are not implicitly granted.

## 2026-09-12: Automatic reasons for routine staff and branch administration

**Authority:** The product owner explicitly requested removal of all reason selectors from employee and branch administration screens.
**Decision:** Staff status changes record `staffing_change`; branch-role assignments and branch lifecycle changes record `access_review`. These codes are generated by the server and client-supplied `reason_code` values are ignored. Expected-state checks, authorization, transactions, before/after snapshots, actor identity, and audit rows remain unchanged.
**Boundary:** This applies only to routine staff status, branch assignment, and branch activation/deactivation. It does not remove explicit reasons from refunds, guardian overrides, ticket cancellation, support access, tenant suspension, or other high-risk workflows that require user-provided context.

## 2026-09-12: Direct staff creation and bounded custom roles

**Authority:** The product owner requested direct employee creation, name/email search, and UI control of custom roles and permissions.
**Decision:** Tenant Owners create active staff accounts from name/email; the server generates an unknown random password and staff use the existing generic recovery flow. Owners may create tenant-specific roles and toggle only the implemented `branches.view` permission. A custom role appears in branch assignment only while that permission is enabled; built-in role maps remain fixed.
**Boundary:** No admin-chosen/shared password, invitation delivery, owner transfer, role deletion, or speculative permission registry. All writes remain tenant-scoped, transactional, audited, and deny foreign/custom-role escalation.
## 2026-09-12: Remediate M2 family authorization and disclosure

**Decision:** Separate family search/create/view, guardian update, child create/update, relationship management, and sensitive-data presentation at the policy boundary. Cashier keeps approved search, masked profile viewing, and initial atomic family registration, but cannot use generic guardian maintenance, add/edit children, or manage relationships. Owner, Branch Manager, and Reception retain the implemented maintenance slice. A single server-side presenter masks Cashier phone/email, omits locale, and returns age without full date of birth.
**Boundary:** These are native policy abilities, not new tenant-configurable permission keys. No sale-context Cashier correction, consent, emergency/safety data, merge, relationship endpoint, visit history, ticket, session, POS, or M3 expansion is introduced. Inactive guardians and children are excluded so search and profile state agree.
**Rationale:** This applies least privilege and minimum disclosure without inventing the future sale context or unresolved legal/product behavior.
