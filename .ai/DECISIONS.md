# Decisions

## 2026-08-24: Documentation-first Laravel baseline

**Decision:** Use an implementation-ready documentation pack and UI-first milestones before scaffolding application code.  
**Rationale:** Critical rules for money, time, tenant isolation, safety, and permissions must align before migrations and workflows are expensive to change.  
**Alternative:** Scaffold immediately from the PRD; rejected because unresolved rules would be embedded as accidental behavior.

## 2026-08-24: Modular monolith for MVP

**Decision:** Begin with one Laravel application and relational database.  
**Rationale:** It is the fastest operationally simple approach and preserves transactional integrity across sessions, POS, and audit.  
**Alternative:** Microservices; deferred until measured scale or independent release needs justify them.

## 2026-08-24: Shared-schema tenancy

**Decision:** Use shared-schema tenancy with explicit tenant scoping and constraints for MVP.  
**Rationale:** Lowest setup and operational cost while supporting the target model.  
**Alternative:** Database per tenant; revisit for contractual isolation, very large tenants, or regional data residency.

## 2026-08-24: Verified PRD source baseline

**Decision:** Treat the re-attached PRD as the same source baseline; both inputs are 27,198 bytes and have SHA-256 `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2`.  
**Rationale:** Prevents unnecessary document divergence while preserving a reproducible source check.

## 2026-08-24: Documentation formats

**Decision:** Deliver the project pack as Markdown, OpenAPI YAML, and standalone HTML only; do not generate Word/DOCX.  
**Rationale:** This matches the user's requested working format and keeps specifications diffable and implementation-friendly.

## 2026-08-24: Proposed fast implementation stack

**Decision:** Recommend PHP 8.5, Laravel 13, Blade/Livewire 4, Tailwind CSS 4, MySQL 8.4 LTS/InnoDB, and Pest 5, using Laravel-native auth, policies, queues, cache, scheduling, notifications, and storage. Confirm supported patch versions when scaffolding.  
**Rationale:** One conventional modular monolith is the shortest safe path to the pilot and preserves transactional correctness.  
**Alternative:** SPA, microservices, tenancy/permission frameworks, Redis, and broad infrastructure packages; defer until a measured need exists.

## 2026-08-24: Conditional module boundaries

**Decision:** Keep games/queues, parent self-service, marketing campaigns, cashier shifts, and other later-phase features out of MVP. Keep basic incident routes/tables/permissions/UI disabled unless OQ-20 is approved; keep cashier close out unless OQ-24 is approved.  
**Rationale:** These behaviors appear outside or ambiguously within the PRD's explicit MVP inventory and must not become hidden scope.

## 2026-08-24: Single canonical documentation directory

**Decision:** Keep every final specification and implementation guide directly under `docs/`, with supporting OpenAPI and wireframe assets in `docs/contracts/` and `docs/wireframes/`; remove the former nested documentation layer and its summary duplicates.  
**Rationale:** One canonical path prevents drift and makes the repository ready for day-to-day implementation work.  
**Alternative:** Maintain short summaries plus a detailed project pack; rejected because it created two sources for the same topics.

## 2026-08-25: Cool mineral operational visual baseline

**Decision:** Use a light, restrained product interface with cool porcelain surfaces, graphite text, and deep petrol teal reserved for primary actions, focus, and selection. Purple and cream are explicitly excluded. `DESIGN.md` is the canonical reusable visual and interaction baseline.  
**Rationale:** Reception and cashier users work in bright, busy environments and need a calm, high-contrast interface that feels modern without becoming an arcade theme.  
**Alternative:** Purple/cream styling, neon/playful entertainment styling, or a dark dashboard; rejected because they do not match the requested direction or reduce clarity and trust in safety and money workflows.

## 2026-09-10: Initial MVP decisions approved by product owner

The following initial decisions are approved for the MVP baseline:

- **OQ-23:** PlayNexus launches as branded SaaS only. White-label capability is deferred.
- **OQ-03:** MVP is online-only. Offline writes and conflict synchronization are deferred; operational outage fallback is a visible failure/read-only procedure.
- **OQ-06:** Initial market is Egypt; branch currency is EGP; default timezone is Africa/Cairo; Arabic and English are supported; tax behavior is configurable pending legal/accounting confirmation.
- **OQ-07:** Collect only the minimum data required for operations and safety; record consent/version events; support masking and deletion/anonymization where legally permitted; retain mandatory financial, safety, and audit evidence.
- **OQ-10:** Staff may be assigned to multiple branches with branch-scoped roles and deny-by-default authorization.
- **OQ-14:** Super Admin support access is denied by default and requires least-privilege, time-bound, reason-required, audited access.
- **OQ-01:** MVP records in-person payments only. Online gateway capture is deferred.
- **OQ-02:** QR/barcode input uses browser keyboard-input scanners. Proprietary wristband/scanner SDKs are deferred.
- **OQ-15:** Child date of birth is optional; the system may use an age/family-band value where needed, without requiring more precision than the approved purpose.

**Still open:** OQ-08 receipt numbering/content requires Finance/Legal approval; OQ-16 pricing, OQ-12 guardian verification, and the remaining operational decisions must close before their affected slices are frozen.

## 2026-09-12 T14 approved implementation contract

Under the user's authorization to execute the proposed three-worker owner-read wave, the coordinator selects the narrow explicit equivalent allowed by T12: `tenant_owners` contains non-null unsigned-bigint `tenant_id`, `user_id`, timestamps; composite primary key `(tenant_id,user_id)` and composite foreign key to `users(tenant_id,id)` with cascade delete. No backfill/inference from branch roles, no generic RBAC tables or assignment endpoint. This is an implementation decision for this slice, not approval of the entire draft matrix. Existing users remain non-owners until explicitly provisioned outside this UI.

Native `TenantPolicy::view(User,Tenant)` grants only a freshly queried active user whose persisted tenant matches the active target tenant and whose exact tenant_owners row exists. Unknown/null/platform identities and branch-only owner strings deny. Policy foreign/inactive scope is denyAsNotFound (404); in-scope missing owner assignment is 403. Existing tenant middleware enforces account revocation and inactive tenant behavior. Do not grant new branch access.

GET `/app/tenant`, route `tenant.show`, existing `auth` + `tenant.access`, resolves tenant from a freshly queried authenticated user (never request IDs), authorizes view, renders `tenant.show`. View data: `$tenant` (Tenant), `$staff` (LengthAwarePaginator of only id, name, email, status; tenant-filtered users, order by id, 25/page). User query/body tenant identifiers are ignored and never switch scope. No API/JSON expansion. UI navigation from dashboard appears only for policy-authorized owner; page has own tenant name, read-only staff name/email/localized status, empty state, pagination, locale controls and return-to-dashboard. Existing locale POST redirect remains unchanged.

T15 owns new migration, TenantPolicy, TenantReadController, provider policy registration, routes only (User model only if essential). T16 owns resources/views/tenant/show.blade.php, dashboard Blade navigation, lang/en/tenant.php and lang/ar/tenant.php only. T17 owns tests/Feature/TenantOwnerReadTest.php only. Each commits in its isolated branch; no shared .ai edits. Tests cover constraints, owner without branch assignment, staff and spoofed-role denials, fresh revocation, inactive account/tenant, foreign policy 404, request-ID isolation, scoped pagination and en/ar rendering. Run executable combined tests only after dependencies integrate; missing peer implementation is not PASS. Browser explicitly deferred. Coordinator reviews actual commits, integrates locally on codex/first and records one combined verification. No main merge or push.
