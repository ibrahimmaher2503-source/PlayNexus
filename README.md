# PlayNexus

PlayNexus is a planned cloud-based, multi-tenant and multi-branch operating system for kids entertainment venues in MENA. This repository is currently in the documentation and architecture baseline stage; Laravel application code has not been scaffolded yet.

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

The audited draft documentation pack is complete in Markdown/YAML/HTML and is pending stakeholder decisions and approval. No Word/DOCX artifact is part of the project. See [.ai/CURRENT_MILESTONE.md](.ai/CURRENT_MILESTONE.md) and [.ai/PROGRESS.md](.ai/PROGRESS.md).

## Start implementation only after

1. Resolve the MVP decisions listed in `docs/00-INDEX.md`.
2. Approve the permission model, pricing rules, checkout verification, and payment mode.
3. Scaffold Laravel and record the exact local commands in this README and `AGENTS.md`.
