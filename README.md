# PlayNexus

PlayNexus is a Laravel 13 documentation-first, multi-tenant and multi-branch operating system for kids entertainment venues in MENA. Access/branch foundation, the approved Egypt family contract, and M3 immutable pricing/tickets/check-in/live sessions with a read-only non-final estimate are implemented. Checkout, child release, POS, financial refunds, and production release remain outside the delivered boundary.

## MVP outcome

The first release will let a venue configure branches and staff, register guardians and children, run safe check-in and checkout, calculate billable play time, issue tickets, record POS sales and payments, send operational notifications, and review essential reports and audit history.

## Proposed implementation baseline

- PHP 8.5 and Laravel 13 modular monolith, verified against the official support policy on 24 August 2026.
- Server-rendered Blade UI with Livewire 4 only for high-interaction operational screens, plus Tailwind CSS 4.
- MySQL 8.4 LTS/InnoDB shared-schema multi-tenancy with mandatory `tenant_id` scoping and database constraints.
- Session authentication for the web product; Laravel Sanctum for first-party API access.
- Laravel policies and gates for authorization; database queues initially for receipts and notifications; Pest 5 for focused tests.
- English and Arabic, LTR and RTL, desktop and tablet first.

Re-check supported patch versions when scaffolding; the major-version rationale and official sources are in the tooling guide.

## Documentation

- [Design system](DESIGN.md)
- [Final documentation index](docs/00-INDEX.md)
- [PRD baseline](docs/01-PRD-Baseline.md)
- [BRD](docs/02-BRD.md) and [SRS](docs/03-SRS.md)
- [Delivery milestones](docs/15-Delivery-Milestones.md)
- [Implementation checklist](docs/16-Implementation-Checklist.md)
- [AI handoff](.ai/HANDOFF.md)

## Current status

M1 is complete and M2 Egypt family engineering is implemented with remaining release gates. M3 is locally accepted: immutable pricing/tickets, atomic ticket-backed check-in, hard branch capacity, masked live sessions and exact read-only estimates are backed by isolated MySQL/SQLite/PHP 8.5 tests, real concurrency and authenticated bilingual responsive Edge QA. M4 checkout/final charge and guardian release, M5 payments/refunds, and production gates remain open. The current integration worktree preserves uncommitted user changes. See [.ai/CURRENT_MILESTONE.md](.ai/CURRENT_MILESTONE.md) and [.ai/PROGRESS.md](.ai/PROGRESS.md).

## Local setup

Verified on 12 September 2026: Laravel 13.31.0, PHP 8.4.21 and 8.5.8, Composer 2.10.3, Node 24.15.0, npm 11.12.1, and MySQL 8.4.11/InnoDB. The complete M1 suite passes on SQLite and isolated MySQL; Vite, Pint, dependency audits, documentation validation, and real Arabic/English browser checks also pass.

Install Composer for Windows before setup, then open a new shell and verify it is available. The command below uses the Windows Package Manager's Composer package; it avoids any ignored local tooling.

```powershell
winget install --id Composer.Composer -e
composer --version
composer install --no-interaction --prefer-dist --no-progress
if (-not (Test-Path .env)) { Copy-Item .env.example .env; php artisan key:generate }
# Configure DB_* in .env for an existing MySQL 8.4 database, then run:
php artisan migrate
npm install --ignore-scripts
npm run build
php artisan serve
php artisan test
vendor/bin/pint --test
```

The guarded `.env` command creates a new local environment and key only when `.env` is absent; it never overwrites an existing environment or key. The default route redirects to `/app`. Branch timezone and EGP currency settings are implemented; timestamps remain stored in UTC.

## Next milestone

M4 has not started. M3 remains locally accepted while OQ-19 checkout/payment station ownership stays open; resuming M4 requires an explicit bounded contract. Open later-milestone decisions do not reopen accepted M1–M3 work.
