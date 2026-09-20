# PlayNexus PRD Baseline

**Document version:** 1.1  
**Status:** Audited source baseline; not an approval record for derived design choices  
**Source:** User-supplied PlayNexus Product Requirements Document v1.0  
**Source date:** June 2026  
**Audit date:** August 2026  
**Verified source SHA-256:** `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2`  
**Purpose:** Preserve source intent and distinguish direct PRD commitments from proposed MVP interpretations used in documents 02 onward.

## 1. Interpretation status

| Label | Meaning |
|---|---|
| **Source** | Directly stated by the PRD. Wording may be normalized without changing meaning. |
| **Derived control** | Necessary decomposition of a Source requirement, such as atomicity or server-side authorization. It is proposed until the appropriate approval gate accepts the SRS. |
| **Assumption** | Working position used to estimate or design; it is not an approved product decision. |
| **Open Decision** | Source ambiguity or unresolved choice that must be decided by the named owner. |
| **Deferred** | Explicit PRD exclusion or capability placed in a later roadmap phase. |

No feature becomes MVP scope merely because a later document contains an endpoint, table, role permission, wireframe, or test.

## 2. Product definition and business intent — Source

PlayNexus is a cloud-based, multi-tenant, multi-branch SaaS operating system for kids areas, indoor playgrounds, family entertainment centers, trampoline parks, soft-play centers, children cafes, and party venues across MENA.

- Digitize venue operations.
- Reduce manual errors in time, billing, ticketing, and checkout verification.
- Increase revenue through accurate tracking and later upsell/retention capabilities.
- Improve parent transparency, notifications, trust, and online experience.
- Support multi-branch and future franchise operation.
- Enable SaaS subscription revenue.
- Prepare for marketplace, AI analytics, and white-label expansion without including those features in MVP.

## 3. Source roles and MVP participation

| Source role | PRD responsibility | MVP participation status |
|---|---|---|
| Super Admin | Tenants, plans/subscriptions, global settings and analytics | **Source:** tenant/platform control; detailed subscription billing and global analytics are not listed in MVP and require scope confirmation. |
| Tenant Owner | Company, branches, roles, settings, consolidated reports | **Source MVP actor.** |
| Branch Manager | Daily operations, staff, revenue, attendance, capacity, overrides, refund approval | **Source MVP actor** for applicable core operations. Booking/game-specific duties are later phase unless approved. |
| Reception Staff | Family registration, sessions, tickets, checkout | **Source MVP actor.** |
| Cashier | Ticket/product sale, payment record, permitted discounts, receipts | **Source MVP actor.** |
| Game Operator | Games, activities, queues, incidents, maintenance requests | **Source role; Deferred for Egypt V1** for game/queue operations and incident management under approved OQ-20. |
| Parent/Guardian | Own/child profiles, bookings, time, notifications, payment history | **Source target role; Assumption ASM-06:** MVP is staff-assisted with notifications/receipts and no authenticated parent portal. |

## 4. Exact MVP capability inventory

| Baseline ID | Source MVP commitment | Downstream implementation family |
|---|---|---|
| PRD-MVP-001 | Tenant account creation and business profile setup. | CAP-01; FR-TEN |
| PRD-MVP-002 | Branch setup and branch-level settings. | CAP-02; FR-TEN |
| PRD-MVP-003 | Staff users and role-based permissions. | CAP-03; FR-AUT, FR-RBAC |
| PRD-MVP-004 | Parent registration and child registration. | CAP-04; FR-CUS |
| PRD-MVP-005 | Check-in session creation and checkout flow. | CAP-05; FR-SES, FR-SAF |
| PRD-MVP-006 | Smart time calculation with Active, Paused, Completed, and Cancelled statuses (historical source wording; Egypt MVP superseded by the approved no-pause lifecycle). | CAP-06; FR-TIM, FR-SES |
| PRD-MVP-007 | Basic ticketing and QR generation. | CAP-07; FR-TKT |
| PRD-MVP-008 | Basic POS for tickets, F&B, merchandise, and add-ons. | CAP-08; FR-POS |
| PRD-MVP-009 | Payment recording and digital receipts. | CAP-08–09; FR-POS |
| PRD-MVP-010 | Daily revenue, attendance, session history, and staff activity logs/reports. | CAP-10, CAP-12; FR-RPT, FR-AUD |
| PRD-MVP-011 | Basic notifications for session ending and receipts. | CAP-11; FR-NOT |

### 4.1 Cross-cutting source obligations

| Baseline ID | Source obligation | MVP handling |
|---|---|---|
| PRD-XCUT-001 | Tenant isolation is mandatory. | Required across UI, API, data, files, jobs, caches, and reports. |
| PRD-XCUT-002 | RBAC, audit logs, guardian matching, controlled checkout, and override tracking are required by the business rules/module specifications/NFRs. | Required wherever the applicable MVP flow uses them. |
| PRD-XCUT-003 | Basic incident records are described in Safety & Security and Game Operator responsibilities but are not named in §5.1 MVP or Phase 1 deliverables. | **Deferred for Egypt V1 by approved OQ-20.** Restricted safety notes remain; incident UI/API/routes/tables remain absent until a later approved contract. |
| PRD-XCUT-004 | Branch capacity is a configuration requirement; game-level capacity/queues appear in a later operational module. | Store branch capacity in MVP. Approved OQ-11 requires a hard, non-overridable transactional check-in limit; game/queue/participation management remains Deferred. |
| PRD-XCUT-005 | The POS module specification mentions daily shift close, while §5.1 and Phase 1 describe only “basic POS.” | **Deferred for Egypt V1 by approved OQ-24.** Payment records retain tenant, branch, cashier, and server time without implying a drawer workflow. |

## 5. Explicit exclusions and phase boundaries

| Baseline ID | Source boundary | Status |
|---|---|---|
| PRD-EXC-001 | Native mobile applications | Deferred |
| PRD-EXC-002 | AI assistant and predictive analytics | Deferred |
| PRD-EXC-003 | Advanced loyalty and referral engine | Deferred |
| PRD-EXC-004 | Marketplace | Deferred |
| PRD-EXC-005 | White-label mobile applications | Deferred |
| PRD-EXC-006 | Advanced inventory and HR modules | Deferred |
| PRD-EXC-007 | Franchise management | Deferred |
| PRD-EXC-008 | External accounting integration | Deferred |

Additional phase boundaries stated by the roadmap:

| Capability | Source phase | MVP implication |
|---|---|---|
| Memberships, loyalty, birthday bookings, CRM/marketing automation, WhatsApp/SMS growth campaigns | Phase 2 — Growth | Deferred except MVP operational ending/receipt notifications. |
| Inventory, employee management, maintenance, advanced reports, franchise controls | Phase 3 — Advanced Operations | Deferred. Staff identity/RBAC remains MVP; full HR does not. |
| AI, marketplace, white-label, parent mobile apps | Phase 4 — Intelligence & Ecosystem | Deferred. |
| Games/queues/time slots | Described in module specifications but absent from Phase 1 deliverables | **Deferred unless scope change is approved.** Branch capacity configuration is retained under PRD-MVP-002. |

## 6. Source functional requirements FR-001 through FR-012

| Source ID | Source requirement | Source priority | MVP disposition | Derived SRS family |
|---|---|---|---|---|
| FR-001 | Support multi-tenant account creation and tenant isolation. | Must | Included | FR-TEN; SEC-TEN; DATA-TEN |
| FR-002 | Allow each tenant to create/manage multiple branches. | Must | Included | FR-TEN |
| FR-003 | Support RBAC for all staff users. | Must | Included | FR-AUT; FR-RBAC; SEC-RBAC |
| FR-004 | Allow staff to register parent and child profiles. | Must | Included | FR-CUS |
| FR-005 | Create/manage check-in and checkout sessions. | Must | Included | FR-SES; FR-SAF |
| FR-006 | Calculate session billing from pricing rules, duration, pauses, and extensions. | Must | Included | FR-TIM; FR-SES |
| FR-007 | Generate QR codes or barcodes for session tickets. | Must | Included as QR-first baseline; barcode is an allowed alternative, not an additional mandatory format. | FR-TKT; INT-HW |
| FR-008 | Provide POS for tickets, F&B, merchandise, and extra games. | Must | Included for tickets, F&B, merchandise, and approved add-ons. Game-operation/participation management is Deferred. | FR-POS |
| FR-009 | Support payment recording and digital receipts. | Must | Included | FR-POS; DATA-FIN; DATA-NUM |
| FR-010 | Generate daily revenue, attendance, and session reports. | Must | Included; MVP list also explicitly includes staff activity logs. | FR-RPT; FR-AUD |
| FR-011 | Support memberships and loyalty in future phases. | Should | **Deferred; not an MVP Should.** | None in MVP |
| FR-012 | Support parent mobile app and marketplace in future phases. | Could | **Deferred.** | None in MVP |

The source IDs above remain valid PRD trace IDs. The 2026-09-13 approved Egypt M4 amendment supersedes pause/resume in the current release; the original source wording remains here only as historical provenance. The detailed SRS uses family IDs to avoid redefining or silently changing the source statements.

## 7. Core business rules BR-001 through BR-010 — Source

| Rule ID | Preserved source rule | MVP disposition |
|---|---|---|
| BR-001 | Every child must be linked to at least one parent or guardian. | Included |
| BR-002 | A session cannot start without a branch, child, ticket/pricing rule, and staff user. | Included; the session module also requires a start time as stored session data. |
| BR-003 | Session statuses include Active, Paused, Completed, and Cancelled. | **Historical source wording; superseded for Egypt MVP by `active`, `pending_payment`, `completed`, `cancelled`.** |
| BR-004 | Only authorized users may pause, resume, cancel, refund, or manually adjust a session. | **Historical source wording; pause/resume is deferred for Egypt MVP; supported actions remain authorized extension, adjustment, cancellation, refund, and checkout settlement.** |
| BR-005 | Checkout validates the parent-child relationship or records a manager-approved override. | Included; verification method remains OQ-12. |
| BR-006 | Discounts above the configured threshold require manager approval. | Included |
| BR-007 | Refunds require a reason and must be included in audit reports. | Included; refund type/window remains OQ-09. |
| BR-008 | Tenant data must be fully isolated from other tenants. | Included |
| BR-009 | Inactive branches cannot create new sessions. | Included |
| BR-010 | Marketing notifications must respect consent and opt-out settings. | Data/permission control retained; marketing sending itself is Deferred from MVP. |

Rules BR-011 onward in the BRD are **Proposed derived controls**, not source rules.

## 8. Source non-functional requirements

| Source category | Preserved source requirement | Derived SRS IDs | Approval note |
|---|---|---|---|
| Performance | Standard actions within 2 seconds under normal load; standard-range dashboard reports within 5 seconds. | NFR-PERF-001–004 | Approved OQ-21 supersedes these source targets with p95 targets; the production workload fixture and measurement evidence remain a release gate. |
| Availability | Target 99.9% uptime; critical peak operations stable. | NFR-AVL-001–002 | SLA exclusions/measurement remain to be approved. |
| Scalability | Support growth in tenants, branches, users, families, sessions, transactions, and reports. | NFR-SCA-001–002; NFR-PERF-003 | OQ-21 targets are approved; environment-specific workload counts remain release evidence. |
| Security | RBAC, secure password hashing, encryption in transit, tenant isolation, and audit logs. | SEC-*; FR-RBAC; FR-AUD | Detailed controls are derived security requirements subject to security approval. |
| Usability | Reception/cashier workflows use minimal steps and suit non-technical staff. | NFR-USA-001–003; NFR-ACC-001 | Accessibility specifics are a Proposed quality control, not stated verbatim in PRD. |
| Localization | Prepare for English/Arabic UI, regional currencies, taxes, and date/time formats. | LOC-001–006 | “Prepared” versus complete bilingual day-one delivery is OQ-06/ASM-09. |
| Backup & Recovery | Automated backups, restore points, and disaster-recovery procedures. | NFR-DR-001–003 | Numeric RPO/RTO/retention are Open Decisions. |
| Compliance | Privacy-ready, consent-aware, aligned with regional data-protection expectations. | SEC-PII-001–003; DATA-PII-001–002 | Legal requirements vary by OQ-06/OQ-07 and require counsel; no compliance claim is implied. |

## 9. Reporting scope audit

| Report/analysis named by PRD | MVP status |
|---|---|
| Daily revenue; breakdown by branch/date/cashier/product/ticket where data exists | Included through FR-RPT-001. Only daily revenue is an explicit MVP commitment; additional dimensions are a Proposed implementation of source reporting details. |
| Attendance by date/hour/age/branch; peak-time/occupancy trends | Attendance included. Hour/age breakdown is Proposed from §13. Game occupancy/capacity trends are Deferred with game management unless separately approved. |
| Session history, duration, pauses, extensions, cancellation, refunds | Session history included. Detailed pause/extension/refund views are derived from source reporting details. |
| Staff activity logs | Included explicitly by the MVP list and FR-RPT-004/FR-AUD. |
| Ticket and product sales | Included as revenue breakdowns where MVP data exists. |
| Lost-revenue analysis and pause analysis | **Deferred/Proposed future analytics**, not a Phase 1 release gate. |
| Birthday, membership, loyalty reports | Deferred with their source modules. |
| PDF/Excel export | Source says later versions; Deferred. |

## 10. Source success metrics

The source PRD gives target directions. Approved OQ-21 now supplies response-time and availability targets; the production workload fixture, sampling/exclusion method, and measured deployment evidence remain release gates rather than open Product decisions.

| Source metric ID | Metric | Source direction | MVP instrumentation/disposition |
|---|---|---|---|
| PRD-KPI-001 | Active tenants | Increase | Instrument tenant status; commercial target is not an engineering acceptance threshold. |
| PRD-KPI-002 | Active branches | Increase | Instrument branch status. |
| PRD-KPI-003 | Monthly recurring revenue | Increase | Commercial metric; subscription billing automation is not established as MVP scope. |
| PRD-KPI-004 | Average revenue per branch | Increase | Derivable from recorded branch revenue once the accounting definition is approved. |
| PRD-KPI-005 | Daily sessions processed | Increase | Instrument completed/eligible sessions by branch-local day. |
| PRD-KPI-006 | Average check-in time | Decrease | Requires workflow start/end event definition and pilot baseline. |
| PRD-KPI-007 | Average checkout time | Decrease | Requires workflow start/end event definition and pilot baseline. |
| PRD-KPI-008 | Customer retention rate | Increase | Commercial tenant-renewal metric; plan/subscription ownership must be confirmed. |
| PRD-KPI-009 | Parent satisfaction score | Increase | Requires a feedback collection process; no survey module is approved for MVP. |
| PRD-KPI-010 | System uptime | Maintain 99.9% | Instrument according to the approved SLA/availability calculation. |

Additional operational controls in the BRD KPI table are **Proposed acceptance/quality measures**, not replacements for these source success metrics.

## 11. Source assumptions

| Source assumption ID | Preserved source assumption | Pack mapping |
|---|---|---|
| PRD-ASM-001 | First release is web-based and optimized for desktop/tablet reception/cashier use. | ASM-01 |
| PRD-ASM-002 | Native apps wait until product-market fit is validated. | PRD-EXC-001; roadmap Phase 4 |
| PRD-ASM-003 | MVP prioritizes operational accuracy over advanced marketing/AI. | Scope guardrail |
| PRD-ASM-004 | Payment gateway integrations vary by country and are finalized during architecture. | OQ-01; ASM-02 |
| PRD-ASM-005 | WhatsApp/SMS integrations require provider selection and cost approval. | OQ-05 |

## 12. Source risks

| Source risk ID | Preserved source risk | BRD mapping |
|---|---|---|
| PRD-RISK-001 | Expanding MVP scope may delay launch. | RISK-01 |
| PRD-RISK-002 | Poor reception workflow may reduce non-technical staff adoption. | RISK-03 |
| PRD-RISK-003 | Weak tenant isolation creates serious SaaS security risk. | RISK-04 |
| PRD-RISK-004 | Unclear pricing rules may create billing disputes. | RISK-02 |
| PRD-RISK-005 | Wristband/scanner/kiosk hardware may require extra testing. | RISK-08 |

Additional BRD risks are **Proposed derived risks** and must be assessed/owned; they are not presented as source risks.

## 13. Source-question crosswalk

| Source question ID | Preserved source question | Pack decision ID |
|---|---|---|
| PRD-OQ-001 | Online payment in MVP or payment recording only? | OQ-01 |
| PRD-OQ-002 | Mandatory or optional wristband hardware? | OQ-02 |
| PRD-OQ-003 | Offline mode for temporary internet issues? | OQ-03 |
| PRD-OQ-004 | Exact Basic, Professional, and Enterprise plans/limits? | OQ-04 |
| PRD-OQ-005 | First-release countries and currencies? | OQ-06 |
| PRD-OQ-006 | Branded SaaS only or white-label system too? | OQ-23 |

## 14. Derivation and approval guardrails

- Source commitments are not silently removed; any deferral requires an approved scope decision.
- Source future requirements FR-011/FR-012 and phase-later modules are not silently promoted into MVP.
- Derived control detail may be prioritized Must for safe implementation while still remaining **Proposed until the SRS approval gate**.
- Decisions are resolved with date, approver, rationale, and impacted IDs. Current state is controlled by `.ai/DECISION_REGISTER.md`; preserved source questions do not reopen later approvals or deferments.
- If source and a derived artifact conflict, the source intent and latest approved decision govern; the conflict is recorded in `13-Traceability-Matrix.md` rather than guessed.
