# PlayNexus

PlayNexus is a Laravel 13 documentation-first foundation for a cloud-based, multi-tenant and multi-branch operating system for kids entertainment venues in MENA. Business modules are intentionally not implemented yet.

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

M1 access and branch foundation is complete on `codex/first`: tenant and branch scope, staff and platform authentication, owner administration, fixed policies, locale/timezone/currency settings, audit history, and the bilingual responsive application shell. M2 guardian and child registration has not started. This is local milestone acceptance, not production readiness. See [.ai/CURRENT_MILESTONE.md](.ai/CURRENT_MILESTONE.md) and [.ai/PROGRESS.md](.ai/PROGRESS.md).

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

M2 starts only from the approved guardian/child identity, duplicate handling, consent, and privacy rules. Open later-milestone decisions do not reopen the accepted M1 boundary.
