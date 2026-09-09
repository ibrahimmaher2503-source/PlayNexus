# PlayNexus Final Project Documentation

**Version:** 1.0 draft  
**Source baseline:** PlayNexus PRD v1.0, June 2026  
**Verified source:** 27,198 bytes; SHA-256 `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2`  
**Implementation target:** Laravel web MVP  
**Status:** Working specification pending stakeholder approval

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

## Decisions required before Sprint 1 closes

- Whether MVP payments are record-only or include an online gateway.
- Whether checkout, payment recording, session completion, and receipt issuance happen as one terminal action or as a recoverable reception/cashier handoff.
- Whether wristbands/scanners are mandatory hardware or optional keyboard-input devices.
- Supported launch countries, currencies, VAT behavior, branch timezone, and receipt requirements.
- Exact pricing models, rounding rules, pause policy, grace period, and overage rules.
- Guardian checkout verification method and emergency override evidence.
- Subscription plans, tenant limits, and trial/suspension behavior.
- Whether temporary offline operation is required. The current architecture assumes online operation.
- Notification providers and whether SMS/WhatsApp are pilot requirements or later integrations.
- Whether the POS module's `daily shift close` is required in the MVP or whether the explicit MVP daily revenue/reconciliation reports are sufficient for the pilot.
- Whether basic incident recording/search is required in the MVP; it appears in the Safety module but not in the PRD's explicit MVP feature list.

Unresolved items are labeled assumptions; they are not hidden product commitments.

## Approved MVP baseline decisions — 2026-09-10

The following initial decisions are approved for implementation planning: branded SaaS only (OQ-23); online-only operation (OQ-03); Egypt launch with EGP, Africa/Cairo, Arabic and English, and configurable tax behavior (OQ-06); minimum operational/safety data with recorded consent and lawful anonymization/retention exceptions (OQ-07); multi-branch staff with branch-scoped roles and deny-by-default authorization (OQ-10); default-deny, time-bound, reason-required, audited Super Admin support access (OQ-14); record-only in-person payments with no gateway in MVP (OQ-01); browser keyboard-input QR/barcode scanners with no proprietary hardware SDK (OQ-02); and optional child date of birth with purpose-limited age/family-band use (OQ-15).

These decisions must be reflected in the affected SRS, architecture, ERD, permissions, API/OpenAPI, wireframe, testing, milestone, and blocker records before dependent implementation is frozen. OQ-08 receipt numbering/content, OQ-12 guardian verification, OQ-16 pricing, and remaining workflow decisions remain open.
