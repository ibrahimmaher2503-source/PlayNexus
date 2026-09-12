# Milestones

## M0: Decisions and scaffold

Approve MVP decisions, choose exact supported versions, scaffold Laravel, configure local/CI environments, create tenant/branch/role seed data, and prove tenant isolation with one feature test.

## M1: Access and branch foundation

Tenant context, branches, staff authentication, role policies, locale/timezone/currency configuration, audit skeleton, and responsive app shell.

**Status: DONE — 2026-09-12.** The implemented boundary passed the complete automated suite on PHP 8.4.21 and 8.5.8, current migrations and suite on isolated MySQL 8.4.11/InnoDB, and real Arabic RTL/English LTR browser acceptance. M2 and production operations remain outside this closure.

## M2: Guardian and child registration

Fast search, duplicate handling, create/edit, guardian-child links, consent, emergency contact, visit history, and authorization tests.

**Status: IN PROGRESS — 2026-09-12.** Delivered current-tenant search, masked results, duplicate-safe initial registration, family profile, basic guardian/child correction, adding another child, audit evidence, authorization/adversarial tests, and bilingual UI. Merge/review, consent, emergency/safety data, relationship revocation, visit history, fresh MySQL/browser evidence, and full M2 acceptance remain open.

## M3: Pricing, tickets, and check-in

Pricing rules, ticket types, QR/barcode token, check-in workflow, live session board, branch capacity, and idempotent session creation.

**Status: IN PROGRESS — 2026-09-12.** Delivered current-scope immutable fixed-duration pricing-rule creation/listing and atomic version replacement with integer EGP storage, branch tax snapshots, fixed grace/overtime terms, optimistic conflicts, audit, authorization tests, and bilingual UI. Calculation, ticket types/QR, check-in, live sessions, capacity, browser/MySQL acceptance, and M3 closure remain open; OQ-18 blocks ticket behavior.

## M4: Time engine and checkout

Pause/resume/extend/cancel, alerts, guardian verification, final calculation, manager adjustment, concurrency protection, and explainable billing breakdown.

## M5: POS, payment, refund, and receipt

Catalog/cart, tax, discount approval, payment recording, refund audit, digital receipt, and transaction reconciliation.

## M6: Reports, notifications, hardening, pilot

Core reports, operational notifications, audit views, localization/accessibility/performance/security passes, backups/restore exercise, pilot seed data, and staff walkthrough. Add basic incident recording/search in this milestone only if its open MVP scope interpretation is approved.

Each milestone ships a usable vertical slice and must meet [Definition of Done](17-Definition-of-Done.md).

## Approved MVP decision amendment — 2026-09-10

M3/M4 use fixed-duration packages, 10-minute grace, 30-minute overtime units, and no pause. M4 checkout requires QR plus registered-guardian confirmation or an audited manager override. M5 receipt numbering is branch-scoped and immutable; receipt voiding preserves history.
