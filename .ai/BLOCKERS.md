# Blockers

## 2026-09-12 M1 closure

No blocker remains for M1 local acceptance. T30 MySQL, T31 PHP 8.5, and T32 browser acceptance are closed. Production readiness remains blocked on deployment-specific TLS, managed secrets, monitoring, backup/restore, and operational approval. M2 and later schema/API freeze still depend on their documented product/legal decisions; they do not reopen M1.

## 2026-09-12 current runtime blocker

T30 MySQL 8.4 and T32 browser acceptance are closed. PHP 8.5 compatibility (T31) remains unverified; full milestone and production readiness must not be claimed from the completed local slice.

No blocker prevents documentation delivery.

Laravel implementation is blocked from schema/API freeze by the open decisions in `docs/00-INDEX.md`, especially payment mode, launch country/currency/tax/receipt rules, pricing examples, guardian verification, checkout/payment topology, child/DOB and duplicate policy, and incident/shift scope. Owners and gates are recorded in the BRD OQ-01–OQ-24 register.

## 2026-09-10 update

The initial MVP baseline decisions are now recorded in `.ai/DECISIONS.md`: Egypt/EGP/Cairo, Arabic/English, online-only, record-only payments, keyboard-input QR/barcode, branch-scoped roles, default-deny support access, minimum-data privacy baseline, optional child DOB, and branded SaaS only.

OQ-08 receipt numbering/content, OQ-12 guardian verification, and OQ-16 pricing rules are now approved and synchronized. Remaining blockers are Laravel scaffolding prerequisites, legal/tax integration confirmation, and later M2–M6 operational decisions not needed for the foundation slice.
