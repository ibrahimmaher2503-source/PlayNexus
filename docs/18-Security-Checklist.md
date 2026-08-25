# Security Checklist

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

