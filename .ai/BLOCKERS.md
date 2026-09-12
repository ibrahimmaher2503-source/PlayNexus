# Blockers

## 2026-09-12 M3 first-slice boundary

No blocker prevents immutable fixed-duration pricing-rule creation/listing/version replacement because OQ-16 is approved. OQ-18 still blocks ticket-type scope, transferability, date binding, and refund behavior, so ticket issuance/QR is not started. Finance-approved worked tax examples are still required before tax-total calculation/checkout acceptance. The browser bridge is currently unavailable. Fresh MySQL 8.4 acceptance is blocked locally: PHP has `pdo_mysql`, but no MySQL 8.4 client/server or Docker is available; the only discovered server is XAMPP MariaDB 10.4.32 and was not substituted.

## 2026-09-12 M2 start boundary

No blocker prevents the first family-registry slice. Full M2 schema/API freeze remains blocked by OQ-17 duplicate resolution after a match and approved consent/privacy notice wording/version/retention. The first slice therefore has no phone uniqueness constraint, never creates a same-tenant duplicate, and does not claim consent capture or M2 completion.

## 2026-09-12 M1 closure

No blocker remains for M1 local acceptance. T30 MySQL, T31 PHP 8.5, and T32 browser acceptance are closed. Production readiness remains blocked on deployment-specific TLS, managed secrets, monitoring, backup/restore, and operational approval. M2 and later schema/API freeze still depend on their documented product/legal decisions; they do not reopen M1.

## 2026-09-12 current runtime blocker

T30 MySQL 8.4 and T32 browser acceptance are closed. PHP 8.5 compatibility (T31) remains unverified; full milestone and production readiness must not be claimed from the completed local slice.

No blocker prevents documentation delivery.

Laravel implementation is blocked from schema/API freeze by the open decisions in `docs/00-INDEX.md`, especially payment mode, launch country/currency/tax/receipt rules, pricing examples, guardian verification, checkout/payment topology, child/DOB and duplicate policy, and incident/shift scope. Owners and gates are recorded in the BRD OQ-01–OQ-24 register.

## 2026-09-10 update

The initial MVP baseline decisions are now recorded in `.ai/DECISIONS.md`: Egypt/EGP/Cairo, Arabic/English, online-only, record-only payments, keyboard-input QR/barcode, branch-scoped roles, default-deny support access, minimum-data privacy baseline, optional child DOB, and branded SaaS only.

OQ-08 receipt numbering/content, OQ-12 guardian verification, and OQ-16 pricing rules are now approved and synchronized. Remaining blockers are Laravel scaffolding prerequisites, legal/tax integration confirmation, and later M2–M6 operational decisions not needed for the foundation slice.
