# Decisions

## 2026-09-12: Close M1 at the access-and-branch boundary

**Decision:** Mark M1 DONE after PHP 8.4/8.5, SQLite/MySQL 8.4.11, focused security, build/format/audit, documentation, and real bilingual browser gates pass.
**Rationale:** The milestone definition is fully represented by tenant/branch context, authentication, fixed policies, owner/platform administration, locale/timezone/currency settings, audit history, and the responsive shell. Guardian/child and later operational modules belong to M2–M6 and are not pulled into this closure.
**Boundary:** This is local milestone acceptance, not production readiness or authorization to start M2 without its approved contracts.

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

## 2026-09-12: Narrow tenant-owner read representation

**Authority:** User authorized execution of the proposed three-worker owner-read wave; coordinator selected the narrow representation recommended as an option by T12.
**Decision:** Use explicit `tenant_owners(tenant_id,user_id)` with a composite primary key and tenant-aware user FK. No inference/backfill from branch roles. Initially authorize only own-tenant profile and staff-list reads via a native policy with fresh user, tenant and ownership state. Do not expand branch-owner or platform access. Full draft RBAC/schema remains a future target.
**Rationale:** Represent the required tenant-level ownership without building a general permission registry for one read operation. Exact route/view and worker contracts are in [the execution ledger](../docs/agent-plan.md).

## 2026-09-12: Owner branch access and bounded staff administration

User authorized the next three-feature wave. Explicit active tenant ownership now grants own-tenant active-branch read/selection without branch assignment. Owner-only management covers existing non-owner staff status and fixed branch-role assignments, not account creation/invitations or ownership transfer. Every mutation requires expected-state checks, a reason code, tenant-serialized transaction and atomic audit. Any owner account is excluded from management to protect ownership. Detailed contracts and file ownership: docs/agent-plan.md T18-T20. Remaining matrix permissions are not implicitly granted.

## 2026-09-12: Automatic reasons for routine staff and branch administration

**Authority:** The product owner explicitly requested removal of all reason selectors from employee and branch administration screens.
**Decision:** Staff status changes record `staffing_change`; branch-role assignments and branch lifecycle changes record `access_review`. These codes are generated by the server and client-supplied `reason_code` values are ignored. Expected-state checks, authorization, transactions, before/after snapshots, actor identity, and audit rows remain unchanged.
**Boundary:** This applies only to routine staff status, branch assignment, and branch activation/deactivation. It does not remove explicit reasons from refunds, guardian overrides, ticket cancellation, support access, tenant suspension, or other high-risk workflows that require user-provided context.
