# Checkpoint 01 — Platform / Super Admin

**Audit and implementation date:** 2026-09-17  
**Final status:** `IMPLEMENTED`  
**Production ready:** No — production Security/Legal validation of support access, deployment configuration, production-database migration rehearsal, and named UAT/go-no-go evidence remain release gates.

## Scope

This checkpoint covers Platform authentication and authorization, tenant listing and safe detail, atomic tenant/initial-owner provisioning, owner invitation acceptance/reissue, administrative suspension/reactivation, effective revocation of established tenant sessions, Platform audit evidence, tenant isolation, and the approved OQ-14/GAP-07 controlled support-access workflow. OQ-04 plan/subscription implementation is not reclassified by this checkpoint.

## Requirements Covered

- `FR-AUT-002`, `FR-AUT-005`: inactive identities and tenants are denied; established sessions are effectively revoked.
- `FR-TEN-001`, `FR-TEN-002`: atomic tenant/initial-owner provisioning and reasoned tenant lifecycle control.
- `FR-RBAC-007`, `SEC-AUD-001`: default-deny, tenant-specific, least-privilege, time-bound, revocable, audited support access visible to the Tenant Owner.
- Applicable tenant-isolation, server authorization, validation, CSRF, safe-error, and privileged-audit requirements.

## Initial State

Platform login separation, platform middleware, atomic/idempotent tenant provisioning, tenant listing, lifecycle mutation, `auth_version` revocation, and Platform audit records already existed and had focused tests. The slice lacked a secure initial-owner invitation lifecycle, a safe tenant detail page, controlled break-glass support access, a unified Platform shell, tenant-visible support history, and a mandatory human explanation for tenant suspension/reactivation. An established session hitting an administratively inactive tenant could receive a misleading not-found response.

## Defects Found

### Functional

- Provisioning created an invited owner but no hashed, expiring, single-use invitation acceptance/reissue path.
- There was no Platform tenant detail route/page.
- Status mutation accepted only a coded reason; it did not require the operator's human explanation.

### Security / Isolation

- Approved OQ-14 break-glass support access was absent.
- Administrative suspension incremented `auth_version`, but the tenant middleware did not consistently present an established-session revocation outcome.

### UI / UX

- Platform pages did not share a compact Platform navigation shell.
- Sensitive lifecycle consequences and the one-time manual invitation handoff were not presented as complete workflows.

### Audit

- No support grant/view/denial/revoke history existed.
- No invitation issue/accept/reissue/denial lifecycle existed to audit.

### Break-glass

- Only default denial existed; there was no reason-required, reauthenticated, scoped, expiring, revocable, Tenant-Owner-visible grant.

## Changes Made

- Added `tenant_owner_invitations` with unique SHA-256 token hashes, explicit manual-delivery truth, expiry, acceptance, and revocation timestamps. Raw tokens are displayed once and never persisted or audited.
- Added guest invitation acceptance and reasoned reissue. Acceptance is single-use, tamper/expiry safe, activates only the linked invited owner, and applies the approved 12-character password baseline.
- Added a safe tenant detail page with identity, state, owner contact, counts, commercial summary when already present, invitation state, and administrative audit history. It does not load family, child, safety, session, ticket, order, payment, or POS detail.
- Required a trimmed human reason for tenant status changes and retained optimistic status/version conflict protection.
- Changed administrative tenant suspension handling to invalidate the effective tenant session, clear branch context, audit the revocation, and return login/401 as appropriate. Reactivation does not reactivate individually suspended staff.
- Added `support_access_grants` and a controlled Platform UI. Grants require current-password reauthentication, tenant, allow-listed read-only scope, reason, optional ticket, and 1–60 minute duration; they auto-expire at request time and support explicit reasoned revocation.
- Implemented only two approved read-only scopes: safe tenant-administration counts and branch-configuration fields. No impersonation or operational write path exists.
- Added Tenant Owner support-access history without exposing Platform administrator identity; ordinary tenant staff are denied.
- Added a bilingual Platform shell and responsive/scannable tenant, tenant-detail, invitation, and support-access screens. Expired grants are labeled truthfully and cannot present a revoke control.

## Backend Verification

- Platform routes remain behind `platform.access`; tenant users, inactive Platform admins, and direct endpoint manipulation are denied server-side.
- Tenant creation, owner, owner role, invitation, and audit writes share the provisioning transaction and idempotency lock.
- Invitation hashes are unique; owner invitation ownership is unique; tenant/user foreign keys restrict destructive deletion.
- Support grants have constrained tenant/actor/revoker foreign keys plus tenant-expiry and actor-time indexes.
- Suspension retains all historical records and only changes administrative access state/auth versions.

## Security / Isolation Verification

- Negative tests cover ordinary tenant users on Platform routes, inactive/revoked Platform admins, cross-tenant identifiers, wrong-tenant support history, invalid support scope, failed password reauthentication, expired/revoked grants, and absence of support write/impersonation routes.
- Branch support output is an explicit allow-list (`id`, name, code, active state, timezone, currency/capacity where applicable); no family/child/session/ticket/order/payment/POS data is queried.
- Web sessions revoked by tenant suspension are redirected to login; JSON calls receive 401. Foreign/unassigned active-tenant objects remain concealed as 404.

## Browser Verification

Actual Microsoft Edge 153 headless CDP automation ran against an isolated migrated/seeded SQLite application at `http://127.0.0.1:8791`. The runner and evidence are `tools/browser-checkpoint01.mjs` and `deliverables/qa/checkpoint-01-platform/`.

Opened and exercised:

1. Platform login and tenant list.
2. A previously authenticated Tenant Owner session.
3. Tenant creation and rendered one-time owner invitation handoff.
4. Safe tenant detail and audit page.
5. Break-glass grant creation and read-only branch-configuration boundary.
6. Administrative suspension, followed by the old Tenant Owner session redirecting to `/login`.
7. Reactivation and successful fresh Tenant Owner login.
8. Arabic locale/RTL tenant list with no page overflow.

Final browser evidence recorded zero console warnings/errors. Captured tenant detail contained no Guardian/Child terms, and the scoped support page showed the expected branch configuration only.

## UI / UX Review

The Platform shell now provides one navigation model for Tenants, Plans, Subscriptions, and Support access without duplicating OQ-04 behavior. Forms expose required fields and field errors; suspension/re-activation explains consequences and requires confirmation plus reason; break-glass is visually identified as a privileged exception. English LTR and Arabic RTL rendered without page overflow. At narrow tablet width the tenant table converts to scannable cards; the Platform nav remains horizontally operable rather than clipping page content.

## Tests

- Focused Platform/support suite: `php artisan test --compact tests/Feature/PlatformAdministrationTest.php tests/Feature/SupportAccessSecurityTest.php` — 34 passed, 362 assertions.
- Cross-module affected regression (Platform, auth, tenant context, branch/RBAC, families, sessions/check-in, POS, reports, audit): 188 total, 187 passed, 1 explicit skip, 1,576 assertions.
- Full-suite final result is recorded in `.ai/TEST_RESULTS.md`.
- `npm run build` — passed, 31 modules transformed.
- `php artisan view:cache` — passed.
- Final Pint, documentation validation, and whitespace validation are recorded in `.ai/TEST_RESULTS.md`.

## Remaining Gaps

- Production Security/Legal validation of the approved support-access policy is an external release gate.
- No notification provider is approved; invitation delivery therefore remains an explicit secure manual handoff rather than a false email-delivery claim.
- This checkpoint used isolated SQLite and real Edge. The new migrations and workflows still need the normal production-MySQL/staging migration rehearsal and operational UAT before `PRODUCTION_READY`.
- Accessibility follows the existing semantic/focus baseline, but a full automated scan plus keyboard/screen-reader acceptance remains a product-wide release gate.

## Final Status

`IMPLEMENTED`

The approved Platform Administration vertical slice works end to end locally, including backend/database integrity, negative authorization, established-session suspension, controlled break-glass support access, bilingual real-browser journeys, and regression coverage. It is not `PRODUCTION_READY` because the remaining items are production/environment/external approval gates rather than missing core behavior.
