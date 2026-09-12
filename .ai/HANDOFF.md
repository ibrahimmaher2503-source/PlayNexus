# Handoff

## 2026-09-12 M3 pricing-version handoff

`POST /app/pricing/{pricingRule}/versions` now replaces the current active rule by retiring it and creating immutable version +1 with the same tenant, branch, and code. Owners and assigned branch managers receive bilingual native replacement controls; reception/cashier users remain view-only. Expected-version checks block stale/replayed forms, stored EGP stays integer-based, the new rule snapshots the locked branch tax settings, and audit metadata contains identifiers and versions only. Follow-up review isolates validation input to its submitted repeated form and removes premature consent copy. Full PHP 8.5 regression passes 226 / 1,859; Pint, Vite, docs, and pricing-route checks pass. Browser and fresh MySQL acceptance remain outstanding; do not add calculation/tickets/QR/check-in/sessions until their contracts are ready.

## 2026-09-12 M3 pricing-rule first-slice handoff

`/app/pricing` lists active immutable pricing rules for the actor's allowed active branches. Owners and branch managers can create version-1 fixed-duration rules for manageable branches using EGP inputs; reception/cashier roles are view-only. Stored facts use integer minor units, 600-second grace, 1,800-second overtime units, no pause, and the locked branch tax snapshot. Full PHP 8.5 regression passes 206 / 1,668; Pint, Vite, docs, and route checks pass. No update/delete/retire/calculator/ticket/QR/check-in/session feature exists. Browser and fresh MySQL acceptance remain outstanding; OQ-18 blocks ticket behavior.

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
