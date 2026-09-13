# Security Checklist

## 2026-09-13 check-in security evidence

The implemented check-in path reauthorizes fresh tenant/actor/branch scope under lock, hides foreign/unassigned/inactive scope, hashes lookup material, removes submitted ticket code before validation flash, masks Cashier data, enforces composite tenant foreign keys, and rolls back ticket/session/event/audit state together. Real InnoDB contention and hostile role/tenant/state tests pass. This is local application evidence, not deployment penetration testing or production approval.

The live estimate accepts no client total or timestamp, reads only the immutable server-side snapshot and one UTC clock, uses bounded integer arithmetic, persists nothing, and hides itself on malformed input. A feature test proves ticket/session/event/scan/audit state is unchanged by GET. This does not replace final checkout recalculation, settlement or release controls.

## M1 review record — 2026-09-12

M1 locally verifies generic/throttled authentication, session rotation and revocation, CSRF-protected mutations, deny-by-default tenant/branch/platform policies, expected-state conflicts, tenant-scoped transactions, and atomic audit records. Composer and npm report zero known vulnerabilities. TLS, managed secrets, centralized monitoring, backup/restore, and production host controls remain release gates and are not marked complete here.

## Identity and access

- [ ] Secure password hashing, login throttling, session rotation, logout invalidation, and secure cookies.
- [ ] Server-side policies on every protected action; deny by default.
- [ ] Tenant and branch ownership checked independently of user-supplied IDs.
- [ ] Sensitive approvals cannot be self-approved unless explicitly allowed and audited.

## Data protection

- [ ] TLS in transit and managed encryption at rest.
- [ ] Secrets outside source control and rotated through the hosting platform.
- [ ] Child/guardian fields minimized, access-limited, retention-defined, and masked in logs/exports.
- [ ] Upload type, size, content, storage visibility, and authorization validated.
- [ ] Consent, opt-out, deletion/retention, and cross-border requirements approved for launch countries.

## Application security

- [ ] CSRF protection for web actions; Sanctum/token scopes for APIs.
- [ ] Form requests, output escaping, safe file names, rate limits, and generic authentication errors.
- [ ] Mass assignment restricted; raw SQL parameterized; IDs do not imply authorization.
- [ ] Idempotency, locking, transactions, and replay protection on financial and session-finalization actions.

## Operations

- [ ] Immutable audit trail for login, permission, session, safety, discount, refund, payment, export, and configuration events.
- [ ] Centralized error monitoring without sensitive payloads.
- [ ] Automated backups, access controls, retention, restore test, disaster recovery owners, and incident playbook.
- [ ] Dependency and container/host security updates included in release operations.
