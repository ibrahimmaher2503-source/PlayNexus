# Owner and Platform Authorization Contract Review

**Status:** Investigation accepted by coordinator. The 2026-09-12 decision in `.ai/DECISIONS.md` selects the narrow explicit `tenant_owners` representation for tenant profile/staff-list reads. The T14 contract in `docs/agent-plan.md` supersedes the representation blocker below for that slice only. Owner-wide branch access and platform authorization remain deferred. The original findings below describe the pre-T14 baseline.

## Actual representation

- The live foundation has one nullable `users.tenant_id`; `TenantContext` accepts only an active tenant from that value. There is no `user_type`, tenant-role relation, platform context, or platform route. [migration](../database/migrations/2026_09_10_000003_create_tenant_branch_foundation.php#L18-L21) [TenantContext](../app/Services/TenantContext.php#L10-L19) [routes](../routes/web.php#L17-L25)
- `branch_user` is the sole access record: it combines tenant, branch, user, free-text `role`, and active state. `User::activeBranches()` requires that pivot, and the branch policy permits only manager/reception/cashier codes. [migration](../database/migrations/2026_09_10_000003_create_tenant_branch_foundation.php#L32-L43) [User](../app/Models/User.php#L27-L39) [BranchPolicy](../app/Policies/BranchPolicy.php#L11-L38)
- Consequently an asserted `tenant_owner` pivot is deliberately denied by the current focused test. A platform identity with null `tenant_id` also cannot enter the current tenant routes. [test](../tests/Feature/BranchViewAuthorizationTest.php#L32-L44) [TenantContext](../app/Services/TenantContext.php#L14-L18)

## Canonical requirements and status

| Statement | Status | Evidence |
| --- | --- | --- |
| Tenant Owner is tenant-wide (`T`), including all branches; branch staff are active-assignment scoped (`B`). | Draft specification, not an approved decision. | [matrix](08-Permission-Matrix.md#L10-L31) |
| Owner may work tenant-wide without a branch-assignment row; branch roles require an active assignment. | Proposed schema, not approved. | [ERD](07-Database-ERD.md#L191-L203) |
| Super Admin is platform-scoped, has no tenant membership by default, and tenant operational access needs explicit audited support access. | OQ-14 is approved; detailed matrix remains draft. | [architecture](06-Architecture-Document.md#L181-L211) [.ai decision](../.ai/DECISIONS.md#L54-L68) |
| Multiple branch assignments with deny-by-default authorization are approved. | Approved OQ-10. | [.ai decision](../.ai/DECISIONS.md#L54-L68) |

The current implementation is compatible with approved branch staff scope, but does **not** represent or authorize an owner/platform identity. Treat matrix/ERD owner details as the target contract pending coordinator/product approval; do not promote them to an implemented approval.

## Contradictions and required decision

The ERD models separate `user_roles` and `user_branch_assignments`, whereas the application has only combined `branch_user`. More importantly, the ERD exempts an owner from branch assignment while the architecture says branch access is the intersection of tenant context and active branch assignments. [ERD](07-Database-ERD.md#L191-L203) [architecture](06-Architecture-Document.md#L181-L200)

**Blocker:** approve one authoritative representation for a tenant owner before implementation: either the documented tenant-scoped role assignment (`user_roles`/fixed `tenant_owner` role) or a deliberately narrower equivalent. The existing `branch_user.role` cannot supply it: inferring tenant-wide ownership from a per-branch row contradicts both documents and current deny-by-default behavior. Also decide whether the first read slice permits an owner to select/read every active branch, or only to use tenant-global read pages; the latter avoids changing branch selection semantics.

Platform scope is a separate blocked slice. It needs an explicit platform identity and `/platform` boundary plus the approved time-bound support workflow; a blanket super-admin `before`/bypass violates the matrix's platform-only limit. [matrix](08-Permission-Matrix.md#L210-L224) [architecture](06-Architecture-Document.md#L185-L189)

## Smallest next vertical slice after that decision

Choose only **owner read of tenant-global staff/tenant profile** first. Do not add platform routes, support impersonation, staff mutation, owner transfer, or branch-wide selector behavior. This proves the owner representation without silently resolving the branch-access contradiction.

Allowed: an active tenant owner reads their own tenant profile and staff list. Denied: a cashier reads that list; an owner reads another tenant by ID (404); a platform user reads it through `/app`; an alleged owner recorded only in `branch_user` receives owner access.

Suggested implementation ownership for one future worker:

| Owned path | Purpose |
| --- | --- |
| new forward migration | Add the coordinator-approved explicit tenant-owner assignment representation with tenant-aware FK/unique constraint; preserve `branch_user` unchanged. |
| `app/Models/User.php`, new minimal relation/model if the chosen representation needs one | Fresh, tenant-scoped owner predicate; never trust a loaded pivot or request role. |
| one `TenantPolicy` (or a narrowly named existing-policy method), `app/Http/Controllers/*`, `routes/web.php`, one Blade view | One read-only `/app/tenant` route, policy authorization, and no client tenant identifier. |
| one focused feature test file and directly affected factories | Prove role, tenant, and state behavior below. |

Required migration/validation/isolation checks:

1. Migration works on fresh and pre-existing tenant users; assignment cannot point across tenants, duplicate the owner role, or use a null/platform user.
2. Fresh DB queries allow the assigned active owner after assignment and deny after removal; branch roles still need their active `branch_user` assignment.
3. Owner can read only their tenant-global route; a same-name second-tenant target is `404`; cashier/manager are `403`; inactive tenant is `404` as current tenant middleware requires. [middleware](../app/Http/Middleware/EnsureTenantAccess.php#L13-L31) [matrix](08-Permission-Matrix.md#L255-L277)
4. An owner role string on `branch_user` remains insufficient, proving no role inference or blanket super-admin bypass.

## Proposed worker prompt (do not execute)

> After coordinator approval of the owner representation, implement only owner read access for `/app/tenant` and a tenant staff list. Add the approved explicit tenant-level owner representation in a forward migration with tenant-aware constraints; leave `branch_user` semantics and all platform routes unchanged. Authorize through a Laravel policy using fresh tenant-scoped data, resolve tenant exclusively from the authenticated user, return 404 for foreign/inactive tenant scope and 403 for in-scope non-owner roles. Add focused migration and HTTP tests for owner allow, staff deny, cross-tenant 404, inactive tenant 404, revocation, and proof that `branch_user.role=tenant_owner` does not grant access. Do not add support access, a super-admin bypass, owner assignment UI, generic RBAC packages/tables beyond the approved representation, or `.ai`/matrix edits. Run the focused tests, documentation validator, and diff check; report REVIEW_REQUIRED.

## Dependencies

- Coordinator/product approval of the representation and the owner read boundary above.
- T10's status enforcement must be integrated before a claim that inactive owners are denied by account status; this report does not assume it.
- A later branch-owner slice must resolve selector/direct-branch handling consistently in `User::activeBranches()`, `BranchContextController`, `EnsureBranchAccess`, and `BranchPolicy`; these callers currently share the assignment prerequisite. [controller](../app/Http/Controllers/BranchContextController.php#L13-L33) [middleware](../app/Http/Middleware/EnsureBranchAccess.php#L14-L26)
