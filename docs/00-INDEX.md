# PlayNexus Final Project Documentation

**Version:** 1.0 draft  
**Source baseline:** PlayNexus PRD v1.0, June 2026  
**Verified source:** 27,198 bytes; SHA-256 `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2`  
**Implementation target:** Laravel web MVP  
**Status:** M1 locally accepted; Egypt M2 engineering implemented / release partial; M3 pricing, tickets, ticket-backed check-in/live board and read-only estimate locally accepted / release partial; M4 preparation and session-lifecycle UI implemented / release partial

## Implementation status — 2026-09-13

M1 access and branch foundation is DONE. The approved Egypt M2 family contract is implemented, including hard duplicate-phone reuse, consent/withdrawal, emergency/safety safeguards and verified relationship lifecycle. M3 is locally accepted with immutable pricing versions, ticket lifecycle, atomic ticket-backed check-in, hard capacity, masked live sessions and an exact non-persisted estimate from immutable pricing/time/tax facts. M4 now includes OQ-19 preparation, due/overdue labels, 30-minute extensions, reasoned manager/owner adjustments and cancellation, plus a frozen `pending_payment` invoice view. Full final checkout, payment, receipt, refund, child release, shift and production approval remain open; an estimate is not checkout and cancellation is not a refund. See `.ai/TEST_RESULTS.md` for current evidence.

`docs/` is the single source of truth for product, technical, delivery, and implementation documentation. Do not create parallel summary copies.

## Documents

| No. | Deliverable | Primary use |
|---:|---|---|
| 01 | [PRD Baseline](01-PRD-Baseline.md) | Normalized source, scope, and unresolved decisions |
| 02 | [Business Requirements Document](02-BRD.md) | Business case, stakeholders, scope, outcomes, and rules |
| 03 | [Software Requirements Specification](03-SRS.md) | Verifiable functional and non-functional requirements |
| 04 | [User Stories](04-User-Stories.md) | Prioritized backlog with acceptance criteria |
| 05 | [Use Cases](05-Use-Cases.md) | End-to-end actor and system behavior |
| 06 | [Architecture Document](06-Architecture-Document.md) | Laravel solution design and operational model |
| 07 | [Database ERD](07-Database-ERD.md) | Entities, relations, constraints, and migration guidance |
| 08 | [Permission Matrix](08-Permission-Matrix.md) | Role, scope, approval, and sensitive-action rules |
| 09 | [API Specification](09-API-Specification.md) | REST conventions and endpoint behavior |
| 09A | [OpenAPI contract](contracts/openapi.yaml) | Machine-readable MVP API contract |
| 10 | [UI/UX Wireframes](10-UI-UX-Wireframes.md) | Information architecture, screen states, and low-fidelity layouts |
| 10A | [Interactive wireframe](wireframes/playnexus-wireframes.html) | Navigable screen model for implementation review |
| 11 | [Testing Strategy](11-Testing-Strategy.md) | Risk-based validation, environments, CI, and release gates |
| 12 | [Tooling and Delivery Guide](12-Tooling-and-Delivery-Guide.md) | Minimal toolchain, why each tool exists, and fastest build order |
| 13 | [Cross-Document Traceability Matrix](13-Traceability-Matrix.md) | Capability-level links, gaps, and change-control handoff |
| 14 | [Coding Standards](14-Coding-Standards.md) | Laravel, database, frontend, and quality conventions |
| 15 | [Delivery Milestones](15-Delivery-Milestones.md) | Outcome-based implementation sequence |
| 16 | [Implementation Checklist](16-Implementation-Checklist.md) | Product, foundation, MVP, and release tracking |
| 17 | [Definition of Done](17-Definition-of-Done.md) | Completion gate for every backlog item |
| 18 | [Security Checklist](18-Security-Checklist.md) | Identity, data, application, and operations controls |
| 19 | [UX/UI Remediation Plan](19-UX-UI-Remediation-Plan.md) | Canonical desktop and responsive improvement backlog |
| 20 | [M0 Audit Report](20-M0-Audit-Report.md) | Evidence-led M0 closure review and repair gate |
| UI | [Design System](../DESIGN.md) | Canonical color, typography, layout, component, RTL, and accessibility rules |

## Source-of-truth order

1. Approved business decisions and signed change records.
2. This canonical documentation set and its traceable requirement IDs.
3. OpenAPI contract, database migrations, policies, and automated tests.
4. Current implementation behavior.

If two artifacts conflict, stop the affected implementation, record the conflict, and resolve the higher-level decision rather than silently choosing one.

## MVP boundary

Included: tenant and branch setup, staff/RBAC, guardian and child profiles, session check-in/out, smart time and pricing, QR/barcode tickets, basic POS, payment recording, digital receipts, daily revenue/attendance/session reports, operational notifications, safety verification, and audit logs.

Deferred: native apps, online marketplace, AI, advanced loyalty and memberships, birthday management, advanced inventory/HR, franchise controls, accounting integrations, white-label products, and broad marketing automation.

## Decision register snapshot

- **Approved:** record-only in-person payments/no online gateway; browser keyboard-input scanners; Egypt/EGP/Africa-Cairo/Arabic-English; online-only operation; branch/year immutable receipt numbering/content; fixed-duration/grace/overtime pricing; QR plus registered-guardian confirmation with audited override; branch-scoped roles/support rules; optional DOB; branded SaaS.
- **Approved/deferred:** incident management is outside the current MVP family/session boundary; restricted safety notes remain.
- **Still open:** OQ-04 subscription limits, OQ-05/OQ-13 notification providers and alert policy, OQ-09 refund method/window, OQ-21 measurable load, OQ-22 security timings/retention, and OQ-24 cashier shift close. OQ-19 station ownership is resolved for the preparation handoff; matching payment/completion remains M5.

Unresolved items are labeled assumptions; they are not hidden product commitments.

## Approved MVP baseline decisions — 2026-09-10

The following initial decisions are approved for implementation planning: branded SaaS only (OQ-23); online-only operation (OQ-03); Egypt launch with EGP, Africa/Cairo, Arabic and English, and configurable tax behavior (OQ-06); minimum operational/safety data with recorded consent and lawful anonymization/retention exceptions (OQ-07); multi-branch staff with branch-scoped roles and deny-by-default authorization (OQ-10); default-deny, time-bound, reason-required, audited Super Admin support access (OQ-14); record-only in-person payments with no gateway in MVP (OQ-01); browser keyboard-input QR/barcode scanners with no proprietary hardware SDK (OQ-02); and optional child date of birth with purpose-limited age/family-band use (OQ-15).

OQ-08 receipt numbering/content, OQ-12 guardian verification, and OQ-16 pricing are approved and reflected in the canonical pack. The remaining open items above stay gated; no M4 work is authorized by this status summary.
