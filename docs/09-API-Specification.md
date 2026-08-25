# PlayNexus REST API Specification

**Document ID:** PN-API-001  
**Status:** Draft MVP contract pending stakeholder decisions and contract validation  
**Base path:** `/api/v1`  
**Machine contract:** `contracts/openapi.yaml`  
**Format:** JSON over HTTPS

## 1. Purpose and consumers

The REST API supports approved PlayNexus clients and future integrations without forcing the Laravel web UI to become an API client. The first-party Blade/Livewire application uses secure session-authenticated web routes. API clients use Laravel Sanctum bearer tokens.

The API covers the MVP operational core: authentication/password reset, tenant/branch/staff administration, guardian/child registration, tickets, sessions, approvals, POS/payment/refund/receipt records, operational notifications, reports, and audit access. The request shapes implement the full-payment/full-refund planning assumptions ASM-08/ASM-10 pending OQ-09; they are not a hidden finance approval. Basic-incident routes are a conditional contract section and must stay disabled unless OQ-20 is approved. Games/queues/participation, cashier shifts, online payment processing, parent self-service, birthday bookings, memberships, loyalty, marketing campaigns, and payment webhooks are deferred. An authenticated notification-delivery callback is included only to distinguish `sent` from provider-confirmed `delivered`.

## 2. General conventions

### 2.1 URL and media types

```text
https://{environment-host}/api/v1
Accept: application/json
Content-Type: application/json
```

Use plural kebab-case resource paths. Resource identifiers are opaque ULIDs. Clients must not infer creation time, tenant, or type from an ID.

Success responses use:

```json
{
  "data": {}
}
```

Collections add pagination metadata:

```json
{
  "data": [],
  "meta": {
    "next_cursor": "opaque-or-null",
    "per_page": 25
  },
  "links": {
    "next": "https://api.example.com/api/v1/sessions?cursor=opaque"
  }
}
```

Delete/archive actions return `204 No Content`. Create commands normally return `201`. State-transition commands return the updated aggregate with `200`.

### 2.2 Naming

- JSON properties use `snake_case`.
- Dates use `YYYY-MM-DD`.
- Timestamps use RFC 3339 UTC with `Z`, for example `2026-08-24T10:15:30.123456Z`.
- Durations use integer seconds.
- Money uses `{ "amount_minor": 12550, "currency": "EGP" }` semantics; no decimal money strings or floats.
- Rates use basis points (`1500` means 15%).
- `lock_version` is an integer and increments after each aggregate mutation.

### 2.3 Content localization

Clients may send `Accept-Language: ar` or `en`. Error `title`/`detail` may be localized, but machine-readable `code`, status, and field pointers remain stable. Stored master-data names are returned as configured; the API does not translate tenant-entered text.

## 3. Authentication and tenant scope

### 3.1 Web versus API authentication

- Web staff login uses Laravel session cookies, CSRF protection, session regeneration, and same-site cookie controls outside `/api/v1`.
- API login returns a Sanctum bearer token. Store it in a secure client secret store, never browser local storage for the first-party web UI.
- Tokens expire, are revocable, and carry abilities. The plaintext token is shown once.
- `POST /auth/logout` revokes only the current token. User suspension/disable revokes all tokens and sessions.
- The PRD lists `/auth/refresh` as an example for JWT/session authentication. This contract uses Laravel sessions and opaque Sanctum tokens, so it deliberately has no JWT refresh endpoint: an expired API token requires re-authentication or an explicitly approved rotation flow. If a JWT client is later selected, refresh-token storage, rotation, replay detection, and revocation require a contract change.

### 3.2 Tenant derivation

For tenant staff, `tenant_id` is derived from the authenticated user/token. The API rejects or ignores no client value: tenant-owned create/update schemas simply do not contain `tenant_id`. A tenant user cannot switch tenant context with `X-Tenant-Id`, route parameters, query values, or payload fields.

Platform endpoints live under `/platform/*` and require `super_admin`. Super Admin does not receive routine operational tenant access. Support access is a separate, time-bound break-glass workflow and is not exposed in this MVP API.

Branch-scoped endpoints accept `branch_id` only as an operational selection. Policies verify it belongs to the authenticated tenant and appears in the actor's active branch assignments. Tenant Owner may select any active branch in their tenant.

### 3.3 Authentication endpoints

| Method/path | Permission | Notes |
|---|---|---|
| `POST /auth/login` | Public, rate limited | Email/password and token name; returns token once |
| `POST /auth/logout` | Authenticated | Revoke current token |
| `GET /auth/me` | Authenticated | Effective tenant, role codes, branch IDs, abilities |
| `POST /auth/password-reset/request` | Public, rate limited | Always returns the same accepted response; never reveals account existence |
| `POST /auth/password-reset` | Public with single-use reset token, rate limited | Validate token/email/password confirmation, reset password, revoke active sessions/tokens |

Example login:

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "reception@example.test",
  "password": "correct-horse-battery-staple",
  "token_name": "Reception Tablet 1"
}
```

Password-reset request returns `202` for syntactically valid known and unknown emails. Reset tokens are random, hashed at rest, expire, are consumed once, and never appear in logs/audit payloads.

```json
{
  "data": {
    "token": "1|plain-text-token-shown-once",
    "token_type": "Bearer",
    "expires_at": "2026-09-23T10:00:00Z",
    "user": {
      "id": "01K3F2ZQJ65V5X8HDM08G4FQ3E",
      "name": "Mona Hassan",
      "tenant_id": "01K3F1YZXF5KT8KAG0KQZ7C9PW",
      "roles": ["reception_staff"],
      "branch_ids": ["01K3F26MGG34P4KZVY8W2RQ1AX"]
    }
  }
}
```

## 4. Authorization and data minimization

Every endpoint maps to the permission codes in `08-Permission-Matrix.md`. An ability on a token can reduce the user's current permissions but can never expand them. Authorization checks execute in this order:

```text
authentication → tenant/account state → branch assignment → permission
→ resource scope → record state → approval/version → command
```

Cross-tenant and hidden-branch resources return `404` even when the supplied ID is valid elsewhere. List/search serializers mask phone, email, date of birth, child photos, safety notes, incident narratives, and external payment references according to role and purpose.

## 5. Idempotency and concurrency

### 5.1 Idempotency

`Idempotency-Key` is required for commands that could duplicate attendance or money:

- check-in and checkout;
- ticket issue;
- order creation;
- payment recording;
- refund request/execution;
- approval request/decision;
- notification send/resend;
- session extension/cancellation/manual adjustment and relationship linking.

Rules:

1. Key length is 16–100 visible ASCII characters; use UUIDv4 or equivalent randomness.
2. Scope is authenticated actor + tenant + route/operation.
3. The server stores a canonical request hash and completed response for at least 24 hours.
4. An identical replay returns the original status/body and `Idempotent-Replayed: true`.
5. Reuse with a different payload returns `409 idempotency_key_reused`.
6. An in-progress duplicate returns `409 request_in_progress` and `Retry-After`.

### 5.2 Optimistic and pessimistic concurrency

Mutable aggregates return `lock_version` and an `ETag` equal to the quoted version, for example `ETag: "3"`. Mutation/state-transition requests send `If-Match: "3"`. Missing required precondition returns `428`; a stale version returns `409 stale_version` with the current version when the caller may view it.

The server still uses row locks and database transactions for check-in, pause/resume/extend/cancel/adjust, checkout, order totals, full payment/refund, approval consumption, and receipt numbering. `If-Match` improves client behavior; it is not the only concurrency control.

## 6. Errors

Errors use `application/problem+json` and RFC 9457-style fields:

```json
{
  "type": "https://docs.playnexus.example/problems/validation_failed",
  "title": "Validation failed",
  "status": 422,
  "code": "validation_failed",
  "detail": "One or more fields are invalid.",
  "instance": "/api/v1/sessions/check-in",
  "request_id": "b7b7629e-1259-4ee0-a65e-262419c20ef0",
  "errors": [
    {
      "field": "/child_id",
      "code": "active_session_exists",
      "message": "The child already has an active session."
    }
  ]
}
```

| HTTP | Stable codes | Meaning |
|---:|---|---|
| `400` | `invalid_json`, `invalid_query` | Malformed request |
| `401` | `unauthenticated`, `token_expired` | Missing/invalid credentials |
| `403` | `forbidden`, `approval_required` | Authenticated but not allowed |
| `404` | `resource_not_found` | Missing or outside visible tenant/branch scope |
| `409` | `invalid_state`, `stale_version`, `active_session_exists`, `already_refunded`, `idempotency_key_reused`, `request_in_progress` | Conflict with current state |
| `422` | `validation_failed`, `pricing_invalid`, `full_amount_required`, `amount_due` | Semantic validation failure |
| `428` | `precondition_required` | Required `If-Match` missing |
| `429` | `rate_limited` | Throttled; use `Retry-After` |
| `500` | `internal_error` | Unexpected failure; no internals exposed |
| `503` | `service_unavailable` | Required system unavailable |

Each response includes `X-Request-Id`; an accepted client `X-Request-Id` must be a valid UUID and is regenerated if invalid.

## 7. Filtering, sorting, pagination, and search

- Cursor pagination is default: `?per_page=25&cursor=opaque`; allowed range `1..100`.
- Stable default order is `created_at DESC, id DESC` unless the resource defines another order.
- Filters use explicit query properties, not a free-form expression language: `branch_id`, `status`, `from`, `to`, etc.
- Date ranges are interpreted in the selected branch time zone and converted to half-open UTC ranges. Tenant-wide multi-time-zone reports require explicit UTC timestamps or group by branch-local date.
- Search is `?search=` and is trimmed, normalized, length-limited, rate-limited, and scoped. Guardian phone search normalizes to E.164 where possible.
- Unknown filters/sorts return `400 invalid_query` rather than being silently ignored.

## 8. Endpoint catalog

### 8.1 Platform, branch, and staff

| Method/path | Permission | Idempotency / version | Result |
|---|---|---|---|
| `GET /platform/tenants` | `platform.tenants.view` | — | paged tenant metadata |
| `POST /platform/tenants` | `platform.tenants.create` | key required | create pending tenant and initial owner invite |
| `PATCH /platform/tenants/{tenant_id}` | `platform.tenants.status` or profile ability | `If-Match` | update account metadata/status |
| `GET /branches` | `branches.view` | — | branches visible to actor |
| `POST /branches` | `branches.create_archive` | key required | create branch with timezone, currency/tax, capacity, and seven-day opening-hours schedule |
| `GET /branches/{branch_id}` | `branches.view` | — | branch |
| `PATCH /branches/{branch_id}` | `branches.update` | `If-Match` | update settings/status/capacity |
| `GET /staff` | `staff.view` | — | scoped staff list |
| `POST /staff` | `staff.manage` | key required | invite staff |
| `PATCH /staff/{user_id}` | `staff.manage` | `If-Match` | status/branch assignment/allowed role changes |

Staff creation accepts name, normalized unique email, optional phone, allowed role codes, and allowed branch IDs; it creates an invited user only. Update accepts status/role/branch changes permitted by the matrix, prevents removal of the last owner, rejects future/unseeded roles, increments `lock_version`, revokes access immediately on suspension, and audits before/after scope.

### 8.2 Guardians and children

| Method/path | Permission | Notes |
|---|---|---|
| `GET /guardians?search=` | `customers.search` | possible matches, masked by role |
| `POST /guardians` | `guardians.create` | duplicate behavior remains provisional pending OQ-17 |
| `GET /guardians/{guardian_id}` | `guardians.view` | scoped customer detail |
| `PATCH /guardians/{guardian_id}` | `guardians.update` | `If-Match`; consent fields require consent ability |
| `POST /guardians/{guardian_id}/children` | `children.manage` | creates child and active relationship atomically |
| `POST /guardians/{guardian_id}/relationships` | `relationships.manage` | link existing child after verification |
| `GET /children?search=` | `customers.search` | name/allowed identifiers only |
| `GET /children/{child_id}` | `children.view` | masked fields by role |
| `PATCH /children/{child_id}` | `children.manage` | `If-Match` |

Create guardian and child:

OQ-15 (DOB/age representation) and OQ-17 (duplicate handling) remain open. The current fields/examples are proposed contract shapes, not an approved legal/product choice; freeze and migration generation are blocked until those decisions close.

```http
POST /api/v1/guardians
Authorization: Bearer {token}
Idempotency-Key: 2ac47a1d-7f6a-49bc-b1ca-3e589dbb27c0

{
  "full_name": "Ahmed Samir",
  "phone_e164": "+201001112233",
  "email": "ahmed@example.test",
  "preferred_locale": "ar",
  "marketing_consent": false,
  "duplicate_reviewed": false
}
```

```json
{
  "data": {
    "id": "01K3G0A9CXJK5EHT6M5TTVAHE4",
    "full_name": "Ahmed Samir",
    "phone_masked": "+20••••••2233",
    "email_masked": "a••••@example.test",
    "marketing_consent": false,
    "lock_version": 1,
    "created_at": "2026-08-24T10:10:00Z"
  }
}
```

```http
POST /api/v1/guardians/01K3G0A9CXJK5EHT6M5TTVAHE4/children
Idempotency-Key: 4b3081f3-3a0b-4cb8-b3f3-2a1be3f86867

{
  "full_name": "Lina Ahmed",
  "date_of_birth": "2020-05-14",
  "relationship": "father",
  "can_check_out": true,
  "is_primary": true,
  "emergency_contact_name": "Ahmed Samir",
  "emergency_contact_phone_e164": "+201001112233"
}
```

### 8.3 Pricing and tickets

| Method/path | Permission | Notes |
|---|---|---|
| `GET /pricing-rules?branch_id=` | `pricing.view` | active/effective rules by default |
| `POST /pricing-rules` | `pricing.manage` | immutable version creation |
| `GET /ticket-types?branch_id=` | `ticket_types.view` | sellable active ticket types |
| `POST /ticket-types` | `ticket_types.manage` | create a manager-controlled ticket type tied to an eligible pricing rule |
| `PATCH /ticket-types/{ticket_type_id}` | `ticket_types.manage` | `If-Match`; update display/validity policy or retire future sales without repricing issued tickets |
| `POST /tickets` | `tickets.issue` | key required; returns QR payload once and printable display code |
| `GET /tickets/{ticket_id}` | `tickets.scan` or view ability | never returns reusable raw code to unauthorized roles |
| `POST /tickets/scan` | `tickets.scan` | key required; logs accepted and rejected scans |
| `POST /tickets/{ticket_id}/cancel` | `tickets.cancel` + approval where policy requires | `If-Match`, reason, approval ID |
| `POST /tickets/{ticket_id}/reprint` | `tickets.reprint` | key + `If-Match`; same ID/QR/validity/price/state, new audit event only |

Issue response excerpt:

```json
{
  "data": {
    "id": "01K3G1BQYV0Z1KAH61Z3AQYCSJ",
    "ticket_type_id": "01K3G14GK5TQH47VVA0A4EBTJB",
    "status": "issued",
    "display_code": "PN-8F4K2M",
    "qr_payload": "pnx_opaque_payload_returned_once",
    "valid_from": "2026-08-24T10:15:00Z",
    "valid_until": "2026-08-24T14:15:00Z",
    "lock_version": 1
  }
}
```

### 8.4 Sessions

| Method/path | Permission | Required controls |
|---|---|---|
| `GET /sessions` | `sessions.view` | branch/status/date filters; cursor paging |
| `POST /sessions/check-in` | `sessions.check_in` | key; active branch; guardian relationship; ticket/pricing rule |
| `GET /sessions/{session_id}` | `sessions.view` | includes server-derived live timing summary |
| `POST /sessions/{session_id}/pause` | `sessions.pause_resume` | key, `If-Match`, pause reason/approval if configured |
| `POST /sessions/{session_id}/resume` | `sessions.pause_resume` | key, `If-Match` |
| `POST /sessions/{session_id}/extend` | `sessions.extend` | key, `If-Match`; configured option or approved manual seconds; server prices and reschedules alert |
| `POST /sessions/{session_id}/checkout-quote` | `sessions.checkout` | non-mutating quote except audit; no idempotency key |
| `POST /sessions/{session_id}/checkout` | `sessions.checkout` | key, `If-Match`, fully paid linked order, OQ-12-approved guardian evidence or override |
| `POST /sessions/{session_id}/cancel` | `sessions.cancel` | key, `If-Match`, reason, approval |
| `POST /sessions/{session_id}/adjustments` | `sessions.adjust.approve` | key, `If-Match`, consumed approval |

Check-in:

```http
POST /api/v1/sessions/check-in
Authorization: Bearer {token}
Idempotency-Key: 03dcc7dd-d411-4d6e-877a-3bf3bc7aa517

{
  "branch_id": "01K3F26MGG34P4KZVY8W2RQ1AX",
  "child_id": "01K3G0J7QYK6XRWA62JKGQQM6T",
  "guardian_id": "01K3G0A9CXJK5EHT6M5TTVAHE4",
  "ticket_id": "01K3G1BQYV0Z1KAH61Z3AQYCSJ",
  "pricing_rule_id": "01K3G12N5E53ZDWBX2X9DYG8Q4"
}
```

```json
{
  "data": {
    "id": "01K3G1GN9Z0SE2CH0B3DT7T960",
    "branch_id": "01K3F26MGG34P4KZVY8W2RQ1AX",
    "child_id": "01K3G0J7QYK6XRWA62JKGQQM6T",
    "ticket_id": "01K3G1BQYV0Z1KAH61Z3AQYCSJ",
    "status": "active",
    "started_at": "2026-08-24T10:20:00Z",
    "expected_end_at": "2026-08-24T11:20:00Z",
    "elapsed_seconds": 0,
    "billable_seconds": 0,
    "currency": "EGP",
    "estimated_total_minor": 25000,
    "lock_version": 1
  }
}
```

Pause:

```http
POST /api/v1/sessions/01K3G1GN9Z0SE2CH0B3DT7T960/pause
If-Match: "1"
Idempotency-Key: 3ab23b06-20ea-4380-850a-943f97e467ef

{
  "pause_reason_id": "01K3G18Y7D1A6SR7FZHCYC9QY6",
  "reason_note": "Child requested a rest."
}
```

Checkout quote:

```json
{
  "data": {
    "session_id": "01K3G1GN9Z0SE2CH0B3DT7T960",
    "quoted_at": "2026-08-24T11:37:30Z",
    "session_lock_version": 3,
    "elapsed_seconds": 4650,
    "excluded_pause_seconds": 600,
    "billable_seconds": 4050,
    "lines": [
      {"code": "base_60_minutes", "amount_minor": 25000},
      {"code": "overtime_15_minutes", "quantity": 1, "amount_minor": 7500}
    ],
    "subtotal_minor": 32500,
    "tax_minor": 4550,
    "adjustment_minor": 0,
    "total_minor": 37050,
    "currency": "EGP",
    "expires_at": "2026-08-24T11:38:00Z"
  }
}
```

Checkout:

```http
POST /api/v1/sessions/01K3G1GN9Z0SE2CH0B3DT7T960/checkout
If-Match: "3"
Idempotency-Key: b9ff5132-d11c-4f8a-b8c7-09dd3ef07989

{
  "order_id": "01K3G3Y3ZB9D79C89BYH6PXRT7",
  "guardian_id": "01K3G0A9CXJK5EHT6M5TTVAHE4",
  "verification_method": "relationship"
}
```

```json
{
  "data": {
    "session": {
      "id": "01K3G1GN9Z0SE2CH0B3DT7T960",
      "status": "completed",
      "ended_at": "2026-08-24T11:37:34Z",
      "billable_seconds": 4054,
      "total_minor": 37050,
      "currency": "EGP",
      "lock_version": 4
    },
    "order": {
      "id": "01K3G3Y3ZB9D79C89BYH6PXRT7",
      "status": "paid",
      "total_minor": 37050,
      "paid_minor": 37050,
      "currency": "EGP",
      "lock_version": 1
    }
  }
}
```

**OQ-19 remains an explicit open decision.** This API does not decide whether Reception posts the payment or hands the draft order to Cashier. It exposes recoverable quote/order, full-payment, and completion commands. `checkout` rejects `amount_due > 0`; a paid-but-not-completed session remains visible and retryable. **OQ-12 also remains open:** `verification_method` is a proposed placeholder constrained at implementation to the Safety/Legal-approved allowlist; manager override uses a bound approval.

## 9. Approvals

| Method/path | Permission | Notes |
|---|---|---|
| `POST /approvals` | action-specific request permission | creates pending, payload-bound request |
| `POST /approvals/{approval_id}/approve` | action-specific approval permission | key + `If-Match`; cannot self-approve |
| `POST /approvals/{approval_id}/reject` | action-specific approval permission | key + `If-Match`; decision reason required |
| `GET /approvals` | requester/approver in scope | filter by branch/status/action |

Refund approval request:

```json
{
  "action": "refund",
  "branch_id": "01K3F26MGG34P4KZVY8W2RQ1AX",
  "subject_type": "order",
  "subject_id": "01K3G3Y3ZB9D79C89BYH6PXRT7",
  "subject_lock_version": 2,
  "request_reason": "Duplicate cash entry",
  "request_payload": {
    "refund_type": "full"
  }
}
```

Approval response:

```json
{
  "data": {
    "id": "01K3G5BJKQ83QZ15SD3RK58QNJ",
    "action": "refund",
    "status": "approved",
    "subject_type": "order",
    "subject_id": "01K3G3Y3ZB9D79C89BYH6PXRT7",
    "expires_at": "2026-08-24T12:30:00Z",
    "lock_version": 2
  }
}
```

## 10. POS, full payments, full refunds, and receipts

| Method/path | Permission | Required controls |
|---|---|---|
| `GET /products?branch_id=` | `products.view` | active sellable items |
| `POST /products` | `products.manage` | create a branch/tenant catalog item from server-validated price and tax fields |
| `PATCH /products/{product_id}` | `products.manage` | `If-Match`; update/retire future sales without changing posted order snapshots |
| `POST /pos/orders` | `orders.manage` | key; server prices catalog/ticket lines |
| `GET /pos/orders/{order_id}` | order/report permission | masked references by role |
| `PATCH /pos/orders/{order_id}` | `orders.manage` | `If-Match`; open orders only |
| `POST /pos/orders/{order_id}/payments` | `payments.record` | key + `If-Match`; server verifies balance |
| `POST /pos/orders/{order_id}/refunds` | `refunds.execute` | key + `If-Match`; approved request required |
| `GET /pos/orders/{order_id}/receipt` | `receipts.view_issue` | JSON receipt; printable web/PDF artifact may be separate |
| `POST /pos/orders/{order_id}/receipt/resend` | `receipts.resend` | key; reuse same receipt facts/number and queue allowlisted receipt notification |

Create sale:

```json
{
  "branch_id": "01K3F26MGG34P4KZVY8W2RQ1AX",
  "guardian_id": "01K3G0A9CXJK5EHT6M5TTVAHE4",
  "items": [
    {
      "item_kind": "product",
      "product_id": "01K3G42YNWZV1RD2G5T7KTHY54",
      "quantity": 2
    }
  ],
  "discount_minor": 0
}
```

Record payment:

```http
POST /api/v1/pos/orders/01K3G3Y3ZB9D79C89BYH6PXRT7/payments
If-Match: "1"
Idempotency-Key: 60c14943-a821-49d1-bb72-c9b312b205f9

{
  "method": "cash",
  "amount_minor": 37050,
  "currency": "EGP",
  "external_reference": null
}
```

```json
{
  "data": {
    "payment": {
      "id": "01K3G4G3PS4S4GRD0J9CQ1YZTT",
      "status": "posted",
      "method": "cash",
      "amount_minor": 37050,
      "currency": "EGP",
      "posted_at": "2026-08-24T11:40:00Z"
    },
    "order": {
      "id": "01K3G3Y3ZB9D79C89BYH6PXRT7",
      "status": "paid",
      "total_minor": 37050,
      "paid_minor": 37050,
      "refunded_minor": 0,
      "receipt_number": "CAI-0001042",
      "lock_version": 2
    }
  }
}
```

Execute refund:

```json
{
  "approval_id": "01K3G5BJKQ83QZ15SD3RK58QNJ",
  "reason": "Duplicate cash entry"
}
```

The refund endpoint never accepts an amount or currency. It derives and reverses the one full posted payment, rejects a second refund, and returns an immutable posted refund plus `order.status=refunded`. Partial payments, split tender, partial refunds, shifts, and cash-drawer balancing return no route/capability in MVP.

## 11. Basic incidents — conditional on OQ-20

Games, queues, participation, and game-capacity routes are not registered in MVP. The incident routes below are proposed for implementation only if Product/Safety/Legal approve OQ-20; otherwise they are not registered or granted.

| Method/path | Permission | Notes |
|---|---|---|
| `POST /incidents` | `incidents.create` | key; encrypted narrative |
| `GET /incidents` | incident view permission | scoped filters and masking |
| `PATCH /incidents/{incident_id}` | `incidents.manage` | key + `If-Match`; append follow-up and/or transition among `open`, `under_review`, `closed` without replacing original report |

## 12. Notifications

| Method/path | Permission | Notes |
|---|---|---|
| `POST /notifications` | `notifications.send` | allowed operational templates only; key required |
| `GET /notifications` | `notifications.view` | masked destination and provider metadata |
| `POST /notifications/{message_id}/resend` | `notifications.resend` | key; failed/permanent policy check |
| `POST /notification-provider-callbacks/{provider}` | verified provider signature | provider event ID is idempotent; out-of-order events cannot regress terminal state |

Operational session alerts and receipts are normally event-generated. Manual send accepts only allowlisted `session_ending` or `receipt` templates, a supported subject (`session_id` or `order_id`), channel, and optional scheduled time. It does not accept arbitrary destination/message text. Canonical states are `queued`, `sending`, `sent`, `delivered`, `failed_retryable`, `failed_permanent`, and `stale`; `sent` never means `delivered`. Marketing purpose/templates are future, unseeded, and rejected.

## 13. Reports and audit

| Method/path | Permission | Filters/result |
|---|---|---|
| `GET /reports/revenue` | `reports.revenue.view` | branch/date/cashier; totals reconcile to orders/payments/refunds |
| `GET /reports/attendance` | `reports.operations.view` | branch/date/hour/age-band aggregates |
| `GET /reports/sessions` | `reports.operations.view` | state/duration/pause/adjustment rows |
| `GET /reports/staff-activity` | `reports.staff.view` | scoped actor/action audit projection |
| `GET /audit-logs` | `audit.view` | raw authorized audit metadata, masked snapshots |

Revenue example:

```http
GET /api/v1/reports/revenue?branch_id=01K3F26MGG34P4KZVY8W2RQ1AX&from=2026-08-24&to=2026-08-25
```

```json
{
  "data": {
    "branch_id": "01K3F26MGG34P4KZVY8W2RQ1AX",
    "timezone": "Africa/Cairo",
    "range": {
      "from_local": "2026-08-24",
      "to_local_exclusive": "2026-08-25"
    },
    "currency": "EGP",
    "gross_sales_minor": 1800000,
    "discounts_minor": 50000,
    "tax_minor": 210000,
    "paid_minor": 1960000,
    "refunded_minor": 30000,
    "net_collected_minor": 1930000,
    "paid_orders": 84,
    "generated_at": "2026-08-25T00:05:00Z"
  }
}
```

Synchronous report ranges are capped by configuration. Overly broad requests return `422 range_too_large` with the maximum allowed range; async export is deferred until required.

## 14. Rate limits and abuse controls

Apply limits by token/user, tenant, route class, and IP where applicable. Initial values are deployment configuration, not hard API guarantees.

| Class | Examples | Baseline behavior |
|---|---|---|
| Authentication | login | strict per email/IP, progressive delay |
| Search/PII | guardian/child search | low burst, audit enumeration patterns |
| Operational commands | check-in/out, scan, payment | sufficient branch burst; idempotency mandatory |
| Reports | revenue/attendance | low concurrency and bounded range |
| Platform admin | tenant creation/status | strict and fully audited |

Return `RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset`, and `Retry-After` on throttling. A throttled payment/check-in retry with the same idempotency key remains safe.

## 15. Receipts, QR, and file responses

- Ticket QR payloads are opaque bearer values. Return them only at issue/reprint to authorized roles; store hash plus encrypted payload; never log them.
- Receipt JSON is authoritative. A printable HTML/PDF representation may be rendered from the immutable order snapshot.
- Child photos and generated artifacts use short-lived signed download URLs returned only after a policy check.
- File endpoints set private cache controls and content-disposition. No direct public bucket URLs.

## 16. API versioning and compatibility

- `/api/v1` is the major contract. Additive optional fields do not require a new major version.
- Do not rename/remove fields, change enum meaning, or make optional input required inside v1 without a deprecation window.
- Clients must ignore unknown response fields but must reject unknown command enum values they generate.
- Deprecations use `Deprecation` and `Sunset` headers and release notes.
- OpenAPI changes are reviewed with implementation and contract tests in the same pull request.

## 17. Provider callbacks and external integrations

Online-payment webhooks remain future scope. The MVP notification-status callback uses a provider-specific adapter selected by `{provider}`, raw-body signature verification, timestamp/replay checks, unique provider event IDs, idempotent event storage, and monotonic state mapping. It returns `202` for an authenticated accepted/duplicate event and a shared problem response for invalid authentication/payload; it never exposes a generic unauthenticated webhook.

## 18. Contract acceptance checklist

- `contracts/openapi.yaml` parses as OpenAPI 3.1 and contains all implemented MVP routes.
- Every operation has a unique `operationId`, security declaration, permission mapping, request validation, success schema, and shared problem responses.
- Tenant-owned request schemas contain no writable `tenant_id`.
- All critical create/money/state commands require `Idempotency-Key`.
- All aggregate mutations require `If-Match` where the current resource version exists.
- Examples use valid enum values, ULID shapes, UTC timestamps, and minor-unit money.
- Cross-tenant and unassigned-branch tests return `404`.
- Error codes and enum values remain synchronized with application enums and `07-Database-ERD.md`.
