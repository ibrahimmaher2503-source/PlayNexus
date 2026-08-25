# PlayNexus Permission Matrix

**Document ID:** PN-IAM-001  
**Status:** Draft MVP authorization baseline pending stakeholder approval  
**Source:** PRD roles, FR-003, BR-004–BR-008  
**Model:** Deny by default; role + tenant scope + branch scope + record state + approval

## 1. Role and scope definitions

| Role | Code | Effective scope | Notes |
|---|---|---|---|
| Super Admin | `super_admin` | Platform (`G`) | Manages SaaS accounts/plans. Has no routine access to tenant child, guardian, incident, or financial-detail records. |
| Tenant Owner | `tenant_owner` | Own tenant, all branches (`T`) | Manages tenant settings, staff assignments, and consolidated reports. |
| Branch Manager | `branch_manager` | Assigned branches (`B`) | Manages branch operations and approves configured exceptions. |
| Reception Staff | `reception_staff` | Assigned branches (`B`) | Registers customers and operates child sessions/tickets. |
| Cashier | `cashier` | Assigned branches (`B`) | Operates POS, records payments, requests high discounts/refunds. |
| Game Operator | `game_operator` | Future / unassigned | Retained only as a future role name. No MVP role seed, login assignment, game permission, or game subsystem is delivered. |
| Parent/Guardian | `parent_guardian` | Own records (`O`) | Future self-service role. Guardian records are not login users in MVP. All permissions are disabled until that feature is delivered. |

### 1.1 Matrix notation

- `G`: platform-wide metadata only.
- `T`: current tenant and all its branches.
- `B`: current tenant and an actively assigned branch.
- `O`: the authenticated guardian's own linked records; future only.
- `R`: may request the action but may not approve/execute it alone.
- `A`: may approve a valid request within scope; requester/approver separation applies.
- `M`: masked or aggregate output only.
- `—`: denied.

An entry grants at most the listed scope. It does not bypass record state, tenant isolation, branch assignment, consent, ownership, approval, or business-rule checks.

## 2. Platform and tenant administration

| Action / permission code | Super Admin | Tenant Owner | Branch Manager | Reception | Cashier | Game Operator | Parent |
|---|---:|---:|---:|---:|---:|---:|---:|
| List tenant account metadata `platform.tenants.view` | G | — | — | — | — | — | — |
| Create tenant `platform.tenants.create` | G | — | — | — | — | — | — |
| Activate/suspend tenant `platform.tenants.status` | G | — | — | — | — | — | — |
| Manage plans/subscriptions `platform.subscriptions.manage` | G | — | — | — | — | — | — |
| View platform aggregate analytics `platform.reports.view` | G/M | — | — | — | — | — | — |
| View own tenant profile/subscription `tenant.profile.view` | support only | T | B/M | — | — | — | — |
| Edit tenant business profile `tenant.profile.update` | — | T | — | — | — | — | — |
| Edit tenant operational settings `tenant.settings.update` | — | T | — | — | — | — | — |
| Create/archive branch `branches.create_archive` | — | T | — | — | — | — | — |
| View branch `branches.view` | support only | T | B | B/M | B/M | B/M | — |
| Edit branch settings/hours/tax `branches.update` | — | T | B | — | — | — | — |
| Activate/deactivate branch `branches.status` | — | T | B | — | — | — | — |
| Change branch capacity `branches.capacity.update` | — | T | B | — | — | — | — |

“Support only” requires the time-bound support-access control in section 11; platform permission alone is insufficient.

## 3. Staff, roles, and access

| Action / permission code | Super Admin | Tenant Owner | Branch Manager | Reception | Cashier | Game Operator | Parent |
|---|---:|---:|---:|---:|---:|---:|---:|
| View tenant staff `staff.view` | support only | T | B | self | self | — | — |
| Invite/disable tenant staff `staff.manage` | — | T | B* | — | — | — | — |
| Assign Tenant Owner `staff.roles.assign_owner` | — | T** | — | — | — | — | — |
| Assign branch roles `staff.roles.assign_branch` | — | T | B* | — | — | — | — |
| Assign/remove branch access `staff.branches.assign` | — | T | B* | — | — | — | — |
| View role definitions `roles.view` | G | T | B | — | — | — | — |
| Change fixed role-permission map `roles.permissions.manage` | deploy only | — | — | — | — | — | — |
| Revoke API token `tokens.revoke` | own/platform | T | own | own | own | own | — |
| Request/reset own password | public generic request / valid single-use token | self | self | self | self | — | — |

`*` Branch Managers can manage only Reception and Cashier users in branches they manage. They cannot create/assign Tenant Owners, Branch Managers, or the future Game Operator role.  
`**` An owner cannot remove the tenant's last active Tenant Owner. Ownership transfer requires re-authentication and audit.

MVP roles are fixed and seeded. “Role management” means assignment, not tenant-authored permission bundles. Custom roles can be introduced only with a separate privilege-escalation review.

## 4. Guardians, children, and sensitive data

| Action / permission code | Super Admin | Tenant Owner | Branch Manager | Reception | Cashier | Game Operator | Parent |
|---|---:|---:|---:|---:|---:|---:|---:|
| Search guardian/child `customers.search` | support only | T | B | B | B/M | — | O/future |
| View basic guardian profile `guardians.view` | support only | T | B | B | B/M | — | O/future |
| Create guardian `guardians.create` | — | T | B | B | B | — | O/future |
| Edit guardian contact `guardians.update` | — | T | B | B | B/limited | — | O/future |
| Manage consent/opt-out `guardians.consent.manage` | — | T | B | B | — | — | O/future |
| View basic child profile `children.view` | support only | T | B | B | B/M | — | O/future |
| Create/edit child `children.manage` | — | T | B | B | — | — | O/future |
| Link/revoke guardian-child relationship `relationships.manage` | — | T | B | B | — | — | O/future* |
| View child photo `children.photo.view` | break-glass | T | B | B | — | — | O/future |
| Upload/replace child photo `children.photo.manage` | — | T | B | B | — | — | O/future |
| View safety notes `children.safety_notes.view` | break-glass | T | B | B | — | — | O/future |
| Edit safety notes `children.safety_notes.manage` | — | T | B | B | — | — | O/future |
| Request anonymization `customers.anonymize.request` | — | T | B | — | — | — | O/future |
| Approve/execute anonymization `customers.anonymize.execute` | — | T/A | — | — | — | — | — |
| Export customer PII `customers.export` | — | T/A | B/A | — | — | — | O/future |

`limited` means cashier may correct name/phone only while completing the current sale and cannot change consent, relationships, photos, or notes.  
`future*` means a guardian may request a relationship change, but staff verification is required before it becomes active.

## 5. Pricing, tickets, and sessions

| Action / permission code | Super Admin | Tenant Owner | Branch Manager | Reception | Cashier | Game Operator | Parent |
|---|---:|---:|---:|---:|---:|---:|---:|
| View pricing rules `pricing.view` | support only | T | B | B | B | — | public quote/future |
| Create/retire pricing rule `pricing.manage` | — | T | B | — | — | — | — |
| View ticket types `ticket_types.view` | support only | T | B | B | B | — | future |
| Manage ticket types `ticket_types.manage` | — | T | B | — | — | — | — |
| Issue/sell ticket `tickets.issue` | — | T | B | B | B | — | future |
| Validate/scan ticket `tickets.scan` | — | T | B | B | B | — | — |
| Reprint ticket `tickets.reprint` | — | T | B | B | B | — | future |
| Cancel unused ticket `tickets.cancel` | — | T | B/A | R | R | — | R/future |
| View session `sessions.view` | support only | T | B | B | B | — | O/future |
| Create check-in `sessions.check_in` | — | T | B | B | — | — | future |
| Pause/resume session `sessions.pause_resume` | — | T | B | B | — | — | — |
| Extend session with configured option `sessions.extend` | — | T | B | B | B/session handoff | — | — |
| Verified checkout `sessions.checkout` | — | T | B | B | — | — | — |
| Cancel active session `sessions.cancel` | — | T/A | B/A | R | — | — | — |
| Request session adjustment `sessions.adjust.request` | — | T | B | R | R | — | — |
| Approve/apply session adjustment `sessions.adjust.approve` | — | T/A | B/A | — | — | — | — |
| Guardian-checkout override `sessions.checkout.override` | — | T/A | B/A | R | — | — | — |
| View session history `sessions.history.view` | support only | T | B | B | B/M | — | O/future |

Reception checkout without override still requires an active checkout-capable guardian relationship and one verification method approved under OQ-12; this matrix does not choose that method. Completion also requires the linked order to be fully paid, while station/role ownership remains open under OQ-19. Completed/cancelled sessions cannot be edited; corrections are append-only adjustments.

## 6. POS, payment, full refund, and receipts

| Action / permission code | Super Admin | Tenant Owner | Branch Manager | Reception | Cashier | Game Operator | Parent |
|---|---:|---:|---:|---:|---:|---:|---:|
| View product catalog `products.view` | support only | T | B | B | B | — | future |
| Manage products/prices `products.manage` | — | T | B | — | — | — | — |
| Create/edit open order `orders.manage` | — | T | B | B/session only | B | — | future |
| Add manual line `orders.manual_line` | — | T | B | — | B | — | — |
| Apply discount at/below threshold `orders.discount.standard` | — | T | B | — | B | — | — |
| Request discount above threshold `orders.discount.request` | — | T | B | — | R | — | — |
| Approve high discount `orders.discount.approve` | — | T/A | B/A | — | — | — | — |
| Record payment `payments.record` | — | T | B | — | B | — | — |
| Void erroneous payment `payments.void` | — | T/A | B/A | — | R | — | — |
| Issue/view receipt `receipts.view_issue` | support only | T | B | B | B | — | own/future |
| Resend/reprint existing receipt `receipts.resend` | — | T | B | B/session | B | — | own/future |
| Request full refund `refunds.request` | — | T | B | — | R | — | R/future |
| Approve full refund `refunds.approve` | — | T/A | B/A | — | — | — | — |
| Execute full refund `refunds.execute` | — | T | B | — | B with approval | — | — |
| Void unpaid order `orders.void` | — | T/A | B/A | R | R | — | — |

A user cannot approve a discount/refund/payment void/order void they requested. Under ASM-08/ASM-10 pending OQ-09, the draft permission baseline accepts exactly one full posted payment and at most one full refund per order; split/partial payment and partial-refund permissions are not seeded. Cashier shifts and cash-drawer balancing remain absent pending OQ-24. An approved refund does not itself prove cash was returned; execution records who completed the full reversal and when.

## 7. Conditional basic incidents; games deferred

All incident permissions in this section remain unseeded and routes remain disabled unless OQ-20 explicitly adds basic incident recording/search to the MVP.

| Action / permission code | Super Admin | Tenant Owner | Branch Manager | Reception | Cashier | Game Operator | Parent |
|---|---:|---:|---:|---:|---:|---:|---:|
| Create incident `incidents.create` | — | T | B | B | — | — | future/R |
| View incident summary `incidents.view_summary` | break-glass | T | B | B/assigned | — | — | own/future |
| View full incident detail `incidents.view_sensitive` | break-glass | T | B | B/need-to-know | — | — | own/future |
| Append follow-up/change state `incidents.manage` | — | T | B | B/assigned | — | — | — |
| Close critical incident `incidents.close_critical` | — | T/A | B/A | — | — | — | — |

Game, queue, participation, and game-capacity permission codes are future-only, unseeded, and unassigned to every MVP role. Branch check-in capacity behavior is a separate OQ-11 decision and gains no override permission until approved.

## 8. Notifications, reports, and audit

| Action / permission code | Super Admin | Tenant Owner | Branch Manager | Reception | Cashier | Game Operator | Parent |
|---|---:|---:|---:|---:|---:|---:|---:|
| View operational notification status `notifications.view` | support only | T | B | B/session | B/receipt | — | own/future |
| Resend failed operational message `notifications.resend` | — | T | B | B/session | B/receipt | — | — |
| Send one-off operational message `notifications.send` | — | T | B | B/template only | — | — | — |
| Send marketing message `notifications.marketing.send` | — (future) | — (future) | — (future) | — | — | — | — |
| View revenue report `reports.revenue.view` | aggregate only | T | B | — | — | — | — |
| View attendance/session report `reports.operations.view` | aggregate only | T | B | B | B/M | — | — |
| View staff activity report `reports.staff.view` | — | T | B | self | self | — | — |
| Export non-PII report `reports.export` | — | T | B | — | — | — | — |
| Export report containing PII `reports.export_pii` | — | T/A | B/A | — | — | — | — |
| View tenant audit log `audit.view` | support only | T | B | own actions | own actions | own actions | — |
| View platform audit log `platform.audit.view` | G | — | — | — | — | — | — |

`notifications.marketing.send` is a future placeholder only: it is not seeded, assigned, exposed by API/UI, or accepted by any template/job in MVP. Receipt and session-ending notifications use an operational allowlist. Authenticated provider callbacks run as a dedicated system integration principal scoped only to `notifications.provider_status.apply`; no staff role receives that permission.

## 9. Approval and override rules

| Action | Requester | Approver | Required data | Expiry/use |
|---|---|---|---|---|
| Discount over configured branch threshold | Cashier | Branch Manager or Tenant Owner in scope | order, original total, discount amount/rate, reason | 10 minutes; one order version |
| Full refund | Cashier; parent future | Branch Manager or Tenant Owner in scope | paid order/payment, server-derived full amount, reason, no prior refund | 30 minutes; one execution |
| Session time/charge adjustment | Reception/Cashier | Branch Manager or Tenant Owner in scope | session, delta, reason, quote before/after | 15 minutes; exact session version |
| Guardian checkout override | Reception | Branch Manager or Tenant Owner in scope | session, child, failed verification reason, alternate evidence | 5 minutes; one checkout |
| Ticket cancellation | Reception/Cashier | Branch Manager or Tenant Owner in scope | unused ticket, sale state, reason | 15 minutes; one ticket version |
| Payment/order void | Cashier/Reception | Branch Manager or Tenant Owner in scope | order/payment, state, reason | 15 minutes; one target version |

Rules:

1. The approval is stored before the protected command and is bound to tenant, branch, action, subject, request payload hash, and current `lock_version`.
2. The approver must authenticate as themselves. Sharing a manager PIN/session is prohibited.
3. The requester cannot approve their own request. A Tenant Owner emergency self-approval is disabled by default; if enabled for a single-operator tenant, it requires re-authentication, reason, a high-severity audit event, and daily owner review.
4. Approval never grants broader data access and cannot be reused, transferred, or applied after expiry/version change.
5. The protected action consumes the approval in the same database transaction as the business write.
6. Rejected and expired requests remain in the audit trail.

## 10. Sensitive-data masking

| Field/data | Default display | Unmasked roles/conditions | Logs/exports |
|---|---|---|---|
| Guardian phone | last 4 digits after search result selection | Tenant Owner, Branch Manager, Reception; Cashier only current sale | mask in logs; PII-export permission required |
| Guardian email | first character + masked domain/local part | Tenant Owner, Branch Manager, Reception; Cashier only current receipt | mask in logs |
| Child photo | placeholder/thumbnail only | Owner/Manager/Reception | never in logs/exports by default |
| Child age/DOB representation | proposed pending OQ-15 | Owner/Manager/Reception only after policy approval | ordinary reports expose approved age band, never infer a precision not stored |
| Child safety notes | “Safety note exists” flag | explicit `children.safety_notes.view` and need-to-know | encrypted at rest; excluded from normal exports/audit snapshots |
| Incident narrative | severity/title only | explicit sensitive incident permission within branch | encrypted; export requires separate PII approval |
| Payment external reference | last 4/short suffix | Owner/Manager/Cashier on current order | never store PAN/CVV; mask logs |
| Notification destination | masked | operational staff for current message only | encrypted destination; masked attempt metadata |
| Support access | no data by default | approved break-glass session only | full reason, tenant, actor, fields accessed, start/end audited |

Search endpoints return the minimum needed to disambiguate a customer, cap result counts, rate-limit repeated queries, and audit enumeration patterns.

## 11. Super Admin and support access

Super Admin is not a universal tenant operator. The role may manage tenant metadata, subscription state, plans, platform health, and aggregated usage. It may not check in children, record tenant payments, approve tenant refunds, change tenant pricing, or browse customer records during routine work.

Support access requires:

1. an eligible support permission;
2. selected tenant and reason/ticket reference;
3. re-authentication;
4. a short-lived support session with read-only access by default;
5. explicit field-level policy checks and masking;
6. append-only platform and tenant-visible audit entries;
7. automatic expiry and immediate revocation capability.

Write support access is not part of the MVP. Data repair uses reviewed migrations/commands with a change record, not controller impersonation.

## 12. Enforcement in Laravel

### 12.1 Request authorization order

```text
authenticated?
→ tenant/platform context valid?
→ account and branch active?
→ role grants ability?
→ resource tenant and branch match?
→ record state permits action?
→ approval present/valid/unused when required?
→ sensitive-field serializer permits requested fields?
→ execute transaction and append audit
```

### 12.2 Implementation mapping

- Middleware resolves authentication, tenant context, branch assignment, locale, and request ID.
- Laravel policies authorize resource actions and branch scope.
- Form Requests validate input only after tenant-scoped resource resolution.
- Application actions enforce state, thresholds, approvals, row locks, and transactions.
- API Resources/Blade view models apply field masking; controllers do not hand raw models to clients.
- Gates cover platform-only actions that have no tenant model.
- Scheduled commands and jobs establish an explicit tenant context and use the same actions/policies where an actor exists.
- UI visibility mirrors permissions for usability, but backend authorization remains authoritative.

Do not use a controller-level `if role == ...` chain. Permission codes are the stable contract; roles are seeded bundles of those codes.

## 13. Denial and audit behavior

| Condition | API behavior | Audit behavior |
|---|---|---|
| Unauthenticated | `401` | auth security log; no tenant data |
| Authenticated but ability denied | `403` | denied action with actor/permission code |
| Tenant/branch mismatch or hidden record | `404` | security audit with masked target ID |
| Invalid state/expired approval/stale version | `409` | failed command reason code |
| Sensitive field omitted by mask | `200` with masked/omitted field | read audit only where policy/legal need requires it |
| Repeated enumeration/rate limit | `429` | security alert signal |

Error messages must not disclose that another tenant's resource exists.

## 14. Release gates

- Every matrix row maps to a seeded permission code and at least one policy test.
- Every branch-scoped permission is tested with assigned and unassigned branches.
- Every resource endpoint is tested with a second tenant's valid ID and must return `404`.
- Every approval action tests self-approval, expiry, payload mismatch, stale version, replay, and cross-branch use.
- Every masked field has serializer/view tests for Reception, Cashier, Manager, Owner, and support mode.
- Future Game Operator, games, shifts, marketing, parent login, split/partial payment, and partial-refund permissions are absent from the MVP seed and route registration. Incident permissions are also absent until OQ-20 is approved.
- Disabling a user or branch invalidates new operations immediately.
- No Parent/Guardian API token can be issued until own-record policies and identity proofing are implemented.
