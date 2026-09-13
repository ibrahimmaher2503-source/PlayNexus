# Master Checklist

## Product decisions

- [ ] Resolve payment mode, hardware, launch markets, currencies, tax/receipt rules, pricing, pauses, overage, checkout verification, subscriptions, offline requirement, notification providers, incident MVP scope, and minimal cashier-close scope.
- [ ] Approve BRD, SRS, permission matrix, architecture, ERD, API, and wireframes.
- [ ] Baseline scope and change-control owner.

## Foundation

- [x] Scaffold the selected Laravel release and record exact setup commands.
- [ ] Configure environment validation, CI, formatting, error tracking, backups, and secrets.
- [x] Implement tenant context, branch scope, authentication, policies, and audit correlation.
- [ ] Seed deterministic demo tenants/branches/users without real child or payment data.

## MVP delivery

- [x] M1 access and branch foundation.
- [ ] M2 guardian and child registration.
  - [x] Security remediation: action-level family abilities, server-side PII masking, inactive-record search consistency, and Cashier negative tests.
  - [x] First slice: tenant-scoped search and atomic guardian + child + active relationship creation.
  - [x] Maintenance slice: family detail, basic guardian/child correction, and adding another child with conflict/audit protection.
  - [x] Egypt decision closure: OQ-17 hard reuse, versioned legal-guardian consent, retention, emergency/safety, relationship lifecycle, visit-history dependency waiver, and OQ-20 incident deferral.
  - [x] Egypt contract implementation: tenant-phone uniqueness, consent grant/withdrawal, emergency/safety fields, relationship link/revoke/reactivate, role/scope controls, and automated SQLite/MySQL acceptance.
  - [ ] Release gates: real browser acceptance and production Legal/DPO approval. Visit history and retention execution wait for M3 session/last-visit data by approved waiver.
- [x] M3 pricing, tickets, and check-in — locally accepted; production release gates remain global.
  - [x] First slice: immutable fixed-duration pricing-rule creation/listing with scope, money, audit, and bilingual UI checks.
  - [x] Version slice: atomic retirement plus immutable version +1 replacement with conflict, audit, scope, and bilingual UI checks.
  - [x] Egypt OQ-18 ticket decision: branch/service-date scope, pre-scan-only reassignment, first-successful-scan transfer lock, and unused-ticket refund eligibility with manager/owner approval and audit.
  - [x] Ticket-only lifecycle: immutable type/dated issue/opaque QR/idempotent validation/first-scan holder lock/audited pre-scan correction/unused manager cancellation/same-identity reprint.
  - [x] Ticket engineering acceptance: fresh isolated MySQL migrations/full suite, true two-process retry checks, scoped authenticated bilingual/mobile headless-browser QA, QR decoding and single-page A5 output.
  - [x] Ticket-backed check-in: atomic consumption plus one Active session, immutable pricing/time facts, fresh scope/eligibility checks, tenant-wide child uniqueness and hard branch capacity.
  - [x] Live-session acceptance: masked bilingual board, server filters/pagination, Owner/Cashier browser QA, SQLite/PHP 8.5/MySQL regression and real distinct-key concurrency.
  - [x] Read-only estimate: approved tax/time fixtures, exact integer boundaries, non-mutation/fail-closed behavior, full MySQL/SQLite/PHP 8.5 and bilingual responsive browser evidence.
  - [x] M3 local closure. Checkout, guardian release and financial workflows remain later; ticket cancellation does not execute a refund.
- [ ] M4 time engine and checkout.
  - [x] OQ-19 reception-to-cashier preparation: guardian verification or audited manager override, frozen quote, idempotent `pending_payment` handoff.
  - [x] Live-session UI: due/overdue labels, fixed 30-minute extension, manager/owner additive adjustment with reason, and cancellation with explicit no-refund consequence.
  - [x] Pending-payment UI: frozen subtotal/tax/total breakdown with payment, receipt, refund, shift, and child-release controls excluded.
  - [ ] Final runtime acceptance and remaining checkout/time-engine gates; pause/resume is intentionally excluded from the Egypt MVP.
- [ ] M5 POS, payment, refund, and receipt.
- [ ] M6 reports, notifications, hardening, and pilot.

## Release

- [ ] Critical acceptance criteria pass.
- [ ] Tenant, authorization, money/time, concurrency, security, localization, RTL, accessibility, and restore checks pass.
- [ ] Pilot data migration/onboarding rehearsed.
- [ ] Monitoring, support ownership, rollback, and incident contacts documented.
- [ ] Staff training and pilot go/no-go approval complete.

## Approved MVP decision amendment — 2026-09-10

- [x] Approve branch-scoped immutable receipt numbers and receipt content baseline.
- [x] Approve QR plus registered-guardian confirmation and audited manager override.
- [x] Approve fixed-duration pricing, 10-minute grace, 30-minute overtime units, integer minor units, configurable tax, and no pause in MVP.
- [ ] Synchronize generated migrations and OpenAPI enums/schemas during Laravel scaffold.
