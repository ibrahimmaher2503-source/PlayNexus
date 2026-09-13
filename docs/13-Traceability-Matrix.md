# PlayNexus MVP Cross-Document Traceability Matrix

## 2026-09-13 check-in/session implementation evidence mapping

The bounded FR-SES-001/002/014, FR-TIM-001/003/007 display subset and OQ-11/OQ-16 arrival/estimate slice maps to `PlaySession`, `PlaySessionEvent`, `PlaySessionPolicy`, `SessionQuoteCalculator`, `GET /app/sessions`, `POST /app/sessions/check-in`, the bilingual live board, `SessionQuoteCalculatorTest`, `PlaySessionCheckInTest`, and the MySQL check-in race in `TicketConcurrencyTest`. It reuses FR-TKT validation/consumption and the approved family safety guards. Coordinator evidence is in `.ai/TEST_RESULTS.md`. Pause/extend/adjust/cancel/checkout, persisted final calculations, release verification, payment and refund traceability remain planned.

## 2026-09-13 ticket-only implementation evidence mapping

The approved OQ-18 ticket scope maps to `TicketType`, `Ticket`, `TicketScan`, `TicketPolicy`, the seven ticket-only first-party web routes documented at the top of `09-API-Specification.md`, the native ticket operation page, `TicketLifecycleTest`, and MySQL-only `TicketConcurrencyTest`. Coordinator results are recorded in `.ai/TEST_RESULTS.md`. That ticket-only wave did not itself close sessions; the separately accepted check-in/session mapping is documented above. Approval/payment/refund work remains open, and external API operationIds below remain planned rather than implemented `/api/v1` endpoints.

**Document version:** 1.1  
**Status:** Audited implementation handoff; product decisions remain open  
**Scope:** Phase 1 / MVP capability groups  
**Sources reviewed:** verified PRD baseline and documents 01–12, including `contracts/openapi.yaml` and the interactive wireframe

## 1. Purpose

This matrix connects each MVP capability to its business intent, verifiable requirements, backlog, operational flow, contract, data model, permissions, UI, and release evidence. It is a release navigation aid, not a replacement for the exact requirements in the SRS.

No endpoint, table, permission, screen, or test adds MVP scope by itself. Items marked **Conditional** or **Decision required** remain disabled or configurable only after the named owner closes the relevant open question.

## 2. Status and change-control legend

| Code | Meaning | Required handling |
|---|---|---|
| **B — Baselined** | The draft artifacts agree on the capability and its safety controls. | Implement after the pack is approved and attach evidence to the stable IDs. |
| **D — Decision required** | A source question changes behavior, schema, policy, or acceptance. | Do not invent a value; close the named OQ and update affected artifacts. |
| **CND — Conditional** | The PRD describes the behavior outside the explicit MVP inventory. | Keep migrations, routes, permissions, UI, and release gates disabled until scope is approved. |
| **F — Future/deferred** | PRD exclusion or later-phase capability. | Do not seed, expose, estimate as MVP, or build through a hidden dependency. |

Change-control order:

1. Approve business decision and update `01-PRD-Baseline.md`/`02-BRD.md`.
2. Update the exact SRS IDs and acceptance criteria.
3. Update stories/use cases, architecture, ERD, permissions, API/OpenAPI, UI, and tests together.
4. Update this matrix and `.ai/DECISIONS.md` in the same change.
5. A work item cites one capability key (`CG-nn`), exact SRS/story/use-case IDs, `operationId`, permission, aggregate, wireframe, and test evidence.

## 3. Product traceability

| Key | MVP capability group | Business baseline | SRS requirements | User stories | Use cases |
|---|---|---|---|---|---|
| CG-01 | Tenancy and branches | PRD-MVP-001–002; CAP-01–02; BRQ-001–002; BR-008–009 | FR-TEN-001–009; SEC-TEN-001; DATA-TEN-001–002; DATA-TIME-001; LOC-004–006 | US-TEN-001–004 | UC-01, UC-13, UC-14 |
| CG-02 | Staff, authentication, RBAC, approvals | PRD-MVP-003; CAP-03; BRQ-003, BRQ-010; BR-004, BR-008 | FR-AUT-001–005; FR-RBAC-001–007; SEC-AUT-001–002; SEC-RBAC-001 | US-AUT-001–002; US-RBAC-001–004 | UC-02, UC-07–UC-09, UC-14 |
| CG-03 | Guardians and children | PRD-MVP-004; CAP-04; BRQ-004; BR-001 | FR-CUS-001–009; DATA-REL-001; DATA-PII-001–002; SEC-PII-001–003 | US-CUS-001–005 | UC-03, UC-14 |
| CG-04 | Pricing rules and tickets | PRD-MVP-006–008; CAP-06–07; BRQ-007–008; BR-013, BR-015 | FR-TIM-001–002, FR-TIM-009; FR-TKT-001–008; DATA-VER-001 | US-TIM-001; US-TKT-001–003 | UC-04, UC-05 |
| CG-05 | Sessions and time operation | PRD-MVP-005–006; CAP-05–06; BRQ-005–007; BR-002–004, BR-011–014 | FR-SES-001–007, FR-SES-011–014; FR-TIM-003–009; DATA-INT-001 | US-SES-001–007; US-TIM-002 | UC-04–UC-06 |
| CG-06 | Checkout and child-release safety | PRD-MVP-005; PRD-XCUT-002; CAP-05, CAP-12; BRQ-010, BRQ-012; BR-005, BR-019 | FR-SES-008–011, FR-SES-014; FR-SAF-001–002; FR-RBAC-005; SEC-AUD-001 | US-SES-008–010; US-SAF-003 | UC-06, UC-07 |
| CG-07 | POS, payments, receipts, refunds | PRD-MVP-008–009; CAP-08–09; BRQ-009–011; BR-006–007, BR-016–018 | FR-POS-001–013; DATA-FIN-001; DATA-MNY-001; DATA-NUM-001; INT-PAY-001–002 | US-POS-001–006 | UC-06, UC-08, UC-09 |
| CG-08 | Core reports | PRD-MVP-010; CAP-10; BRQ-013 | FR-RPT-001–008; DATA-AUD-001; NFR-PERF-002 | US-RPT-001–004 | UC-10 |
| CG-09 | Operational notifications | PRD-MVP-011; CAP-11; BRQ-014; BR-020 | FR-NOT-001–007; INT-NOT-001–003; NFR-AVL-002 | US-NOT-001–003 | UC-11 |
| CG-10 | Audit and conditional incidents | PRD-MVP-010; PRD-XCUT-002–003; CAP-12; BRQ-015–016; BR-019 | FR-AUD-001–005; FR-SAF-003–006 conditional on OQ-20; DATA-AUD-001; SEC-AUD-001 | US-AUD-001; US-SAF-001–002 conditional | UC-12 conditional; UC-14 |

## 4. Implementation traceability

API paths are relative to `/api/v1`. The OpenAPI contract contains 56 paths, 70 operations, and 93 schemas and passes both the repository validator and `openapi-spec-validator`. The two incident paths carry `x-release-scope: conditional-OQ-20`.

| Key | API operations (`operationId`) | Principal ERD aggregates | Permission/UI | Release evidence | Status |
|---|---|---|---|---|---|
| CG-01 | `listTenants`, `createTenant`, `updateTenant`; `listBranches`, `createBranch`, `getBranch`, `updateBranch` | `plans`, `tenants`, `subscriptions`, `branches`, `branch_opening_hours` | Matrix §2; WF-02, WF-11, WF-12 | T-CW-001, T-CW-011, T-CW-015 | **PARTIAL:** OQ-06 launch configuration is approved; OQ-04 still gates plans/limits. |
| CG-02 | `login`, `logout`, `getCurrentUser`, `requestPasswordReset`, `resetPassword`; `listStaff`, `createStaff`, `updateStaff`; approval list/request/approve/reject operations | `users`, RBAC tables, `user_branch_assignments`, `approval_records` | Matrix §3/§9/§11; WF-01, WF-10 | authorization charter; T-CW-006, T-CW-009, T-CW-015 | **PARTIAL:** OQ-10/OQ-14 are approved; OQ-22 still gates exact security timings/retention. |
| CG-03 | guardian search/create/get/update; child search/get/update; `createGuardianChild`, `linkGuardianChildRelationship` | `guardians`, `children`, `guardian_children`, `audit_logs` | Matrix §4/§10; WF-04 | T-CW-002, T-CW-015 | **IMPLEMENTED / RELEASE PARTIAL:** OQ-07/OQ-15/OQ-17 are approved; production Legal/DPO and deferred history/retention execution remain. |
| CG-04 | `listPricingRules`, `createPricingRule`; ticket-type list/create/update; ticket issue/get/scan/cancel/reprint | `pricing_rules`, `pause_reasons`, `ticket_types`, `tickets`, `ticket_scans` | Matrix §5/§9; WF-05, WF-13 | T-CW-003, T-CW-007, T-CW-016 | **READY:** OQ-16 fixes pricing shape; OQ-18 fixes branch/service-date scope, first-scan transfer lock, and unused-ticket refund eligibility. Tax fixtures and OQ-09 remain downstream finance gates. |
| CG-05 | session list/get/check-in/pause/resume/extend/cancel/adjust; `quoteSessionCheckout` | `sessions`, `session_pauses`, `session_extensions`, `session_adjustments`, `session_events`, `idempotency_requests` | Matrix §5/§9; WF-05, WF-06, WF-07 | T-CW-003–004, T-CW-007, T-CW-013–014, T-CW-019 | **PARTIAL:** OQ-11/OQ-16 support accepted M3 arrival/estimate; later M4 operations are not started and OQ-19 remains open. |
| CG-06 | `checkoutSession`; checkout quote; approval request/approve/reject | `guardian_children`, `sessions`, `approval_records`, `orders`, `payments`, `audit_logs` | Matrix §5/§9–10; WF-07 | T-CW-005–006, T-CW-014 | **B/D:** OQ-12 evidence is approved; OQ-19 still selects station/handoff topology. M4 is not started. |
| CG-07 | product list/create/update; order create/get/update; `recordOrderPayment`, `executeOrderRefund`, `getOrderReceipt`, `resendOrderReceipt` | `products`, `orders`, `order_items`, `payments`, `refunds`, `branch_sequences` | Matrix §6/§9–10; WF-07, WF-08 | T-CW-005, T-CW-008–010, T-CW-014 | **B/D:** OQ-01/OQ-06/OQ-08 are approved; OQ-09 and OQ-24 still gate refund and cashier-close behavior. |
| CG-08 | revenue, attendance, session, and staff-activity report operations; `listAuditLogs` for authorized evidence | committed `orders`, `payments`, `refunds`, `sessions`, `audit_logs`; no reporting mart | Matrix §8; WF-09 | T-CW-012, T-CW-015; performance charter | **B/D:** OQ-06/OQ-08 are approved; OQ-21 still defines measurable load/range. |
| CG-09 | notification list/send/resend; `applyNotificationProviderStatus` | `notification_messages`, `notification_attempts`, `notification_provider_events` | Matrix §8; WF-14 | T-CW-017 | **B/D:** canonical codes align; OQ-05/OQ-13 select providers, channels, lead time, retry, and escalation. Marketing remains future/unseeded. |
| CG-10 | `listAuditLogs`; conditional incident list/create/update | `audit_logs`, `platform_audit_logs`; conditional `incidents`, `incident_updates` | Matrix §7–§11; WF-15 conditional | T-CW-006, T-CW-010, T-CW-015; T-CW-018 only if approved | **B/CND/D:** audit is MVP; incident tables/routes/permissions/UI/tests stay disabled until OQ-20 is approved. |

## 5. Audit findings and remaining decisions

### 5.1 Consistency findings closed in this audit

| Finding | Resolution |
|---|---|
| New PRD attachment might differ from the original input | Closed: both files are 27,198 bytes with SHA-256 `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2`. |
| Required MVP contract operations were missing | Closed: staff/reset, relationship, pricing, ticket lifecycle, session lifecycle, approvals, catalog/cart, receipts, reports, notifications, and incident-conditional operations are present. |
| Games/queues and cashier shifts leaked into MVP artifacts | Closed: routes/tables/seeds are absent; OQ-24 decides minimal shift close. |
| Partial refund/payment behavior conflicted | Closed for planning: API/ERD implement one full payment and full refund only, explicitly pending OQ-09; no partial amount is accepted by the refund request. |
| Ticket, notification, and incident names differed | Closed: `consumed`; `queued/sending/sent/delivered/failed_retryable/failed_permanent/stale`; and `open/under_review/closed`. UI-label mappings are documented. |
| DOB, duplicate, guardian verification, currency, receipt, and incident designs appeared approved | Closed: each is labeled proposed/conditional and the affected migration freeze is blocked by its OQ. |
| Checkout/payment topology was accidentally selected | Closed: quote/order, payment, and completion are separate idempotent/recoverable boundaries pending OQ-19. |
| Testing used legacy identifiers and missed lifecycle evidence | Closed: T-CW-001–019 use current SRS/story/use-case IDs; ticket, notification, incident-conditional, cancellation, adjustment, and handoff tests are explicit. |
| Wireframe smoke expected only 11 screens | Closed: prototype and smoke test both cover 15 screens, including LTR/RTL and responsive navigation. |

### 5.2 Open decision register

| Decision group | OQs | Blocks or changes |
|---|---|---|
| Payment and physical devices | OQ-01–OQ-03 | gateway/provider scope, hardware acceptance, offline architecture |
| Commercial plans and notification vendors | OQ-04–OQ-05 | plan limits, channels, adapters, cost and callbacks |
| Launch localization, privacy, tax, receipts | OQ-06–OQ-08 | schema fields, retention, fiscal math, numbering/content, test matrix |
| Refunds, role/branch assignments, capacity | OQ-09–OQ-11 | financial states, RBAC model, check-in concurrency/override policy |
| Checkout identity, alerts, support access | OQ-12–OQ-14 | child-release evidence, retry/escalation, break-glass behavior |
| Child/duplicate/pricing/ticket/checkout rules | OQ-15–OQ-19 | migration shapes, worked examples, ticket eligibility, station topology |
| Incident scope and policy | OQ-20 | whether the conditional incident vertical slice ships at all |
| Measurable scale/security configuration | OQ-21–OQ-22 | performance dataset/targets, timeouts, rate limits, retention |
| White-label and cashier close | OQ-23–OQ-24 | confirms exclusions or adds separately baselined scope |

## 6. Minimum implementation handoff checklist

- [ ] Stakeholders approve the pack or record requested changes; approval is not inferred from document completeness.
- [ ] G1 decisions that affect the first vertical slices are closed in `.ai/DECISIONS.md`.
- [ ] Conditional OQ-20 incident artifacts are either enabled together or kept disabled together.
- [ ] Every active story names its CG/SRS/use-case/API/permission/table/wireframe/test links.
- [ ] Contract tests prove tenant/branch scope, permission, validation, state conflict, idempotency, concurrency, and privacy-safe errors for each mutation.
- [ ] T-CW-001–019 required for the approved pilot scope pass with real results recorded in `.ai/TEST_RESULTS.md`.
- [ ] No deferred game/queue, parent portal, shift, marketing, membership, birthday, inventory/HR, franchise, AI, marketplace, white-label, offline-sync, split/partial-payment, or gateway-capture capability is exposed without approved change control.
