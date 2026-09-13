# Milestones

## M0: Decisions and scaffold

Approve MVP decisions, choose exact supported versions, scaffold Laravel, configure local/CI environments, create tenant/branch/role seed data, and prove tenant isolation with one feature test.

## M1: Access and branch foundation

Tenant context, branches, staff authentication, role policies, locale/timezone/currency configuration, audit skeleton, and responsive app shell.

**Status: DONE — 2026-09-12.** The implemented boundary passed the complete automated suite on PHP 8.4.21 and 8.5.8, current migrations and suite on isolated MySQL 8.4.11/InnoDB, and real Arabic RTL/English LTR browser acceptance. M2 and production operations remain outside this closure.

## M2: Guardian and child registration

Fast search, duplicate handling, create/edit, guardian-child links, consent, emergency contact, visit history, and authorization tests.

**Status: IMPLEMENTED / RELEASE PARTIAL — 2026-09-13.** Delivered active current-tenant search, DB-enforced duplicate-safe registration with hard existing-family reuse, role-aware masked presentation, guardian/child maintenance, Arabic-first versioned child-data consent, separate optional marketing choice and withdrawal, required emergency contact, encrypted restricted safety notes, verified relationship link/reactivate/revoke with the final-guardian invariant, audit evidence, negative authorization/PII tests, and bilingual UI. Cashier retains lookup, masked viewing, and first registration but no maintenance permission. Full SQLite and isolated MySQL 8.4.11/InnoDB regression passes 241 / 1,997. Real browser acceptance and production Legal/DPO approval remain release gates. Visit history and retention execution wait for M3 session/last-visit data by approved waiver; merge, photos, and incidents are deferred.

## M3: Pricing, tickets, and check-in

Pricing rules, ticket types, QR/barcode token, check-in workflow, live session board, branch capacity, and idempotent session creation.

**Status: LOCALLY ACCEPTED / RELEASE PARTIAL — 2026-09-13.** Delivered immutable fixed-duration pricing versions; branch/date ticket type/issue/opaque QR/validate/pre-scan correction/unused cancellation/reprint; atomic ticket-backed check-in, hard capacity and masked live board; plus an exact read-only Active-session estimate from immutable time/money/tax facts. The estimate handles grace, ceil overtime and inclusive/exclusive half-up tax, is labelled non-final, persists nothing and fails closed on malformed snapshots. Full MySQL passes 281 / 2,434; SQLite/PHP 8.5 pass 279 of 281 / 2,387 with two MySQL-only skips; real concurrency and authenticated responsive bilingual Edge QA pass. Checkout, guardian release, payments, receipts, financial refunds and production gates remain M4/M5/release scopes.

## M4: Time engine and checkout

**Status: PARTIAL — 2026-09-13.** OQ-19 is resolved as a reception-to-cashier handoff: Reception/Manager verifies the guardian or records an audited override, freezes the exact server quote, and moves the session to `pending_payment`; Cashier receives the queue for the later M5 matching-payment command. Preparation is idempotent and lock/version guarded. Payment, receipt, child release, refunds, shifts, and the remaining time-engine transitions are not implemented.

Pause/resume/extend/cancel, alerts, guardian verification, final calculation, manager adjustment, concurrency protection, and explainable billing breakdown.

## M5: POS, payment, refund, and receipt

Catalog/cart, tax, discount approval, payment recording, refund audit, digital receipt, and transaction reconciliation.

## M6: Reports, notifications, hardening, pilot

Core reports, operational notifications, audit views, localization/accessibility/performance/security passes, backups/restore exercise, pilot seed data, and staff walkthrough. Add basic incident recording/search in this milestone only if its open MVP scope interpretation is approved.

Each milestone ships a usable vertical slice and must meet [Definition of Done](17-Definition-of-Done.md).

## Approved MVP decision amendment — 2026-09-10

M3/M4 use fixed-duration packages, 10-minute grace, 30-minute overtime units, and no pause. M4 checkout requires QR plus registered-guardian confirmation or an audited manager override. M5 receipt numbering is branch-scoped and immutable; receipt voiding preserves history.
