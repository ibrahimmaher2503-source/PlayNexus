# Master Checklist

## Product decisions

- [ ] Resolve payment mode, hardware, launch markets, currencies, tax/receipt rules, pricing, pauses, overage, checkout verification, subscriptions, offline requirement, notification providers, incident MVP scope, and minimal cashier-close scope.
- [ ] Approve BRD, SRS, permission matrix, architecture, ERD, API, and wireframes.
- [ ] Baseline scope and change-control owner.

## Foundation

- [ ] Scaffold the selected Laravel release and record exact setup commands.
- [ ] Configure environment validation, CI, formatting, error tracking, backups, and secrets.
- [ ] Implement tenant context, branch scope, authentication, policies, and audit correlation.
- [ ] Seed deterministic demo tenants/branches/users without real child or payment data.

## MVP delivery

- [ ] M1 access and branch foundation.
- [ ] M2 guardian and child registration.
- [ ] M3 pricing, tickets, and check-in.
- [ ] M4 time engine and checkout.
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
