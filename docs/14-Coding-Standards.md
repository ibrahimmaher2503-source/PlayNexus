# Coding Standards

## PHP and Laravel

- Follow the selected Laravel release conventions and PSR-12-compatible formatting.
- Prefer framework generators and conventional directories.
- Use typed method signatures, enums for stable states, form requests for HTTP validation, policies for authorization, and jobs for asynchronous side effects.
- Keep controllers and Livewire components thin; use a named action when a workflow crosses multiple aggregates or needs a transaction.
- Do not create interfaces, repositories, service containers, traits, or base classes for one implementation without a measured need.

## Database

- Migrations are forward-only in shared environments and include foreign keys, indexes, and tenant-aware uniqueness.
- Query tenant scope explicitly and test it. Avoid unbounded eager loading and N+1 queries.
- Use integer minor units for money and UTC for stored timestamps.

## Frontend

- Use Blade components and a small Tailwind token layer; use Livewire only when server-driven interactivity materially reduces custom JavaScript.
- Semantic HTML, labels, keyboard access, visible focus, RTL, responsive layout, loading/empty/error/success states, and clear Arabic/English copy are required.
- Avoid a frontend SPA, separate design-system package, and new dependency for behavior covered by HTML, CSS, or Laravel.

## Quality

- Add one focused test for each risky rule or regression.
- Name tests by behavior and outcome.
- Never log passwords, tokens, child photos, full sensitive profiles, or payment secrets.

