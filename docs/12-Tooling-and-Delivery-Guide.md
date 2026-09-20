# PlayNexus Tooling and Delivery Guide

## 2026-09-15 installed-stack amendment

The delivered repository is Laravel 13 with server-rendered Blade, small vanilla JavaScript enhancements, Tailwind/Vite, PHPUnit, and MySQL 8.4/InnoDB acceptance. It does not install Livewire, Flux, Pest, Dusk, a SPA, or a separate design-system package. Recommendations below for those tools are historical planning options and must not be treated as setup instructions for this checkout. Reuse the installed stack unless a measured need and explicit scope change approve another dependency.

## 2026-09-15 M6 operational tooling

Use Laravel's installed database queue, scheduler, streamed responses and database layer; no provider SDK, reporting package, SPA, microservice or export dependency was added. Release, worker, monitoring, migration, restore, synthetic pilot and staff-walkthrough commands are canonical in `22-M6-Operations-Runbook.md`.

## 2026-09-13 latest verified delivery gate

M3 pricing/tickets/check-in/live board/read-only estimate passes the full suite on PHP 8.4.21/MySQL 8.4.11 (281 / 2,434) and PHP 8.4.21/8.5.8 with SQLite (279 of 281 / 2,387 with two MySQL-only skips), plus a real two-process InnoDB race and authenticated headless Edge Arabic/English responsive QA. Commands and exact evidence are in `.ai/TEST_RESULTS.md`. Task-local databases and compiled-view paths were used; the pre-existing 8206 runtime was not migrated or restarted.

**Technical recommendation:** Build the MVP as one Laravel 13 modular monolith with Livewire 4, MySQL 8.4 LTS, and Laravel-native authentication, authorization, queues, cache, scheduling, storage, and tests; confirm supported patch versions when scaffolding.  
**Optimization goal:** Shortest safe path to a pilot, not maximum framework choice or future flexibility.  
**Recommendation verified:** 24 August 2026

## ملخص البدء السريع

- ابدأ بـ **Laravel 13 + PHP 8.5 + Livewire 4 + MySQL 8.4 LTS** في مشروع واحد وقاعدة بيانات واحدة.
- على Windows استخدم **Laravel Herd** للتشغيل المحلي، وثبّت MySQL 8.4 LTS محليًا أو استخدم خدمة MySQL المحلية في Herd Pro لو كانت متاحة.
- اختر **Livewire Starter Kit** مع تسجيل الدخول المدمج، واستخدم **Pest 5** للاختبارات.
- ابنِ أول مسار كامل بهذا الترتيب: إنشاء المنشأة والفرع، تسجيل ولي الأمر والطفل، Check-in، متابعة الوقت، Checkout، الدفع، الإيصال.
- استخدم Laravel Policies للصلاحيات وعمود <code>tenant_id</code> لعزل البيانات. لا تبدأ بحزمة multi-tenancy أو permission package.
- ابدأ بالـdatabase للـqueue والـcache. أضف Redis فقط عندما يظهر احتياج مقاس.
- انشر Staging يوميًا. استخدم Laravel Cloud لو المنطقة والـdata residency مناسبين، أو Forge على سيرفر في المنطقة المعتمدة.
- لا تضف React، microservices، Octane، Horizon، WebSockets، Elasticsearch، تطبيق موبايل، أو Offline mode في الـMVP.
- لا تبنِ الألعاب والطوابير، أعياد الميلاد، العضويات والولاء، الحملات التسويقية، إدارة ورديات الكاشير، أو بوابة ولي الأمر قبل اعتمادها كمرحلة لاحقة؛ سعة الفرع والتنبيهات التشغيلية ضمن النواة، وسجل الحوادث مؤجل من إصدار مصر الأول بقرار OQ-20.
- استخدم Codex في User Story واحدة كل مرة، واطلب منه تشغيل الاختبارات ومراجعة الـdiff، لكن لا تتركه يخترع قواعد التسعير أو الأمان.

**MVP boundary:** The first release is a staff-operated responsive web application. It includes tenant/branch setup, staff access, family records, tickets, sessions/time, safe checkout, basic POS/payment recording/full refund, operational notifications, core reports, and audit. Approved OQ-20 defers basic incidents. Parent self-service, games/participation queues, birthdays, memberships, loyalty, marketing campaigns, cashier shift/cash-drawer management, inventory/HR, franchises, AI, marketplace, white-label products, offline writes, split/partial tender, and online gateway capture remain out until an approved scope change updates the SRS, API, data model, tests, and plan.

## 1. The smallest stack that fits

| Layer | Choice | Used for | Why this choice |
| --- | --- | --- | --- |
| Runtime | PHP 8.5 | Application and CLI | Current stable PHP branch, supported by Laravel 13 and Laravel Cloud; gives a longer support runway than older branches |
| Framework | Laravel 13 | Web app, domain orchestration, auth, validation, policies, jobs, scheduling, storage, notifications, testing | Current stable Laravel major with security support through March 2028 |
| UI | Blade + Livewire 4 | Reception, cashier, manager, and settings screens | Reactive server-driven UI while staying mostly in PHP; official starter kit owns its source code |
| Components | Tailwind CSS 4 + free Flux UI starter components | Accessible visual primitives and layout | Already aligned with the official Livewire starter kit; no second component system |
| Database | MySQL 8.4 LTS with InnoDB | All operational and financial data | Stable long-term-support line with transactions, row locks, constraints, and familiar operational tooling |
| Authentication | Laravel starter-kit session authentication | Staff web login, password reset, email verification | Built in, server-side, and sufficient for the MVP browser application |
| Authorization | Laravel policies and gates + predefined role enum/table | Tenant, branch, role, and action checks | Native, explicit, testable; no custom-role editor is required in MVP |
| Tenancy | Shared schema with mandatory <code>tenant_id</code> and branch scope | Data isolation | One database and one migration path are fastest; isolation remains a first-class invariant |
| Queue | Laravel database queue initially | Receipts, alerts, exports, provider calls | No Redis dependency at pilot scale; swap driver later without changing job code |
| Cache | Laravel database cache initially, short-lived and non-authoritative | Reference data and modest query caching | Keeps the initial infrastructure small; correctness never depends on cache |
| Scheduler | Laravel scheduler | Session alerts, cleanup, reconciliations, backup checks | One Laravel-native schedule, one platform scheduler integration |
| Files | Laravel Storage, local in development and private S3-compatible storage in production | Child photos if approved, exports, generated assets | Same application API across environments |
| Tests | Pest 5 + Laravel test helpers | Unit, feature, Livewire, policy, API, and critical browser tests | Current Pest major; one readable test style; browser plugin added only when browser tests begin |
| Formatting | Laravel Pint | PHP formatting | First-party Laravel tool with almost no configuration |
| Assets | Vite + Node.js 24 LTS | Tailwind and frontend build | Official Laravel asset path and a production-suitable Node LTS line |
| Source and CI | Git, GitHub, GitHub Actions | Version control, review, test/build gates | One repository and a simple MySQL 8.4 service container in CI |
| Local runtime | Laravel Herd on Windows | PHP, Nginx, Composer, Laravel CLI, Node tooling | Native Windows workflow with less overhead than running the entire app in Docker |
| Deployment | Laravel Cloud first if region/legal fit; otherwise Forge + approved regional provider | Staging and production | Cloud is the least operational work; Forge preserves region and server choice |
| Coding assistant | Codex, optional but useful | Small vertical slices, tests, refactors, documentation, review | Accelerates implementation when given exact scope, acceptance criteria, and validation commands |

### Why PHP 8.5 and Laravel 13

Laravel 13 was released on 17 March 2026, supports PHP 8.3 through 8.5, and requires at least PHP 8.3. PHP 8.5 is an actively supported stable branch. Pest 5 requires PHP 8.4 or newer. Pin PHP 8.5 across local, CI, staging, and production to avoid environment-only failures.

Official sources:

- [Laravel 13 release and support policy](https://laravel.com/docs/13.x/releases)
- [Laravel 13 installation](https://laravel.com/docs/13.x/installation)
- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Pest support policy](https://pestphp.com/docs/support-policy)

### Why Livewire instead of React or Vue

The primary product is a form-heavy authenticated operations tool, not a public consumer SPA. Livewire keeps routing, validation, authorization, state, and tests close to Laravel. The official starter kit includes Livewire 4, Tailwind, Flux UI, authentication, settings, and responsive layouts.

Use small Alpine behavior already bundled with Livewire for scanner focus, disclosure, or local toggles. Do not introduce a separate JavaScript framework.

- [Laravel 13 starter kits](https://laravel.com/docs/13.x/starter-kits)
- [Livewire 4 installation](https://livewire.laravel.com/docs/4.x/installation)
- [Livewire 4 testing](https://livewire.laravel.com/docs/4.x/testing)

### Why MySQL 8.4 LTS

PlayNexus needs durable transactions, row locking, uniqueness, foreign keys, check constraints, reporting, and real concurrency tests. MySQL 8.4 is an LTS release intended for a stable feature set and longer support. Use its InnoDB engine, <code>utf8mb4</code>, strict SQL mode, and the current 8.4 patch release supplied by the managed platform or official packages.

Do not use SQLite as the main development or CI database for money, concurrency, and tenant constraints. It remains acceptable for a throwaway framework smoke test, but it cannot prove MySQL/InnoDB behavior. Keep financial and session transactions short; lock hot rows in a consistent order; exercise duplicate-key, deadlock, and lock-timeout paths against MySQL 8.4.

- [MySQL Innovation and LTS release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html)
- [Which MySQL version to use](https://dev.mysql.com/doc/refman/8.4/en/which-version.html)
- [Laravel 13 database support](https://laravel.com/docs/13.x/database)

## 2. Architecture rules that keep delivery fast

1. **One repository, one Laravel application, one primary database.**
2. **One shared schema.** Every tenant-owned table includes a non-null <code>tenant_id</code>; branch-owned records also include <code>branch_id</code>.
3. **Laravel conventions first.** Models, migrations, policies, form requests, Livewire components, jobs, notifications, and tests stay in their expected directories.
4. **Extract only risky workflows.** Check-in, pause/resume, checkout, payment, refund, and approvals may use small action classes so web and API entry points share one transaction boundary.
5. **No generic repository layer.** Eloquent already provides the data-access abstraction used by the application.
6. **No event bus between modules.** Laravel events/jobs are enough for after-commit side effects.
7. **Database constraints are part of the design.** Use foreign keys, unique indexes, check constraints, and transactions rather than relying only on forms.
8. **Money is integer minor units plus ISO currency.** Never store or calculate money with binary floats.
9. **Time is server-authoritative.** Store instants in UTC; store the branch IANA time zone; render in branch-local time.
10. **Completed finance and session facts are immutable.** Correct them with adjustments/refunds and audit records.
11. **Cache is never the source of truth.**
12. **External calls happen after the business transaction.** Queue receipt/SMS/email work after commit.

### Suggested Laravel shape

Keep Laravel defaults. Add only a few business-focused directories when the first workflows require them:

    app/
      Actions/
        Sessions/
        Payments/
      Enums/
      Http/
        Controllers/
        Requests/
      Livewire/
      Models/
      Policies/
      Jobs/
      Notifications/
    database/
      factories/
      migrations/
      seeders/
    resources/
      lang/
        ar/
        en/
      views/
    routes/
      web.php
      api.php
      console.php
    tests/
      Unit/
      Feature/
      Browser/

Do not create empty directories for future modules. Create each path when its first real class exists.

## 3. Setup order

### Step 1: lock product decisions

Before coding money or checkout, approve:

- Launch country, currency, tax/receipt obligations, and branch time zone.
- Payment recording only versus online gateway processing.
- Pricing, pause, rounding, extension, overtime, discount, and refund rules.
- Guardian-verification evidence and manager override policy.
- Capacity behavior and override policy.
- Notification channel/provider, templates, alert lead time, retry/callback behavior, and cost approval.
- Incident categories, severities, transition/reopen rules, visibility, retention, and escalation procedure.
- Child age/date fields, duplicate-family policy, privacy/consent/retention/deletion rules, and Super Admin support-access boundary.
- Subscription plans/limits only if the MVP enforces them; otherwise keep billing automation and speculative limits out.
- Pilot scanner, printer, and wristband expectations.
- Production data residency and backup RPO/RTO.

Unknown decisions should be tracked as named blockers. Do not encode guessed financial or child-safety behavior.

### Step 2: install the local toolchain

On the current Windows workstation, install [Laravel Herd for Windows](https://herd.laravel.com/docs/windows/getting-started/installation). Herd includes the Laravel/PHP web environment and common command-line tooling. Install [MySQL Community Server 8.4 LTS](https://dev.mysql.com/downloads/mysql/) separately, or use a MySQL local service if Herd Pro is already licensed.

Verify:

    php --version
    composer --version
    laravel --version
    node --version
    npm --version
    mysql --version

Expected majors for this guide:

    PHP 8.5
    Laravel 13
    Node.js 24 LTS
    MySQL 8.4 LTS
    Pest 5

The Node project recommends production applications use an Active LTS or Maintenance LTS release. Node 24 is an LTS line at the verification date.

- [Laravel Herd Windows installation](https://herd.laravel.com/docs/windows/getting-started/installation)
- [Node.js release policy](https://nodejs.org/en/about/previous-releases)

### Step 3: create the application

Run:

    laravel new playnexus
    cd playnexus

Choose:

- Livewire starter kit.
- Laravel built-in authentication.
- Pest.
- MySQL.

Then:

    npm install
    npm run build
    php artisan test
    composer run dev

The official Laravel installer and starter-kit flow may change. Follow the current prompts rather than scripting assumptions around prompt order.

### Step 4: establish repository guardrails

Create one private GitHub repository and protect the main branch.

Commit:

- Composer and NPM lock files.
- <code>.env.example</code> with names and safe defaults, never secrets.
- Project documents and the requirement IDs.
- A one-command test/build script through Composer.
- One CI workflow using MySQL 8.4.

Initial Composer scripts should expose simple names:

    composer test
    composer lint
    composer build
    composer check

Keep the implementation behind those scripts small. The same commands run locally and in CI.

### Step 5: build a walking skeleton

Prove this before implementing modules:

1. Sign in.
2. resolve tenant and branch context.
3. authorize one branch page.
4. write/read one tenant-scoped record in MySQL.
5. dispatch and process one queued job.
6. render English/LTR and Arabic/RTL.
7. pass CI.
8. deploy staging and hit the Laravel health route.

This exposes environment, tenancy, queue, localization, and deployment mistakes while the codebase is still small.

## 4. Development tool map

| Tool | Use it for | Do not use it for |
| --- | --- | --- |
| PRD and project pack | Scope, rules, IDs, acceptance, API and UX contracts | Replacing unresolved business decisions with assumptions |
| GitHub Issues | One vertical story or defect per issue with acceptance IDs | A second copy of the full specification |
| GitHub pull requests | Small reviewable diff, CI evidence, migration note, screenshot when UI changes | Long-lived feature branches |
| Codex | Inspect context, implement one scoped slice, add tests, run checks, summarize diff | Approving money, privacy, safety, compliance, or production access decisions |
| Laravel Herd | Fast local app serving and tool versions on Windows | Production hosting |
| Artisan/Tinker | Generate framework files, run migrations/tests/jobs, inspect safe local data | Editing production data casually |
| MySQL <code>mysql</code> client | Schema/query inspection, explain plans, restore checks | Bypassing application authorization in operations |
| Browser developer tools | Responsive, network, focus, console, print, and RTL inspection | Proving server authorization |
| Pest | Executable business examples and regression protection | Repeating every feature at every layer |
| Laravel Pint | Consistent PHP formatting | Architectural review |
| Vite | Production CSS/JS build and local refresh | Adding a second frontend application |
| GitHub Actions | Repeatable install, migration, test, and build gates | Hosting long-running production workers |
| Laravel Cloud/Forge | Deployment, workers, scheduler, health and infrastructure operations | Replacing application-level tenancy, authorization, or audit controls |

### Using Codex effectively

Official OpenAI documentation presents Codex workflows for understanding codebases, code changes, testing, security review, documentation upkeep, and deployment. Keep each request concrete and let the agent verify its work.

A useful implementation prompt:

    Implement CG-05, FR-SES-001–002, US-SES-001, and UC-04 only.
    Read PRODUCT.md and the matching documents in docs/ first.
    Use Laravel 13, Livewire, policies, MySQL/InnoDB constraints, and existing patterns.
    Do not add a package unless Laravel or the installed stack cannot meet the requirement.
    Add the smallest unit/feature/browser tests required by 11-Testing-Strategy.md.
    Run targeted tests, Pint, and the frontend build.
    Report changed files, checks, assumptions, and any unresolved product decision.

Split parallel Codex tasks only when files and outcomes do not overlap, for example one agent reviewing tenancy tests while another drafts Arabic copy. Never let two agents independently change the same migration or money workflow.

- [Official Codex and ChatGPT engineering use cases](https://learn.chatgpt.com/use-cases?category=engineering)
- [Official OpenAI model prompting guidance](https://developers.openai.com/api/docs/guides/latest-model)

## 5. Local, CI, staging, and production choices

| Concern | Local | CI | Staging | Production |
| --- | --- | --- | --- | --- |
| PHP | Herd PHP 8.5 | PHP 8.5 | PHP 8.5 | PHP 8.5 |
| Web | Herd/Nginx | Framework test runner | Cloud or Forge app | Cloud or Forge app |
| Database | MySQL 8.4 LTS | Ephemeral MySQL 8.4 service | Isolated MySQL 8.4 LTS, or the platform engine after a recorded compatibility test | Managed MySQL 8.4 LTS in an approved region; record and test any platform-engine deviation |
| Queue | Database queue worker | Sync/fake for most tests; real database worker smoke | Database queue worker | Database worker until measured need for managed/Redis queue |
| Cache | Database or array in focused tests | Array/database as test requires | Database | Database initially |
| Files | Local private disk | Fake disk | Private object storage | Private S3-compatible object storage |
| Mail/SMS | Log/fake | Fake | Provider sandbox | Approved provider |
| Payments | Fake/manual recording | Fake | Provider sandbox if selected | Approved provider only |
| Observability | Local logs | CI artifacts | Platform logs and alerts | Logs, health, error/queue/backup alerts |
| Data | Synthetic seed | Deterministic synthetic factories | Synthetic production-shaped data | Real tenant data |

### Production hosting decision

**Fastest option:** Laravel Cloud, if legal, data-residency, latency, support, and cost review accepts an available region. Cloud provides managed compute, databases, cache, object storage, queues, scheduled tasks, logs, preview environments, TLS, and zero-downtime deployment.

Before selecting Cloud's managed database, confirm its current MySQL engine/version and test the constraints, transactions, locking, and backup/restore behavior required by this baseline. If it is not MySQL 8.4 LTS, either connect an approved external MySQL 8.4 service or record the deviation and pass the same compatibility and recovery evidence before release.

At the verification date, Laravel Cloud publicly lists US, Canada, European, Singapore, Sydney, and Tokyo regions, with no public Middle East region. Frankfurt may be operationally close for MENA, but that is not a data-residency approval. Measure latency from pilot venues and obtain legal approval before storing real guardian/child data there.

**Regional-control option:** Laravel Forge on an approved cloud/VPS region with managed MySQL 8.4 LTS and private object storage. This allows provider/region choice but adds server patching, backup, monitoring, and operational responsibility.

- [Laravel 13 deployment choices](https://laravel.com/docs/13.x/deployment)
- [Laravel Cloud documentation](https://cloud.laravel.com/docs)
- [Laravel Cloud published regions](https://marketing.cloud.laravel.com/)
- [Laravel Forge documentation](https://forge.laravel.com/docs/)

Do not choose production hosting by convenience alone. Record the decision, region, data processor, retention, backup, and support owner.

## 6. CI pipeline

Use one GitHub Actions workflow on pull requests and main:

1. Check out the pinned action versions.
2. Set up PHP 8.5 and Node 24.
3. Start MySQL 8.4 as a service container, wait for its health check, and create the isolated test database.
4. Install Composer dependencies from the lock file without scripts that need secrets.
5. Install NPM dependencies with <code>npm ci</code>.
6. Copy the CI environment template and generate an application key.
7. Run migrations from empty.
8. Run Pint check.
9. Run unit and feature tests.
10. Build production assets.
11. Run critical browser tests on main or a dedicated job once they exist.

Use the production database major and keep the pull-request job under 10 minutes. Add parallel testing only after the measured suite exceeds the target.

GitHub documents service containers for Actions. Pin the official <code>mysql:8.4</code> image, use synthetic credentials, wait for <code>mysqladmin ping</code>, and run migrations from empty. Laravel can run tests through <code>php artisan test</code>, and Pest handles the selected test style.

- [GitHub Actions service containers](https://docs.github.com/en/actions/tutorials/use-containerized-services)
- [MySQL 8.4 LTS release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html)
- [GitHub Actions quickstart](https://docs.github.com/en/actions/get-started/quickstart)
- [Laravel 13 testing](https://laravel.com/docs/13.x/testing)
- [Pest installation](https://pestphp.com/docs/installation)

### Gates

- Pull request cannot merge if migration, unit/feature tests, formatting, or build fails.
- Main cannot deploy if critical browser journeys fail.
- Production deploy is manual approval until the pilot is stable.
- Security, dependency, and secret review becomes required before pilot.
- A migration that cannot safely coexist with the previous app version must include a written deployment/rollback plan.

## 7. Delivery milestones

These are outcome milestones, not promises of calendar duration. For one experienced full-stack Laravel developer, use them to plan roughly 8 to 12 focused weeks, then adjust after the first two milestones. A small experienced team can parallelize UI, core workflows, and QA only when boundaries are clear.

### M0: Decisions and skeleton

**Target:** 2 to 4 working days after product decisions are available.

- Product decisions listed in Section 3 recorded.
- Laravel/Livewire app, MySQL 8.4, auth, locale/direction, CI, staging, health check.
- Tenant and branch context visible.
- One queue job and one backup/restore smoke path proven.

### M1: Tenant, branch, staff, and permissions

**Target:** 4 to 6 working days.

- Tenant onboarding and first branch.
- Predefined roles and branch assignments.
- Policies for every exposed action.
- Cross-tenant and cross-branch negative tests.
- Branch settings needed by registration/check-in only.

### M2: Family registration and check-in

**Target:** 5 to 8 working days.

- Guardian/child search and duplicate warning.
- Child-to-guardian relationship and consent.
- Ticket issue/validation, expiry, cancel/reprint, scan history, price selection, and the approved branch-capacity check.
- QR/wristband generation after the record is durable.
- First complete browser journey.

### M3: Time engine, live sessions, and checkout

**Target:** 7 to 10 working days.

- Approved time and pricing examples encoded as unit tests.
- Active, paused, completed, cancelled state transitions.
- Live session list with modest polling.
- Guardian verification and manager override.
- Durable, idempotent payment and session-completion boundaries with the approved OQ-19 recoverable `pending_payment` handoff; notification remains after-commit.
- Real concurrency tests.

### M4: POS, payments, discounts, and refunds

**Target:** 7 to 10 working days.

- Product/ticket catalog and sale.
- Cash and external-terminal recording selected for MVP.
- Discount threshold approval.
- Refund with reason and original transaction link.
- Money reconciliation tests.

### M5: Reports, audit, and notifications

**Target:** 5 to 8 working days.

- Daily revenue, attendance, sessions, and staff activity.
- Filters and CSV export.
- Session-ending alert and digital receipt jobs.
- Audit search for critical actions.
- If a later decision reopens OQ-20 scope: define and approve basic incident create, scoped search, append-only follow-up, and state transitions before implementation.
- Query plans and agreed standard-range performance checks.

### M6: Pilot hardening

**Target:** 7 to 12 working days.

- Arabic copy and RTL review.
- WCAG 2.2 AA critical-path audit.
- Security review and dependency/secret checks.
- Load, concurrency, backup restore, migration, and rollback rehearsal.
- Scanner and printer compatibility.
- Staff rehearsal, fixes, monitoring, runbooks, and support ownership.

Scope cuts happen before quality cuts. If time is short, remove non-critical exports, customization, or secondary reports. Do not remove tenant isolation, policies, transaction integrity, guardian verification, audit, backups, or critical tests.

## 8. Build order within each milestone

Deliver a vertical slice rather than finishing all models, then all APIs, then all screens.

For each story:

1. Confirm requirement, business rule, use case, wireframe, API operation, and acceptance IDs.
2. Write one happy-path feature test and the critical denial/boundary tests.
3. Add the smallest migration and database constraints.
4. Implement policy and one action/transaction boundary.
5. Expose it through Livewire and, only where specified, the versioned API.
6. Add loading, empty, validation, error, permission, concurrency, and RTL states.
7. Run targeted tests, Pint, build, and one browser smoke.
8. Review the diff, deploy staging, and obtain operations feedback.

The browser UI and <code>/api/v1</code> routes may share the same application action. Do not build a separate API service. Do not implement future API endpoints only because they appear in a long-term specification.

## 9. Daily workflow

### Start of day

- Pull main and read CI/deployment status.
- Select one story small enough to finish and demonstrate today.
- Confirm its requirement IDs and unresolved decisions.
- Create a short <code>codex/...</code> branch or equivalent team naming convention.

### Build loop

- Ask Codex or the developer to inspect existing patterns before editing.
- Change the smallest coherent slice.
- Run the nearest test after each domain or transaction change.
- Keep migrations additive during active deployment cycles.
- Test one English/LTR and one Arabic/RTL screen state for UI work.
- Commit when the slice is green and understandable, not after a huge batch.

### Before pull request

    composer check

The script should run at least tests, Pint check, and production asset build. Add static analysis when configured.

The pull request states:

- Story/use-case/rule IDs.
- User-visible outcome.
- Migration and rollback impact.
- Tests and manual checks run.
- Screenshots for desktop/tablet and RTL when UI changed.
- Packages added, why Laravel/native code was insufficient, and removal cost.
- Known limits or follow-up issue.

### End of day

- Merge only after CI and review.
- Deploy staging.
- Demonstrate the completed slice from a seeded account.
- Update the issue, not the same requirements in multiple documents.
- Record any product decision and its owner.

## 10. Packages and infrastructure to avoid until needed

| Avoid now | Native/minimal replacement | Add only when |
| --- | --- | --- |
| Microservices | One Laravel application | Independent scaling/deployment/team ownership is measured and worth distributed failure modes |
| React/Vue/Inertia SPA | Livewire + Alpine | A proven interaction cannot be delivered acceptably with Livewire |
| Multi-tenancy package | Mandatory tenant scope, policies, foreign keys, tests | Database-per-tenant or domain/database switching becomes an approved requirement |
| Spatie Permission or custom permission builder | Predefined roles + policies/gates | Pilot customers require tenant-defined roles that cannot be represented safely |
| Redis | Database queue/cache | Queue latency, locks, cache throughput, or multi-node sessions show a measured need |
| Horizon | Standard queue worker and failed-job tools | Redis is already justified and operations need Horizon-specific visibility |
| Octane/FrankenPHP tuning | Standard PHP runtime | Profiling shows PHP boot/runtime, not database or external calls, is the bottleneck |
| Reverb/WebSockets | Modest polling for live sessions | Polling load or required update latency fails the measured acceptance target |
| Scout/Meilisearch/Elasticsearch | Indexed MySQL search | Search latency/relevance fails with production-shaped data |
| Sanctum | Session authentication for browser | A mobile app, external SPA, or third-party API client is approved |
| Passport/OAuth server | Sanctum or signed integration credentials later | PlayNexus must act as an OAuth authorization server |
| Cashier | Plain transaction/payment records | A supported Cashier provider and recurring subscription model are approved |
| Filament/Nova as the operations UI | Livewire screens matching approved workflows | A separate internal super-admin surface has a clear owner and does not leak into venue UI |
| PDF library | Print-friendly HTML receipt and CSV | A regulator/customer requires server-generated PDF with exact layout |
| Excel library | CSV | Multi-sheet formatting/formulas become an accepted requirement |
| Auditing package | Explicit immutable audit table for critical actions | Audit coverage becomes broad enough that maintained package semantics clearly reduce work |
| DTO/command bus/repository interfaces | Form requests, arrays/value objects, actions, Eloquent | Multiple real implementations or transport boundaries exist |
| Kubernetes | Laravel Cloud or one Forge-managed application server | Scale, isolation, or platform policy requires it and an operations team owns it |
| Offline sync | Safe online-only behavior and visible connection state | Product approves conflict, identity, payment, device security, and support design |
| Games/queues, birthdays, memberships, loyalty, marketing campaigns, cashier shifts, inventory/HR, franchises, AI/analytics, marketplace, white-label, parent/mobile app | None in MVP | A later product phase or explicit pilot scope change is funded and baselined after the operational core is healthy |

### The only likely early runtime addition

The ticket flow needs QR generation. Add a focused maintained QR library when M2 begins, not during initial scaffolding. [endroid/qr-code](https://github.com/endroid/qr-code) is a small general PHP option that can generate SVG/PNG. Pin it in Composer, encode an opaque ticket token rather than sensitive child data, and test scanning with the selected devices.

Do not install its Symfony bundle in Laravel.

## 11. API and integration tooling

- Treat the API Specification as the contract.
- Prefix implemented endpoints with <code>/api/v1</code>.
- Use Laravel Form Requests, policies, API Resources, rate limiting, and consistent error objects.
- Keep browser session routes and API routes as entry points to the same action classes.
- Maintain the existing OpenAPI contract only for routes selected for implementation; do not generate speculative future endpoints or a separate API service.
- Use Pest HTTP tests as the executable contract.
- Use <code>curl</code> for simple manual checks. Add an API client collection only when non-developers or external partners need it.
- Use provider sandbox/fake adapters for payment, SMS, WhatsApp, and email.
- Never expose provider secrets to the browser or commit them to the repository.
- Webhooks are out until a provider is selected; then add signature, replay-window, idempotency, and retry tests before enabling.

Session authentication is enough for the MVP staff web app. Add Sanctum only when a real external API or first-party mobile/SPA client requires token authentication.

## 12. Production baseline

Before real data:

- <code>APP_ENV=production</code> and <code>APP_DEBUG=false</code>.
- TLS, secure cookies, trusted hosts/proxies, CSRF, session expiry, and login rate limits configured.
- Secrets stored in the platform secret store.
- Web root points only to Laravel <code>public</code>.
- Run <code>php artisan optimize</code> during deployment.
- Run additive migrations with <code>php artisan migrate --force</code>.
- Reload long-running services with <code>php artisan reload</code> where the platform does not do it automatically.
- Laravel health route monitored.
- Queue worker and scheduler configured, supervised, and alerted.
- Failed jobs reviewed and retry behavior documented.
- Database connection encrypted and least privileged.
- Private object storage; temporary URLs for sensitive files.
- Automated database and object backups plus a current restore test.
- Logs include correlation, tenant, branch, actor, action, and result where safe, but not child notes, identity evidence, secrets, or full payment details.
- Error rate, response time, queue delay/failures, database connections, disk/storage, and backup status alerted.
- One documented rollback decision path. Database rollback is not assumed safe.

[Laravel 13 deployment documentation](https://laravel.com/docs/13.x/deployment) documents server requirements, optimization, service reload, debug mode, and the health route.

## 13. Definition of done for a vertical slice

A story is done only when:

- Acceptance and business-rule IDs are satisfied.
- Tenant and branch scope are explicit.
- Policy allows and denies the right roles.
- Database constraints and transaction boundary protect the invariant.
- Money/time use authoritative server values.
- Audit is recorded for critical actions.
- UI has loading, empty, validation, error, success, disabled, stale/conflict, and RTL behavior as applicable.
- Targeted unit/feature tests pass; critical browser path updated where required.
- Pint and production build pass.
- No secret or sensitive sample data is committed.
- Staging demonstration succeeds.
- Documentation changes only where contract or decision changed.

## 14. Decision triggers

Review the stack only when evidence crosses a trigger:

| Signal | Review |
| --- | --- |
| Queue delay breaches operational alert target | Redis/managed queue and Horizon or platform queue dashboard |
| Live-session polling causes material database/app load | Better query/index/cache first, then WebSockets |
| Standard action/report misses PRD target | Query plan/index and N+1 review before Octane or new infrastructure |
| Search fails target on production-shaped data | MySQL FULLTEXT/index review before Scout/search service |
| Standard roles cannot serve two pilot customers | Custom role UI and permission package assessment |
| Cloud region fails legal/latency review | Forge or approved regional platform |
| Browser/mobile consumer is approved | Sanctum and explicit API surface |
| External payment provider approved | Adapter, webhook, idempotency, reconciliation, provider-specific package review |
| Multiple teams need independent releases and scaling | Revisit service boundaries after mapping transaction and data ownership |

## 15. Official source index

### Laravel ecosystem

- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases)
- [Laravel 13 installation](https://laravel.com/docs/13.x/installation)
- [Laravel starter kits](https://laravel.com/docs/13.x/starter-kits)
- [Laravel database](https://laravel.com/docs/13.x/database)
- [Laravel authorization](https://laravel.com/docs/13.x/authorization)
- [Laravel queues](https://laravel.com/docs/13.x/queues)
- [Laravel cache](https://laravel.com/docs/13.x/cache)
- [Laravel scheduling](https://laravel.com/docs/13.x/scheduling)
- [Laravel file storage](https://laravel.com/docs/13.x/filesystem)
- [Laravel testing](https://laravel.com/docs/13.x/testing)
- [Laravel deployment](https://laravel.com/docs/13.x/deployment)
- [Livewire 4](https://livewire.laravel.com/docs/4.x/installation)
- [Pest](https://pestphp.com/docs/installation)

### Runtime, data, CI, hosting, and accessibility

- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Node.js releases](https://nodejs.org/en/about/previous-releases)
- [MySQL 8.4 release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html)
- [Which MySQL version to use](https://dev.mysql.com/doc/refman/8.4/en/which-version.html)
- [GitHub Actions](https://docs.github.com/en/actions/get-started)
- [Laravel Herd](https://herd.laravel.com/docs/windows/getting-started/installation)
- [Laravel Cloud](https://cloud.laravel.com/docs)
- [Laravel Forge](https://forge.laravel.com/docs/)
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- [OWASP ASVS 5.0.0](https://owasp.org/www-project-application-security-verification-standard/)
- [Official Codex/ChatGPT engineering use cases](https://learn.chatgpt.com/use-cases?category=engineering)

Versions and hosting availability change. Keep Composer/NPM lock files, pin runtime majors, and re-check these official sources before setup and each major upgrade.
