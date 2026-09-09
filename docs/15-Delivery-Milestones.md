# Milestones

## M0: Decisions and scaffold

Approve MVP decisions, choose exact supported versions, scaffold Laravel, configure local/CI environments, create tenant/branch/role seed data, and prove tenant isolation with one feature test.

## M1: Access and branch foundation

Tenant context, branches, staff authentication, role policies, locale/timezone/currency configuration, audit skeleton, and responsive app shell.

## M2: Guardian and child registration

Fast search, duplicate handling, create/edit, guardian-child links, consent, emergency contact, visit history, and authorization tests.

## M3: Pricing, tickets, and check-in

Pricing rules, ticket types, QR/barcode token, check-in workflow, live session board, branch capacity, and idempotent session creation.

## M4: Time engine and checkout

Pause/resume/extend/cancel, alerts, guardian verification, final calculation, manager adjustment, concurrency protection, and explainable billing breakdown.

## M5: POS, payment, refund, and receipt

Catalog/cart, tax, discount approval, payment recording, refund audit, digital receipt, and transaction reconciliation.

## M6: Reports, notifications, hardening, pilot

Core reports, operational notifications, audit views, localization/accessibility/performance/security passes, backups/restore exercise, pilot seed data, and staff walkthrough. Add basic incident recording/search in this milestone only if its open MVP scope interpretation is approved.

Each milestone ships a usable vertical slice and must meet [Definition of Done](17-Definition-of-Done.md).

## Approved MVP decision amendment — 2026-09-10

M3/M4 use fixed-duration packages, 10-minute grace, 30-minute overtime units, and no pause. M4 checkout requires QR plus registered-guardian confirmation or an audited manager override. M5 receipt numbering is branch-scoped and immutable; receipt voiding preserves history.
