# Wave 02 — Families, Privacy, Pricing and Tickets

**Verification date:** 2026-09-18  
**Final wave status:** `CLOSED_WITH_BLOCKERS`  
**Meaning:** every safely implementable approved Wave 2 behavior is complete and locally verified. Destructive family anonymization/deletion and any formal DSAR workflow remain disabled until the approved Legal/DPO gate supplies the final policy.

## Scope

- 08 — Guardians, Families & Children
- 09 — Consent, Privacy, Retention & Data Rights
- 10 — Operational Pricing Rules
- 11 — Tickets & QR
- Direct supporting rows from Audit, Localization, UI/UX, Accessibility, Errors/Concurrency, Data Model/Integrity, Security and QA

OQ-15, OQ-17 and OQ-18 were treated as approved contracts. Wave 1 and Actor Dashboards were regression-only.

## HTML Tasks Covered

119 exact rows from `.ai/checkpoints/playnexus_master_checkpoints_v3_explained (2).html`: all 66 rows in sections 08–11 and 53 directly relevant supporting rows. Counts reconcile to 110 `منفذ`, 6 `جزئي`, 2 `محجوب بقرار`, 1 `مؤجل`. The authoritative HTML now embeds revision `2026-09-18-families-privacy-pricing-tickets-v1`; it applies once per browser profile without overwriting unrelated rows.

## Initial State

Families, operational pricing and ticketing already had substantial backend, UI and test coverage. The main current-scope defects were missing consent renewal/evidence UI, no approved retention eligibility/hold administration, unbounded ticket history, a mobile pricing overflow, and a MySQL-incompatible generated SaaS billing index name that prevented any clean MySQL migration.

## Defects Found

### Families

- Withdrawn child-data consent could not be renewed through an approved verified-guardian flow.
- Consent evidence was not sufficiently visible on the family profile.
- Repeated marketing withdrawal could append duplicate no-op events.

### Privacy

- No three-year dry-run eligibility calculation or owner-facing hold/release administration existed.
- No DB-enforced tenant/guardian ownership for retention holds existed.

### Pricing

- The pricing screen overflowed horizontally at 390px.

### Tickets / QR

- Ticket history loaded an unbounded collection and had no pagination control.

### Security

- Consent renewal needed a locked, verified, active legal-guardian relationship and explicit current notice acceptance.
- Retention administration needed owner-only authorization and concealed foreign-object handling.

### Data

- The new hold aggregate required composite tenant ownership, indexes and restrictive history-preserving deletes.
- `subscription_billing_records` used an automatically generated index name longer than MySQL's 64-character limit; this blocked the whole schema on MySQL.

### UI / UX

- The family screen lacked clear consent status/evidence and a controlled renewal state.
- There was no operational privacy/retention screen.

### Accessibility

- New consent/hold controls required explicit labels, alert-linked errors, non-color status text and responsive EN/AR behavior.

### Audit

- New consent and retention actions needed request correlation and privacy-safe payloads.

## Changes Made

- Added `family_retention_holds`, `FamilyRetentionHold`, `FamilyRetention`, and owner-only `FamilyPrivacyController` with dry-run, hold and release paths.
- Added append-only consent renewal, repeat-withdrawal idempotency, verified-guardian checks, explicit notice acceptance and request-correlated audit events.
- Added bilingual family consent evidence and privacy/retention UI plus owner navigation.
- Paginated ticket history at 50 rows while preserving scan grouping and query parameters.
- Removed the 390px pricing overflow.
- Added the explicit MySQL-safe `saas_billing_tenant_subscription_period_idx`; no OQ-04 commercial behavior changed.
- Added focused retention/consent tests, isolated browser fixtures and an Edge CDP Wave 2 journey.

## Guardians / Families Verification

Tenant-scoped normalized-phone search/reuse, DB uniqueness, guarded create/edit, DOB-derived age, relationships, final-guardian protection, emergency/safety data, visit history, masking and Reception UX are covered by the 116-test focused suite and actual browser journey. Equivalent local/+20 input produced one canonical Guardian.

## Children / Relationships Verification

Relationship creation/revocation locks the child aggregate, enforces same-tenant composite ownership and prevents removal of the final active consent/checkout-capable Guardian. Only verified mother/father/legal-guardian relationships with `can_consent` can renew child-data consent.

## Consent / Privacy Verification

Consent is explicit, versioned and append-only. Withdrawal restricts child use; approved renewal restores it without replacing history. Retention uses the approved three-year eligibility baseline, supports active holds and a dry-run report, and never mutates family data. Destructive execution stays disabled by DEC-RET-01 until Legal/DPO approval; formal DSAR workflow is likewise not invented.

## Pricing Verification

The existing immutable pricing version architecture remains authoritative: integer minor units, fixed duration, 600-second grace, 1,800-second ceiling overtime units, inclusive/exclusive half-up tax, immutable historical snapshots and manager-scoped UI. Browser validation covered invalid and valid creation and denied Reception mutation.

## Tickets / QR Verification

Issuance snapshots tenant/branch/service date/price/currency. QR values are opaque `pnx_...` tokens with no PII. Validation rejects wrong branch/date/state. First scan, consumption and Session creation are transactional; real MySQL contention proves one winning scan. Unused transfer/cancellation/refund eligibility and post-scan lock follow OQ-18.

## Reception Dashboard Integration

The implemented Reception dashboard links to the family flow. Edge exercised Dashboard → family search → create/reuse Guardian → child/profile → consent withdrawal/renewal → ticket issue → QR/scan without using internal IDs.

## Browser Verification

`node tools/browser-wave02.mjs` passed 48 checks and produced 31 screenshots. Pages exercised: Reception dashboard; family search/create/profile/edit; consent withdrawal/renewal; owner privacy dry-run/hold/release; manager pricing invalid/valid submissions; Ticket issue/list/detail/QR/scan; and foreign/unauthorized denials. EN/LTR and AR/RTL were checked at 390, 820 and 1440px. Result: no root horizontal overflow, console errors or HTTP 5xx. Evidence: `deliverables/qa/wave02/browser-results.json`.

`node tools/verify-master-checklist-wave02.mjs` opened the authoritative HTML in an isolated Edge context and verified all 653 DOM rows, 119 exact Wave 2 notes/statuses with zero mismatches, and the combined Wave 1 + Wave 2 state: 227 reviewed, 201 implemented, 20 partial, 5 blocked, 1 deferred and 426 not reviewed.

## Security / Negative Tests

The suite and browser journey cover foreign Guardian/Child/Ticket concealment, relationship/hold tenant mismatch, wrong-branch scans, posted-price tampering, role denial, opaque token tampering, invalid state transitions, repeated scans and unauthorized privacy/pricing actions. Cashier receives no family administration or sensitive-note authority.

## Concurrency / Data Integrity

- SQLite focused: 116 tests, 114 passed, 2 MySQL-only skipped, 1,046 assertions.
- MySQL 8.4.11 clean migration: PASS after shortening the invalid index name.
- MySQL selected family/privacy integrity: 3 tests / 29 assertions PASS.
- MySQL real Ticket concurrency: 2 tests / 47 assertions PASS.
- Composite FKs, unique phone/token/relationship constraints, row locks, immutable snapshots and restricted deletes preserve history.

## Automated Tests

| Command | Result |
|---|---|
| `php artisan test tests/Feature/EgyptFamilyContractTest.php tests/Feature/FamilyPrivacyRetentionTest.php` | PASS — 13 tests / 89 assertions |
| focused 17-class Family/Pricing/Ticket command | PASS — 116 total / 114 passed / 2 skipped / 1,046 assertions |
| selected closed-module regression command | PASS — 174 total / 173 passed / 1 skipped / 1,549 assertions |
| `php artisan test` | PASS — 489 total / 485 passed / 4 skipped / 3,977 assertions |
| MySQL `php artisan migrate:fresh --force` | PASS — all migrations on MySQL 8.4.11 |
| MySQL selected family/privacy tests | PASS — 3 tests / 29 assertions |
| MySQL `php artisan test tests/Feature/TicketConcurrencyTest.php` | PASS — 2 tests / 47 assertions |
| `vendor/bin/pint --test` | PASS |
| `npm run build` | PASS — 31 modules; optional Fontaine notice only |
| `php artisan view:cache` | PASS |
| `npm audit --audit-level=high` | PASS — 0 vulnerabilities |
| `composer audit --no-interaction` | NOT RUN — Composer command unavailable on this shell's PATH |
| `python tools/validate_documentation.py` | PASS — 42 Markdown files, 0 errors, 2 known placeholder warnings |
| `git diff --check` | PASS — line-ending warnings only |

## Regression

Platform Administration, Tenant/Branch/Auth/RBAC, support access, Actor Dashboards, Check-in/Sessions, POS, Receipts, Reports and Refunds passed the 174-test targeted regression and the 489-test full suite.

## Master HTML Task Mapping

Evidence shorthand: **F** focused 116-test Wave suite; **B** Edge browser 48 checks/31 screenshots; **M** MySQL migration/concurrency; **R** 174-test closed-module regression; **A** full 489-test suite.

| Section | Exact Task | Status | Evidence | Remaining Gap |
|---|---|---|---|---|
| 08 | Search guardian by normalized phone. | منفذ | F, B | None |
| 08 | Search child by Arabic/English name. | منفذ | F, B | None |
| 08 | Tenant-unique normalized guardian phone. | منفذ | F, M | None |
| 08 | Reuse existing family عند phone match. | منفذ | F, B | None |
| 08 | Prevent concurrent duplicate guardian creation. | منفذ | DB unique + F/M | None |
| 08 | Create/update guardian. | منفذ | F, B | None |
| 08 | Create/update child. | منفذ | F, B | None |
| 08 | Many-to-many guardian-child relationships. | منفذ | F | None |
| 08 | Relationship types والactive status. | منفذ | F | None |
| 08 | Prevent removal of final active legal/checkout-capable guardian. | منفذ | F | None |
| 08 | Emergency contact. | منفذ | F, B | None |
| 08 | Optional DOB/age representation حسب القرار. | منفذ | F; OQ-15 | None |
| 08 | Restricted safety notes. | منفذ | F, B | None |
| 08 | Visit/session history. | منفذ | F, B | None |
| 08 | Cross-tenant search returns no information. | منفذ | F, B 404 | None |
| 08 | Family page UX سريع للReception. | منفذ | B, screenshots | None |
| 08 | Mask PII حسب permission. | منفذ | F | None |
| 09 | Arabic-first privacy notice. | منفذ | F, B AR | Legal approval remains a release gate |
| 09 | Legal guardian consent for child data. | منفذ | F | None |
| 09 | Marketing consent منفصل واختياري. | منفذ | F | None |
| 09 | Consent choices غير preselected. | منفذ | F, B | None |
| 09 | Append-only consent grant/withdrawal history. | منفذ | F | None |
| 09 | Notice version + actor + timestamp + channel. | منفذ | F, UI evidence | None |
| 09 | Data minimization حسب role/use case. | منفذ | F, B | None |
| 09 | PII masking. | منفذ | F | None |
| 09 | Retention eligibility calculation. | منفذ | New service + F/B | None |
| 09 | 3-year baseline أو القرار النهائي المعتمد. | منفذ | DEC-RET-01 + F | Final deployed schedule approval is a gate |
| 09 | Legal / financial / safety / complaint / litigation holds. | منفذ | Hold categories + F/B | Final Legal/DPO policy approval gate |
| 09 | Legal-hold create/release workflow. | منفذ | Owner UI + F/B | None |
| 09 | Dry-run retention report. | منفذ | Owner UI + F/B | None |
| 09 | Anonymization/deletion job idempotent. | محجوب بقرار | Destructive action deliberately absent | DEC-RET-01 requires Legal/DPO approval |
| 09 | Financial/audit facts لا تمحى خطأ. | منفذ | No destructive path; restricted FKs | None |
| 09 | Data access/correction/deletion request workflow لو مطلوب قانونيًا. | محجوب بقرار | Correction exists; no invented DSAR workflow | Legal/DPO must approve whether/how required |
| 09 | Privacy/admin UI مع audit كامل. | منفذ | New owner UI + F/B | None |
| 10 | Create pricing rule. | منفذ | F, B | None |
| 10 | Fixed duration/base price. | منفذ | F | None |
| 10 | Grace period. | منفذ | F; 600-second boundaries | None |
| 10 | Overtime unit/rounding. | منفذ | F; 1,800-second ceiling | None |
| 10 | Extensions and extension price. | منفذ | F/A | None |
| 10 | Tax inclusive/exclusive. | منفذ | F | Finance validation is a release gate |
| 10 | Effective date/status. | منفذ | Version status contract + F | None |
| 10 | Version/snapshot rule عند التغيير. | منفذ | F | None |
| 10 | Old sessions لا تتأثر بتغيير rule. | منفذ | F | None |
| 10 | Minor units/decimal-safe money calculations. | منفذ | F | None |
| 10 | Explainable calculation breakdown. | منفذ | F/B | None |
| 10 | Worked fixtures للحدود الزمنية والضرائب. | منفذ | F | None |
| 10 | Pricing UI مفهوم للBranch Manager. | منفذ | B at 3 widths/2 locales | None |
| 10 | Pause behavior غير معروض إذا غير معتمد. | منفذ | OQ-16 UI/code inspection | Pause remains deferred by decision |
| 11 | Issue branch/service-date scoped ticket. | منفذ | F, B | None |
| 11 | Non-guessable QR payload. | منفذ | F/B opaque token | None |
| 11 | QR لا يحتوي PII حساسة. | منفذ | F/B payload inspection | None |
| 11 | Display/print QR. | منفذ | F/B | Pilot device approval gate |
| 11 | Keyboard scanner flow. | منفذ | F/B | Pilot device approval gate |
| 11 | Camera scan لو مدعوم. | مؤجل | OQ-02 approved keyboard baseline | Browser camera is not V1 scope |
| 11 | Validate issued ticket. | منفذ | F/B | None |
| 11 | Wrong branch/service date rejected. | منفذ | F/B | None |
| 11 | Expired/cancelled/consumed rejected. | منفذ | F | None |
| 11 | First successful scan locks holder assignment. | منفذ | F/M | None |
| 11 | Atomic consume + check-in. | منفذ | F/M | None |
| 11 | Concurrent scans produce one success. | منفذ | M: 2 tests/47 assertions | None |
| 11 | Cancel unused ticket with reason. | منفذ | F | None |
| 11 | Reprint same identity/QR. | منفذ | F/B | None |
| 11 | Effective expiry حتى لو batch لم يغير status. | منفذ | F | None |
| 11 | Ticket refund eligibility. | منفذ | F/A; OQ-18/OQ-09 | Settlement remains refund-module controlled |
| 11 | Ticket history/audit. | منفذ | F/B; paginated UI | None |
| 11 | Ticket UI سريع للfront desk. | منفذ | B, screenshots | None |
| 28 | Family/consent/safety events. | منفذ | F, correlated safe audit | None |
| 28 | Ticket events. | منفذ | F/M | None |
| 28 | Correlation ID across multi-step transaction. | منفذ | Request-correlated consent/retention + existing ticket path | None |
| 32 | Arabic translations complete for critical flows. | منفذ | B AR | Formal linguistic approval gate |
| 32 | RTL layout. | منفذ | B AR at 390/820/1440 | None |
| 32 | Phone normalization. | منفذ | F/B | None |
| 32 | Locale switch never changes numeric financial result. | منفذ | F/R | None |
| 33 | Conflict/stale-data states. | منفذ | F | None |
| 33 | Confirmation for destructive/sensitive actions. | منفذ | B; explicit hold/release/consent copy | None |
| 33 | Preserve safe form data after validation errors. | منفذ | F/B | None |
| 33 | Tables pagination/sorting/responsive behavior. | منفذ | Ticket pagination + B | Sorting is not required for these bounded lists |
| 33 | Responsive desktop/tablet/mobile as applicable. | منفذ | B at 390/820/1440 | None |
| 33 | Reception flow minimizes clicks. | منفذ | Dashboard-to-ticket B journey | None |
| 33 | Visual hierarchy prioritizes child safety and money actions. | منفذ | B screenshots | None |
| 34 | Labels/accessible names. | منفذ | Blade review + B | Formal audit gate |
| 34 | Non-color-only state communication. | منفذ | Text badges/warnings + B | None |
| 34 | Form errors linked to fields. | منفذ | Blade/test review | None |
| 34 | RTL accessibility validation. | منفذ | B RTL + semantic review | Formal screen-reader/scan gate |
| 35 | Validation failure creates no partial business records. | منفذ | F/A | None |
| 35 | Cross-scope errors leak no protected existence. | منفذ | F/B 404 | None |
| 35 | DB transactions/locks where required. | منفذ | F/M | None |
| 35 | Idempotency uniqueness constraints. | منفذ | F/M | None |
| 35 | Concurrent ticket scan. | منفذ | M | None |
| 36 | Every tenant-owned aggregate has unambiguous tenant scope. | منفذ | Composite ownership + F/M | None |
| 36 | Non-guessable public identifiers. | منفذ | QR token tests | None |
| 36 | UTC timestamps + timezone context. | منفذ | F | None |
| 36 | Exact monetary representation. | منفذ | F | None |
| 36 | Pricing/tax/receipt version snapshots. | منفذ | F/R | None |
| 36 | Audit append-only. | منفذ | A | None |
| 36 | Guardian-child history preserved. | منفذ | F + restrictive hold FKs | None |
| 36 | Foreign keys/unique constraints match business invariants. | منفذ | F/M | None |
| 36 | Migrations tested on clean and existing DB. | جزئي | Clean SQLite/MySQL and repeated test migrations pass | No production-like existing-data rehearsal |
| 36 | Production MySQL behavior tested, not SQLite only. | منفذ | MySQL 8.4.11 migration + family + scan contention | Staging rehearsal remains production gate |
| 41 | Tenant isolation tests: query/object/cache/queue/file/report/search. | جزئي | Wave 2 query/object/search isolation passes | Global cache/queue/file/report matrix is later security work |
| 41 | Branch isolation tests. | منفذ | Ticket branch negatives F/B | None |
| 41 | Privilege-escalation tests. | منفذ | F/B role negatives | None |
| 41 | Input validation/injection/XSS/path traversal/mass assignment. | جزئي | Wave 2 validation/scope/mass assignment covered | Full SAST/DAST input matrix pending |
| 41 | Dependency scanning. | جزئي | npm audit: 0 | Composer executable unavailable in this shell |
| 41 | DAST/manual critical-flow review. | جزئي | Real Edge critical-flow review complete | Formal DAST remains production gate |
| 41 | No unresolved Critical/High unless formally accepted. | جزئي | No observed runtime/npm Critical/High | Complete security-tool evidence pending |
| 42 | Automated unit tests for calculations/state guards/normalization. | منفذ | F | None |
| 42 | Integration tests for DB constraints/scoping/permissions/transactions. | منفذ | F/M | None |
| 42 | Feature/API tests. | منفذ | F/A | None |
| 42 | Critical browser journeys by role. | منفذ | B | None |
| 42 | Arabic browser pass. | منفذ | B | None |
| 42 | English browser pass. | منفذ | B | None |
| 42 | Desktop/tablet viewport pass. | منفذ | B 390/820/1440 | None |
| 42 | Repeat-submit scenarios. | منفذ | Consent no-op, QR repeat and existing create idempotency | None |
| 42 | Concurrency scenarios. | منفذ | M/F | None |
| 42 | Failure injection where material. | منفذ | Atomic ticket/session rollback tests | None |
| 42 | Security negative tests. | منفذ | F/B | None |
| 42 | Regression suite after every gap fix. | منفذ | R/A | None |
| 42 | Test evidence linked to requirements. | منفذ | This file | None |

## Status Counts

| Status | Count |
|---|---:|
| Total tasks reviewed | 119 |
| منفذ | 110 |
| جزئي | 6 |
| ناقص | 0 |
| لا يعمل | 0 |
| يحتاج UI | 0 |
| محجوب بقرار | 2 |
| مؤجل | 1 |
| Production Ready | 0 |

## Remaining Genuine Decision Blockers

- DEC-RET-01: destructive anonymization/deletion stays disabled until Legal/DPO approves the final schedule, exceptions and execution authority.
- The formal data access/deletion request workflow must not be invented before Legal/DPO confirms the jurisdictional/operator procedure.

## Remaining Production Gates

- Legal/DPO approval of notice wording, controller contacts, final retention/hold/DSAR procedure.
- Finance approval of production tax/pricing configuration.
- Pilot scanner/printer device matrix and operator UAT.
- Production-like existing-data migration rehearsal, staging/UAT, formal accessibility and security acceptance, load/resilience and go/no-go.
- Composer dependency audit must be rerun from an environment with Composer on `PATH`.

## Final Wave Status

`CLOSED_WITH_BLOCKERS`
