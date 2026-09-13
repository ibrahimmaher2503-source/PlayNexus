# PlayNexus Agent Guide

## Mission

Deliver the smallest safe vertical slice of the PlayNexus MVP with a polished, fast reception and cashier experience. Preserve tenant isolation, authorization, money accuracy, child safety, and auditability in every change.

## Current repository state

Laravel M1 access/branch foundation, the approved Egypt M2 family engineering contract, and M3 immutable pricing/tickets/check-in/live sessions/read-only estimates are implemented in the integration worktree. Read the newest `docs/agent-plan.md` and `.ai/CURRENT_MILESTONE.md` entries; historical acceptance entries are not evidence for a later slice. Validation alone is not check-in, cancellation is not a financial refund, and a live estimate is not a completed checkout. Do not infer production readiness or M4/M5 behavior.

## Verified scaffold commands

Verified on 2026-09-12: Laravel 13.31.0, PHP 8.4.21 and 8.5.8, Composer 2.10.3, Node 24.15.0, npm 11.12.1, and isolated MySQL 8.4.11/InnoDB. The full M1 suite, build, format, dependency audits, documentation validator, and bilingual browser acceptance pass. Composer must be installed and available on `PATH`; do not depend on ignored `.codex` files.

```powershell
winget install --id Composer.Composer -e
# Open a new shell, then confirm: composer --version
composer install --no-interaction --prefer-dist --no-progress
if (-not (Test-Path .env)) { Copy-Item .env.example .env; php artisan key:generate }
# Configure an existing MySQL 8.4 database in .env, then run:
php artisan migrate
npm install --ignore-scripts
npm run build
php artisan serve
php artisan test
vendor/bin/pint --test
```

## Next implementation boundary

M3 is locally accepted and the product owner stopped before M4. OQ-12 guardian verification is approved; OQ-19 station ownership/recovery remains open. Do not begin M4 or add final billing, guardian release, payment, receipt or refund behavior until the owner explicitly resumes it with a bounded contract. Preserve existing dirty changes.

## Architecture rules

- Start with one Laravel modular monolith. Do not introduce microservices.
- Use Laravel routes, form requests, controllers or Livewire actions, Eloquent relationships, policies, jobs, events, notifications, migrations, factories, and seeders before custom frameworks.
- Every tenant-owned record must carry `tenant_id` unless its ownership is unambiguously inherited and enforced through a constrained parent; prefer explicit `tenant_id` on high-risk and high-volume tables.
- Enforce tenant and branch scope at query, authorization, validation, unique-index, and test levels.
- Store money as integer minor units plus ISO currency. Store timestamps in UTC and render in the branch timezone.
- Wrap checkout, payment, refund, discount approval, and session adjustment workflows in database transactions where consistency matters.
- Record actor, reason, before/after state, tenant, branch, and request correlation for sensitive overrides.
- Deny by default. Do not trust a role name without checking permission and resource scope.

## UI-first rules

- Treat `DESIGN.md` as the canonical visual and interaction baseline; reuse its tokens and component vocabulary.
- Implement the visible happy path and its loading, empty, validation, success, conflict, and authorization states as one vertical slice.
- Optimize reception and cashier screens for keyboard and touch, desktop and tablet, English/LTR and Arabic/RTL.
- Do not hide guardian verification, pricing breakdowns, approvals, or destructive consequences behind ambiguous controls.
- Reuse a small component vocabulary; do not build a separate design-system package for the MVP.

## Testing rules

- Add focused automated checks for tenancy, policies, pricing/time math, money, checkout verification, refunds, concurrency, and bugs.
- Run the smallest relevant test set after each change; record commands and real results in `.ai/TEST_RESULTS.md`.
- Never substitute a mocked green path for a missing authorization or data-isolation check.

## Documentation discipline

- Treat `docs/` as the single canonical specification and implementation-documentation set; do not create parallel summary copies.
- Keep `.ai/` status, OpenAPI, migrations, policies, and implemented behavior synchronized with the matching canonical file in `docs/`.
- Record consequential choices in `.ai/DECISIONS.md`; record unresolved blockers in `.ai/BLOCKERS.md`.
