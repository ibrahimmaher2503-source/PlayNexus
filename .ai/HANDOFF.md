# Handoff

## 2026-09-13 M3 local-closure handoff

Continue from the uncommitted integration worktree `C:\Users\N\.codex\worktrees\t08-integration\PlayNexus` and preserve unrelated dirty changes. Active session cards now show a read-only, non-final as-of estimate calculated from the immutable snapshot with integer fixed-duration/grace/overtime/tax rules; malformed snapshots show no estimate and do not break the board. No quote is persisted and no checkout, release, payment or receipt state is created.

Focused quote/session checks pass 18 / 177; full SQLite on PHP 8.4 and 8.5 passes 279 of 281 / 2,387 with two MySQL-only skips; full isolated MySQL 8.4.11 passes 281 / 2,434. Vite/Pint/Blade/diff and authenticated Edge Arabic/English Owner/Cashier responsive QA pass. The temporary 8214 QA runtime must be stopped after final evidence collection; private MySQL 33417 remains available and must not be stopped.

M3 is locally accepted. The product owner explicitly stopped before M4, so no M4 code or implementation wave has started. OQ-12 guardian evidence is already approved and synchronized; OQ-19 station ownership/recovery remains open. Do not add final totals, child release, payment, receipt, pause/extension/adjustment or alerts until the owner resumes M4 with a bounded contract. OQ-09 still gates financial refunds. No commit/push was made.

## 2026-09-13 ticket-backed check-in/live-board handoff

Continue from the uncommitted integration worktree `C:\Users\N\.codex\worktrees\t08-integration\PlayNexus`; preserve prior M1/M2/pricing/ticket changes. `GET /app/sessions` and `POST /app/sessions/check-in` now implement the bounded ticket-backed arrival flow. A successful authorized check-in consumes one issued ticket and creates one Active play session, expected end, immutable pricing/time snapshot, session event, scan evidence and audit in one transaction. Tenant-wide Active/Paused child uniqueness and hard branch capacity are enforced without override. Cashier is board-only and receives no raw guardian phone.

Focused check-in passes 9 / 128; full SQLite and PHP 8.5 pass 270 of 272 / 2,338 with two MySQL-only skips; full isolated MySQL passes 272 / 2,385 and the real two-process gate passes 2 / 47. Authenticated headless Edge checks pass for Owner/Cashier, Arabic/English and mobile/tablet/desktop. Evidence is in `.ai/TEST_RESULTS.md` and `deliverables/qa/sessions/`.

Existing port 8206/database was not migrated or restarted. Migration `2026_09_13_000015_create_play_sessions` was applied only to disposable task databases. M4 did not start; if explicitly resumed, its first bounded work is the checkout/time-and-charge contract. Do not expose final billing, guardian release, payments or refunds until the matching approved examples and OQ-19 station handoff are frozen. No commit/push was made.

## 2026-09-13 historical ticket-only implementation handoff

Continue from the uncommitted integration worktree `C:\Users\N\.codex\worktrees\t08-integration\PlayNexus`; preserve prior M1/M2/pricing edits. Ticket types, dated issuance, encrypted opaque QR, idempotent validation/first-scan assignment lock, audited pre-scan correction, unused manager/owner cancellation and immutable reprint are implemented. Existing active types retain frozen price/source facts even when pricing is replaced. Current family consent/verified-checkout/emergency safeguards govern issue, correction and validation. Scan validation is not consumption/check-in; cancellation explicitly does not refund money.

Full isolated MySQL 8.4.11 regression passes 262 / 2,233; SQLite passes 261 / 2,210 with one explicit MySQL-only skip. Real two-process concurrency and authenticated headless Edge ticket visual/QR/A5-print checks pass; see `.ai/TEST_RESULTS.md`. The unavailable interactive bridge does not invalidate scoped headless QA, but no full M2 UAT/production claim follows.

Existing server 8206/database was not changed. The new ticket migration was accepted only on task-local MySQL databases `playnexus_ticket_m3` and `playnexus_ticket_ui_m3`; select/approve the intended runtime database before applying it there. QA identities are synthetic and are not seeded into the existing application. Next bounded slice: atomic idempotent ticket consumption/check-in/session creation, then live board/capacity. Do not invent OQ-09 refund execution or unapproved calculator tax totals.

The temporary ticket QA web server on 8213 was stopped after acceptance; its screenshots/PDF and synthetic database are preserved. The private loopback MySQL process on 33417 remains available; do not stop it or infer the existing application's database configuration without checking dependencies. No user data was deleted and no commit/push was made.

## 2026-09-13 M3 ticket-policy handoff

OQ-18 is approved and synchronized across the canonical contracts. Implement tickets as tenant/branch-specific and bound to a branch-local service date. Allow audited assignment correction only before the first successful scan; that scan permanently locks assignment. Only an unused Issued ticket with no successful scan, consumption, or linked session is refund-eligible, and only with in-scope Branch Manager/Tenant Owner approval plus reason/audit and a linked reversal when paid. Do not invent OQ-09 refund window/payment execution or finance tax examples. No ticket code was added by this decision-only change.

## 2026-09-13 Egypt M2 handoff

The approved Egypt M2 family contract is implemented in this uncommitted worktree. Registration/profile flows now enforce hard same-tenant phone reuse, versioned legal-guardian child-data consent, separate optional marketing choice/withdrawal, emergency contact, encrypted restricted safety notes, and verified relationship link/reactivate/revoke with the final-guardian invariant. Full SQLite and isolated MySQL 8.4.11/InnoDB regression passes 241 / 1,997; Pint, Vite, documentation, and whitespace checks pass. Browser acceptance is not claimed. Visit history and automated retention action wait for M3 session data; production deployment still requires Legal/DPO approval of the actual notice, controller/DPO details, processors/transfers, and marketing-provider/licensing setup.

## 2026-09-12 remediation handoff

Continue from this worktree with the M1/M2 remediation uncommitted. Cashier family access is intentionally limited to active, tenant-scoped masked search/profile data and the approved atomic first registration; guardian edits, child maintenance, relationship management, and sensitive fields are denied. Owner/branch manager/reception retain the approved maintenance actions. Branch creation accepts a UUID idempotency key and safely replays identical requests; JSON errors include `code`, safe `message`, `request_id`, and validation errors when applicable. Full SQLite and isolated MySQL 8.4.11/InnoDB regression passes 234 / 1,943; build, Pint, docs, and diff checks pass. Browser acceptance is not claimed. Do not add consent, emergency/safety, relationship lifecycle, merge, visit history, ticketing, sessions, POS, or other M3 expansion without approved contracts.

## 2026-09-12 M3 pricing-version handoff

`POST /app/pricing/{pricingRule}/versions` now replaces the current active rule by retiring it and creating immutable version +1 with the same tenant, branch, and code. Owners and assigned branch managers receive bilingual native replacement controls; reception/cashier users remain view-only. Expected-version checks block stale/replayed forms, stored EGP stays integer-based, the new rule snapshots the locked branch tax settings, and audit metadata contains identifiers and versions only. Follow-up review isolates validation input to its submitted repeated form and removes premature consent copy. Full PHP 8.5 regression passes 226 / 1,859; Pint, Vite, docs, and pricing-route checks pass. Browser and fresh MySQL acceptance remain outstanding; do not add calculation/tickets/QR/check-in/sessions until their contracts are ready.

## 2026-09-12 M3 pricing-rule first-slice handoff

`/app/pricing` lists active immutable pricing rules for the actor's allowed active branches. Owners and branch managers can create version-1 fixed-duration rules for manageable branches using EGP inputs; reception/cashier roles are view-only. Stored facts use integer minor units, 600-second grace, 1,800-second overtime units, no pause, and the locked branch tax snapshot. Full PHP 8.5 regression passes 206 / 1,668; Pint, Vite, docs, and route checks pass. No update/delete/retire/calculator/ticket/QR/check-in/session feature exists. Browser and fresh MySQL acceptance remain outstanding; OQ-18 was subsequently approved in the top handoff, but ticket code remains pending.

## 2026-09-12 M2 family profile handoff

`/app/families/{guardian}` now shows an authorized current-tenant family profile and supports basic guardian/child corrections plus adding one child. Updates use expected versions, locks, transactions, duplicate-phone recovery, and safe audit metadata; child addition increments the guardian version so a repeated form submission cannot create another child. Full PHP 8.5 regression is 187 / 1,471 and build/Pint pass. Browser acceptance is blocked by `User unavailable`, and the current M2 migration still needs isolated MySQL evidence. Do not add consent, merge, safety/emergency, relationship revocation, visit history, or check-in without the matching approved contract.

## 2026-09-12 M2 family registry first-slice handoff

Continue from `codex/first`. `/app/families` searches only the current tenant by normalized guardian phone or child name; `/app/families/create` atomically creates one guardian, one child, one active link, and `family.created` audit evidence. Same-tenant phone duplicates create nothing and point back to the existing family; foreign-tenant matches remain hidden. PHP 8.5 full regression is 169 tests / 1,328 assertions; build and Pint pass. Browser creation/search passed and its synthetic data was removed, but duplicate visual verification was interrupted by browser unavailability. Do not mark M2 complete or add consent/merge/edit/history/check-in behavior until OQ-17 and approved legal consent wording/version/retention are closed.

## 2026-09-12 roles and staff administration follow-up

Owners can manage custom roles at `/app/roles`, toggle the enforced active-branch view permission, assign eligible custom roles at `/app/assignments`, search staff by name/email, and add active accounts at `/app/staff/create`. Passwords are generated and not disclosed; employees use password recovery. Full PHP 8.5 regression passes 150 tests / 1,180 assertions. Browser and remaining quality gates are recorded in `.ai/TEST_RESULTS.md`.

## 2026-09-12 automatic admin reason follow-up

Reason selectors are removed from staff status, branch assignment, and branch lifecycle UI. These mutations no longer validate client `reason_code`; audit rows derive `staffing_change` for account status and `access_review` for branch access/lifecycle. High-risk reasons outside these routine administration flows remain unchanged. Acceptance: 142 tests / 1,109 assertions, build, Pint, docs, and browser checks pass.

## 2026-09-12 application shell follow-up

The authenticated tenant UI now follows `DESIGN.md`: a 248px desktop sidebar, 64px top bar, and responsive RTL/LTR drawer. Tenant/branch context, locale, identity, and logout are centralized; owner-only links remain policy-gated and no unimplemented PRD modules were exposed. Acceptance: 142 tests / 1,106 assertions, frontend build, Pint, documentation validation, and focused browser evidence all pass.

## 2026-09-12 branch lifecycle UI follow-up

The branch management table now presents one explicit state-aware action with consequence copy and a required reason instead of a redundant status selector. Current SQLite regression is 142 tests / 1,102 assertions on PHP 8.4.21 and 8.5.8; accepted MySQL evidence remains 142 / 1,096 because the follow-up changes only Blade, translations, and UI assertions.

## 2026-09-12 M1 closure handoff

Continue from `codex/first` with M1 DONE. Final M1 verification is 142 tests / 1,096 assertions on SQLite under PHP 8.4.21 and 8.5.8, plus 142 / 1,096 on isolated MySQL 8.4.11/InnoDB after fresh migrations. Browser, build, format, dependency audits, and docs passed. M2 later started with the bounded family-registry slice above. Preserve dirty `main`; no push was made.

## 2026-09-12 current handoff

Continue from `codex/first` after merge commits `385b58d` (T30) and `59fbd4c` (T32). MySQL 8.4.11/InnoDB and the T32 browser journeys are accepted; integrated regression is 141 tests / 1,077 assertions. The next bounded runtime task is T31 PHP 8.5 compatibility. Preserve dirty `main`, do not push, and do not commit T32's local SQLite/seed artifacts.

## Current state

The repository contains the audited PlayNexus product context, the canonical `DESIGN.md`, and one canonical documentation set directly under `docs/` in Markdown/YAML/HTML. The re-attached PRD matches the verified source hash. No Laravel application has been scaffolded and no application tests have run.

## How to continue

1. Read `README.md`, `AGENTS.md`, `DESIGN.md`, and `docs/00-INDEX.md`.
2. Review the detailed BRD through traceability matrix and resolve OQ-01–OQ-24 by gate priority.
3. Update `.ai/DECISIONS.md` with approvals.
4. Scaffold the chosen Laravel release, replace provisional commands, and implement M1 as a visible vertical slice.

The API contract currently validates as OpenAPI 3.1 with 56 paths, 70 operations, and 93 schemas. The interactive HTML wireframe contains 15 responsive keyboard-navigable screens. Incident artifacts are conditional on OQ-20. There is deliberately no Word/DOCX generator or artifact.

## Risks

- Incorrect tenant scoping is a critical security risk.
- Undefined pricing/pause/rounding rules can create billing disputes.
- Weak guardian verification or untracked overrides can create child-safety risk.
- Adding growth modules before the operational core is proven will delay the pilot.
