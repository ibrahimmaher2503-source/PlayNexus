# Checkpoint 00.3 - Canonical Decision Register

**Audit date:** 2026-09-17  
**Authority order:** `.ai/DECISIONS.md` approved entries, then the BRD OQ log, then requirement/traceability documents. Historical milestone and blocker text is evidence of prior state, not authority over a later approval.

Decision status and implementation status are independent. In particular, OQ-04 is `RESOLVED_APPROVED` while its implementation remains `PARTIAL` and not production ready.

## Inventory summary

| Decision status | Count |
|---|---:|
| RESOLVED_APPROVED | 31 |
| DEFERRED_APPROVED | 4 |
| OPEN | 9 |
| SUPERSEDED | 4 |
| DUPLICATE | 1 |
| INVALID_OR_STALE | 5 |
| **Total** | **54** |

## OQ-01 through OQ-24

| Decision ID | Topic | Status | Owner | Approved decision / deferred rationale | Date | Requirements affected | Implementation status | Evidence |
|---|---|---|---|---|---|---|---|---|
| OQ-01 | Payment capture boundary | RESOLVED_APPROVED | Product Owner; Finance release validation | Record one in-person payment; no online gateway, raw card, split or partial capture in Egypt V1. | 2026-09-10; refined 2026-09-14 | FR-POS-006-013, INT-PAY-001-002 | IMPLEMENTED for cash recording; gateway deferred | `.ai/DECISIONS.md`; BRD OQ table |
| OQ-02 | Scanner/hardware | RESOLVED_APPROVED | Product Owner; Operations device validation | Browser keyboard-input QR/barcode scanners; no proprietary SDK requirement. | 2026-09-10 | FR-TKT, INT-HW-001-002 | IMPLEMENTED; device matrix release gate remains | Same |
| OQ-03 | Offline behavior | RESOLVED_APPROVED | Product Owner; Operations | Online-only. Outage handling is visible failure/read-only manual continuity, never offline writes/sync. | 2026-09-10 | PRD-ASM-001, session/POS commands | IMPLEMENTED boundary; continuity rehearsal pending | Same |
| OQ-04 | SaaS plans/subscriptions | RESOLVED_APPROVED | Product Owner / Commercial | Starter 1/5, Growth 3/15, Professional 10/50, Enterprise custom; editable EGP monthly/yearly prices and discount; 14-day trial, 7-day grace, approved lifecycle, limits, overrides and manual SaaS billing. | 2026-09-15 | PRD-OQ-004, FR-TEN, subscription derived requirements | PARTIAL; SQLite-green, MySQL/browser acceptance missing | `.ai/DECISIONS.md:3-19`; matrix OQ-04 evidence |
| OQ-05 | Notification channel order | RESOLVED_APPROVED | Product Owner; Procurement supplies vendors | WhatsApp primary, SMS fallback, email for receipts/administration. Vendor identity is DEC-NOT-01, not a reopening of channel order. | 2026-09-15 | FR-NOT, INT-NOT | PARTIAL local/database transport only | `.ai/DECISIONS.md:11` |
| OQ-06 | Launch market/localization | RESOLVED_APPROVED | Product Owner; Finance/Legal validation | Egypt, EGP, Africa/Cairo, Arabic and English; branch-configurable tax. Operator tax classification is a release approval. | 2026-09-10 | LOC-001-006, FR-TEN-005/007/009 | PARTIAL production validation | `.ai/DECISIONS.md`; BRD OQ-06 |
| OQ-07 | Privacy/consent/retention baseline | RESOLVED_APPROVED | Product Owner; Legal/DPO release approval | Arabic-first notice, minimum data, explicit legal-guardian child-data consent, separate marketing consent, append-only evidence and three-year eligibility subject to holds. | 2026-09-12 | FR-CUS, DATA-PII, SEC-PII | PARTIAL; retention execution absent | `.ai/DECISIONS.md:80-95` |
| OQ-08 | Receipt numbering/content | RESOLVED_APPROVED | Product Owner; Finance/Legal release validation | Immutable `BRANCH-YYYY-000001`, never reused; approved seller/branch/time/lines/discount/tax/total/payment/actor/QR facts. | 2026-09-10 | FR-POS-008-009, DATA-NUM-001, LOC-006 | IMPLEMENTED locally | SRS approved amendment; BRD OQ-08 |
| OQ-09 | Refund policy | RESOLVED_APPROVED | Product Owner; Finance/Operations validation | One full cash refund, original branch, same local business date, non-empty reason, separate Manager/Owner approval; ticket eligibility also applies. | 2026-09-14 | FR-POS-010-013, FR-TKT-006 | IMPLEMENTED locally | `.ai/DECISIONS.md:27-39` |
| OQ-10 | Multi-branch RBAC | RESOLVED_APPROVED | Product Owner; Operations | Multiple branch-scoped assignments/roles; deny by default per active branch. | 2026-09-10 | FR-RBAC, SEC-RBAC-001 | IMPLEMENTED primary behavior | Initial MVP decision entry; BRD OQ-10 |
| OQ-11 | Capacity | RESOLVED_APPROVED | Product Owner; Safety/Operations | Hard non-overridable transactional check-in block; Active sessions count; pause is unavailable. | 2026-09-13 | FR-SES-002/014, branch capacity | IMPLEMENTED including contention evidence | `.ai/DECISIONS.md:63-67` |
| OQ-12 | Guardian checkout verification | RESOLVED_APPROVED | Product Owner; Safety/Legal validation | QR plus registered guardian phone last four or handoff code; failure blocks; manager override is permissioned, reasoned, single-use and audited. | 2026-09-10 | FR-SES-008-010, FR-SAF-001-002 | IMPLEMENTED locally | SRS approved amendment; BRD OQ-12 |
| OQ-13 | Alert timing/retry | RESOLVED_APPROVED | Product Owner; Operations templates/escalation | Start 10 minutes before expected end; retry after 2 and 5 minutes; maximum three attempts; stop after provider-confirmed delivery; audit attempts/callbacks. No additional escalation is approved. | 2026-09-15 | FR-NOT-001-006 | PARTIAL pending provider inputs | `.ai/DECISIONS.md:11` |
| OQ-14 | Support access | RESOLVED_APPROVED | Product Owner; Security/Legal release validation | Default deny; tenant-specific, least-privilege, reason-required, revocable, audited, Tenant Owner-visible, auto-expiring, maximum 60 minutes; no permanent impersonation. | 2026-09-15 refinement | FR-RBAC-007, SEC-AUD-001 | PARTIAL: default deny exists, workflow missing | `.ai/DECISIONS.md:13`; GAP-07 |
| OQ-15 | Child age data | RESOLVED_APPROVED | Product Owner; Legal/Safety validation | DOB optional; collect/use only purpose-limited age/family-band precision. | 2026-09-10 | FR-CUS-004, FR-RPT-002, DATA-PII-001 | IMPLEMENTED primary behavior | Initial decision; corrected SRS section 15 |
| OQ-16 | Session pricing/time | RESOLVED_APPROVED | Product Owner; Finance validates branch tax | Fixed duration, 600-second grace, next-second overtime rounded to 1,800-second units, integer minor units, immutable inclusive/exclusive tax snapshot with half-up rounding; no pause. | 2026-09-10; examples 2026-09-13 | FR-TIM-001-009 | IMPLEMENTED locally | SRS amendment; `.ai/DECISIONS.md:55-61` |
| OQ-17 | Duplicate guardian phone | RESOLVED_APPROVED | Product + Operations | Tenant-unique normalized phone; reuse existing family; no create-anyway or automatic merge; concurrent duplicates fail without foreign disclosure. | 2026-09-12 | FR-CUS-002, DATA-TEN | IMPLEMENTED | `.ai/DECISIONS.md:80-95` |
| OQ-18 | Ticket scope/transfer/refund eligibility | RESOLVED_APPROVED | Product Owner; Operations/Finance | Tenant/branch/service-date scoped; correction only before first successful scan; permanently locked afterward; only unused/no-scan/no-session tickets refund-eligible. | 2026-09-13 | FR-TKT-001/003/006 | IMPLEMENTED | `.ai/DECISIONS.md:73-78` |
| OQ-19 | Reception-to-Cashier handoff | RESOLVED_APPROVED | Product Owner; Operations/Finance validation | Reception/Manager verifies guardian and freezes quote into `pending_payment`; Cashier posts exact payment atomically with completion; idempotent, audited recovery. | 2026-09-13 | FR-SES-007-010, FR-POS-006 | IMPLEMENTED | `.ai/DECISIONS.md:49-53` |
| OQ-20 | Incident management | DEFERRED_APPROVED | Product Owner; Safety/Legal before reopening | No incident-management module in Egypt V1/M2. Restricted safety notes remain. UI/API/routes/tables must remain absent. Reopen only with approved categories, states, visibility, escalation, retention and ownership. | 2026-09-12 | FR-SAF-003-006, UC-12 | DEFERRED | `.ai/DECISIONS.md:80-95`; BRD OQ-20 |
| OQ-21 | Performance targets | RESOLVED_APPROVED | Product Owner; Architecture/QA prove deployment | p95 <500 ms normal UI/API, <1 s critical transactions, <3 s standard reports; 99.9% availability; bounded/paginated queries. Environment workload counts are release-test inputs. | 2026-09-15 | NFR-PERF-001-003, INT-API-004, NFR-AVL-001 | PARTIAL; deployed load/availability proof missing | `.ai/DECISIONS.md:17`; BRD OQ-21 |
| OQ-22 | Security/operations baseline | RESOLVED_APPROVED | Product Owner; Security/Operations validation | Staff idle 30 min, privileged idle 15 min, password minimum 12, immediate revocation, throttling principle, mandatory Super Admin MFA, enforceable Owner MFA, immutable audit, external secrets, production debug off. Exact remaining values are DEC-SEC-01-03. | 2026-09-15 | FR-AUT, SEC-AUT/RATE/AUD, DATA-AUD | PARTIAL | `.ai/DECISIONS.md:17`; BRD OQ-22 |
| OQ-23 | Branding model | RESOLVED_APPROVED | Product Owner | Branded SaaS only; white-label is deferred and hidden. | 2026-09-10 | PRD-EXC-005, scope | IMPLEMENTED boundary | Initial MVP decision; BRD OQ-23 |
| OQ-24 | Cashier shifts/drawer | DEFERRED_APPROVED | Product Owner; Finance/Operations before reopening | No shift open/close, drawer balance or variance workflow in Egypt V1. Payments must retain tenant, branch, cashier and server time. Reopen with a new reconciliation contract. | 2026-09-14 | FR-POS-013, reports | DEFERRED; required payment evidence implemented | `.ai/DECISIONS.md:35` |

## Decisions outside OQ-01 through OQ-24

| Decision ID | Topic | Status | Owner | Approved decision / Open question / Deferred rationale | Date | Requirements affected | Implementation status | Evidence |
|---|---|---|---|---|---|---|---|---|
| DEC-ARCH-01 | Application architecture | RESOLVED_APPROVED | Engineering/Architecture | One Laravel modular monolith and relational database; microservices require measured need. | 2026-08-24 | NFR-SCA/MNT, architecture | IMPLEMENTED | `.ai/DECISIONS.md` modular-monolith entry |
| DEC-ARCH-02 | Tenancy architecture | RESOLVED_APPROVED | Engineering/Architecture | Shared schema with explicit tenant scoping and constraints; database-per-tenant only after a new contractual/scale/residency decision. | 2026-08-24 | DATA-TEN, SEC-TEN | IMPLEMENTED primary model | `.ai/DECISIONS.md` shared-schema entry |
| DEC-DOC-01 | Canonical documentation | RESOLVED_APPROVED | Product/Engineering | Markdown/OpenAPI/HTML under one canonical `docs/` tree; no parallel summary pack. | 2026-08-24 | Governance | IMPLEMENTED | `.ai/DECISIONS.md` documentation entries |
| DEC-UX-01 | Visual baseline | RESOLVED_APPROVED | Product/Design | Cool porcelain/graphite/petrol-teal operational UI; `DESIGN.md` authoritative; purple/cream/neon excluded. | 2026-08-25 | UI/UX/NFR-USA | PARTIAL production brand/accessibility approval | `.ai/DECISIONS.md` visual entry |
| DEC-SUP-01 | GAP-07 break-glass detail | RESOLVED_APPROVED | Product Owner; Security/Legal release validation | OQ-14 plus 60-minute cap, Tenant Owner visibility, revocation and prohibition on permanent impersonation. No further Product choice is needed. | 2026-09-15 | FR-RBAC-007, SEC-AUD-001 | PARTIAL implementation | `.ai/DECISIONS.md:13`; gap ledger |
| DEC-RET-01 | GAP-08 engineering retention baseline | RESOLVED_APPROVED | Product Owner; Legal/DPO release approval | Eligibility three years after last activity/closure; holds and statutory finance/audit exceptions; prefer anonymization/pseudonymization; hold/release and dry-run administration; destructive execution disabled until final approval. | 2026-09-15 | DATA-PII-002, SEC-PII-003 | PARTIAL/MISSING execution | `.ai/DECISIONS.md:15` |
| DEC-API-01 | External `/api/v1` launch | DEFERRED_APPROVED | Product Owner / Product Architecture | No approved launch consumer; web release is not blocked. Keep target contract non-deployed. Reopen only with named consumer, auth, versioning, idempotency and support owner. | 2026-09-15 | INT-API-001-005 | PARTIAL web API rules; external API absent | `.ai/DECISIONS.md:19`; route inventory |
| DEC-SAAS-01 | Recurring SaaS billing | DEFERRED_APPROVED | Product Owner / Commercial | Manual SaaS billing only. No recurring charges, gateway abstraction, callbacks, dunning, collection or raw card data. Reopen with provider/commercial contract. | 2026-09-15 | OQ-04 commercial boundary | DEFERRED | `.ai/DECISIONS.md:9` |
| DEC-FIN-01 | Discount and zero-total policy | RESOLVED_APPROVED | Product Owner; Finance validation | Every positive discount requires payload-bound single-use Manager/Owner approval; fixed minor-unit discount; total cannot become zero. | 2026-09-14 | FR-POS-004-005 | IMPLEMENTED | `.ai/DECISIONS.md:37` |
| DEC-SESSION-01 | M4 lifecycle boundary | RESOLVED_APPROVED | Product Owner | No pause/resume; fixed 30-minute extension; additive reasoned version-guarded adjustment; reasoned terminal cancellation is never refund. | 2026-09-13 | FR-SES-003-006, FR-TIM | IMPLEMENTED/deferred as applicable | `.ai/DECISIONS.md:41-47` |
| DEC-TKT-01 | Ticket-type price replacement | RESOLVED_APPROVED | Product/Engineering | Ticket type retains captured price/source version; later pricing replacement does not silently reprice it; new price requires new type/code. | 2026-09-13 | DATA-VER-001, FR-TKT-001 | IMPLEMENTED | `.ai/DECISIONS.md:69-71` |
| DEC-NOT-01 | Notification vendors | OPEN | Procurement / Product Commercial | Select the compliant production vendor for each approved channel, or explicitly approve a local-only pilot with customer delivery disabled. | N/A | FR-NOT, INT-NOT | BLOCKS real provider adapter/credentials | `.ai/DECISIONS.md:11` reserves vendor input |
| DEC-NOT-02 | Sender identities | OPEN | Operations + Procurement | Choose operator-owned, PlayNexus-owned or mixed verified WhatsApp/SMS/email sender identities and support ownership. | N/A | INT-NOT-002, notification templates | BLOCKS provider onboarding | Same |
| DEC-NOT-03 | Bilingual templates | OPEN | Operations + Legal/Privacy | Approve exact Arabic/English session-ending and receipt/admin templates, mandatory wording and variable/redaction rules. | N/A | FR-NOT-004/006, LOC-001/002 | BLOCKS customer messages | Same |
| DEC-NOT-04 | Delivery callbacks | OPEN | Procurement + Engineering/Security | Decide whether launch vendors must provide verified delivery callbacks; if unavailable, UI must remain sent-only and never claim delivered. | N/A | INT-NOT-001/003, FR-NOT-004/005 | BLOCKS delivered state/callback endpoint | Same |
| DEC-NOT-05 | Notification cost ownership | OPEN | Commercial / Finance | Decide whether channel costs are included in SaaS price, tenant-paid/passed through, or disabled until separately contracted. | N/A | OQ-04 commercial context, notifications | BLOCKS procurement/launch pricing | Repository contains no approved allocation |
| DEC-SEC-01 | Absolute authenticated-session timeout | OPEN | Security + Operations | Approve a maximum session lifetime in addition to approved 30/15-minute idle limits. | N/A | SEC-AUT-002, FR-AUT-005 | BLOCKS production session configuration | OQ-22 omits absolute duration; historical recommendation is 8 hours |
| DEC-SEC-02 | Exact rate-limit matrix | OPEN | Security + Architecture | Approve exact login, reset, search, QR and sensitive-operation limits and tenant-safe keys. | N/A | SEC-RATE-001 | BLOCKS production rate configuration/acceptance | Login currently 5/min; sensitive route groups 120/min; not approved as full matrix |
| DEC-SEC-03 | Audit and technical-log retention | OPEN | Security + Legal/DPO + Operations | Approve online/archive durations, access, disposal and legal-hold treatment separately for immutable audit and technical logs. | N/A | DATA-AUD-001, SEC-PII-003, NFR-OBS-003 | BLOCKS retention/archive configuration | OQ-22 baseline reserves statutory/deployed validation |
| DEC-DR-01 | RPO/RTO and backup authority | OPEN | Engineering/Architecture + Operations + Security | Approve numeric RPO/RTO, backup retention/region and restore authority for the production topology. | N/A | NFR-DR-001-003 | BLOCKS production DR plan and restore acceptance | SRS NFR-DR-002; no approved numbers |
| DEC-M2-TEMP-01 | Conservative family-registry slice | SUPERSEDED | Product Owner | Temporary no-unique-constraint/no-final-OQ-17 boundary replaced by approved M2 privacy/family baseline. | 2026-09-12 | FR-CUS | Superseded implementation contract | Earlier and later 2026-09-12 entries in `.ai/DECISIONS.md` |
| DEC-M2-TEMP-02 | Basic family-profile maintenance boundary | SUPERSEDED | Product Owner | Narrow pre-consent/profile boundary replaced by approved full M2 contract. | 2026-09-12 | FR-CUS, SEC-PII | Superseded | Same |
| DEC-M4-TEMP-01 | Checkout-preparation-only exclusion | SUPERSEDED | Product Owner | M4 preparation excluded payment/completion temporarily; approved M5 cash contract later supplies them. No-pause/verification facts remain active. | 2026-09-13/14 | FR-SES, FR-POS | Superseded by implemented M5 | M4 and M5 decision entries |
| DEC-M5-TEMP-01 | Pre-approval M5 planning assumptions | SUPERSEDED | Product Owner | Earlier open refund/shift/discount/zero-total recommendations were replaced by the 2026-09-14 approved M5 decision. | 2026-09-14 | FR-POS | Superseded | Historical blocker/milestone sections and final M5 decision |
| ALIAS-NOT-01 | Combined “OQ-05/OQ-13 notification decision” label | DUPLICATE | N/A | Alias only: OQ-05 owns channel order; OQ-13 owns timing/retry. DEC-NOT-01-05 own remaining procurement inputs. | 2026-09-17 mapping | FR-NOT, INT-NOT | N/A | This register |
| STALE-AGENT-01 | AGENTS M3/OQ-19-open statement | INVALID_OR_STALE | N/A | Obsolete milestone text contradicted later OQ-19/M5/M6 decisions; corrected in this checkpoint. | 2026-09-17 | Governance | N/A | `AGENTS.md` correction |
| STALE-INDEX-01 | Index OQ-05/13/21/22/24-open statement | INVALID_OR_STALE | N/A | Contradicted 2026-09-15 decisions and OQ-24 deferral; corrected. | 2026-09-17 | Governance | N/A | `docs/00-INDEX.md` correction |
| STALE-SRS-01 | SRS open OQ-15/21/22/23 wording | INVALID_OR_STALE | N/A | Historical clarification wording was not current decision state; corrected to resolved baselines/sub-decisions. | 2026-09-17 | Governance | N/A | `docs/03-SRS.md` correction |
| STALE-UC-01 | Use-case “unresolved decisions” table | INVALID_OR_STALE | N/A | Historical crosswalk incorrectly presented resolved/deferred OQs as open; corrected. | 2026-09-17 | Governance | N/A | `docs/05-Use-Cases.md` correction |
| STALE-GAP-01 | Gap ledger open OQ-04/05/13/21/22 grouping | INVALID_OR_STALE | N/A | Mixed approved decisions, implementation gaps and deployment inputs; corrected and split. | 2026-09-17 | Governance | N/A | `docs/23-Decision-and-Implementation-Gap-Ledger.md` correction |

## Notification decision decomposition

| Item | State |
|---|---|
| Channel order | Approved: WhatsApp, SMS fallback, email receipts/admin |
| Alert lead time | Approved: 10 minutes |
| Retry | Approved: after 2 then 5 minutes; maximum three attempts |
| Fallback | Approved: WhatsApp to SMS; no further escalation behavior approved |
| Provider | OPEN: DEC-NOT-01 |
| Sender identity | OPEN: DEC-NOT-02 |
| Templates/legal wording | OPEN: DEC-NOT-03 |
| Callback availability/trust | OPEN: DEC-NOT-04 |
| Cost ownership | OPEN: DEC-NOT-05 |

## OQ-21 deployment profile still required

OQ-21's targets are approved. Architecture/QA must nevertheless record the production-test fixture: normal and peak concurrent users, tenant/branch counts, transaction/session and notification volumes, standard report horizon, list page sizes/date caps, data-volume horizon, and the p95 sampling/exclusion method. These are environment-specific release evidence, not an open Product decision.

## OQ-22 decomposition

| Item | State |
|---|---|
| Staff idle timeout | Approved: 30 minutes |
| Privileged/platform idle timeout | Approved: 15 minutes |
| Absolute timeout | OPEN: DEC-SEC-01 |
| Password policy | Approved minimum: 12 characters; no additional Product complexity rule is inferred |
| Privileged MFA | Approved: mandatory Super Admin; Tenant Owner MFA enforceable |
| Revocation latency | Approved: immediate on disable/suspend |
| Login/reset/API/search rate values | OPEN: DEC-SEC-02; throttling principle already approved |
| Audit/technical-log retention | OPEN: DEC-SEC-03; family operational three-year policy does not silently define these stores |
