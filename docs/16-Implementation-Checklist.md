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
