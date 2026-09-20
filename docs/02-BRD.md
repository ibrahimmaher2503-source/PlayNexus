# PlayNexus Business Requirements Document (BRD)

## 2026-09-15 M6 bounded business delivery

CAP-10 is implemented for committed-source revenue, attendance, session and staff activity oversight within tenant/branch scope. CAP-11 is implemented through durable operational intent and deterministic database transport so provider availability cannot corrupt core operation; this is not real customer delivery. CAP-12 audit search/export is expanded with masking and scope, while OQ-20 incident management remains deferred. The engineering defaults and external release approvals are recorded, not silently promoted to product/legal commitments.

**Implementation note — 2026-09-15:** M5 locally implements cash-only catalog/cart sales, matching `pending_payment` session settlement, controlled discounts, immutable browser receipts, transaction lookup and approved same-day full cash refunds. SQLite, authenticated bilingual desktop, isolated MySQL 8.4.11/InnoDB, and real duplicate settlement/refund contention pass. Production approvals remain open; cashier shifts and provider delivery are deferred.

## 2026-09-15 current Egypt session boundary

The approved Egypt MVP does not offer pause/resume. The current session states are `active`, `pending_payment`, `completed`, and `cancelled`; extensions and additive adjustments are explicit, server-priced records. Any paused-state requirement or example retained below is historical source intent or a superseded target, not an active Egypt MVP commitment.

**Document version:** 1.1  
**Status:** Draft for stakeholder approval  
**Product:** PlayNexus — Kids Entertainment Operating System  
**Release scope:** Phase 1 / MVP  
**Prepared from:** PlayNexus Product Requirements Document (PRD), version 1.0, dated June 2026, normalized in `01-PRD-Baseline.md`  
**Primary markets:** Egypt, Saudi Arabia, UAE, GCC, and MENA  
**Audience:** Sponsor, product, operations, finance, safety, design, engineering, QA, implementation, and support teams

## 1. Purpose and source of truth

This BRD defines the business outcomes, boundaries, capabilities, rules, success measures, risks, and approval gates for the PlayNexus MVP. It translates the source PRD into an implementation-governance baseline. Detailed software behavior is defined in `03-SRS.md`; user value and acceptance examples are defined in `04-User-Stories.md`; end-to-end operational flows are defined in `05-Use-Cases.md`.

The PRD remains the source for product intent. Where it does not make a decision, this BRD labels the item as a **working assumption** or **open question**. A working assumption permits estimation but is not a silent product decision.

## 2. Executive summary

Kids entertainment venues commonly manage registration, timed play, tickets, sales, and child release through disconnected tools or manual records. This creates slow queues, billing leakage, inconsistent controls, poor visibility, and child-safety risk.

PlayNexus will provide a cloud-based, multi-tenant, multi-branch operating system. The MVP will support the complete visit journey: configure a tenant and branch; authorize staff; register guardians and children; issue or validate an entry ticket; check a child in; track active time and approved extensions/adjustments; alert staff as a session approaches its end; verify checkout; record sales and payments; issue receipts; and reconcile operations through core reports and an immutable audit trail. Pause/resume remains outside the approved Egypt MVP.

The launch principle is operational correctness before growth features. Memberships, loyalty, birthday packages, advanced CRM, native apps, inventory, HR, franchise controls, AI, marketplace, and white-label delivery are not MVP commitments.

## 3. Business context

### 3.1 Business problem

- Reception teams need a fast, low-error way to identify families and start sessions.
- Managers need consistent pricing, timing, discount, refund, and override controls.
- Parents need transparent charges, timely session alerts, receipts, and controlled child release.
- Owners need branch-level and consolidated revenue, attendance, session, and staff activity visibility.
- The SaaS operator needs strict tenant isolation and repeatable onboarding for many customers.

### 3.2 Opportunity

PlayNexus can become the operational system of record for venues across MENA, initially by reducing leakage and improving daily execution, and later by adding retention, commerce, and intelligence capabilities on top of reliable operational data.

### 3.3 Operating model

- One **tenant** represents one subscribed business customer.
- A tenant owns one or more **branches**.
- Staff access is tenant-scoped and, where assigned, branch-scoped.
- Customer, child, session, ticket, sale, payment, report, notification, and audit data belongs to exactly one tenant.
- Super Admin is a platform role; all other staff roles operate inside a tenant.

## 4. Business objectives

| ID | Objective | MVP evidence | Priority |
|---|---|---|---|
| BOBJ-01 | Digitize the arrival-to-checkout workflow. | A visit can be completed without an external operational register. | Must |
| BOBJ-02 | Reduce time and billing errors. | Charges are derived from stored pricing rules, timestamps, extensions, adjustments, discounts, and taxes. | Must |
| BOBJ-03 | Improve child-release safety and accountability. | Every checkout records guardian verification or an authorized override, actor, time, and reason. | Must |
| BOBJ-04 | Improve operating speed for non-technical staff. | Measured check-in and checkout completion times trend downward during the pilot. | Must |
| BOBJ-05 | Give managers reliable operational visibility. | Core reports reconcile to sessions, sales, payments, refunds, and audit events. | Must |
| BOBJ-06 | Establish a scalable SaaS foundation. | Tenants and branches can be provisioned without code changes and tenant-isolation tests pass. | Must |
| BOBJ-07 | Create trustworthy data for later growth products. | Required operational records are complete, traceable, and exportable through approved reporting interfaces. | Should |

## 5. Stakeholders

| Stakeholder | Business interest | MVP decision responsibility |
|---|---|---|
| Product Sponsor / Steering Group | Funding, timing, commercial fit, risk acceptance | Scope, budget, release approval |
| Product Owner | Value, priorities, requirements integrity | Backlog and acceptance decisions |
| SaaS Super Admin / Operations | Tenant provisioning and platform control | Onboarding and support procedures |
| Tenant Owner | Business setup, branches, staff, consolidated performance | Tenant policy and pilot acceptance |
| Branch Manager | Daily operation, capacity, overrides, reconciliation | Branch configuration and operational acceptance |
| Reception Staff | Registration, tickets, check-in/out, guardian verification | Workflow usability feedback |
| Cashier | Cart, discounts, payment recording, receipts, refunds | POS usability and reconciliation feedback |
| Parent / Guardian | Fast service, charge transparency, notifications, safe release | Pilot feedback and consent expectations |
| Finance / Accounting | Tax configuration, payment totals, refunds, receipts | Financial reconciliation sign-off |
| Safety / Compliance | Child data, consent, guardian verification, incident response | Safety and privacy sign-off |
| Engineering / Architecture | Secure, maintainable delivery | Technical feasibility and operational readiness |
| QA / Security | Verifiable quality, isolation, access control | Test and security exit evidence |
| Support / Implementation | Repeatable onboarding and issue handling | Support readiness sign-off |

## 6. MVP scope

### 6.1 In scope

| Capability ID | MVP capability | Business outcome |
|---|---|---|
| CAP-01 | Tenant and business profile setup | A customer can be onboarded with a segregated workspace. |
| CAP-02 | Branch setup | Each venue has its own status, hours, capacity, tax, and pricing configuration. |
| CAP-03 | Staff identity and RBAC | Staff can perform only authorized actions in allowed tenant/branch scope. |
| CAP-04 | Guardian and child registration | Families can be found and safely reused across visits without uncontrolled duplicates. |
| CAP-05 | Check-in and checkout | Each child visit has a traceable lifecycle and release verification. |
| CAP-06 | Smart time and pricing engine | Active time, approved extensions/adjustments, rounding, tax, and final charge are reproducible; pause/resume is deferred in the Egypt MVP. |
| CAP-07 | Basic ticketing and QR | Entry entitlement is issued, identified, validated, and audited. |
| CAP-08 | Basic POS and payment recording | Tickets, F&B, merchandise, add-ons, and extensions can be sold and recorded. |
| CAP-09 | Digital receipts | Paid transactions have a unique, reproducible receipt. |
| CAP-10 | Core reporting | Authorized users see revenue, attendance, sessions, and staff activity by date and branch. |
| CAP-11 | Operational notifications | Session-ending alerts and receipts are triggered and delivery attempts are logged. |
| CAP-12 | Audit and safety controls | Sensitive actions, manual overrides, checkout verification, and refunds are accountable. Approved OQ-20 defers basic incident management from Egypt V1. |

### 6.2 Explicitly out of scope for MVP

- Native iOS or Android applications and parent self-service mobile app.
- Online booking and birthday/package management.
- Memberships, loyalty, wallet, gift cards, referrals, and reward redemption.
- Marketing campaigns, segmentation, and marketing automation beyond storing required consent preferences.
- Advanced game, queue, time-slot, and occupancy management. Branch-level maximum capacity configuration and visibility may be retained as an operational guardrail; game-level enforcement is deferred.
- Inventory, procurement, recipe, and stock-depletion management.
- HR, payroll, rostering, and advanced cashier shift management.
- Split tender, deposits, subscriptions, and integrated online payment capture unless separately approved.
- Cashier shift open/close, cash-drawer balancing and variance handling; OQ-24 defers them from the Egypt MVP.
- External accounting integration, marketplace, franchise management, AI analytics/assistant, and white-label apps.
- Offline operation and automatic conflict synchronization unless approved through OQ-03.
- Advanced analytics and PDF/Excel exports; on-screen core reports are the MVP commitment.

### 6.3 MVP boundary decisions

The following protect the 10–14 week development objective stated in the PRD:

- Payment **recording** is included; payment **gateway capture** is subject to OQ-01.
- QR ticket generation and validation are included; dedicated wristband/kiosk hardware integration is subject to OQ-02.
- Refund recording is included because the PRD defines refund controls and auditability; partial-refund and multi-currency complexity are not included unless approved.
- Notifications are limited to session-ending alerts and receipts; provider and channel depend on OQ-05.
- Incident records are basic operational records, not a full case-management system.

## 7. Business capability requirements

| ID | Business requirement | Capability | Priority | Business acceptance |
|---|---|---|---|---|
| BRQ-001 | The SaaS operator shall provision and control tenant accounts without exposing one tenant's records to another. | CAP-01 | Must | Isolation evidence passes for UI, API, jobs, reports, and files. |
| BRQ-002 | A Tenant Owner shall configure the business and one or more branches, including active status, hours, capacity, tax, currency, and local pricing. | CAP-01, CAP-02 | Must | A configured active branch can operate; an inactive branch cannot open new sessions. |
| BRQ-003 | Authorized users shall create staff accounts, assign predefined roles, and constrain access by tenant and branch. | CAP-03 | Must | Permission tests allow intended actions and deny unauthorized/cross-scope actions. |
| BRQ-004 | Reception staff shall register, find, and update guardians and linked children, including contact, emergency, consent, and safety notes. | CAP-04 | Must | A child always has at least one active guardian link and duplicate-phone warnings are shown. |
| BRQ-005 | Reception staff shall start a session for an eligible child at an active branch using a valid ticket or pricing rule. | CAP-05, CAP-07 | Must | The session contains the required child, branch, actor, rule/ticket, start time, and status. |
| BRQ-006 | Authorized staff shall extend, adjust, cancel, and complete active sessions according to the approved Egypt policy; pause/resume is deferred. | CAP-05, CAP-06 | Must | Only valid transitions succeed; each sensitive action is attributed and auditable. |
| BRQ-007 | The platform shall calculate an explainable final charge from authoritative timestamps and versioned commercial rules. | CAP-06 | Must | Recalculation using stored inputs produces the charged amount within the defined currency precision. |
| BRQ-008 | Authorized staff shall issue, identify, scan, cancel, and reprint QR tickets with a retained event history. | CAP-07 | Must | Expired, cancelled, or already-consumed tickets cannot authorize another session. |
| BRQ-009 | Cashiers shall create orders for supported catalog items, apply permitted discounts and taxes, and record supported payment methods. | CAP-08 | Must | A paid sale reconciles line totals, discount, tax, payment, and final total. |
| BRQ-010 | Authorized managers shall approve controlled discounts, refunds, manual time changes, and checkout overrides where policy requires. | CAP-03, CAP-06, CAP-08, CAP-12 | Must | Approval identity, reason, old/new values, and time are retained. |
| BRQ-011 | The platform shall issue a uniquely numbered digital receipt for each completed payment and retain a reproducible copy. | CAP-09 | Must | Reopening or resending a receipt shows the same commercial facts and receipt number. |
| BRQ-012 | Checkout shall verify an authorized guardian or require a manager-approved override before releasing the child. | CAP-05, CAP-12 | Must | Completion is blocked until the verification or override record is saved. |
| BRQ-013 | Managers and owners shall access accurate revenue, attendance, session-history, and staff-activity reports within their scope. | CAP-10 | Must | Report aggregates reconcile to source records for the same filters. |
| BRQ-014 | The platform shall trigger session-ending alerts and receipt notifications and record every delivery attempt and outcome available from the provider. | CAP-11 | Must | Trigger, recipient, channel, template, attempt time, and current result are queryable. |
| BRQ-015 | **Deferred:** Authorized staff shall record and retrieve child-safety incidents by child, branch, date, and reporting staff member. | CAP-12 | Deferred by approved OQ-20 | If later reopened, a new approved contract must define scoped, timestamped, searchable, auditable behavior. |
| BRQ-016 | All material operational and administrative actions shall produce tenant-scoped audit evidence. | CAP-12 | Must | Audit records identify actor, action, subject, time, scope, and relevant before/after values. |

## 8. Core business process

1. The SaaS operator provisions a tenant; the Tenant Owner completes business and branch configuration.
2. The Tenant Owner or Branch Manager creates staff accounts and assigns approved roles/scopes.
3. Reception finds an existing guardian or registers a guardian and child, captures required consent and safety information, and verifies the guardian relationship.
4. A valid ticket is issued/sold or an approved pricing rule is selected.
5. Reception checks the child in; the time engine starts from a server-authoritative timestamp.
6. Authorized staff may extend, adjust, or cancel the active session according to policy; pause/resume is unavailable in the Egypt MVP and the system records the reason and actor for supported changes.
7. The system triggers the configured session-ending alert.
8. At checkout, the system calculates and displays the charge breakdown; any amount due is recorded through POS.
9. Reception verifies the collecting guardian, or an authorized manager records an exceptional override.
10. The system completes the session, finalizes the ticket/payment records, issues the receipt, logs the actions, and includes the records in reports.

## 9. Business rules

Rules BR-001 through BR-010 preserve the source PRD identifiers. Rules BR-011 onward are **Proposed derived controls** intended to make operation testable; they do not become approved requirements until the BRD/SRS approval gates accept them.

| Rule ID | Rule | Enforcement point |
|---|---|---|
| BR-001 | Every child shall be linked to at least one guardian. | Registration and guardian-link changes |
| BR-002 | A session shall not start without a branch, child, valid ticket or pricing rule, and initiating staff user. | Check-in |
| BR-003 | Egypt MVP session states are Active, Pending payment, Completed, and Cancelled. | Session lifecycle |
| BR-004 | Only authorized users may extend, cancel, refund, or manually adjust a session; pause/resume is deferred. | Authorization policy |
| BR-005 | Checkout shall validate the guardian-child relationship or retain a manager-approved override. | Checkout |
| BR-006 | A discount above the configured threshold requires manager approval. | POS total confirmation |
| BR-007 | A refund requires a reason and shall appear in audit and financial reports. | Refund |
| BR-008 | Tenant data shall be isolated from all other tenants. | Every data access path |
| BR-009 | An inactive branch shall not create new sessions. | Check-in |
| BR-010 | Marketing notifications shall respect consent and opt-out settings. | Deferred marketing features; data model remains consent-aware |
| BR-011 | A child shall not have more than one Active session at the same time within the tenant. | Check-in and session transition |
| BR-012 | Only an Active session may be extended or cancelled; Pending payment, Completed, and Cancelled sessions reject ordinary lifecycle mutation. | Session lifecycle |
| BR-013 | Charge calculations shall use server timestamps and the pricing/tax configuration snapshot effective when the session/order was created. | Time engine and POS |
| BR-014 | **Deferred for the Egypt MVP:** pause billing and pause intervals are not available; the retained source rule is a historical target only. | Time engine |
| BR-015 | A ticket that is expired, cancelled, or consumed shall not authorize a new session. | Ticket validation |
| BR-016 | A financial record shall not be deleted after posting; correction occurs through an authorized void/refund record. | POS and reporting |
| BR-017 | Receipt numbers shall use the approved immutable branch-scoped yearly sequence `BRANCH-YYYY-000001`; numbers are never reused and voids preserve their reason and history. | Receipt issuance |
| BR-018 | All monetary values shall use the configured currency and its supported minor-unit precision; cross-currency sales are not supported in one order. | Pricing, POS, reports |
| BR-019 | Manual overrides shall require an authorized actor and a non-empty reason and shall never overwrite audit history. | Sessions, checkout, discounts, refunds |
| BR-020 | Operational notifications may be sent independent of marketing opt-in where legally permitted; legal classification and mandatory wording require approval under OQ-07. | Notifications |
| BR-021 | An MVP ticket belongs to one branch and branch-local service date; the first successful scan permanently locks its holder assignment. Only an unused ticket with no successful scan/session is refund-eligible, with manager/owner approval, reason, linked reversal when paid, and audit. | Ticket issue, scan, transfer correction, cancellation, and refund eligibility |

## 10. Success measures and KPIs

### 10.1 Source product success metrics

The following are the complete PRD success metrics. The PRD gives a target direction, not a numeric target, except for 99.9% uptime. Instrumentation definitions, owners, measurement windows, baselines, and numeric targets are Open Decisions.

| Source metric | Measure | Source direction | MVP treatment |
|---|---|---|---|
| PRD-KPI-001 | Active tenants | Increase | Instrument tenant status; commercial outcome, not a feature acceptance gate. |
| PRD-KPI-002 | Active branches | Increase | Instrument branch status. |
| PRD-KPI-003 | Monthly recurring revenue | Increase | Commercial metric; subscription billing automation is not approved MVP scope. |
| PRD-KPI-004 | Average revenue per branch | Increase | Derivable after revenue/accounting definition approval. |
| PRD-KPI-005 | Daily sessions processed | Increase | Derivable from eligible sessions by branch-local day. |
| PRD-KPI-006 | Average check-in time | Decrease | Instrument after workflow boundary definition and pilot baseline. |
| PRD-KPI-007 | Average checkout time | Decrease | Instrument after workflow boundary definition and pilot baseline. |
| PRD-KPI-008 | Customer retention rate | Increase | Commercial tenant-renewal metric; plan/subscription ownership remains OQ-04. |
| PRD-KPI-009 | Parent satisfaction score | Increase | Requires a feedback process; no survey feature is approved for MVP. |
| PRD-KPI-010 | System uptime | Maintain 99.9% | Measure through the approved SLA and monitoring definition. |

### 10.2 Proposed MVP control and acceptance measures

The following measures are **Proposed derived controls**, not additional source success metrics. Numeric targets not defined by the PRD must be baselined and approved before production launch.

| KPI ID | Measure | Definition | Target / gate | Source |
|---|---|---|---|---|
| KPI-01 | Average check-in time | Median and 90th percentile from selecting/creating the family to Active session creation. | Direction: decrease; numeric target must be baselined and approved at Gate G2. | Session and audit timestamps |
| KPI-02 | Average checkout time | Median and 90th percentile from checkout initiation to Completed session. | Direction: decrease; numeric target must be baselined and approved at Gate G2. | Session, payment, audit timestamps |
| KPI-03 | Billing adjustment rate | Completed sessions requiring a post-calculation manual adjustment / completed sessions. | Direction: decrease; pilot baseline then target. | Sessions and overrides |
| KPI-04 | Revenue reconciliation variance | Absolute difference between posted sales less refunds and report total for identical filters. | **Proposed release control:** 0 within currency precision. | Orders, payments, refunds, report |
| KPI-05 | Unauthorized child-release events | Completed checkouts without valid guardian verification or authorized override. | **Proposed safety control:** 0. | Checkout verification records |
| KPI-06 | Duplicate-family rate | Confirmed duplicate guardian profiles / guardian profiles created. | Direction: decrease; baseline during pilot. | Customer merge/review log; merge is not MVP automation |
| KPI-07 | Notification attempt coverage | Eligible session-ending/receipt events with at least one logged delivery attempt. | **Proposed control:** 100%, excluding explicitly configured channel absence. | Domain events and notification log |
| KPI-08 | Core-operation availability | Availability of authentication, registration, check-in/out, time engine, POS payment recording, and verification. | 99.9% monthly target, subject to SLA definition. | Monitoring platform |
| KPI-09 | Tenant isolation defects | Confirmed production events where one tenant accesses another tenant's data. | **Proposed security control:** 0. | Security incidents |
| KPI-10 | Pilot adoption | Eligible visits processed fully in PlayNexus / eligible pilot visits. | Target must be baselined and approved at Gate G4. | Pilot operations log |

Source product metrics remain product outcomes and are not silently replaced by the proposed engineering controls.

## 11. Dependencies

The dependency list is a **Proposed delivery dependency register** derived from the PRD risks, assumptions, and open questions; owners/dates require confirmation.

| ID | Dependency | Needed for | Owner | Required by |
|---|---|---|---|---|
| DEP-01 | Approved MVP pricing models, rounding, extension, discount, refund, and tax examples plus the no-pause boundary | Time engine, POS, QA oracle | Product + Finance | Gate G1 |
| DEP-02 | Initial countries, currencies, time zones, tax/receipt obligations, privacy and retention guidance | Configuration, localization, legal compliance | Sponsor + Legal + Finance | Gate G1 |
| DEP-03 | Role and permission approval | RBAC and safety workflows | Product + Operations + Safety | Gate G1 |
| DEP-04 | Notification provider/channel and commercial approval | Session alerts and receipt delivery | Product + Procurement | Before notification integration sprint |
| DEP-05 | Hosting, backup, monitoring, email/domain, and environment decisions | Deployment and operations | Architecture + DevOps | Gate G2 |
| DEP-06 | Pilot venue, representative catalog/pricing data, staff, and devices | Usability and operational validation | Implementation + Pilot Tenant | Gate G3 |
| DEP-07 | Browser, printer, scanner, and QR/wristband support matrix | Front-desk compatibility | Product + Pilot Tenant | Gate G2 |
| DEP-08 | Data-processing terms, consent wording, privacy notice, and—only after a later decision reopens OQ-20—incident escalation policy | Registration and notifications; deferred incidents | Legal + Safety | Gate G3 |

## 12. Risks and treatments

RISK-01, RISK-02, RISK-03, RISK-04, and RISK-08 map to the five source risks PRD-RISK-001 through PRD-RISK-005 as documented in `01-PRD-Baseline.md`. RISK-05 through RISK-07 and RISK-09 through RISK-10 are **Proposed derived risks** requiring owner assessment; their likelihood/impact ratings are planning estimates, not approved facts.

| Risk ID | Risk | Likelihood / impact | Treatment | Owner |
|---|---|---|---|---|
| RISK-01 | MVP expands into growth-phase features. | High / High | Enforce the scope table and require change control with schedule impact. | Product Sponsor |
| RISK-02 | Ambiguous pricing rules cause disputed charges. | High / High | Approve a decision table and worked examples; version configurations; automate boundary tests. | Product + Finance |
| RISK-03 | Reception flow is too slow or complex. | Medium / High | Prototype with actual staff; measure steps and time; run pilot usability tests. | Product + UX |
| RISK-04 | Tenant or branch scoping fails. | Low / Critical | Centralize authorization/scoping, add negative isolation tests, and conduct security review. | Engineering + Security |
| RISK-05 | Guardian verification is bypassed informally. | Medium / Critical | Make verification mandatory, tightly permission overrides, audit and report exceptions. | Operations + Safety |
| RISK-06 | Network or provider outage interrupts front-desk work. | Medium / High | Define operational fallback procedures; monitor providers; decide offline scope through OQ-03. | Operations + Architecture |
| RISK-07 | Country-specific tax, receipt, or privacy rules are discovered late. | Medium / High | Complete jurisdiction review before schema/API freeze. | Legal + Finance |
| RISK-08 | Hardware behaves inconsistently across pilot devices. | Medium / Medium | Use browser-compatible QR first, publish a supported-device matrix, and pilot early. | Engineering + Implementation |
| RISK-09 | Reports diverge from source transactions. | Medium / High | Define one posting model, prohibit destructive financial edits, and automate reconciliation tests. | Engineering + Finance |
| RISK-10 | Sensitive child data is over-collected or retained too long. | Medium / High | Apply data minimization, controlled fields, retention policy, encryption, and access logging. | Privacy + Security |

## 13. Working assumptions requiring confirmation

| Assumption ID | Working assumption for planning | If rejected |
|---|---|---|
| ASM-01 | MVP is a responsive web application optimized for current desktop/tablet browsers at reception and cashier stations. | Re-estimate native or legacy-device work. |
| ASM-02 | MVP records cash and other configured payment methods but does not capture online payments through a gateway. | Select gateway and add payment/webhook/certification scope. |
| ASM-03 | QR can be displayed or printed using normal browser printing; proprietary wristband/kiosk drivers are excluded. | Add hardware discovery, SDK integration, and field testing. |
| ASM-04 | The service is online-only; branches follow a documented manual continuity procedure during loss of connectivity. | Design offline data ownership, synchronization, and conflict handling. |
| ASM-05 | Each branch operates in one configured currency and time zone; an order cannot mix currencies. | Add FX, settlement, and cross-zone requirements. |
| ASM-06 | Parent-facing actions in MVP are staff-assisted; parents receive notifications/receipts but do not log into a portal. | Add identity, self-service, consent, and account-recovery scope. |
| ASM-07 | One child may have multiple guardians and one guardian may have multiple children; one guardian link is designated for the checkout verification event. | Redesign relationship and verification rules. |
| ASM-08 | A single payment record settles a basic MVP order; split tender and partial payment are deferred. | Extend payment allocation, refund, and reconciliation models. |
| ASM-09 | Arabic/English readiness means translatable UI, Unicode data, RTL-compatible design, and locale-aware formats; exact launch-language completeness remains OQ-06. | Adjust translation, QA, and release scope. |
| ASM-10 | **Approved by OQ-09:** one full same-branch, same-local-business-day cash refund against an eligible payment; partial refunds are deferred. | Extend payment allocation, receipt state, API, reports, and reconciliation only after a later approved change. |
| ASM-11 | Basic incident recording is treated as Conditional/Proposed for MVP because the PRD safety module describes it but the Phase 1 MVP list does not name it. | If rejected, remove incident UI/API permissions and tests from MVP while retaining guardian checkout safety/audit. |

## 14. Open questions and decision log

| ID | Decision required | Options / impact | Decision owner | Due gate |
|---|---|---|---|---|
| OQ-01 | **Resolved:** record in-person payments only; no online gateway capture in MVP. | Avoids gateway/webhook/PCI expansion while preserving an auditable payment record. | Product owner approved; Finance pilot validation | G1 |
| OQ-02 | **Resolved:** use browser keyboard-input QR/barcode scanners; no proprietary hardware SDK in MVP. | Keeps hardware replaceable and the pilot web-native. | Product owner approved; Operations device validation | G1 |
| OQ-03 | **Resolved:** online-only; outages use a visible failure/read-only manual continuity procedure, not offline writes. | Avoids unsafe synchronization of identity, timing, numbering, and money. | Product owner approved; Operations runbook gate | G1 |
| OQ-04 | **Resolved for Production V1 (2026-09-15):** editable demo Starter 1 branch/5 users, Growth 3/15, Professional 10/50 and Enterprise custom; monthly/yearly EGP prices; 14-day trial plus 7-day grace; limits only; manual commercial billing first. | Freezes subscription lifecycle, limits, restricted/read-only expiry and Super Admin controls without inventing an online gateway. | Product owner approved; Commercial validates launch prices | G2 |
| OQ-05 | **Resolved channel order (2026-09-15):** WhatsApp first, SMS fallback, email for receipts/administration. Actual vendors, sender identities, production credentials and templates are deployment inputs. | Freezes channel intent without claiming provider delivery before procurement and callback verification. | Product owner approved; Procurement deployment gate | G1 |
| OQ-06 | **Resolved for initial launch:** Egypt, EGP, Africa/Cairo, Arabic and English; tax rate/mode remain branch-configurable and require operator accounting validation. | Freezes localization/runtime baseline without deciding an operator's legal tax classification. | Product owner approved; Legal + Finance deployment validation | G1 |
| OQ-07 | **Resolved for Egypt engineering baseline (2026-09-12):** Arabic-first notice; written/electronic legal-guardian consent for child data; optional separate marketing consent; append-only evidence/withdrawal; active lifecycle plus three-year default retention subject to documented holds. Production notice and licensing still require Legal/DPO sign-off. | Freezes M2 fields/workflows without claiming production legal approval. | Product approved; Legal / DPO production gate | G1 |
| OQ-08 | **Resolved:** immutable branch-scoped yearly display number `BRANCH-YYYY-000001`; never reuse numbers and preserve void reason/history. Include seller/branch, timestamp, number, service, quantity, prices, discount, tax, total, payment method, actor, and verification QR. | Freezes receipt identity/content; production fiscal/legal validation remains. | Product owner approved; Finance + Legal deployment validation | G1 |
| OQ-09 | **Resolved (2026-09-14):** one full cash refund only, at the original branch on the same branch-local business date, with a non-empty reason and separate Manager/Owner approval. Ticket-linked refunds also require unused/no-scan/no-session eligibility. | Freezes refund UI, permissions, state and reconciliation for the Egypt pilot. | Product owner approved; Finance + Operations deployment validation | G1 |
| OQ-10 | **Resolved:** staff may hold multiple branch-scoped assignments/roles; authorization is deny by default and evaluated per active branch. | Freezes RBAC scope and selector behavior. | Product owner approved; Operations validation | G1 |
| OQ-11 | **Resolved for Egypt MVP (2026-09-13):** branch capacity is a hard check-in block with no manager override; Active sessions occupy capacity and the final check is transactional. Pause/resume is not an available state in this MVP. | Affects safety workflow and concurrency. | Product default authorized by owner; Safety + Operations validation | G1 |
| OQ-12 | **Resolved:** checkout uses session/ticket QR plus registered-guardian phone last four digits or a handoff code; failure blocks checkout. Manager override is permission-checked, reason-required, single-use, and audited. | Freezes normal evidence and controlled exception behavior. | Product owner approved; Safety + Legal release validation | G1 |
| OQ-13 | **Resolved for Production V1 (2026-09-15):** session alert 10 minutes before end; retry after 2 then 5 minutes on technical failure; maximum three attempts; stop after confirmed delivery; persist attempts/callbacks. | Freezes scheduler behavior and provider volume; vendor-specific callback mapping remains a deployment input. | Product owner approved; Operations validates templates/escalation | G2 |
| OQ-14 | **Resolved:** Super Admin tenant-content access is denied by default and requires least-privilege, time-bound, reason-required, audited authorization. | Freezes support-access safety baseline. | Product owner approved; Security + Legal implementation gate | G1 |
| OQ-15 | **Resolved:** child date of birth is optional; purpose-limited age/family bands may be used without collecting greater precision than needed. | Freezes minimum-data child profile baseline. | Product owner approved; Legal + Safety validation | G1 |
| OQ-16 | **Resolved for the read-only Egypt MVP estimate (2026-09-13):** fixed duration; 600-second included grace; overtime starts on the next second and rounds up in 1,800-second units; integer minor units; branch-snapshotted inclusive/exclusive tax with half-up rounding. Checkout/discount/extension/payment examples remain their later gates. | Freezes the non-final calculation examples without determining a venue's legal tax classification. | Product default authorized by owner; Finance validates branch tax setup | G1 |
| OQ-17 | **Resolved (2026-09-12):** normalized phone is tenant-unique for MVP; a match uses the existing family. No create-anyway or automated merge; concurrent duplicates fail safely and foreign matches remain undisclosed. | Freezes duplicate handling and uniqueness behavior. | Product + Operations | G1 |
| OQ-18 | **Resolved for Egypt MVP (2026-09-13):** branch-specific and branch-local-service-date-bound; audited reassignment only before the first successful scan; permanently non-transferable afterward; refund-eligible only while unused and only with in-scope manager/owner approval, reason, audit, and linked reversal when paid. OQ-09 retains refund window/method/execution. | Freezes ticket eligibility, QR validation, transfer lock, and refund precondition. | Product approved; Operations + Finance implementation acceptance | G1 |
| OQ-19 | **Resolved (2026-09-13):** Reception verifies the active checkout-capable guardian (or a reasoned manager override) and freezes the server quote into `pending_payment`; Cashier receives the scoped queue and M5 payment posting atomically completes the session. Replays are idempotent; manager recovery/override is permission-checked, reasoned, and audited. | Separates child-release verification from payment custody while preserving a recoverable handoff. | Product owner approved; Operations + Finance validate M5 settlement/recovery | G1 |
| OQ-20 | **Deferred (2026-09-12):** incident-management workflow is outside M2 and the MVP registry/profile release. M2 keeps restricted encrypted child safety notes only. | Avoids inventing incident categories or escalation while preserving minimum family safety data. | Product approved; Safety + Legal before later activation | G1 |
| OQ-21 | **Resolved acceptance targets (2026-09-15):** p95 below 500 ms normal UI/API, below 1 second critical transactions, below 3 seconds standard reports, 99.9% availability, pagination and bounded queries. Environment load counts are recorded before production testing. | Freezes measurable targets without pretending local timings prove deployed capacity or availability. | Product owner approved; Architecture + QA deployment evidence | G2 |
| OQ-22 | **Resolved baseline (2026-09-15):** 30-minute staff idle, 15-minute sensitive/platform idle, 12-character minimum password, throttled login/reset/sensitive APIs, immediate revocation on disable/suspend, mandatory Super Admin MFA, enforceable Tenant Owner MFA, immutable UI audit, external secrets and production debug off. | Freezes application defaults; statutory retention and deployed controls retain Security/Legal validation. | Product owner approved; Security + Operations deployment validation | G2 |
| OQ-23 | **Resolved:** branded SaaS only for MVP; white-label capability is deferred. | Prevents hidden theme/domain/tenant-branding scope. | Product owner approved | G0 |
| OQ-24 | **Deferred (2026-09-14):** no cashier shift open/close, drawer balance or variance workflow in the Egypt MVP. Payments retain branch, cashier and server timestamp for later reconciliation. | Keeps M5 bounded without inventing drawer behavior. | Product owner approved; Finance + Operations validate later expansion | G1 |

Decisions shall be recorded with date, approver, chosen option, rationale, and affected requirement IDs. An approved change updates all impacted pack documents.

## 15. Approval gates

| Gate | Approval purpose | Required evidence | Approvers |
|---|---|---|---|
| G0 — Scope baseline | Confirm the MVP boundary and business objectives. | Approved BRD, named owners, accepted exclusions, prioritized open questions. | Sponsor, Product Owner, Operations |
| G1 — Rules and compliance freeze | Resolve decisions that alter architecture/data. | Approved pricing examples, role matrix, country/localization scope, safety/privacy decisions, payment and hardware decisions. | Product, Finance, Safety, Legal, Architecture |
| G2 — Solution readiness | Confirm buildable design and pilot measurement plan. | Approved SRS, architecture, ERD, API, wireframes, test strategy, KPI definitions, support/device assumptions. | Product, Engineering, QA, Operations |
| G3 — Pilot readiness | Confirm the release can be used safely in a real branch. | Passed critical tests, migration/configuration, trained pilot staff, backup/restore proof, monitoring, fallback procedures, and incident procedure only if OQ-20 approves that module. | QA, Security, Operations, Pilot Tenant |
| G4 — Production release | Accept residual risk and launch. | Pilot outcomes, zero open critical defects, reconciliation evidence, security/privacy approval, rollback plan, signed UAT. | Sponsor, Product, Operations, Finance, Security |
| G5 — MVP closure | Validate outcomes and control Phase 2 intake. | KPI baseline/results, lessons learned, backlog triage, operational ownership. | Steering Group |

## 16. Traceability summary

| Objective | Source PRD trace | Capabilities | Detailed SRS families |
|---|---|---|---|
| BOBJ-01 | FR-002–FR-009 | CAP-01 through CAP-09 | FR-TEN, FR-RBAC, FR-CUS, FR-SES, FR-TIM, FR-TKT, FR-POS |
| BOBJ-02 | FR-006, FR-008, FR-009 | CAP-05, CAP-06, CAP-08, CAP-09 | FR-SES, FR-TIM, FR-POS, DATA-FIN-001, DATA-MNY-001 |
| BOBJ-03 | FR-004, FR-005; BR-001, BR-005 | CAP-04, CAP-05, CAP-12 | FR-CUS, FR-SES, FR-SAF-001–002, SEC-AUD-001 |
| BOBJ-04 | Source usability NFR | CAP-04 through CAP-09 | NFR-USA, NFR-PERF |
| BOBJ-05 | FR-010; PRD-MVP-010 | CAP-10, CAP-12 | FR-RPT, FR-AUD, DATA-AUD-001 |
| BOBJ-06 | FR-001–FR-003; BR-008 | CAP-01 through CAP-03, CAP-12 | FR-TEN, FR-RBAC, SEC-TEN-001, NFR-SCA |
| BOBJ-07 | Source reporting/data intent | CAP-10 through CAP-12 | FR-RPT, FR-NOT, FR-AUD, DATA-VER-001 |

Source FR-001 through FR-010 are included through the rows above. FR-011 and FR-012 are explicitly future requirements and remain Deferred from MVP.

## 17. Business acceptance of the MVP

The MVP is business-ready only when:

1. Every Source-Must and approved derived-Must business requirement has mapped, passed acceptance evidence or an explicitly approved waiver; Conditional/Proposed items are not treated as approved by implication.
2. The critical journey from family lookup/registration through checkout, payment recording, receipt, reporting, and audit completes at the pilot branch.
3. Tenant isolation, permission denial, guardian verification, financial reconciliation, backup restoration, and time-calculation boundary tests pass.
4. Required OQ decisions are closed or explicitly deferred with accepted impact.
5. Pilot users complete role-based UAT and the G4 approvers accept the residual risks.

## 18. Approval record

| Role | Name | Decision | Date | Comments |
|---|---|---|---|---|
| Product Sponsor | Not assigned in source | Pending | Not approved | |
| Product Owner | Not assigned in source | Pending | Not approved | |
| Operations / Safety | Not assigned in source | Pending | Not approved | |
| Finance | Not assigned in source | Pending | Not approved | |
| Legal / Privacy | Not assigned in source | Pending | Not approved | |
| Engineering / Architecture | Not assigned in source | Pending | Not approved | |
| QA / Security | Not assigned in source | Pending | Not approved | |
