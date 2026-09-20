# PlayNexus MVP Cross-Document Traceability Matrix

## 2026-09-15 current implementation reconciliation

GAP-02–06 and GAP-09 map to the locale submit fix, branch-manager Reception/Cashier administration, occurrence-based revenue breakdowns and branch currency/timezone rendering, authentication/security-denial audit, and immutable session-history detail. Current identifiers are bigint and current idempotency is workflow-specific. Historical references to `pause_reasons`, provider events, generic idempotency, or external operationIds remain gated targets rather than implemented schema/routes. `plans`, `subscriptions`, and `subscription_billing_records` implement the approved bounded commercial catalog and pass SQLite feature/domain tests; MySQL concurrency and real-browser acceptance remain unverified, and automated recurring billing remains deferred.

## 2026-09-15 M6 implementation mapping

| Capability | Implemented evidence | Focused verification |
|---|---|---|
| CAP-10 / FR-RPT-001–007 | `ReportController`, `reports/index.blade.php`, `routes/reports.php` | `M6ReportsTest` |
| CAP-11 / FR-NOT-001–007 | notification migration/models, collector, queued local processor, scheduler, masked status UI | `M6OperationalNotificationTest` |
| CAP-12 / FR-AUD-003–005 | extended scoped audit filters/actions, half-open date range and filtered CSV | `AuditLogViewTest` plus M6 browser/security checks |
| M6 recovery/pilot | `M6PilotSeeder`, `22-M6-Operations-Runbook.md` | `M6PilotSeederTest`, migration and isolated restore evidence |

OQ-05/OQ-13 channel and retry behavior is approved; real provider delivery/callbacks await DEC-NOT-01–04. OQ-20 incidents are intentionally absent by deferment. OQ-21 targets and the OQ-22 baseline are approved, while deployment evidence and DEC-SEC-01–03 remain separate.

## 2026-09-15 M5 remediation mapping — engineering accepted

FR-POS-001/004 maps to `PosController`, `PosPolicy`, catalog/cart UI and `PosCatalogTest`. FR-POS-002/006/007 maps to `ProcessOrdinaryPosOrder`, its controller/routes and `OrdinaryPosOrderPaymentTest`, including persisted server-priced lines and atomic cash posting/ticket issuance. FR-POS-005 maps to `DiscountApprovalAction`, its policy, the visible request/review/approved-payment states and `DiscountApprovalTest`; only payment consumes a persisted-cart-bound approval. FR-POS-008/009 maps to `SettlePendingSession`, `ReceiptController`, immutable receipt snapshots and `ReceiptTest`. FR-POS-010/011 maps to `ProcessCashRefund`, `RefundPolicy`, composite financial links and `CashRefundTest`, including ticket invalidation and separate refund annotation.

The integrated SQLite suite and authenticated bilingual browser journey pass. The full isolated MySQL 8.4.11/InnoDB suite and `M5FinancialConcurrencyTest` also pass, including real duplicate settlement and refund execution contention. Production approval is separate. Historical pre-M5 finance exclusions below describe their dated slices only.

## 2026-09-13 check-in/session implementation evidence mapping

The bounded FR-SES-001/002/014, FR-TIM-001/003/007 display subset and OQ-11/OQ-16 arrival/estimate slice maps to `PlaySession`, `PlaySessionEvent`, `PlaySessionPolicy`, `SessionQuoteCalculator`, `GET /app/sessions`, `POST /app/sessions/check-in`, the bilingual live board, `SessionQuoteCalculatorTest`, `PlaySessionCheckInTest`, and the MySQL check-in race in `TicketConcurrencyTest`. It reuses FR-TKT validation/consumption and the approved family safety guards. Coordinator evidence is in `.ai/TEST_RESULTS.md`. Pause/extend/adjust/cancel/checkout, persisted final calculations, release verification, payment and refund traceability remain planned.

## 2026-09-13 ticket-only implementation evidence mapping

The approved OQ-18 ticket scope maps to `TicketType`, `Ticket`, `TicketScan`, `TicketPolicy`, the seven ticket-only first-party web routes documented at the top of `09-API-Specification.md`, the native ticket operation page, `TicketLifecycleTest`, and MySQL-only `TicketConcurrencyTest`. Coordinator results are recorded in `.ai/TEST_RESULTS.md`. That ticket-only wave did not itself close sessions; the separately accepted check-in/session mapping is documented above. Approval/payment/refund work remains open, and external API operationIds below remain planned rather than implemented `/api/v1` endpoints.

**Document version:** 1.2
**Status:** M5 local engineering acceptance mapped; production gates remain separate
**Scope:** Phase 1 / MVP capability groups  
**Sources reviewed:** verified PRD baseline and documents 01–12, including `contracts/openapi.yaml` and the interactive wireframe

## 1. Purpose

This matrix connects each MVP capability to its business intent, verifiable requirements, backlog, operational flow, contract, data model, permissions, UI, and release evidence. It is a release navigation aid, not a replacement for the exact requirements in the SRS.

No endpoint, table, permission, screen, or test adds MVP scope by itself. Deferred or decision-dependent items remain disabled until the named owner approves a later scope change; preserved historical proposals do not override `.ai/DECISION_REGISTER.md`.

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
| CG-01 | Implemented `/app` tenant/branch administration; external operationIds remain target-only | `tenants`, `branches`, `branch_opening_hours`, `plans`, `subscriptions`, `subscription_billing_records` | Matrix §2; WF-02, WF-11, WF-12 | OQ-04 focused suite: 27 tests / 142 assertions; full SQLite: 437 / 433 passed / 4 skips | **PARTIAL acceptance:** bounded editable plans/subscriptions, limits, lifecycle, UIs and manual billing are implemented and SQLite-green; MySQL concurrency and browser acceptance remain unverified. Recurring billing remains deferred. |
| CG-02 | `login`, `logout`, `getCurrentUser`, `requestPasswordReset`, `resetPassword`; `listStaff`, `createStaff`, `updateStaff`; approval list/request/approve/reject operations | `users`, RBAC tables, `user_branch_assignments`, `approval_records` | Matrix §3/§9/§11; WF-01, WF-10 | authorization charter; T-CW-006, T-CW-009, T-CW-015 | **PARTIAL:** OQ-10/OQ-14 and the OQ-22 baseline are approved; DEC-SEC-01–03 and production security validation remain. |
| CG-03 | guardian search/create/get/update; child search/get/update; `createGuardianChild`, `linkGuardianChildRelationship` | `guardians`, `children`, `guardian_children`, `audit_logs` | Matrix §4/§10; WF-04 | T-CW-002, T-CW-015 | **IMPLEMENTED / RELEASE PARTIAL:** OQ-07/OQ-15/OQ-17 are approved; production Legal/DPO and deferred history/retention execution remain. |
| CG-04 | Implemented `/app` pricing/ticket commands | `pricing_rules`, `ticket_types`, `tickets`, `ticket_scans`; no `pause_reasons` | Matrix §5/§9; WF-05, WF-13 | T-CW-003, T-CW-007, T-CW-016 | **LOCAL READY:** immutable pricing/tickets implemented; pause excluded. |
| CG-05 | Implemented session list/check-in/extend/cancel/adjust/checkout/settlement | `play_sessions`, `play_session_adjustments`, `play_session_events`, workflow command/idempotency records | Matrix §5/§9; WF-05, WF-06, WF-07 | T-CW-003–005, T-CW-007, T-CW-013–014, T-CW-019 | **ENGINEERING ACCEPTED:** OQ-19 flow and MySQL contention evidence pass. |
| CG-06 | checkout preparation; matching payment/completion; approval request/approve/reject | `guardian_children`, `sessions`, `approval_records`, `orders`, `payments`, `audit_logs` | Matrix §5/§9–10; WF-07 | T-CW-005–006, T-CW-009, T-CW-014 | **LOCAL READY:** approved OQ-12/OQ-19 flow is implemented and SQLite/browser accepted. |
| CG-07 | product list/create/retire; order create/payment; discount approval; refund; receipt retrieval/reprint | `products`, `orders`, `order_items`, `payments`, `refunds`, `branch_sequences` | Matrix §6/§9–10; WF-07, WF-08 | T-CW-005, T-CW-008–010, T-CW-014 | **ENGINEERING ACCEPTED:** OQ-01/OQ-06/OQ-08/OQ-09 and real MySQL financial contention are accepted; OQ-24 shifts are deferred. |
| CG-08 | revenue, attendance, session, and staff-activity report operations; `listAuditLogs` for authorized evidence | committed `orders`, `payments`, `refunds`, `sessions`, `audit_logs`; no reporting mart | Matrix §8; WF-09 | T-CW-012, T-CW-015; performance charter | **B/D:** OQ-06/OQ-08/OQ-21 targets are approved; deployed workload fixtures and measurements remain release evidence. |
| CG-09 | Implemented local intent/list/queued processing; provider send/resend/callback target-only | `notification_messages`, `notification_attempts`; no provider events | Matrix §8; WF-14 | T-CW-017 | **PARTIAL BY DECISION:** local transport accepted; OQ-05/OQ-13 gate real providers. |
| CG-10 | `listAuditLogs`; deferred incident list/create/update proposal | `audit_logs`, `platform_audit_logs`; no incident tables in Egypt V1 | Matrix §7–§11; WF-15 deferred | T-CW-006, T-CW-010, T-CW-015; T-CW-018 deferred | **B/D:** audit is MVP; approved OQ-20 keeps incident tables/routes/permissions/UI/tests absent. |

## 5. Audit findings and remaining decisions

### 5.1 Consistency findings closed in this audit

| Finding | Resolution |
|---|---|
| New PRD attachment might differ from the original input | Closed: both files are 27,198 bytes with SHA-256 `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2`. |
| Required MVP contract operations were missing | Closed: staff/reset, relationship, pricing, ticket lifecycle, session lifecycle, approvals, catalog/cart, receipts, reports, notifications, and incident-conditional operations are present. |
| Games/queues and cashier shifts leaked into MVP artifacts | Closed: routes/tables/seeds are absent and OQ-24 explicitly defers shifts. |
| Partial refund/payment behavior conflicted | Closed: OQ-09 implements one exact cash payment and at most one full eligible refund; no partial amount is accepted. |
| Ticket, notification, and incident names differed | Closed: `consumed`; `queued/sending/sent/delivered/failed_retryable/failed_permanent/stale`; and `open/under_review/closed`. UI-label mappings are documented. |
| DOB, duplicate, guardian verification, currency, receipt, and incident designs appeared approved | Closed: each is labeled proposed/conditional and the affected migration freeze is blocked by its OQ. |
| Checkout/payment topology was accidentally selected | Closed: OQ-19 approves Reception/Manager preparation followed by atomic Cashier settlement/completion. |
| Testing used legacy identifiers and missed lifecycle evidence | Closed: T-CW-001–019 use current SRS/story/use-case IDs; ticket, notification, incident-conditional, cancellation, adjustment, and handoff tests are explicit. |
| Wireframe smoke expected only 11 screens | Closed: prototype and smoke test both cover 15 screens, including LTR/RTL and responsive navigation. |

### 5.2 Decision register

| Decision group | OQs | Blocks or changes |
|---|---|---|
| Payment and physical devices | OQ-01–OQ-03 | gateway/provider scope, hardware acceptance, offline architecture |
| Commercial plans and notification vendors | OQ-04–OQ-05 | plan limits, channels, adapters, cost and callbacks |
| Launch localization, privacy, tax, receipts | OQ-06–OQ-08 | schema fields, retention, fiscal math, numbering/content, test matrix |
| Refunds, role/branch assignments, capacity | OQ-09–OQ-11 | OQ-09–OQ-11 approved; changes require synchronized financial/RBAC/capacity updates |
| Checkout identity, alerts, support access | OQ-12–OQ-14 | child-release evidence, retry/escalation, break-glass behavior |
| Child/duplicate/pricing/ticket/checkout rules | OQ-15–OQ-19 | approved baseline fixes migration shapes, ticket eligibility and station topology |
| Incident scope and policy | OQ-20 | explicitly deferred; reopening needs a new approved incident contract |
| Measurable scale/security configuration | OQ-21–OQ-22 | approved targets/baseline plus DEC-SEC-01–03 and environment release evidence |
| White-label and cashier close | OQ-23–OQ-24 | OQ-24 defers shifts/drawer balancing; later inclusion needs a separate baseline |

## 6. Minimum implementation handoff checklist

- [ ] Stakeholders approve the pack or record requested changes; approval is not inferred from document completeness.
- [ ] G1 decisions that affect the first vertical slices are closed in `.ai/DECISIONS.md`.
- [x] OQ-20 incident artifacts remain disabled together under the approved Egypt V1 deferment.
- [ ] Every active story names its CG/SRS/use-case/API/permission/table/wireframe/test links.
- [ ] Contract tests prove tenant/branch scope, permission, validation, state conflict, idempotency, concurrency, and privacy-safe errors for each mutation.
- [ ] T-CW-001–019 required for the approved pilot scope pass with real results recorded in `.ai/TEST_RESULTS.md`.
- [ ] No deferred game/queue, parent portal, shift, marketing, membership, birthday, inventory/HR, franchise, AI, marketplace, white-label, offline-sync, split/partial-payment, or gateway-capture capability is exposed without approved change control.
