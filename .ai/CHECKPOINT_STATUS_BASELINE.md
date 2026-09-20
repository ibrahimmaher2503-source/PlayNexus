# Checkpoint 00.2 - Module Status Baseline

**Audit date:** 2026-09-17  
**Checkpoint result:** `PASS_WITH_GAPS`  
**Purpose:** bridge the requirement-level evidence in `.ai/REQUIREMENT_STATUS_MATRIX.md` to the major interactive-checklist modules. `IMPLEMENTED` below means the principal local behavior is evidenced; it does not mean `PRODUCTION_READY`.

| Module | Overall status | Strongest evidence | Most important gap | Next recommended checkpoint |
|---|---|---|---|---|
| Governance | PARTIAL | Canonical PRD/BRD/SRS, decisions, traceability and gap ledger validate with zero documentation errors | External approvals, named go/no-go, hosted CI and UAT are open | Release governance and approval gate review |
| Platform Admin | IMPLEMENTED | Checkpoint 01: atomic tenant/owner provisioning, hashed single-use invitation lifecycle, safe detail, reasoned suspension/reactivation with established-session revocation, scoped OQ-14 break-glass, tenant-visible history, negative tests and real EN/AR Edge journeys | Production Security/Legal validation, MySQL/staging migration rehearsal and named UAT/go-no-go remain release gates | Platform production acceptance |
| SaaS Plans/Subscriptions | PARTIAL | Catalog migration/models, `SubscriptionAccess`, lifecycle command, platform/tenant UIs; 27 focused tests/142 assertions | OQ-04 lacks MySQL concurrency and actual browser acceptance; recurring provider billing is deferred | OQ-04 MySQL/browser acceptance |
| Tenant onboarding | IMPLEMENTED | Wave 01: atomic tenant/owner provisioning, secure invitation acceptance, derived Owner setup guide and real EN/AR Edge journeys | Production invitation transport, accessibility/UAT and commercial acceptance remain release gates | Tenant onboarding production acceptance |
| Branches | PARTIAL | Wave 01: inactive drafts, shared readiness gate, scoped Owner/Manager configuration/status, capacity/time/tax/receipt/payment integrations and real EN/AR responsive Edge journeys | UC-13 active-session deactivation/handoff policy is unspecified; current Wave MySQL and production UAT remain | Resolve active-session deactivation procedure, then production acceptance |
| Auth | IMPLEMENTED | Wave 01: login/logout/reset, 30/15-minute idle controls, immediate versioned revocation, mandatory Platform MFA, configurable Owner MFA, per-account factor throttling and focused negative/browser tests | DEC-SEC-01 absolute lifetime and DEC-SEC-02 complete rate matrix remain open; deployed cookie/TLS proof remains | Approve exact policy values, then production auth review |
| RBAC | IMPLEMENTED | Wave 01: Staff identity/status UI, multi-Branch assignments, bounded custom roles, immediate revocation, OQ-14 support access and adversarial role/browser tests | Full all-module permission-key parity, formal security review and production UAT remain | RBAC production security acceptance |
| Families | IMPLEMENTED | Wave 02: tenant-scoped registry/profile/relationship lifecycle, OQ-15/17 behavior, append-only consent renewal/evidence, safety masking, real EN/AR Edge journeys and focused/MySQL tests | Production Legal/DPO approval and UAT remain release gates | Family/privacy production acceptance |
| Privacy | PARTIAL | Wave 02: approved three-year eligibility, owner dry-run, tenant-bound hold/release, consent evidence, masking, safe audit and EN/AR UI are implemented | DEC-RET-01 deliberately blocks destructive anonymization/deletion; formal DSAR procedure and Legal/DPO sign-off remain | Approve policy, then implement/rehearse destructive execution |
| Pricing | IMPLEMENTED | Wave 02: versioned minor-unit pricing, boundary/tax/snapshot tests, authorized EN/AR UI and real browser validation | Finance approval of production configuration remains | Finance configuration acceptance |
| Tickets | IMPLEMENTED | Wave 02: scoped issuance, opaque PII-free QR, OQ-18 state/transfer/refund rules, paginated UI, atomic scan/session and MySQL contention evidence | Approved pilot scanner/printer matrix and operator UAT remain | Hardware compatibility acceptance |
| Check-in | IMPLEMENTED | Atomic ticket consumption/session creation, capacity lock and concurrency tests | Production load/device UAT remains | Check-in performance/UAT |
| Sessions | IMPLEMENTED | Lifecycle/adjustment/checkout actions, immutable events/snapshots and focused/MySQL tests | Pause/resume intentionally deferred; production UAT remains | Session operations UAT |
| Checkout | IMPLEMENTED | Guardian verification/override, frozen quote, cash-settlement handoff and tests | Legal/operational release approval remains | Child-release operational sign-off |
| POS | IMPLEMENTED | Server-priced catalog/cart/order actions, POS UI and payment tests | Production finance/tax validation remains | POS finance acceptance |
| Discounts | IMPLEMENTED | Persisted-cart approval workflow, policies, reason/audit and tests | Production approval thresholds/operations sign-off | Manager approval UAT |
| Payments | IMPLEMENTED | One cash payment, immutable posting, idempotency and real MySQL contention evidence | Online capture is explicitly deferred; production reconciliation sign-off open | Cash reconciliation UAT |
| Receipts | IMPLEMENTED | Branch/year sequence, immutable snapshot, retrieval/reprint and tests | Finance/Legal approval of production receipt wording/number scope | Fiscal receipt approval |
| Refunds | IMPLEMENTED | Approved full same-branch/day cash refund action, approval, immutable reversal and tests | Production Finance/Legal acceptance | Refund operations UAT |
| Notifications | PARTIAL | Message/attempt schema, collector, async local processor, scheduler, masked UI and tests | OQ-05/OQ-13 behavior is approved; provider, sender, template, callback and cost decisions remain open | Provider selection/integration checkpoint |
| Safety | PARTIAL | Guardian checkout verification and audited manager override are implemented | Production child-safety/legal procedure pending; incident module is deferred OQ-20 | Child-safety operational acceptance |
| Reports | IMPLEMENTED | Revenue/attendance/session/staff activity UI, scoped queries, reconciliation and CSV tests | OQ-21 targets are approved; environment workload and deployed acceptance are missing | Reporting performance checkpoint |
| Audit | PARTIAL | Append-only migration, scoped search/filter/CSV and sensitive-action assertions | Complete event-catalog/correlation coverage and retention policy not proven | Audit event coverage checkpoint |
| API | PARTIAL | Stable JSON errors and documented OpenAPI 3.1 target | No deployed/versioned external `/api/v1`; idempotency/pagination are workflow-specific | External API scope decision |
| Localization | PARTIAL | Wave 02 EN/AR family/privacy/pricing/ticket journeys pass at 390/820/1440 with correct LTR/RTL and stable financial values | Whole-product linguistic/legal wording acceptance remains | Localization acceptance checkpoint |
| UI/UX | PARTIAL | Wave 02 Reception-to-family-to-ticket journey and owner privacy/manager pricing interfaces pass real responsive Edge review | Moderated whole-product reception/cashier UAT remains | Integrated browser UAT |
| Accessibility | PARTIAL | Semantic labels/focus/layout patterns exist in the shared UI | No automated accessibility scan plus keyboard/screen-reader acceptance across critical flows | Accessibility checkpoint |
| Concurrency | PARTIAL | Wave 02 reconfirms MySQL 8.4 first-scan single-winner behavior and family/retention constraints; earlier sessions/M5 contention remains green | OQ-04 plan/subscription and branch/user plan-limit races remain outside this Wave | OQ-04 MySQL concurrency checkpoint |
| Data integrity | PARTIAL | Wave 02 clean MySQL migration, composite family/hold ownership, normalized-phone/token uniqueness, locks and immutable snapshots pass | Existing production-like data upgrade rehearsal and destructive retention execution remain | Schema/migration rehearsal |
| Performance | CONDITIONAL | Queries are paginated/bounded in implemented screens | OQ-21 p95 targets are approved; environment workload counts and deployed execution remain release evidence | Capacity and load checkpoint |
| Observability | MISSING | Request IDs and application/audit logs exist only as building blocks | No deployed metrics, traces, alerts or staging alert exercises | Observability implementation checkpoint |
| Backup/DR | MISSING | Operations runbook describes intended recovery | No automatic encrypted production backup, approved RPO/RTO or isolated restore report | Backup/restore checkpoint |
| Deployment | PARTIAL | Build/migration/runbook tooling exists and local checks pass | No selected staging/production deployment rehearsal, hosted CI result or rollback evidence | Deployment readiness checkpoint |
| Security | PARTIAL | Strong local tenancy/policy/validation negative tests and append-only audits | OQ-22 baseline is approved; exact absolute timeout/rates/retention plus deployed TLS, storage, rotation and security tests remain | Production security review |
| QA/UAT | PARTIAL | Full local automated suite, focused suites, Pint, Vite, docs and historical browser/MySQL evidence | No current integrated staging UAT/sign-off; OQ-04 browser/MySQL acceptance absent | Full release-candidate acceptance |

## Baseline interpretation

- No module is `PRODUCTION_READY`.
- No module is `BROKEN` based on current verified evidence.
- `DEFERRED` capabilities remain outside these module scores: pause/resume, incident management, cashier shifts/drawer balancing, online/recurring payment capture, provider callbacks, advanced exports/analytics, native apps, white-label, loyalty, marketplace, advanced inventory/HR, franchise and external accounting.
- The risk-ranked roadmap is the **Approved Must Requirements Not Production Ready** section of `.ai/REQUIREMENT_STATUS_MATRIX.md`.
