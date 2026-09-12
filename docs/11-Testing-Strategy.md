# PlayNexus Testing Strategy

## M1 executed baseline — 2026-09-12

The current M1 regression contains 142 tests and 1,102 assertions on SQLite with both PHP 8.4.21 and PHP 8.5.8. The preceding database-affecting baseline passed 142 tests and 1,096 assertions after fresh migrations on isolated MySQL 8.4.11/InnoDB; the later difference is six UI assertions for explicit branch lifecycle actions and copy. Focused UI/audit regression passed 33 tests and 217 assertions. Vite build, Pint, Composer audit, npm audit, documentation validation, and real Arabic RTL/English LTR browser checks passed. These results are an M1 baseline, not evidence for unimplemented M2–M6 or production operations.

**Document status:** Draft MVP quality baseline pending scope and policy decisions  
**Audience:** Engineering, QA, product, security, and venue operations  
**Approach:** Risk-based, automated at the lowest useful layer, production-like where behavior depends on the database or browser  
**Source baseline:** PlayNexus PRD v1.0 and the project pack requirements

## 1. Purpose

PlayNexus handles child checkout safety, tenant-isolated customer data, timed billing, cash and payment records, branch capacity, and staff privileges. Testing must give rapid feedback without treating every function as equally risky.

The strategy prioritizes failures that could:

1. Release a child to an unverified guardian.
2. expose one tenant's data to another tenant.
3. charge, refund, or report the wrong amount.
4. start, pause, resume, or complete a session incorrectly.
5. allow an unauthorized action or hide it from the audit trail.
6. prevent reception or cashier work during peak periods.

The target is confidence in these outcomes, not an arbitrary count of test cases or a high line-coverage number.

## 2. Quality objectives

| Quality attribute | MVP objective | Evidence |
| --- | --- | --- |
| Safety | Checkout requires a valid guardian relationship or a permitted, reasoned, approved override | Feature tests, browser journey, manual operations drill, audit log inspection |
| Tenant isolation | Every tenant-bound read, write, export, job, and report stays within tenant scope | Cross-tenant negative tests at request and service layers |
| Financial integrity | Durable transaction totals reconcile with line items, discounts, tax, payments, and refunds | Unit examples, feature tests against production database engine, reconciliation queries |
| Time accuracy | Billing uses valid state transitions and deterministic duration, pause, extension, overtime, and rounding rules | Unit boundary tables and feature tests with a frozen clock |
| Authorization | Server-side policy enforces role and branch scope regardless of UI visibility | Policy tests and HTTP/API denial tests |
| Operability | Critical staff journeys are clear, keyboard operable, and recoverable from validation and transient failure | Browser tests plus manual tablet, scanner, printer, RTL, and assistive technology checks |
| Performance | Standard actions meet the PRD 2 second target; standard reports meet the 5 second target under agreed normal load | Timed API checks and a production-like load test |
| Availability and peak stability | Core operations target 99.9 percent monthly availability and degrade safely when optional providers fail | Synthetic health checks, measured uptime/error budget, peak-load run, provider-failure test, incident review |
| Recoverability | Backups are encrypted, monitored, restorable, and meet approved RPO/RTO | Scheduled restore drill with evidence |
| Privacy and consent | Only necessary guardian/child data is exposed; consent evidence, retention, correction, access, and deletion behavior follow the approved launch-country policy | Policy/field-masking tests, consent-version assertions, upload checks, legal UAT after OQ-07 closes |

## 3. Risk model

Score each risk using **impact from 1 to 5** multiplied by **likelihood from 1 to 5**.

- 16 to 25: Critical. Automated in every relevant layer, blocked in CI, manually rehearsed before pilot.
- 9 to 15: High. Automated feature coverage and targeted browser/manual coverage.
- 4 to 8: Medium. Feature or unit coverage based on the failure boundary.
- 1 to 3: Low. Exploratory or regression coverage when changed.

### Initial MVP risk register

| Risk | Impact | Likelihood | Score | Primary controls |
| --- | ---: | ---: | ---: | --- |
| Cross-tenant record or report exposure | 5 | 4 | 20 | Scoped queries, policy tests, adversarial HTTP tests, log review |
| Wrong guardian accepted at checkout | 5 | 3 | 15 | Relationship validation, approval path, browser journey, audit assertion |
| Duplicate checkout or payment from concurrent terminals | 5 | 3 | 15 | Transaction and lock/idempotency tests using MySQL 8.4/InnoDB |
| Incorrect timed charge or rounding | 4 | 4 | 16 | Pure domain tests, boundary table, immutable quote snapshot |
| Unauthorized refund, override, discount, or session adjustment | 5 | 3 | 15 | Policies, approval state machine, denial tests, audit assertions |
| Capacity exceeded by simultaneous check-ins | 4 | 3 | 12 | Concurrency test, database transaction, manager override test |
| Time-zone or daylight-saving error | 4 | 3 | 12 | UTC storage, branch-zone rendering, transition date tests |
| Report totals do not reconcile | 4 | 3 | 12 | Ledger-based feature tests and reconciliation acceptance check |
| Network retry creates duplicate sale | 4 | 3 | 12 | Idempotency test, durable response replay |
| Staff cannot finish task with keyboard or Arabic RTL | 3 | 3 | 9 | Browser checks, manual keyboard and RTL pass |
| Queue failure loses notification or receipt delivery | 3 | 3 | 9 | Retry and idempotency tests, failed-job monitoring |
| Backup exists but cannot restore | 5 | 2 | 10 | Automated restore drill and signed result |

Update this table after pricing, payment, hardware, and launch-country decisions are approved. Offline behavior changes only through an explicit scope/architecture decision; the MVP baseline remains safe online-only operation.

### MVP boundary under test

The suite does not turn roadmap material into release scope. Parent portals/apps, games/participation queues, birthday bookings, memberships, loyalty, marketing campaigns, cashier shift/cash-drawer management, advanced inventory/HR, franchises, AI, marketplace, white-label products, offline writes, split/partial payments, and online gateway capture are excluded until an approved change updates the SRS and traceability matrix. Branch-wide check-in capacity, operational session/receipt notifications, and basic incident records remain MVP concerns; they must not pull in the deferred game/CRM modules.

## 4. Test pyramid and boundaries

The suite should be broad at the fast domain and HTTP layers, narrow at the real-browser layer, and intentional in manual testing.

    Manual and pilot drills
      Hardware, operations, disaster recovery
        Real-browser critical paths
          HTTP, policy, database, and API tests
            Pure domain and unit examples

### 4.1 Pure domain and unit tests

**Use for:** Pricing calculations, time intervals, rounding, tax allocation, permitted state transitions, capacity decisions, permission rules expressed as pure values, and receipt number formatting.

**Rules:**

- No framework boot, database, network, queue, or filesystem.
- Freeze or inject the clock.
- Use table-driven examples at exact boundaries.
- Test money in integer minor units, never binary floating point.
- Prefer examples that state business meaning over testing private methods.

**Do not use for:** Eloquent scopes, database constraints, transactions, route middleware, policies, localization rendering, or Livewire behavior.

### 4.2 Feature, HTTP, policy, and database tests

This is the main confidence layer.

**Use for:** Requests, Livewire actions, APIs, validation, authentication, authorization, tenant and branch scope, database constraints, transaction boundaries, queues/events, audit records, report queries, and exports.

**Rules:**

- Use the same database family as production for locking, constraints, date/time behavior, and query plans.
- Assert both the response and durable database outcome.
- For denied actions, assert no business record, payment, audit approval, or queued side effect was created.
- Prefer a real service class plus faked external boundary over mocking internal Laravel code.
- Test jobs separately, and test that the request dispatches the correct job.
- Keep one test focused on one observable business outcome.

### 4.3 Browser tests

**Use only for workflows where browser behavior matters:**

1. Login and branch context.
2. Search or create family, then check in.
3. Live session action, guardian verification, payment, checkout, and receipt.
4. POS sale with a permitted discount and a denied over-limit discount.
5. Arabic/RTL navigation and one complete operational form.
6. Keyboard-only completion of check-in and checkout.
7. A validation error that preserves entered data and restores useful focus.

Use the real browser for Livewire updates, focus, directionality, JavaScript errors, scanner-like keyboard input, sticky action areas, and responsive layout. Do not duplicate every HTTP validation case here.

### 4.4 Manual, exploratory, and pilot tests

Manual testing owns behavior that automation cannot represent economically:

- Guardian-verification usability with real reception staff.
- Receipt printer, barcode scanner, QR camera, and wristband combinations selected for the pilot.
- Bright-light tablet legibility, touch targets, gloves, noise, and interruption recovery.
- Screen reader smoke pass, keyboard pass, 200 percent zoom, and Arabic reading quality.
- Country-specific receipt, tax, phone, consent, and identity wording.
- Failed internet behavior. MVP should prevent unsafe writes unless an offline design is later approved.
- Backup restore and disaster runbook execution.
- Peak-hour pilot observation and staff training comprehension.

Record device, browser, operating system, hardware model, locale, branch time zone, build identifier, and evidence for every manual run.

## 5. Critical workflow coverage

| Test ID | Workflow | Required assertions | Current trace: capability; SRS; story; use case |
| --- | --- | --- | --- |
| T-CW-001 | Create tenant and first branch | Tenant/branch relationship, isolated owner, valid country/time zone/currency, active state | CG-01; FR-TEN-001, FR-TEN-004–005, SEC-TEN-001; US-TEN-001, US-TEN-003; UC-01 |
| T-CW-002 | Register guardian and child | Child has guardian, approved duplicate-phone handling, search returns only current tenant | CG-03; FR-CUS-001–005, DATA-REL-001; US-CUS-001–003; UC-03 |
| T-CW-003 | Standard check-in | Required child, branch, pricing/ticket, staff and server start time; ticket/session commits once; audit actor recorded | CG-04, CG-05; FR-SES-001–002, FR-SES-014, FR-TKT-005; US-TKT-002, US-SES-001; UC-04 |
| T-CW-004 | Pause and resume | Valid transition only, approved pauses excluded when configured, overlapping pauses blocked | CG-05; FR-SES-003–004, FR-TIM-003; US-SES-004; UC-05 |
| T-CW-005 | Standard checkout | Guardian verified, server quote used, required settlement durable, session completed once, and receipt facts consistent | CG-06, CG-07; FR-SES-007–010, FR-POS-006–009, FR-SAF-001; US-SES-007–008, US-SES-010, US-POS-003–004; UC-06 |
| T-CW-006 | Guardian mismatch override | Standard staff denied; authorized manager plus reason succeeds; immutable approval/audit evidence created | CG-06, CG-10; FR-SES-008, FR-SAF-001–002, FR-RBAC-005; US-SES-009, US-SAF-003; UC-07 |
| T-CW-007 | Overtime and extension | Boundary calculation, visible overage, and accepted extension change the quote prospectively under the approved pricing fixture | CG-05; FR-SES-005, FR-SES-007, FR-TIM-003–006; US-SES-005, US-SES-007, US-TIM-002; UC-05, UC-06 |
| T-CW-008 | POS sale | Line totals, discount, tax, payment and receipt reconcile; retry does not duplicate | CG-07; FR-POS-002–009, DATA-FIN-001; US-POS-001–004; UC-08 |
| T-CW-009 | Over-limit discount | Cashier denied or approval pending; authorized manager decision and reason logged | CG-02, CG-07; FR-POS-005, FR-RBAC-005; US-RBAC-003, US-POS-002; UC-08 |
| T-CW-010 | Full refund | Permission, reason, original payment link, refundable-balance limit, immutable reversal, and audit entry | CG-07; FR-POS-010–012; US-POS-005; UC-09 |
| T-CW-011 | Inactive branch | Historical sessions remain visible in scope; new sessions/orders are blocked in UI and server | CG-01; FR-TEN-006, FR-TEN-008; US-TEN-004; UC-13 |
| T-CW-012 | Revenue report | Filters honor tenant/branch scope; totals reconcile to durable payments and refunds | CG-08; FR-RPT-001, FR-RPT-005–007; US-RPT-001; UC-10 |
| T-CW-013 | Concurrent check-in at capacity | The approved OQ-11 capacity policy is applied once; blocking mode cannot over-commit, and the losing request receives current capacity and a safe recovery path | CG-05; FR-SES-001–002, FR-SES-014; US-SES-001; UC-04; OQ-11 |
| T-CW-014 | Concurrent checkout/payment handoff and recovery | Payment posting and session completion each have a durable idempotency boundary; concurrent/retried requests create at most one payment, receipt number, and completion; a paid-but-not-completed interruption is visible and safely recoverable without another charge; completion still requires current settlement and guardian verification | CG-06, CG-07; FR-SES-009–010, FR-SES-014, FR-POS-006–009, DATA-INT-001; US-SES-010, US-POS-003–004; UC-06; OQ-19 |
| T-CW-015 | Cross-tenant adversarial access | Foreign IDs, filters, exports, nested routes, jobs and direct API requests disclose or mutate nothing | CG-01–CG-10; FR-RBAC-003, SEC-TEN-001, SEC-RBAC-001; US-RBAC-004; UC-14 |
| T-CW-016 | Ticket expiry, cancellation, and reprint | Expired/cancelled tickets cannot authorize check-in and every validation is logged; only an unused Issued ticket can be cancelled with reason; reprint preserves identifier, QR, validity, price, and state while appending audit evidence; invalid terminal transitions change nothing | CG-04; FR-TKT-003–004, FR-TKT-006–008; US-TKT-002–003; UC-04 |
| T-CW-017 | Notification delivery, retry, and terminal states | Provider acceptance is Sent, never Delivered without verified confirmation; retryable failures stop at the configured bound; permanent/not-sendable and stale/cancelled outcomes do not retry or send; duplicate/out-of-order callbacks cannot regress state or duplicate the core event | CG-09; FR-NOT-001–006, INT-NOT-001–003; US-NOT-001–003; UC-11 |
| T-CW-018 | Incident creation, lifecycle, and update | Creation records one scoped Open incident; authorized follow-up/state changes append actor/time history without replacing original facts; denied, cross-scope, invalid, or concurrent updates preserve prior state; approved search exposes the chronological lifecycle | CG-10; FR-SAF-003–005, FR-AUD-001–004; US-SAF-001–002, US-AUD-001; UC-12; OQ-20 |
| T-CW-019 | Session cancellation, approved adjustment, and terminal integrity | Active/Paused cancellation requires permission, reason, and configured approval; an open pause closes safely and alerts become stale; manual adjustment retains before/after values, requester/approver, reason, and recalculated effect; Completed/Cancelled reject ordinary mutation and corrections remain additive | CG-05; FR-SES-006, FR-SES-011, FR-TIM-007, FR-RBAC-005; US-SES-005–006; UC-05 |

These tests are release blockers for capabilities included in the approved pilot. Each must be automated except the explicit human factors within guardian verification and hardware. T-CW-014 proves topology-independent safety and recovery without assuming that payment posting, session completion, and receipt delivery are one database action; repeat it against the station/role topology selected when OQ-19 closes. T-CW-018 becomes a release blocker only if OQ-20 adds incidents to MVP; it then proves append-only and authorization invariants while exact transitions use the approved policy.

### MVP state coverage

| Aggregate | Baselined states/transitions | Required evidence |
| --- | --- | --- |
| Tenant | Pending, Active, Suspended | Provisioning/status feature tests, access revocation, retained history, T-CW-001/T-CW-015 |
| Branch | Inactive, Active | Activation validation, reasoned deactivation, new-work denial, retained history, T-CW-011 |
| Staff user | Invited, Active, Suspended | Invitation/credential activation, suspension/reactivation, session revocation, attribution retention |
| Session | Active, Paused, Completed, Cancelled | Check-in, pause/resume, completion, cancellation/adjustment, terminal guards, T-CW-003–005/T-CW-019 |
| Ticket | Issued, Consumed, Cancelled, Expired; reprint as event | T-CW-003/T-CW-016, scan history, terminal-state denial |
| Order/payment/refund/receipt | Order Draft/Paid/Voided/Refunded; Payment Posted/Voided; Refund Posted; Receipt Issued with refund annotation | T-CW-005/T-CW-008–010/T-CW-014, reconciliation and immutable-history checks |
| Notification | `queued`, `sending`, `sent`, `delivered`, `failed_retryable`, `failed_permanent`, `stale` (UI may label `sending` Processing and `stale` Cancelled/Stale) | T-CW-017 with bounded retries and verified callbacks |
| Incident | Open, UnderReview, Closed; exact transitions pending OQ-20 | T-CW-018 with append-only history and parameterized approved transitions |

## 6. Domain-specific test charters

### 6.1 Multi-tenancy and branch scope

For every tenant-bound resource, test:

- List, show, create, update, delete or cancel where applicable.
- Known foreign tenant ID, random ID, and foreign nested-parent ID.
- URL parameter, request body, header, export, search, autocomplete, report aggregate, queue payload, notification, cache key, and audit lookup.
- A user assigned to one branch trying to access another branch in the same tenant.
- Tenant owner all-branch access and an explicitly restricted branch manager.
- Soft-deleted, inactive, and historical records.
- Background jobs after a user's permission or tenant status changes.
- Aggregate counts that could leak the existence of foreign records even when rows are hidden.

A response should normally be indistinguishable from a missing resource where revealing existence would leak data. Log the denied attempt without logging child or payment secrets.

### 6.2 Authentication and authorization

- Successful login, invalid password, disabled user, inactive tenant, inactive branch, password reset, session expiry, and logout.
- Rate limiting does not reveal account existence.
- Policies cover view, create, update, pause, resume, cancel, checkout, adjust, discount, refund, approve, export, and manage staff.
- Every role in the Permission Matrix receives positive tests for allowed actions and negative tests for critical denied actions.
- Hiding a button is tested only as UX. Server policy denial is the security assertion.
- Role or branch-scope changes take effect for existing sessions according to the approved policy.
- Last tenant owner cannot remove or deactivate their own final owner access.

### 6.3 Money, tax, payment, and reconciliation

- Use integer minor units and an explicit ISO currency on every amount-bearing record.
- Test zero, smallest unit, exact boundary, just below/above discount thresholds, largest permitted amount, negative/refund path, and unsupported currency mismatch.
- Test inclusive and exclusive tax modes only after country rules are approved.
- Allocate rounding deterministically and assert line sum, tax, discount, paid, refunded, and net identities.
- Server recalculates checkout and POS totals; altered browser totals are ignored.
- Accepted quote inputs are snapshot or versioned so later pricing changes do not rewrite the completed sale.
- Payment submission accepts an idempotency key or equivalent durable uniqueness boundary.
- A timeout followed by retry returns the original durable result rather than charging again.
- Refund cannot exceed refundable balance and always links to the original payment/transaction.
- Reports reconcile from durable transactions, not UI events or mutable session estimates.

### 6.4 Time engine

Use a frozen clock and branch time zone. Cover:

- Exact plan end, one second before, one second after, and rounding boundary.
- Multiple pause/resume intervals, zero-length pause, open pause, overlapping pause attempt, and disallowed pause type.
- Extension before expiry, at expiry, and after overtime begins.
- Checkout across midnight, month/year end, leap day, and local daylight-saving transitions relevant to launch countries.
- UTC persistence and branch-local rendering.
- Staff browser clock wrong by minutes or hours. The server remains authoritative.
- Simultaneous actions with the same expected session version.
- Cancelled and completed sessions reject further mutation except a separately authorized adjustment.

The approved pricing rule examples become executable tests. Until the business selects pause, overtime, and rounding policy, tests should mark those decisions as unresolved rather than inventing financial behavior.

### 6.5 Concurrency and idempotency

Run real parallel requests against MySQL 8.4 with InnoDB for:

- Last capacity slot claimed by two check-ins.
- Same child checked in at two terminals.
- Same session paused/resumed or checked out by two users.
- Same sale/payment submitted twice by retry, double-click, or provider callback.
- Discount approval and checkout happening in different order.
- Pricing setting changed while a cashier holds a sale.
- Refund requested twice.

Assertions cover HTTP result, row count, state version, ledger totals, audit entries, jobs, and receipts. A passing sequential test is not evidence for concurrency.

### 6.6 Security, privacy, and consent

Use [OWASP ASVS 5.0.0](https://owasp.org/www-project-application-security-verification-standard/) as the verification catalog, selecting requirements appropriate to a sensitive multi-tenant business application.

Minimum charters:

- Authentication, session fixation, logout invalidation, password reset, brute-force controls, and secure cookie configuration.
- CSRF on browser writes and appropriate authentication on API routes.
- Broken object-level and function-level authorization.
- SQL injection, stored/reflected XSS, mass assignment, unsafe redirects, and malicious filenames/uploads.
- Validation of QR/barcode values as untrusted input.
- Rate limits on login, search, scans, exports, and write endpoints.
- Secret scanning, dependency vulnerability review, and production debug mode off.
- Sensitive data absent from URLs, logs, exceptions, analytics, and client-side state.
- Security headers, TLS, cache policy for authenticated pages, and least-privilege database credentials.
- Audit log append-only behavior for critical actions and protection against tenant crossing.
- Provider webhooks, if added, require signature, replay, timestamp, and idempotency tests.
- Operational-contact consent and optional marketing consent are separate, versioned, attributed, and never inferred from each other; no MVP marketing-send path is enabled.
- Child/guardian fields and search results expose only task-necessary data. OQ-07 policy drives retention, correction, subject access, deletion/anonymization exceptions, and legal holds; tests must not invent the policy.
- Optional child photos remain private, permission checked, type/size validated, and absent from logs, public URLs, and unauthorized exports.
- Super Admin support access is denied by default until OQ-14 defines a least-privilege, approved, audited path.

Run a focused independent penetration test before the first real customer pilot and after material authentication, tenancy, payment, or upload changes.

### 6.7 Accessibility

Target [WCAG 2.2 Level AA](https://www.w3.org/TR/WCAG22/).

Automated browser checks should catch semantic names, obvious contrast failures, duplicate IDs, invalid ARIA, and focusability. Manual checks remain required for:

- Logical focus order in English/LTR and Arabic/RTL.
- Visible focus that is not covered by sticky UI.
- Complete keyboard-only registration, check-in, checkout, POS, and report filtering.
- Error summary announcement and field association.
- Status communicated without color.
- 200 percent zoom and responsive reflow.
- Accessible authentication, no blocked paste, no memory puzzle.
- Minimum target size and no dragging-only action.
- Timer updates that do not flood assistive technology.
- Screen-reader labels for session status, money, tables, and validation.

Automated accessibility passing does not equal WCAG conformance. Log manual evidence against the exact success criteria.

### 6.8 Localization and bidirectionality

Test English/LTR and Arabic/RTL as first-class variants:

- Root language and direction attributes, mirrored shell and navigation, logical CSS properties.
- Mixed-direction phone, email, receipt, ticket, QR, and session references.
- Validation, status labels, receipt content, exported headings, and notifications.
- Long translations, plural forms, truncation, and narrow tablet layouts.
- Locale-aware dates, times, decimal/group separators, and configured ISO currency.
- Branch time zone rather than the server or browser default.
- Search normalization for Arabic letter variants only when product has approved the intended matching behavior.
- User-entered names remain unchanged; do not transliterate silently.

Use curated Arabic fixtures reviewed by a fluent speaker. Random generated Arabic text is useful for layout stress but not copy approval.

### 6.9 Performance and resilience

Agree the normal-load profile before claiming the PRD targets. Initial pilot profile should include:

- Number of tenants and branches.
- Concurrent reception/cashier users per branch.
- Active sessions per branch.
- Parents, children, transactions, and sessions retained.
- Report date ranges and export sizes.
- Background notification volume.

Measure server response time and browser task time separately. At minimum:

- Check-in, pause/resume, checkout quote, payment recording, and POS submit meet the 2 second standard-action target at the agreed normal load.
- Standard daily revenue, attendance, and session reports meet the 5 second target.
- No correctness loss at peak concurrency.
- Slow-query log and query plan reviewed for critical indexes.
- Queue backlog, retry count, failed jobs, database connections, CPU, memory, and error rate monitored.
- External notification slowness does not hold open checkout or payment transactions.
- Peak-hour tests keep check-in, checkout, payment recording, and guardian verification correct while reports/notifications are slow or queued.
- Synthetic health checks and platform metrics measure the 99.9 percent monthly core-availability target using an approved definition and exclusions; a test run alone is not an uptime claim.
- Stateless application nodes, shared durable queue/cache choices, and tenant/branch data growth are exercised at the approved scale without process-local correctness assumptions.
- Pilot staff task timing records check-in/checkout duration, step count, error/recovery rate, and training friction so the PRD's “decrease” success direction has a measured baseline.

Use a small production-like data set first. Add distributed load tooling only when one-machine load cannot reproduce the approved profile.

### 6.10 Backup, restore, and disaster recovery

Backups are not accepted until a restore succeeds.

1. Product approves recovery point objective (RPO) and recovery time objective (RTO).
2. Production database and required object storage are backed up automatically.
3. Backup encryption, retention, access, regional location, and deletion policy are documented.
4. Monitoring alerts on missed or failed backup.
5. A scheduled job restores the latest backup into an isolated environment.
6. Smoke checks verify migrations, tenant counts, referential integrity, recent transaction totals, sample receipt/session lookup, and object availability.
7. A human disaster drill validates credentials, DNS/traffic plan, queue behavior, communication, and measured recovery time.
8. Restore evidence includes backup timestamp, restore timestamp, software versions, checks, result, and owner.

Run an automated restore check at least monthly for MVP and a full disaster drill before pilot, then at an approved recurring interval.

## 7. Test environments

| Environment | Purpose | Data and integrations |
| --- | --- | --- |
| Local | Fast implementation and focused browser debugging | Seeded synthetic data; local MySQL 8.4/InnoDB; mail/notification fake; no production secrets |
| CI | Repeatable gates for every change | Ephemeral MySQL 8.4/InnoDB matching production; fixed clock and deterministic seed; external services faked |
| Staging | Release candidate, migration, integration, performance, a11y, and operations rehearsal | Production-like topology and anonymized/synthetic scale data; provider sandboxes; pilot hardware |
| Production | Smoke, monitoring, backup, and controlled operational verification | Real data; no destructive test suite; synthetic health tenant only if approved and clearly isolated |

Environment configuration is version controlled where it is not secret. Production secrets never enter fixtures, logs, screenshots, recordings, or CI artifacts.

## 8. Test data strategy

- Factories create tenants first, then branches and tenant-bound entities.
- Every feature test names the tenant and branch in the fixture so scope is visible.
- Maintain a compact deterministic scenario seed for product review: two tenants, multiple branches, every predefined role, active/paused/overtime/completed sessions, one approval, one refund, and reportable transactions.
- Use explicit money and time values for assertions, not random values.
- Use random/fuzz values only for robustness checks, preserving the failing seed.
- Keep synthetic Arabic and English names, phone formats, currencies, and time zones.
- Never copy production child, guardian, identity, or payment data into local or CI.
- If staging needs production-shaped volume, generate it or use an approved irreversible anonymization process.
- Browser tests own their records and do not depend on execution order.

## 9. CI and release gates

### Pull request gate

Required and fast:

1. Dependency lock-file integrity and install.
2. Laravel Pint formatting check.
3. Static analysis once configured for the code touched.
4. Unit and feature suites on PHP 8.5 and MySQL 8.4/InnoDB.
5. Migration from an empty database.
6. Frontend production build.
7. Critical tenant-isolation, policy, money, and time tests.
8. Deterministic ticket, notification-state, incident-lifecycle, and session-terminal feature suites, including T-CW-016 through T-CW-019.

Target feedback: under 10 minutes. Do not add parallel-test infrastructure until the suite exceeds the agreed target.

### Main branch gate

In addition to the pull request gate:

- Critical browser journeys in the primary supported Chromium browser.
- Arabic/RTL browser smoke.
- Migration from the latest released schema snapshot or a sanitized copy.
- API contract checks and generated specification consistency where applicable.
- Security dependency and secret scanning.

### Nightly or scheduled gate

- Full browser matrix chosen for the support policy.
- Concurrency suite against MySQL 8.4/InnoDB.
- Accessibility automation.
- Larger report and performance data.
- Queue retry and notification-provider sandbox checks.
- Backup restore smoke when environment and cost permit.

### Release-candidate gate

- Staging deployment and migration rehearsal.
- All T-CW critical workflows pass.
- T-CW-016 through T-CW-019 include retained feature/API evidence and the applicable role-based UI or operational rehearsal.
- No open release-blocking defects.
- Manual keyboard, Arabic/RTL, screen-reader smoke, zoom, scanner, and printer checks.
- Performance targets met against the approved load profile.
- Backup restore evidence current.
- Operations runbook, monitoring, rollback, and support ownership reviewed.
- Product owner and pilot operations representative sign off.

## 10. Defect severity and response

| Severity | Definition | Release treatment |
| --- | --- | --- |
| S0 Critical incident | Child safety breach, tenant data exposure, duplicate/incorrect charge at scale, unrecoverable corruption, or total production outage | Stop release or disable affected capability; incident response immediately |
| S1 Release blocker | Critical workflow unavailable, unauthorized critical action, wrong money/time result, failed migration, no restore path | Must be fixed and verified before release |
| S2 Major | Material workaround required, report materially wrong, common accessibility blocker, serious performance miss | Fix before pilot unless product, engineering, QA, and operations record a time-limited exception |
| S3 Minor | Localized visual/copy issue or uncommon inconvenience with safe workaround | May ship with owner and target date |

Flaky tests are defects. Quarantine is time-limited, assigned, and forbidden for safety, tenancy, money, authorization, or migration gates.

## 11. Exit criteria

### Feature-ready

- Acceptance criteria are testable and linked to requirement/story/use case IDs.
- Domain and feature tests pass.
- Authorization and tenant-negative paths pass.
- Any changed UI has keyboard, validation, loading, empty, error, and RTL review.
- No new S0, S1, or unowned S2 defect.

### MVP pilot-ready

- All critical workflow IDs T-CW-001 through T-CW-019 pass.
- Pricing, pause, rounding, overtime, guardian verification, payment, tax/receipt, capacity override, country/currency, and hardware decisions are approved and encoded in tests.
- No open S0 or S1. Any S2 exception is signed, time-limited, and has a safe workaround.
- Production-like performance meets the approved load profile.
- Independent security review or penetration test has no unresolved critical/high issue.
- WCAG 2.2 AA manual audit of critical workflows has no blocker.
- Migration, rollback decision path, backup restore, monitoring, alerts, and on-call/support runbook have current evidence.
- Pilot reception and cashier staff complete the standard visit flow and exception checkout during rehearsal.

### General availability

Add successful pilot evidence, incident review, support readiness, agreed uptime measurement, repeated restore results, and resolved pilot-critical feedback.

## 12. Traceability

Use stable identifiers in tests and work items:

    Capability: CG-06
    Requirement: FR-SES-010
    Business rule: BR-005
    User story: US-SES-010
    Use case: UC-06
    API operation: checkoutSession — POST /api/v1/sessions/{id}/checkout
    Wireframe: WF-07
    Test: T-CW-005

The traceability matrix is a release view, not duplicated prose. One automated test may cover several identifiers, and one critical rule will usually require several tests.

| Current baseline | Primary tests | Supporting evidence |
| --- | --- | --- |
| CG-01 — FR-TEN-001–009, SEC-TEN-001; US-TEN-001–004; UC-01, UC-13, UC-14 | T-CW-001, T-CW-011, T-CW-015 | WF-02/WF-12, tenant/branch scope negatives, status history, query review |
| CG-02 — FR-AUT-001–005, FR-RBAC-001–007; US-AUT-001–002, US-RBAC-001–004; UC-02, UC-14 | Role matrix tests and T-CW-006, T-CW-009, T-CW-015 | Policy coverage, session revocation, manual role review |
| CG-03 — FR-CUS-001–009; US-CUS-001–005; UC-03, UC-14 | T-CW-002, T-CW-015 | WF-04 browser, Arabic, privacy, and duplicate-policy review |
| CG-04 — FR-TKT-001–008, FR-TIM-001–002; US-TKT-001–003, US-TIM-001; UC-04, UC-05 | T-CW-003, T-CW-016 | WF-05/WF-13, ticket scan log, expiry boundary, cancellation/reprint audit, printer check |
| CG-05 — FR-SES-001–007, FR-SES-011–014, FR-TIM-003–009; US-SES-001–007, US-TIM-002; UC-04–UC-06 | T-CW-003–004, T-CW-007, T-CW-013–014, T-CW-019 | WF-05–WF-07, approved pricing fixtures, terminal-state evidence, MySQL 8.4/InnoDB concurrency run |
| CG-06 — FR-SES-008–011, FR-SES-014, FR-SAF-001–002; US-SES-008–010, US-SAF-003; UC-06, UC-07 | T-CW-005–006, T-CW-014 | Guardian-verification drill, override audit, handoff interruption/recovery evidence |
| CG-07 — FR-POS-001–013, DATA-FIN-001; US-POS-001–006; UC-06, UC-08, UC-09 | T-CW-005, T-CW-008–010, T-CW-014 | Reconciliation, durable idempotency replay, receipt/refund evidence |
| CG-08 — FR-RPT-001–008; US-RPT-001–004; UC-10 | T-CW-012, T-CW-015 | Performance, reconciliation, pagination, and export authorization |
| CG-09 — FR-NOT-001–007, INT-NOT-001–003; US-NOT-001–003; UC-11 | T-CW-017 | WF-14, queue attempt history, bounded retry, terminal-state and verified-callback evidence |
| CG-10 — FR-AUD-001–005, FR-SAF-003–006; US-AUD-001, US-SAF-001–003; UC-07, UC-12, UC-14 | T-CW-006, T-CW-010, T-CW-015, T-CW-018 | WF-15, append-only audit/incident history, scope/permission negatives, incident update rehearsal |
| NFR-ACC-001 and LOC-001–006 | Browser and manual charters | WCAG audit log, keyboard evidence, RTL screenshots |
| NFR-PERF-001–004, NFR-AVL-001–002, NFR-SCA-001–002, and NFR-DR-001–003 | Performance, availability, scale, and restore suites | Load report, uptime/error-budget evidence, provider degradation run, restore record |
| DATA-PII-001–002, SEC-PII-001–003, SEC-UPL-001, OQ-07, and OQ-14 | Privacy/consent/security charters | Field-masking, consent-version, upload, retention-policy, subject-rights, and support-access evidence |

## 13. Ownership and cadence

| Activity | Primary owner | Cadence |
| --- | --- | --- |
| Tests with each change | Developer | Every change |
| Risk and acceptance review | Product, QA, engineering | Refinement and milestone start |
| Exploratory and regression session | QA with developer | Weekly and before release |
| Operations workflow rehearsal | Product/operations with QA | Each pilot candidate |
| Security review | Engineering/security | Each release, deeper before pilot |
| Performance baseline | Engineering/QA | Each milestone and material data/query change |
| Restore verification | Operations/engineering | Monthly MVP minimum |
| Traceability and exit review | QA/product/engineering | Release candidate |

## 14. Official references

- [Laravel 13 testing](https://laravel.com/docs/13.x/testing)
- [Laravel 13 HTTP tests](https://laravel.com/docs/13.x/http-tests)
- [Laravel 13 database testing](https://laravel.com/docs/13.x/database-testing)
- [Laravel 13 authorization](https://laravel.com/docs/13.x/authorization)
- [Laravel 13 browser testing guidance](https://laravel.com/docs/13.x/dusk)
- [Pest browser testing](https://pestphp.com/docs/browser-testing)
- [OWASP ASVS 5.0.0](https://owasp.org/www-project-application-security-verification-standard/)
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- [MySQL Innovation and LTS release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html)
- [Which MySQL version to use](https://dev.mysql.com/doc/refman/8.4/en/which-version.html)

These sources were verified on 24 August 2026. Pin application dependencies in lock files and review support status before implementation and each major release.

## Approved MVP decision amendment — 2026-09-10

Add focused checks for: branch-scoped receipt numbering under concurrency and void immutability; QR/guardian-confirmation success, mismatch, missing-code, replay, and audited manager override; fixed package pricing, 10-minute grace boundary, 30-minute overtime rounding, integer-piastre arithmetic, tax snapshotting, and rejection of pause commands in MVP.
