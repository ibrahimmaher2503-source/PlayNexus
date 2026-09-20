# PlayNexus Agent Plan

## 2026-09-15 available-gap closure

- MYSQL PASS: isolated 8.4.11/InnoDB, 410/410 tests / 3,411 assertions, strict mode and `ONLY_FULL_GROUP_BY`.

- DONE: GAP-01–06, GAP-09, and DOC-01–09 in the shared integration worktree.
- PASS: final SQLite 410 total / 406 passed / 4 MySQL-only skips / 3,313 assertions; grouped authenticated EN/AR Manager/report browser acceptance; build/Blade/docs/routes/diff gates.
- PRODUCT BASELINES APPROVED, RELEASE INPUTS REMAIN: GAP-07/OQ-14 support and GAP-08 retention have approved engineering baselines; Security/Legal/DPO validation remains. OQ-05/OQ-13 channel and retry behavior is approved while DEC-NOT-01–05 remain open. OQ-21 targets and the OQ-22 baseline are approved while workload evidence and DEC-SEC-01–03 remain. Recurring billing remains deferred.
- Preserve the dirty worktree. No commit/push. Do not implement a blocked item by assumption.

## 2026-09-15 M6 bounded execution plan — LOCALLY_ENGINEERING_ACCEPTED

M6 starts from the locally engineering-accepted M5 baseline recorded immediately below. Preserve all current uncommitted M0-M5 work and change an accepted flow only for a reproducible regression with the smallest shared root-cause fix.

1. **Contract and graph:** refresh CodeGraph; trace report, notification, audit, queue, scheduler and authorization paths; apply approved OQ-05/OQ-13/OQ-21/OQ-22 baselines without inventing the still-open DEC-NOT/DEC-SEC inputs.
2. **Core reports:** add one scoped report surface for revenue reconciliation, attendance, session facts and staff audit activity. Reuse committed financial/session/audit tables, branch-local half-open UTC date ranges, allowlisted server sorting, bounded pagination and filter-identical CSV exports.
3. **Durable notifications:** add database-backed notification intent/attempt records and one idempotent queued delivery job using a deterministic local transport. Provider credentials, real messages and provider-specific callbacks remain blocked by OQ-05/OQ-13.
4. **Audit completion:** extend the existing authorized audit query with allowlisted sorting, bounded pagination and filter-identical CSV while retaining compact masked snapshots and append-only storage. OQ-20 incident management stays absent.
5. **Hardening and pilot:** extend synthetic local-only pilot data; add focused isolation, reconciliation, date-boundary, export, notification, masking, append-only and bounded-query checks; add migration/rollback, isolated backup/restore, performance, monitoring, staff walkthrough and support runbooks.
6. **Acceptance:** run focused checks, then full SQLite on PHP 8.4/8.5, isolated MySQL 8.4/InnoDB and relevant concurrency, Pint, Vite, Blade cache, routes, documentation validation, whitespace, secret/dependency checks, migration rollback/restore rehearsal, and authenticated English LTR/Arabic RTL desktop browser journeys with browser warning/error inspection.

All local engineering gates above now have current evidence in `.ai/TEST_RESULTS.md`: focused/full PHP 8.4/8.5 SQLite, full isolated MySQL 8.4.11/InnoDB, real concurrency, rollback/reapply, isolated restore, 1,000-row performance, dependency/secret/static/build/docs and authenticated bilingual multi-role browser checks pass. Status is `LOCALLY_ENGINEERING_ACCEPTED`. `PILOT_READY` still requires a selected staging environment, deployed monitoring, Finance/Legal approval, staff rehearsal/sign-off and named go/no-go approval.

## 2026-09-15 M5 final code review — locally engineering-closed

CodeGraph was refreshed for the financial actions, controllers and focused tests, then the Laravel request/policy/transaction paths were reviewed manually. The review closed three remaining local defects: cash payments now fail closed when the branch has not enabled cash; successful idempotent settlement replay is evaluated before current payment-method configuration; and the complete discount request/review/approved-payment workflow is visible only to the correct roles without exposing the request form to Manager/Owner or approval history to Reception.

Focused M5 regression passes 55 tests / 368 assertions. Full process-local SQLite passes on PHP 8.4.21 and PHP 8.5.8: 383 total / 379 passed / 4 explicit MySQL-only skips / 3,131 assertions. Full official isolated MySQL 8.4.11/InnoDB passes 383 / 3,229, including `M5FinancialConcurrencyTest` at 1 / 29 with two independent PHP processes proving one settlement/payment/receipt/completion and one refund execution/audit under duplicate contention. Global Pint, Vite, Blade cache, route inspection, documentation validation and `git diff --check` pass. Authenticated Arabic RTL desktop review confirms the Owner sees the approval queue but not the Cashier request form; browser warnings/errors are empty. M5 is `LOCALLY_ENGINEERING_ACCEPTED`; production Finance/Legal, hosted CI and operational release controls remain separate.

## Historical 2026-09-15 M5 SQLite/browser checkpoint — superseded above

Coordinator integrated and personally reviewed all three Luna/xhigh deliveries. Four cross-slice defects were corrected centrally: the approved Owner/Manager/Cashier transaction permission matrix, tenant legal/display seller snapshot, explicit ordinary receipt-sequence row update, and branch-currency hydration for transaction refund eligibility. Focused finance suites and the final full SQLite regression are green (379 total / 376 passed / 3 environment skips / 3,109 assertions); Pint, Vite, routes, docs and whitespace pass.

Authenticated desktop QA used an isolated SQLite fixture and completed catalog → quote → draft → exact cash payment → immutable QR receipt → transaction-history review in English and Arabic RTL. Receipt `PN-ALPHA-2026-000002` proved sequence advancement and displayed the tenant legal seller identity; history and receipt agreed on refund eligibility; browser logs contained no errors. No shared database or `.env` was changed. M5 remains `MYSQL_8_4_GATE_PENDING`, because the only available listener is MariaDB 10.4.32 and no Docker/MySQL 8.4 runtime exists. Do not start M6 or claim production readiness from this local result.

## Historical 2026-09-15 M5 remediation in progress — superseded above

Owner requested implementation of all confirmed review findings. Three Luna/xhigh workers own separate slices: POS catalog/XSS/selected-branch/tenant-wide SKU; persisted ordinary POS orders and atomic discount/payment/ticket issuance; settlement permissions, immutable receipt display/print and full-refund integrity. Coordinator owns shared route/schema integration, canonical contract reconciliation and final acceptance. Preserve all pre-existing dirty work; no main merge or push.

The permission matrix remains authoritative: active Tenant Owner and assigned Branch Manager/Cashier may record payment and request/execute an approved refund. Approval is separate and cannot be self-issued. Discount binding must be derived from persisted server-priced lines; empty-line fixture compatibility is not a production contract. Payment consumes approval atomically and line totals must reconcile to the order. Tenant-wide products are available to assigned branches, but management of global products remains Owner-only. Receipt seller is the snapshotted tenant legal/display name, distinct from cashier; refund status is an annotation over immutable issued commercial facts.

Closure requires central review of actual files, focused and full regression, schema negatives, lost-response replay, real MySQL concurrency and authenticated bilingual desktop/print evidence. Worker-reported green checks alone are not acceptance. Usage-limit interruptions left partial files and are not delivery evidence.

## 2026-09-14 M5 implementation plan — DECISIONS APPROVED; IMPLEMENTATION STARTED

### Outcome and governing contract

M5 closes the staff-assisted visit financially: a Cashier receives the existing `pending_payment` handoff, records exactly one in-person cash payment for the frozen EGP amount, allocates one immutable branch/year receipt number, completes the verified session, and can reopen/print the same receipt. It also adds the smallest reusable product catalog and draft cart needed for an ordinary non-session POS sale. All money is integer minor units, all time is server UTC rendered in `Africa/Cairo`, and every tenant/branch relationship is enforced in queries, policies, composite foreign keys, tests, and audit.

The later approved OQ-19 decision in `.ai/DECISIONS.md` governs the implementation: the matching cash-payment command commits order, payment, receipt facts, and session completion in one database transaction. A lost HTTP response is recovered by replaying the same idempotency key and returning the original result, never by posting another payment. Provider delivery is not part of that transaction.

### Approved implementation decisions

1. **OQ-09 — refund policy:** one full cash refund only, at the original branch, during the same branch-local business date, with a non-empty reason and a separate Branch Manager/Tenant Owner approval. Cashier may execute but not approve. Ticket-linked refunds additionally require the approved unused/no-scan/no-session OQ-18 eligibility. No partial refund, store credit, exchange, gateway call, or destructive edit.
2. **OQ-24 — cashier shifts:** shift open/close, cash drawer and variance handling are deferred. M5 records cashier, branch and server timestamp on each payment; M6 can reconcile by branch-local date without inventing a drawer workflow.
3. **Discount baseline:** the branch threshold starts at `0` basis points. Every positive discount needs a current, payload-bound, single-use Manager/Owner approval and reason. Discounts are fixed minor-unit amounts, cannot reduce the total to zero, and never change the stored unit price.
4. **Zero-total behavior:** zero-total checkout is unavailable in the first pilot.

### Explicit non-goals

- Online gateway/card capture, raw card data, split tender, partial payment, partial refund, deposits, credit/store balance, FX or multi-currency orders.
- Cashier shifts/drawer balancing unless OQ-24 is explicitly approved.
- Inventory depletion, procurement, recipes, product options, memberships, loyalty, parent portal, marketplace, accounting integration, PDF exports, or advanced reports.
- Receipt email/SMS/WhatsApp delivery and provider callbacks; M5 supports stable browser display/print only. Provider work remains M6 behind OQ-05/OQ-13.
- Reopening completed sessions, editing posted commercial facts, or treating session cancellation/ticket cancellation as a financial refund.

### Work packages and order

#### M5-W0 — contract and documentation freeze

- Reconcile stale pre-OQ-19 text in architecture, ERD, API, traceability, milestone and test documents before migrations.
- Freeze the cash-only state model: `draft → paid` and optional later `paid → refunded`; one payment per order; one receipt identity; one order per session.
- Add receipt snapshot fields to the canonical ERD/API because current seller/branch/cashier names cannot reproduce historical receipt content after profile changes.
- Confirm the four decisions above and record approvals in `.ai/DECISIONS.md`. Do not implement the blocked refund/shift/discount behavior by assumption.
- Exit: documentation validator passes and no canonical file describes M4 as unstarted or OQ-19 as open.

#### M5-W1 — immutable financial foundation

- Add forward migrations for `products`, `orders`, `order_items`, `payments`, and `branch_sequences`; add `approval_records` only when the discount decision is approved, and `refunds` only after OQ-09.
- Add `discount_approval_bps` to branch settings with default zero when the discount contract is approved.
- Store immutable order-line and receipt snapshots, `receipt_issued_at`, payment request fingerprint/idempotency key, server actor/timestamps, `lock_version`, currency and tenant/branch IDs.
- Enforce tenant-aware composite foreign keys, unique `(tenant_id, session_id)`, unique one-payment-per-order, unique idempotency key, and unique branch/year receipt allocation through the locked `branch_sequences` row.
- Use existing Laravel models, policies, requests/actions and DB transactions; introduce no payment SDK or new dependency.
- Exit: fresh/upgrade/rollback-or-forward-recovery migration checks pass on SQLite and isolated MySQL 8.4/InnoDB; cross-tenant inserts and duplicate financial identities fail.

#### M5-W2 — critical pending-payment settlement vertical slice

- Add a Cashier-focused queue within `/app/pos` fed only by accessible active branches and `pending_payment` sessions.
- Show child/guardian masked context, branch, frozen subtotal/tax/total, verification method, preparer/time and one unambiguous action: confirm receipt of the exact cash amount and complete the session.
- Under one transaction: refetch active tenant/actor/branch; lock session and any existing linked order/payment/sequence; revalidate `pending_payment`, frozen snapshot, amount/currency, guardian verification/override and submitted expected version; create the single session order/line; post one exact cash payment; allocate the receipt number; persist the immutable receipt snapshot; set `ended_at`, final elapsed/billable facts and `completed`; append session event and audit rows.
- Identical replay returns the original paid/completed receipt. Same key with changed payload, stale version, wrong role/branch, altered amount, missing verification or terminal state changes nothing and returns the safe conflict/denial contract.
- Receipt printing uses the existing browser-print approach, Arabic RTL/English LTR, a single clean page, and the same stored facts/number every time.
- Exit: T-CW-005 and T-CW-014 pass, including real two-process MySQL concurrency and retry after a simulated lost response.

#### M5-W3 — reusable catalog and ordinary POS cart

- Product management: Owner and assigned Branch Manager create/retire branch or tenant catalog items; Cashier/Reception can only view active items in scope. Posted snapshots never change when a product changes.
- POS desktop surface: 1440px two-column layout with searchable/type-filtered catalog on one side and a compact sticky cart/totals/payment panel on the other; keyboard and pointer alternatives; no tablet-sized buttons stretched across tables.
- Draft cart accepts product/ticket-type identifiers and quantity only. Server resolves current sellability, price, tax and currency, then rebuilds all line/order totals. Manual lines stay absent unless their documented permission is explicitly included.
- Cash payment reuses the same settlement/receipt action without a session completion step. Empty cart, inactive product, wrong branch/currency, client price/tax and stale catalog/version fail without partial records.
- For ticket sales, payment must precede ticket issuance and the issued ticket/order item are committed idempotently; do not retrofit already-issued historical tickets.
- Exit: T-CW-008 passes with ordinary retail and ticket sale, immutable snapshots, retry safety and no cross-tenant/branch disclosure.

#### M5-W4 — discount approval

- Implement only after the discount baseline is confirmed. Cashier submits a reasoned request bound to tenant, branch, order, exact discount payload and current order version; another Manager/Owner approves or rejects within ten minutes.
- Payment consumes the valid approval in the same transaction. Self-approval, expiry, payload mismatch, stale version, replay and cross-branch use fail closed.
- UI keeps the cart usable while approval is pending and clearly distinguishes requested, approved, rejected, expired and changed-cart states.
- Exit: T-CW-009 passes on SQLite/MySQL and policy/UI negative cases prove hidden controls are not the security boundary.

#### M5-W5 — full cash refund

- Retrieve by receipt/transaction reference and show immutable original facts, eligibility and current refund state.
- Request, approve and execute remain separate permissions. Execution locks order/payment/approval, derives full amount/currency server-side, enforces the approved window and ticket eligibility, appends one refund/reversal and audit evidence, and annotates the original receipt without replacing it.
- Duplicate/concurrent execution returns the original result or conflict; cumulative refund never exceeds the posted payment. “Approved” never means cash was returned until execution records its actor/time.
- Exit: T-CW-010 plus real MySQL duplicate-refund concurrency pass.

#### M5-W6 — transaction history and milestone acceptance

- Add a branch-scoped transaction list/search by receipt number, status, date and cashier for operational lookup only; full revenue reporting remains M6.
- Extend audit filters/actions for order, payment, receipt, discount approval and refund without exposing payment references or child-sensitive data.
- Run focused feature/policy/money tests after each slice; then fresh SQLite and isolated MySQL 8.4 full suites, MySQL concurrency, PHP 8.4/8.5, Pint, Vite, Blade, routes, docs/OpenAPI validation and `git diff --check`.
- Authenticated browser acceptance covers Cashier and Manager/Owner, Arabic RTL and English LTR, 1920×1080 desktop plus tablet breakpoint, keyboard-only payment, validation/conflict/replay, receipt print and forbidden states. Confirm no horizontal page overflow, raw translation keys, console errors or misleading payment/release copy.
- Exit: all included M5 T-CW-005/008/009/014 pass; T-CW-010 is mandatory only after OQ-09 approval. Record exact evidence in `.ai/TEST_RESULTS.md`, update all canonical docs, and keep production/legal/finance/provider/backup gates explicit.

### Suggested isolated implementation ownership

- **Worker A:** W1 schema/models/receipt sequence and migration tests.
- **Worker B:** W2 session settlement action/policies/concurrency tests.
- **Worker C:** W3/W4 POS UI, catalog/cart and bilingual browser states.
- Coordinator owns decision/doc freeze, integration, W5 authorization-sensitive refund, complete regression, MySQL concurrency, browser acceptance and final evidence. Workers use isolated worktrees and return commit SHA, changed files, checks and risks before integration.

### Blast radius and regression surfaces

Primary: branch settings/model, session state/policy/queue, navigation, audit labels/filters, new finance migrations/models/actions/routes/views/translations, ticket issuance when sold through POS, family visit receipt reference and seed/factory data. Mandatory regressions: tenant/branch route binding, owner/manager/reception/cashier role boundaries, M3 ticket lifecycle, M4 frozen quote and idempotency, integer tax/rounding, inactive branch denial, immutable completed sessions, Arabic RTL print and full MySQL locking.

### Milestone completion rule

M5 started after W0 decisions were recorded. Payment/receipt/session completion may be accepted independently, but the milestone is not called complete while any owner-approved included slice lacks SQLite, MySQL, concurrency and browser evidence. OQ-09 is approved as one full eligible cash refund; deferred OQ-24 does not block M5.

## 2026-09-14 integrated desktop and Arabic closure

The current M0–M4 workspace is ready for the next owner-approved milestone. The desktop shell and operational content use the 1440px canvas consistently, responsive ticket cards are limited to sub-1024px screens, and sparse branch/session collections use adaptive columns. Eleven authenticated Arabic RTL pages were checked at 1920×1080 without horizontal page overflow or untranslated keys. Operational Arabic now uses concise, meaningful Modern Standard Arabic suitable for Egyptian teams.

Audit gained tenant-scoped employee, branch, record-type and local-date filters plus M4 session events. Family profiles gained tenant- and child-scoped recent visit history. Full SQLite passes 329 of 332 with three explicit skips and 2,774 assertions; full isolated MySQL 8.4.11/InnoDB passes 332 / 2,843. Pint, Vite, documentation and whitespace gates pass. Do not infer M5: payment, completion, receipts, refunds and child release remain excluded until explicitly started.

## 2026-09-14 M0 repair wave — local acceptance

Three user-requested `gpt-5.6-luna` / `xhigh` workers owned the CI definition, deterministic demo seed and safe setup/toolchain slices. Coordinator review added the production seed refusal, reconciled the CI shape to run M0 on MySQL plus the full regression on SQLite, corrected the existing import-order formatting gate, and synchronized canonical evidence.

Local outcome: full SQLite PASS at 314 total / 311 passed / 3 skipped / 2,659 assertions; isolated MySQL 8.4.11 M0 PASS at 7 / 49 after 21 migrations and 31 InnoDB tables; Composer strict validation, global Pint, Vite, docs and whitespace PASS. The hosted workflow remains unexecuted. Stop before M1 review until the owner accepts the M0 report and hosted CI is green.

## 2026-09-14 M4 lifecycle/time-and-charge implementation — browser accepted; MySQL evidence pending

The approved OQ-19 reception-to-cashier handoff is now implemented with the remaining bounded M4 lifecycle controls: server-derived on-time/due/overdue labels; active-session extension only in fixed 30-minute units using immutable snapshot money; Manager/Owner additive, reasoned, append-only charge/time correction; and reasoned, terminal, non-refund cancellation. A frozen quote is prepared only after guardian last-four verification or a permission-checked reasoned Manager/Owner override, then `pending_payment` becomes immutable for this slice. Extension time moves the overtime boundary and its frozen unit amount is charged exactly once.

Every write is tenant/branch scoped, transaction-locked, version-guarded, UUID-idempotent and recorded as session event plus audit evidence. Native HTML actions redirect back to the scoped board; JSON clients receive the structured result. Focused M4 acceptance is PASS on SQLite: 26 passed, 1 explicit MySQL-only concurrency skip, 197 assertions. Scoped Pint, Vite, Blade compilation, route registration, documentation validation and whitespace checks pass. Authenticated browser acceptance is PASS on an isolated task-local SQLite runtime: a synthetic Owner signs in, adds a 30-minute unit (`256.50` to `342.00 EGP`), verifies a guardian by last four digits, sees the frozen invoice in `pending_payment`, and reviews English LTR plus Arabic RTL desktop UI. M5 payment/completion, receipt, refund, child release, shifts, pause/resume and provider notifications are excluded. Isolated MySQL 8.4/InnoDB evidence remains required before calling the milestone database-runtime-accepted.

## 2026-09-13 M4 checkout-preparation slice — OQ-19 accepted

OQ-19 is resolved as a reception-to-cashier handoff. Reception or an assigned Branch Manager verifies an active checkout-capable guardian by registered-phone last four digits, or records a permission-checked manager override with a non-empty reason. The server calculates from immutable session facts, freezes the exact quote, records the verification/event/audit evidence, increments the lock version, and moves the session to `pending_payment` for the Cashier queue. Cashier cannot prepare checkout; M5 matching payment posting will atomically complete the session. Identical UUID retries return the original preparation, while changed replays, stale locks, ineligible/foreign guardians, and terminal sessions fail safely without mutation. This slice excludes payment, refund, receipt, child-release and shift behavior.

**Focused result:** `php artisan test --compact tests/Feature/PlaySessionCheckoutPreparationTest.php` — PASS, 8 tests / 66 assertions on process-local SQLite. Full regression, isolated MySQL and browser acceptance are pending and are not claimed here.

**Central integration result:** Full process-local SQLite `php artisan test --compact` — PASS, 293 total / 291 passed / 2 skipped / 2,494 assertions. `npm run build` — PASS (existing optional `fontaine` notice); scoped Pint, Blade cache/compilation and `git diff --check` — PASS. A full global Pint run only exposed the pre-existing `ordered_imports` issue in `StaffStatusController`, outside M4; it was not modified. PHP 8.5, isolated MySQL and browser acceptance remain pending.

## 2026-09-13 M3 read-only live quote wave

After accepting ticket-backed check-in, implement the smallest remaining M3 pricing proof without entering M4 checkout: a deterministic read-only live estimate from each Active session's immutable pricing snapshot and server time. Three Luna/xhigh workstreams own the pure calculator, boundary tests, and bilingual copy; the coordinator owns policy/query integration, UI composition, browser/MySQL regression and canonical documentation.

Contract: elapsed clamps at zero; base price covers base duration plus the configured grace; any positive seconds beyond that boundary round up to whole overtime units; money stays integer minor units. `exclusive` tax adds a half-up rounded tax to the configured subtotal; `inclusive` tax keeps the configured total and extracts the half-up rounded included tax. The snapshotted rate/mode/currency are authoritative. No 14% application default is introduced: an arithmetic 1,400 bps fixture reflects Egypt's standard rate, while actual venue/activity tax treatment remains the operator's legal/accounting configuration responsibility. The display says estimate/as-of and is never persisted or presented as checkout, final charge, amount due, payment or receipt.

Acceptance examples for `base=15,000`, `duration=3,600`, `grace=600`, `unit=1,800`, `overtime=7,500`, `rate=1,400 bps`: at 4,200 seconds, exclusive tax is 2,100 and total 17,100; at 4,201 seconds, one overtime unit gives subtotal 22,500, tax 3,150 and total 25,650; at 6,001 seconds, two units give subtotal 30,000, tax 4,200 and total 34,200. Inclusive at 4,201 seconds keeps total 22,500, extracts tax 2,763 and net 19,737. Zero-rate remains exact. Malformed snapshots fail closed and show no estimate.

**Accepted result:** three `gpt-5.6-luna` / `xhigh` workers delivered calculator, exact tests and bilingual copy; coordinator integrated fail-closed read-only rendering and responsive cards. Focused quote/session checks pass 18 / 177. Full PHP 8.4/PHP 8.5 SQLite pass 279 of 281 / 2,387 with two MySQL-only skips; full MySQL 8.4.11/InnoDB passes 281 / 2,434. Vite, Pint, Blade, whitespace and authenticated Arabic/English Owner/Cashier Edge QA pass. M3 is locally accepted. The product owner stopped before M4; OQ-12 is approved, OQ-19 remains open, and no checkout, release, payment, receipt or M4 implementation is implied.

## 2026-09-13 historical ticket-backed check-in implementation wave

At that checkpoint, the user accepted closure of the verified ticket-only stage and authorized the check-in stage. Three reused Luna/xhigh workers delivered backend/session integrity, bilingual UI and adversarial review; the coordinator integrated and verified. The check-in/live-board wave below is now accepted. M4 did not start; if the owner explicitly resumes it, first freeze the bounded checkout/time-and-charge contract and OQ-19 station ownership. Do not implement payments/refunds or guess those decisions.

This wave implements explicit ticket-backed check-in only: authorized Owner/assigned Branch Manager/Reception atomically validates and consumes one issued ticket, permanently locks its holder, creates one Active play session with UTC server start/expected end and immutable ticket pricing facts, and appends scan/session/audit evidence. Cashier can view masked session data but cannot check in. No active session may already exist for the child anywhere in the tenant; enforce capacity under the same transaction and child lock, without overrides. UUID identical retries return the existing session after fresh scope/role checks; changed payloads conflict. Rejected check-in must not consume/lock/create partial state, and identifiable validation failures remain privacy-safe scan evidence.

Use `play_sessions` to avoid collision with Laravel's existing authentication `sessions` table. The native page supports branch/family/status filtering and paginated live sessions with elapsed/expected-end indicators only; no estimated billing or final charge claim. Direct-pricing check-in, pause/extend/cancel/checkout, payments/refunds, notifications, and retention execution remain later bounded slices. No dependency addition or shared runtime/database restart/migration. Workers edit only their assigned files and must not run shared `view:cache` while tests run.

**Accepted check-in result:** focused check-in passes 9 tests / 128 assertions. Full SQLite and PHP 8.5 pass 270 of 272 tests / 2,338 assertions with two explicit MySQL-only skips; full isolated MySQL 8.4.11/InnoDB passes 272 / 2,385. `TicketConcurrencyTest` passes 2 / 47 using two independent PHP processes and proves distinct-key simultaneous check-in consumes/starts exactly once. Authenticated headless Edge QA verifies synthetic Owner and Cashier roles, Arabic RTL/English LTR, responsive mobile/tablet/desktop layout, no raw guardian phone and non-cacheable board responses. Existing port 8206 was untouched. Checkout, final billing, payments/refunds and production acceptance are not claimed.

## 2026-09-13 historical M3 ticket lifecycle implementation wave

Three user-requested Luna/xhigh workers own bilingual ticket UI/local QR rendering, focused lifecycle/security tests, and an independent read-only security review. Coordinator owns backend/data corrections, integration, canonical status, and final verification. Shared worktree edits must stay inside assigned files.

Boundary: immutable branch ticket type from an active pricing version; branch-local dated issuance with integer price snapshot and encrypted opaque QR; idempotent validation scanning; permanent holder lock after first accepted scan; audited pre-scan assignment correction, manager-only unused-ticket cancellation, and immutable reprint. Validation is not consumption or check-in. No session/capacity implementation, payment posting, or financial refund/reversal is claimed; OQ-09 and the bounded session slice still own those flows. Rejected scans must never lock assignment or disclose inaccessible resources. Current M2 consent/relationship/safety safeguards apply to ticket issuance and validation.

Workers return evidence, not acceptance. Coordinator independently integrated the three outputs and corrected source-version issuance, family safety/scope, QR sizing, mobile action reachability and single-page printing.

**Accepted ticket-only result:** full MySQL 8.4.11/InnoDB regression passes 262 tests / 2,233 assertions, including a real two-process issue/scan retry check. Full SQLite passes 261 of 262 tests / 2,210 assertions; the MySQL-only concurrency test is explicitly skipped. Headless Edge QA verifies authenticated Owner/Cashier identities, Arabic RTL/English LTR, mobile controls, QR decoding and one-page A5 output. Pint and Vite pass; current commands and evidence are in `.ai/TEST_RESULTS.md`. No full M3 or production acceptance is claimed. Next bounded work is atomic ticket consumption/check-in/session creation against the canonical contract, then live sessions/capacity; finance-approved calculator examples and OQ-09 still gate billing/refund execution.

## 2026-09-13 approved Egypt M3 ticket contract

Implement the smallest ticket/check-in slice against the approved OQ-18 boundary: every ticket is tenant/branch scoped and carries a branch-local service date; authorized assignment correction is allowed only while Issued and before any successful scan; the first accepted scan permanently locks guardian/child assignment; wrong branch/date and terminal-state scans fail safely and are logged; ticket consumption and session creation are atomic/idempotent. Refund eligibility requires an unused Issued ticket, no successful scan/consumption/session, an in-scope Branch Manager/Tenant Owner action-bound approval, reason, audit, and a linked reversal when a posted payment exists. OQ-09 still owns refund window/method/execution, so do not invent them. No ticket implementation is claimed by this decision entry.

## 2026-09-12 approved Egypt M2 completion contract

Implement the newly approved M2 contract without entering M3: tenant-unique normalized guardian phone with existing-family recovery; append-only Arabic-first child-data consent grant/withdrawal and separate optional marketing choice; required emergency contact plus encrypted restricted safety notes; verified relationship link/reactivate/revoke with the final-guardian invariant; bilingual responsive UI and negative tenant/role/retry tests. Child photos, duplicate merge/create-anyway, incidents, tickets, check-in, sessions, pricing expansion, POS, and payments stay excluded. Visit history waits for M3 session data under the approved dependency waiver. Production Legal/DPO sign-off is a deployment gate, not permission to weaken the engineering safeguards.

**Accepted implementation result — 2026-09-13:** the contract above is implemented. Full SQLite and isolated MySQL 8.4.11/InnoDB suites pass 241 tests / 1,997 assertions; Pint, Vite, documentation validation, and whitespace checks pass. Real browser acceptance is unavailable, and production Legal/DPO approval remains a release gate. Visit history and retention execution remain coupled to M3 session/last-visit data by the approved waiver.

## 2026-09-12 M1/M2 remediation checkpoint

The remediation stays inside the approved M1/M2 surface: action-level family abilities; server-side role-aware masking; active-only family discovery; hostile custom-role and cross-tenant denial; tenant-scoped branch-create idempotency; controlled staff uniqueness conflicts; stable JSON error request IDs; and responsive shell/assignment improvements. Cashier may perform the approved first atomic family registration and view masked active family data, but cannot maintain guardians, children, relationships, or sensitive fields.

**Accepted automated result:** full SQLite and isolated MySQL 8.4.11/InnoDB suites pass 234 tests / 1,943 assertions. Pint, Vite, documentation validation, and whitespace checks pass. Real browser acceptance remains blocked by bridge availability, so no visual PASS is claimed. M2 remains IN PROGRESS pending the product/legal lifecycle decisions recorded in `.ai/BLOCKERS.md`; no consent, emergency/safety, relationship lifecycle, merge, visit history, ticketing, session, POS, or other M3 behavior was added.

## 2026-09-12 M3 T42-T44 pricing version wave

Replace an active pricing rule only by atomic immutable versioning. `POST /app/pricing/{pricingRule}/versions` receives name, base duration minutes, base/overtime EGP strings, and `expected_version`. After tenant/branch authorization and row locks, the target must still be active and current; retire it and create version +1 with the same tenant/branch/code, fixed OQ-16 constants, current branch tax snapshot, and actor. Audit stores old/new IDs and versions only.

- **T42 backend:** version command/route, current-rule conflict rules, transaction/audit, and focused tests.
- **T43 UI:** manager-only native replacement controls per active rule, bilingual explanation, preserved values, validation/conflict/success states, and UI tests.
- **T44 review:** adversarial tests for stale/replayed request, retired/foreign/view-only rule, rollback, immutable old facts, exact money conversion, and audit payload.

No migration/model expansion, in-place update/delete, activation scheduling, calculator/tax total, ticket/QR/check-in/session work. Coordinator reviews and gates the integrated result.

**Accepted result:** T42-T44 are integrated. Current active rules are replaced only by atomic retirement plus version +1 creation; stale/replayed and out-of-scope attempts fail without partial writes. Manager-only bilingual replacement controls use the existing pricing surface. Follow-up review isolates failed-form values to the submitted child/pricing form and removes consent copy before its policy is approved. Focused version tests pass 18 / 181 and full PHP 8.5 regression passes 226 / 1,859; Pint, Vite, documentation, and pricing-route checks pass. Browser and fresh MySQL acceptance remain outstanding.

## 2026-09-12 M3 T39-T41 immutable pricing-rule wave

Three Luna/xhigh workers implement the first M3 slice. Contract: list current-scope rules and create one immutable active branch rule with code/name/version 1, `base_duration_seconds`, integer `base_price_minor`, fixed `grace_period_seconds=600`, fixed `overtime_unit_seconds=1800`, integer `overtime_price_minor`, EGP currency, selected branch tax snapshot, actor, status, and timestamps. Unique `(tenant_id, branch_id, code, version)` and tenant-aware branch/actor foreign keys are mandatory.

- **T39 data:** migration, model/factory/relations, composite constraints/indexes, and data-integrity tests.
- **T40 backend:** owner/branch-manager manage policy, scoped view for eligible fixed roles, controller/routes, validation, transaction/audit, and security tests.
- **T41 UI:** bilingual responsive pricing list/create surface, branch context, integer-to-EGP presentation, fixed-rule explanation, empty/validation/success states, navigation, and UI tests.

This historical pricing wave seeded no price and added no in-place edit, calculator/tax total, ticket type, issuance, QR, check-in, session, extension, or retirement/version cloning. OQ-18 was subsequently approved in the 2026-09-13 contract above; ticket implementation remains a separate wave. Coordinator owns integration corrections, canonical status, full tests, build, and available browser/MySQL evidence.

**Accepted result:** T39-T41 are integrated. Coordinator review separated viewable from manageable branches for mixed-role staff and added regression coverage. Focused pricing tests pass 19 / 197, including exact decimal-to-minor conversion, tenant/branch denial, custom-role denial, duplicate conflict, audit rollback, and bilingual view-only UI. Full gates are recorded in `.ai/TEST_RESULTS.md`; browser and fresh MySQL acceptance remain outstanding.

## 2026-09-12 M2 T36-T38 family-profile wave

The user authorized another three-agent Luna/xhigh wave. Fixed boundary: current-tenant family detail; edit guardian name/phone/email/preferred locale and child name/optional DOB with expected `lock_version`; add one child and active allowlisted relationship to an existing guardian atomically. Same-tenant phone conflict must point to the existing family and create no write. Audit JSON stores IDs and changed field names, never raw PII.

- **T36 backend:** family-profile policy/actions/routes, transactional update/add-child commands, conflict/duplicate handling, and focused feature tests.
- **T37 UI:** bilingual responsive family detail, edit forms, add-child form, validation/success/conflict recovery, and UI tests.
- **T38 review:** independent adversarial review/tests for tenant leaks, stale versions, duplicate phone, rollback, audit PII, inactive actors/branches, and route binding.

Consent, safety/emergency data, relationship revocation, guardian merge, visit history, tickets, and check-in remain excluded. The coordinator integrates shared contracts, updates canonical docs, and runs full/browser/MySQL-proportional gates.

**Accepted result:** T36-T38 are integrated. Coordinator review fixed a retry hole by requiring and incrementing the guardian version on child addition, and exposed localized audit action labels. Focused profile tests pass 18 / 143; full PHP 8.5 regression passes 187 / 1,471; Pint, Vite, and route checks pass. Browser acceptance is blocked by `User unavailable`; fresh isolated MySQL evidence remains outstanding. No excluded feature was added.

## 2026-09-12 M2 T33-T35 first family-registry wave

The user authorized M2 start with three Luna/xhigh workers. Fixed contract: one visible flow at `/app/families` and `/app/families/create`; search by trimmed/capped child name or normalized guardian phone; only current-tenant safe results; create one guardian, one child and one active guardian-child link in one transaction. Same-tenant normalized phone match creates nothing and returns the existing-family path; cross-tenant matches stay undisclosed. No phone uniqueness constraint, consent event, safety note/photo, editing, visit history, check-in or speculative permission key in this wave.

- **T33 data:** migration, Guardian/Child models and factories, relations/casts/masked-phone helper, and database-integrity tests only.
- **T34 backend:** family authorization for active owner or fixed eligible branch roles, E.164 normalization, controller/routes, transaction/audit, duplicate guard, and backend security tests only.
- **T35 UI:** bilingual accessible search/create/results/empty/error/success views, navigation entry and UI feature tests only.

**Accepted result:** T33-T35 are integrated after coordinator corrections for pivot naming, actor evidence, phone formats, query/view contracts, child-name search, allowed relationship values, duplicate recovery, and transaction rollback. Focused tests pass 19 / 148; full PHP 8.5 regression passes 169 / 1,328; Pint and Vite pass. Browser creation/search passed and synthetic data was removed; duplicate visual verification was interrupted by browser unavailability and remains covered automatically. The new migration still needs isolated MySQL acceptance. M2 remains in progress, not complete.

## 2026-09-12 custom roles and staff administration accepted

Three requested Luna/xhigh workers split custom roles, staff search, and direct staff creation. Coordinator reviewed and tightened the result: `/app/roles` creates tenant roles and toggles the enforced `branches.view` permission; eligible roles are assignable at `/app/assignments`; staff search is tenant-scoped by name/email; `/app/staff/create` creates an active account with a generated unknown password for password recovery. Built-in role maps, owner transfer, email delivery, and speculative permission keys remain out of scope.

## 2026-09-12 M1 closed

T31 PHP 8.5 compatibility is DONE: the full suite passes on PHP 8.5.8. Final coordinator review added the shared authenticated shell, polished tenant settings, semantic state tokens, audit labels/filters for branch and tenant settings, and a missing branch-settings translation fix. Final M1 acceptance: 142 tests / 1,096 assertions on SQLite under PHP 8.4.21 and PHP 8.5.8; 142 / 1,096 after fresh migrations on isolated MySQL 8.4.11/InnoDB; focused UI/audit 33 / 217; Pint, Vite, Composer audit, npm audit, documentation validation, whitespace, real Arabic RTL/English LTR browser, and console checks PASS. M1 DONE; M2 subsequently started above; no main merge or push.

## 2026-09-12 T30 and T32 final acceptance integrated

Three requested Luna/xhigh workers completed MySQL execution, browser execution, and independent review. T30 `3c2d6fd` is merged by `385b58d`; isolated MySQL 8.4.11/InnoDB passed migrations, 46 tests / 389 assertions focused and 138 / 1,059 full. T32 `108ca47` is merged after it fixed safe same-page locale switching and browser-verified `/app/branches/1/settings` in Arabic RTL and English LTR. Post-merge SQLite verification passed 54 / 444 focused and 141 / 1,077 full; Pint and diff checks pass. T31 PHP 8.5 remains outstanding. No main merge or push.

## 2026-09-12 T21-T23 delivery accepted

Three Luna/xhigh feature workers delivered complete vertical slices: T21 staff invitation `4c7df3b`, T22 branch lifecycle `29aa88e`, and T23 tenant audit viewer `77745f0`. Coordinator reviewed actual diffs/tests, wired the feature route files and owner navigation, corrected invitation test assertions/session setup, and accepted the combined result after 23/188 focused and 103/691 full tests plus Pint/build/docs/diff checks. Browser remains deferred; MySQL/PHP 8.5 and broader platform/owner-transfer scope remain incomplete. No main merge or push.

## 2026-09-12 T18-T20 integrated feature wave

Three requested Luna/xhigh workers delivered complete features in isolated worktrees from `bb5f883`. Integrated T18 `ee9a35b` (owner all-active-branch access), T19 `7c6e171` plus `401a235` (existing non-owner staff status and per-row validation correction), T20 `3066663` (fixed branch assignment management). T20 worker later amended its commit to `95e99b6` solely for SQL-null assertion handling; coordinator applied that correction directly after the original commit was integrated. Shared audit migration, route composition, owner navigation and canonical documentation are coordinator-owned.

Delivered: active owners list/select/read all active own-tenant branches; ownership revocation clears selected context unless an independent permitted staff assignment remains. Owners manage existing non-owner staff status at `/app/staff` and fixed branch roles/access at `/app/assignments`. All owner accounts remain protected. Administration requires reason codes, expected-state conflict checks, scoped transaction locks and atomic successful-change audit. No user creation, invitation delivery, owner assignment/transfer, platform access or arbitrary permission maps.

Review found and corrected Query Builder misuse, query callback type mismatch, JSON audit serialization, per-row old-input contamination, and generic HTML409 presentation. Added a localized conflict page. Tests were inspected as code as well as executed; a stale-branch regression's setup used the wrong query and was corrected before acceptance.

Verification in isolated integration checkout: focused `php artisan test --filter='OwnerBranchAccessTest|StaffManagementTest|BranchAssignmentManagementTest'` PASS 28 tests / 187 assertions after three initial failures were resolved. Full `php artisan test` PASS 80 tests / 503 assertions. Process-local SQLite `:memory:`, empty DB_URL, array sessions, SESSION_CONNECTION unset. `php vendor/bin/pint --test` PASS after scoped formatting; `npm run build` PASS (optional fontaine notice); documentation validator PASS 0 errors / 2 existing warnings; `git diff --check` PASS.

Status: DONE for local implementation and automated acceptance. Browser DEFERRED_BY_USER. MySQL migration/row-lock concurrency and PHP8.5 remain unverified; SQLite transaction rollback/expected-state tests are not evidence of MySQL concurrent behavior. Successful admin-change audit is delivered, not the complete denied/security audit program. Full M1/production acceptance remain incomplete. No shared service changes, main merge or push.

## 2026-09-12 T14-T17 delivery accepted

| Task | Worker | Delivery / outcome |
| --- | --- | --- |
| T14 owner-read contract | Coordinator | DONE: explicit tenant_owners, tenant-global read only; canonical docs synchronized |
| T15 backend | owner_backend_luna, Luna/xhigh | DONE: bb6df9e reviewed and integrated |
| T16 UI | owner_ui_luna, Luna/xhigh | DONE: a451bca reviewed and integrated |
| T17 tests | owner_tests_luna, Luna/xhigh | DONE: 96fbe14 reviewed and integrated |

Workers reported exact base/SHA/owned files, executed static checks and missing runtime verification. Coordinator verified actual diffs, fixed findings through worker feedback, and ran combined tests (52/315), Pint, build and docs/diff checks successfully. This is local code and automated acceptance; browser DEFERRED_BY_USER, new MySQL migration and PHP 8.5 unverified. No main merge or push. Future owner-wide branch scope must get its own fixed contract before another implementation wave; full M1 is not complete.

## 2026-09-12 user-directed browser deferral and next proposed wave

The user explicitly deferred browser testing for now. T13 local code integration and automated checks are complete; browser acceptance is DEFERRED_BY_USER, not PASS, and does not block planning the next wave. MySQL coverage of this wave remains unverified.

Proposed sequence (not dispatched): T14 coordinator defines and records the tenant-owner representation and the read-only route/data contract, reconciling the ERD/architecture conflict. Recommended initial scope is own-tenant profile and staff list, with explicit tenant-level ownership; a branch role must never imply ownership. Owner access to all branches and platform access stay separate.

After T14, three workers can run concurrently: T15 backend owns the approved migration, owner relation, policy, read controller and route; T16 UI owns the tenant profile/staff-list Blade view and its English/Arabic strings against the fixed view contract; T17 security tests owns one focused feature test file for owner allow, staff deny, foreign scope, inactive states and revocation against the same contract. Each uses a separate worktree; coordinator owns shared status files and integration. T17's executable verification depends on integrating T15/T16, so interim test failures are not acceptance failures. One final integration/review/check round follows. These are proposed tasks, not started work or full M1 completion.

## 2026-09-12 combined integration checkpoint

Recovered local integration at `6dafe21`: T10 `c3b2065` and T11 `c61f9e8` are merged into `codex/first`; T12 report `4093e8d` is integrated. Reviewed status enforcement, forward migration/backfill, mass-assignment exclusion, policy checks, locale middleware/controller and the failed-login interaction. Retained the pending focused fix that restores the validated locale after failed-login session invalidation; its regression checks guest state and the following Arabic RTL page.

Combined verification: `php artisan test` PASS (37 tests / 247 assertions), process-local SQLite `:memory:` and array sessions; `php vendor/bin/pint --test` PASS; `npm run build` PASS (optional fontaine notice); documentation validator PASS (0 errors / 2 existing placeholder warnings); `git diff --check` PASS.

Three Terra review agents were attempted (`staff_review_now`, `locale_review_now`, `owner_review_now`); all ended with usage-limit errors, so no independent agent review is claimed. Coordinator performed the code review. T12 is accepted as investigation only: owner representation and owner branch scope remain unresolved; no owner/platform implementation is authorized by that report.

Status: code integrated and automated checks PASS; T13 runtime acceptance remains PARTIAL. No combined T10/T11 browser or MySQL acceptance is claimed; historical T07/T09 evidence does not cover this wave. Next bounded acceptance is active login -> locale switch/invalid-login persistence -> select branch -> suspend user -> protected reload clears authentication/context. Then close T13 and define owner representation before dispatching the next implementation wave. Full M1, PHP 8.5 and production remain incomplete. No main merge, push or shared service change.

## Parallel M1 wave — 2026-09-10

Current goal: complete independent M1 gaps in parallel, then one coordinated review/integration round. Base application commit 0e360eb; start workers from the planning commit containing this section. User requested three Terra subagents; coordinator dispatched /root/staff_status (A), /root/locale_switch (B), /root/owner_contract (C), all from 38fa251 in separate worktrees. Coordinator monitors, reviews and integrates accepted results locally.

| Task | Owner | Status | Dependency / acceptance |
| --- | --- | --- | --- |
| T10 staff status enforcement | A | INTEGRATED; RUNTIME_PENDING | T09 integrated; invited/active/suspended/disabled statuses, deny non-active login and revoke existing access on next request |
| T11 session locale switch | B | INTEGRATED; RUNTIME_PENDING | T09 integrated; en/ar switch persists, LTR/RTL and validation localization work without server reconfiguration |
| T12 owner/platform authorization contract investigation | C | DONE (investigation only) | Read-only code/spec analysis; identify authoritative scope representation, contradictions and smallest next vertical slice |
| T13 combined review/integration | Orchestrator | PARTIAL | Review A/B independently as delivered; merge accepted A then B, combine runtime journey; C informs next wave |

Ownership: A owns User/status migration and factory/seeder adjustments, existing auth controller/tenant middleware/TenantContext/BranchPolicy only as necessary, new StaffStatusTest and directly affected existing tests. B owns routes/web.php, bootstrap/app.php locale registration, new locale controller/middleware, lang files, existing Blade views/CSS only as needed, new LocaleSwitchTest. C owns only docs/owner-platform-contract-review.md; no implementation or approval edits. A/B return check evidence in their final reports; orchestrator alone updates shared .ai status/test records and this ledger at integration. Do not edit each other's tests or shared configuration. All workers use isolated data/ports/cookies; no shared DB/server/.env changes.

T10 contract: follow users.status values and invited default from ERD; add a forward migration preserving deployed schema and backfill existing users active for compatibility. New normal users default invited; test factories explicitly create active staff. Status is not a request-writable mass-assignment field. Enforce current DB status at the trust boundary and policy access, not stale loaded relationships. Non-active login gives existing generic failure; current non-active sessions lose authentication/branch context on the next protected request. Browser protected requests go to login; JSON gets 401 after revocation. Logout remains available. Existing inactive-tenant behavior/404 scope remains unchanged; no admin status editor, invite sending, owner scope or password reset. Record that physical deletion of every session row is not claimed; next-request enforcement is this slice's guarantee. Use scoped automated checks, existing suite, and an isolated browser active-login -> DB status suspension -> protected refresh -> logged-out check. Migration must be tested against fresh and existing rows; do not replace historical migrations.

T11 contract: session-based en/ar preference only (no users.locale migration). Add POST /locale with validated locale allowlist, CSRF and safe redirect to fixed local login or app route based on auth. No client-provided redirect target. Middleware runs after session starts and before rendering/validation; fallback en. Visible language control on login and app. Translate login required/invalid/type validation messages used by the slice; no broad translation dependency. Keep auth/policy decisions untouched. Focused tests cover persistence, invalid locale, safe redirect and guest/auth rendering; browser keyboard switch on login/app and Arabic validation on same runtime. Session locale after logout may reset to configured default; do not weaken session invalidation to preserve it.

T12: inspect permission matrix, ERD, decisions and real schema/code; distinguish explicit approvals from proposals. No invented tenant-owner assignment from branch_user role, no blanket super-admin bypass. Report competing models already documented, contradictions needing decisions, recommended minimal native representation with migration/validation/isolation tests, exact owned files and dependencies for the next implementation prompt. No implementation tasks are marked ready until the coordinator reviews it.

One review round: accept/reject precise diffs, fix only confirmed findings, integrate accepted code without a separate user prompt for each mechanical step under the user's accelerated coordination request. No main merge/push. Run each worker's scoped checks once, one combined suite after merge, and browser evidence only for changed flows. No repetitive broad QA or speculative fourth task. Full M1 remains incomplete until its actual remaining requirements are closed.

## 2026-09-10 T09 integration accepted

T09 integrated locally into codex/first from accepted 0c93d50, preserving coordinator 27ade78. The only conflict was .ai/TEST_RESULTS.md; both evidence sections were retained. Combined tests PASS: 26 tests/156 assertions; Pint PASS; docs validator PASS (0 errors/2 known warnings); diff check PASS. Application/tests/dependencies match accepted worker code. No additional build, MySQL or browser run was needed for this documentation-only conflict resolution. T09 integration DONE; full M1, owner/platform permissions, PHP 8.5 and production remain incomplete. No main merge or push.

This entry supersedes earlier T09 pending-review/not-integrated statements below.

## Orchestrator acceptance of T08 — 2026-09-10

T08 is DONE after review of 7a3963e48011f230a8d27ce91320fc8e1f942395. This acceptance supersedes pending-review statements below. The canonical coordination baseline is now this tracked ledger on codex/first in the t08-integration worktree; the untracked main-checkout ledger is historical. The bounded auth/branch slice is integrated and accepted. Full M1, PHP 8.5 and production acceptance remain incomplete.

Verified accepted-target ancestry, four-file documentation-only diff, retained worker evidence and all substantive coordinator review entries. The omitted old statement that no scaffold/tests exist is obsolete here. Main and QA refs/status and existing stash were preserved. Independently reran 21 tests/81 assertions, Pint, build, documentation validator (0 errors, 2 status-marker warnings) and diff checks successfully. No service restart, main merge or push.

T09 is accepted on worker branch at 0c93d5073960ae8dc6c93c86d22b1c176fb09bcd. Next is local integration and combined verification; no integration performed by this review.

## T09 review — 2026-09-10

- Reviewed nine-file diff against 92aca44, all policy call sites, fresh pivot lookup, scoped 403/404 behavior and regression tests. No confirmed correctness findings in the bounded contract.
- Independently reran full suite: 26 tests/156 assertions PASS with SQLite :memory:/array sessions; Pint PASS; docs validator PASS (0 errors, 2 known marker warnings); diff check PASS. Worker tree clean. No frontend/dependency/schema changes, so no additional build or MySQL migration run. T07 MySQL evidence predates this policy; T09 validation is SQLite only.
- Reviewed original worker browser outputs in task 01a08944-c762-7bb0-8945-a89e0044ac79: 8194/app as T09 Operator, permitted branch selection/reload, role revocation removes context, direct /branches/1 renders 403. No independent live browser rerun or screenshot-render claim. Port 8194 not listening at review.
- T09 DONE on worker branch, not integrated. Fixed tenant-owner/platform access, staff lifecycle, full M1, PHP 8.5 and production remain incomplete. Per-branch policy queries are acceptable for this bounded selector; optimize only if measured branch scale warrants it.
- Next integration must merge accepted worker 0c93d50 into codex/first while preserving this coordinator review commit and worker evidence. The coordinator review advances codex/first separately, so recheck ancestry and use a normal local merge if fast-forward is no longer possible. No force/rebase, main merge or push. Run combined tests and docs/diff checks before acceptance.

## T09 execution contract — DONE on worker branch

- Base: accepted codex/first at 84c5b00 plus this planning-only commit. User launches one isolated worker branch codex/t09-branch-view-policy; no implementation dispatched by the orchestrator.
- Objective: existing /app branch list, POST /branch-context/{branch}, GET /branches/{branch}, and stored branch-context revalidation enforce the same branches.view decision. Active membership alone must not grant access for arbitrary role strings.
- Supported branch roles for this slice: branch_manager, reception_staff, cashier. Existing stored reception is an explicit compatibility alias for reception_staff; do not mass-rewrite data or remove coverage for the alias. Unknown, empty, future and misplaced tenant_owner/super_admin pivot values deny by default. This does not define actual tenant-owner/platform access; no authoritative tenant-level ownership representation exists in current User/schema. Implement that separately before claiming full M1.
- Reuse TenantContext and activeBranches scope, plus Laravel-native BranchPolicy view and Gate authorization. Preserve 404 for foreign/unassigned/inactive scope; return 403 for an active in-scope assignment whose role lacks branches.view, following permission-matrix section 13. Filter denied branches from the selector and clear denied selected context after role revocation/change. Missing permissions and stale loaded relations must not preserve access.
- Role mapping remains server-side fixed code, no package/tables/custom permission editor. Only branches.view is delivered; do not pre-grant future operations or add an update/settings endpoint merely to exercise a second permission.
- Owner: one worker owns policy, any minimal shared role predicate, integration points in User/branch controllers/middleware/provider/routes as actually needed, focused tests, and .ai/PROGRESS.md, .ai/TEST_RESULTS.md, .ai/CURRENT_MILESTONE.md. It may add a clearly scoped implementation note to docs/08-Permission-Matrix.md without changing the business matrix. Coordinator alone edits this ledger. No concurrent workers on these files.
- Verification: direct policy negatives and real HTTP list/select/read negatives for unsupported role, role from a different branch, inactive tenant/branch/assignment and cross-tenant records; canonical and legacy reception success; role revocation clears selected context; auth/logout/throttle regressions unchanged. Run focused checks then full suite, Pint and docs/diff checks. Perform a bounded real-browser assigned-list/select/reload role-revocation check using isolated synthetic data; report exact engine and limitations. Do not restart shared services.
- Delivery: focused commit(s), exact checks/results, changed files, browser evidence and remaining scope; REVIEW_REQUIRED until orchestrator review. Depends on T08 DONE. Integration order: review T09 -> accepted correction if needed -> integrate -> combined verification. Owner/platform scope, staff lifecycle and further permissions remain deferred tasks needing separate contracts.

## Current startup snapshot — 2026-09-10

This snapshot supersedes the current-state and readiness statements in the historical ledger below. Earlier review results remain historical evidence, not tests rerun during this startup.

### Current Goal

T07B evidence accepted at `37b4f6b`; the bounded auth/branch-entry slice and its target-engine acceptance are DONE. T08 local integration has been performed in the isolated `codex/first` checkout at `C:/Users/N/.codex/worktrees/t08-integration/PlayNexus`; the coordinator baseline is versioned there and is pending orchestrator review. No main merge or push was performed; this is not full M1 or production acceptance.

### Current Repository State

- Active source checkout: `C:/Users/N/OneDrive/Documents/ChatGPT/PlayNexus`, branch `main`, HEAD `b6d09e1`; its pre-existing dirty/untracked coordinator state is preserved. Active integration checkout: `C:/Users/N/.codex/worktrees/t08-integration/PlayNexus`, branch `codex/first`, fast-forwarded from `03108c6` to accepted `37b4f6b`.
- Existing untracked items: `.codex/`, `bootstrap/` (generated cache PHP files), and this ledger. Preserve them; cache artifacts alone do not constitute an application.
- `codex/first` now points to the T08 coordination commit on top of accepted `37b4f6b`; its application and dependency tree is unchanged relative to `37b4f6b`. `codex/t04-tenant-branch-foundation` remains at `03108c6`.
- Worker worktree `C:/Users/N/.codex/worktrees/344d/PlayNexus`: `codex/t03-scaffold-review` at `7866de3`, with an untracked `docs/agent-plan.md`.
- Worker worktree `C:/Users/N/.codex/worktrees/3284/PlayNexus`: detached at `b6d09e1`, clean at inspection.
- Existing stash: `orchestrator-docs-before-t03-merge`; leave intact.
- Delivered worker worktree: `C:/Users/N/.codex/worktrees/t05-auth-branch-access/PlayNexus`, branch `codex/t05-auth-branch-access`, clean at `c94a0461b1a2412c163ae22ea8d434a02b1a82ac`, parent `03108c6`. Its 21-file diff contains auth/branch UI and tests, with no dependency or migration change. It is not integrated into `main` or `codex/first`.
- Naming reconciliation: the worker calls this slice T05; in this ledger it corresponds to T06 (auth implementation). T05 remains the independent review responsibility. Refer to branch/commit as well as task ID to avoid duplicate work.
- Follow-up `9056439dc341aea76040dd4723fab7e13e536f0f` is now the clean auth worktree HEAD, directly after `c94a046`; five scoped files changed. Neither auth commit has been integrated into `codex/first` or `main`.
- QA worktree `C:/Users/N/.codex/worktrees/t07-runtime-acceptance/PlayNexus` is clean at `835346d8dbfad3b668813b0e93737c5767b6d371`, branch `codex/t07-runtime-acceptance`, parent `9056439`. Only `.ai/TEST_RESULTS.md` changed (22 added lines).
- Latest QA HEAD is `37b4f6b782c0020984567bb774d982b42428d3cb`, parent 835346d; only `.ai/TEST_RESULTS.md` changed (43 added lines). Tracked tree is clean; pre-existing untracked `ibrahim.err` remains untouched. Earlier clean/HEAD statements above are historical.
- `codex/first` contains approved-decision entries and canonical documentation changes absent from `main`. Its blocker text claims receipt/pricing/verification synchronization while its decision register still lists those items as open; resolve this discrepancy before dependent planning.

### Architecture Context

One Laravel modular monolith; Blade with selective Livewire; shared-schema MySQL tenancy; authenticated identity supplies tenant context; active branch assignment and permission checks deny by default. Money uses integer minor units plus ISO currency; timestamps persist in UTC and render in branch timezone. `docs/` owns specifications; `DESIGN.md` owns the petrol-teal/cool-porcelain UI, keyboard/touch and Arabic/English behavior. OpenAPI, permission matrix and ERD are contracts to inspect before splitting work.

### Task Graph and Status

| ID | Task | Status | Evidence / next gate |
| --- | --- | --- | --- |
| T00 | Startup inspection and ledger reconciliation | DONE | Branches, worktrees, commit history, guidance, architecture, milestones and test source inspected |
| T01 | Historical decision-impact investigation | REVIEW_REQUIRED | Earlier acceptance preserved below; report not re-reviewed in this startup |
| T02 | Approved decision / canonical contract synchronization | REVIEW_REQUIRED | Branch-specific docs exist; contradictory approval statements require reconciliation |
| T03 | Laravel scaffold | REVIEW_REQUIRED | Commit exists and is in `codex/first`; prior review preserved, no fresh runtime verification |
| T04 | Tenant/branch foundation slice | REVIEW_REQUIRED | Implementation and five focused test methods exist on `codex/first`; earlier SQLite results not rerun |
| T05 | Independent code review and SQLite verification of delivered foundation/auth slice | DONE | 9056439 reviewed; both confirmed defects closed, existing suite and independent probes pass; excludes runtime acceptance |
| T06 | Staff login/logout and authorized branch-entry vertical slice | DONE | Bounded slice accepted through 37b4f6b; not integrated; does not include complete M1 RBAC/staff lifecycle |
| T07 | Complete target-engine runtime acceptance | DONE | T07A and T07B evidence reviewed and accepted |
| T07A | SQLite database-session and English/Arabic browser fallback | DONE | Reviewed 835346d and worker interaction history; explicitly excludes MySQL |
| T07B | Provision isolated MySQL 8.4 and execute remaining acceptance | DONE | 37b4f6b: MySQL runtime, InnoDB constraints, tests and browser/database-session evidence accepted |
| T08 | Local integration and versioned coordinator baseline | DONE | Fast-forwarded codex/first `03108c6` -> `37b4f6b`; coordinator files and accepted worker evidence are preserved in a focused documentation commit; orchestrator review remains pending |

`REVIEW_REQUIRED` here is the current orchestration evidence status, not rejection of previously accepted code. Supported statuses include TODO, READY, IN_PROGRESS, BLOCKED, DONE, REVIEW_REQUIRED, CHANGES_REQUIRED and REJECTED.

### Dependencies and Execution Waves

- Correction wave complete: follow-up 9056439 accepted for the two reported defects after independent checks.
- Current wave: T08 local integration executed in the isolated checkout. The accepted history was fast-forwarded, the main coordinator ledger was imported and reconciled with worker evidence, and scoped checks were run before a focused coordination-document commit. Preserve main dirty/untracked state and worker ibrahim.err. No service restart, product changes, main merge or push. Fixed-role policies/gates planning follows only after orchestrator review of this baseline.
- T02 business-contract reconciliation gates affected pricing/receipt/guardian slices; it does not automatically block a bounded M1 authentication slice.
- Before the next major decision, re-read this ledger, Git status/worktrees/history, relevant docs, implementation and tests on the selected branch.
- Review T02 contracts before dependent business slices; preserve T03 -> T04 history and schedule T05 against the actual integrated result.
- No parallel implementation wave is scheduled during runtime acceptance.

### Agent Ownership and Conflict Map

- T08 prompt issued: one integration worker imported this ledger into an isolated codex/first checkout, updated factual integration status there, and combined coordinator review entries with worker `.ai/TEST_RESULTS.md` history. This was a scoped exception to earlier ledger-write exclusions; the original main-checkout ledger and all unrelated state stayed untouched. T08 is now REVIEW_REQUIRED pending orchestrator acceptance.
- Orchestrator owns this canonical ledger, task graph, prompts, review classification and integration recommendations.
- User manages the existing auth worker session. Correction ownership: `routes/web.php`, `app/Providers/AppServiceProvider.php`, focused auth tests, and the existing three worker-owned `.ai` status files. Coordinator alone owns this ledger and review records in the main checkout. No additional worker launched by the orchestrator.
- Next proposed QA worker owns only isolated runtime/test data, reproducible QA evidence and `.ai/TEST_RESULTS.md` updates on its own branch. No application fixes, shared service changes or coordinator-ledger edits; report findings before a correction wave.
- Shared/high-risk: `.ai/*`, `docs/contracts/openapi.yaml`, permission/ERD docs, routes, migrations, dependencies and `AGENTS.md`. Assign one owner per shared file before any wave.
- Worker worktrees must receive a versioned common coordination baseline; the current ledger is untracked and will not follow a branch checkout automatically.

### Integration Order

1. Select the baseline appropriate to the user's objective and reconcile branch-specific decisions/contracts.
2. Review existing commits and acceptance evidence before recommending reuse or integration; do not rebuild an existing scaffold.
3. Integrate accepted changes in dependency order, preserving approved docs and the coordinator ledger.
4. Verify the integrated journey and relevant negative cases before marking the wave DONE.

### Risks and Decisions

- Current `main` documentation and historical ledger describe different stages. Always name the branch and commit when reporting progress.
- Application test source on `codex/first` covers identity-derived tenant context, branch assignment/active-state denial, indexes and cross-tenant foreign-key rejection. Source inspection is not a passing execution result.
- T07 MySQL 8.4/InnoDB migration, browser and database-session evidence is accepted for the bounded slice; T08 did not rerun that runtime. PHP 8.5, full M1 and production readiness remain unverified. Historical SQLite checks alone do not prove MySQL readiness.
- Preserve unrelated files, worker ledgers and stash contents. No implementation changes in this startup.
- Worker completion requires actual diff/commit and acceptance review, exact verification results, remaining risks and dependencies; this T08 commit is coordination-only and does not establish full M1, PHP 8.5 validation or production readiness.

## T08 integration result — 2026-09-10

- Created the existing `codex/first` branch checkout at `C:/Users/N/.codex/worktrees/t08-integration/PlayNexus` without overwriting an existing path, verified its starting `03108c6` ref and ancestry, then ran `git merge --ff-only 37b4f6b782c0020984567bb774d982b42428d3cb` to reach accepted `37b4f6b`.
- Imported `docs/agent-plan.md` from the main checkout and reconciled the coordinator's unique T07 review entries into `.ai/TEST_RESULTS.md` while retaining the accepted worker evidence. The source ledger hash was `E7F78F42DB8F2A308D4F2B5E54725E1BF211FE6CF4C8E0E8F4F888787E1654CF` before and after import.
- Integration rerun explicitly used process-local SQLite `:memory:` and array sessions; the tracked PHPUnit configuration was not changed and no inherited MySQL QA override was left active in the test process. The required integrated checks and their exact outcomes are recorded in `.ai/TEST_RESULTS.md`.
- Result: `REVIEW_REQUIRED` pending orchestrator review. The accepted bounded auth/branch slice and T07 MySQL 8.4/InnoDB acceptance remain intact; full M1, PHP 8.5 validation, production readiness and the policies/gates slice remain incomplete and were not started.

## T07B acceptance review — 37b4f6b, 2026-09-10

- Verified exact one-file commit, ancestry from 9056439 and fast-forward relationship from codex/first; application code unchanged since accepted correction. Worker runtime stopped; current check found no listeners on 33407/8190/8191.
- Reviewed original task execution evidence in task `01a08944-c762-7bb0-8945-a89e0044ac79` (local transcript used because app summary omitted tool outputs in the final completed turn). Runtime output shows MySQL 8.4.11, loopback 33407, 12/12 InnoDB tables and four migration rows; direct invalid cross-tenant insert returns ERROR 1452 on the expected composite FK.
- Reviewed worker test commands with process-local MySQL connection/database/port and session overrides and output: focused 19 tests/78 assertions, full 21/81 PASS. No force=true in tracked PHPUnit env entries; overrides are not a tracked config change. Reviewed database-session logout counts (0 authenticated rows, 0 branch-context rows) and Arabic DOM output (ar/rtl), together with the committed browser flow evidence. Coordinator did not restart runtime, rerun browser or independently render screenshots in this review.
- Previous environment blocker is closed for this bounded slice. PHP 8.5 target, full role policies/staff lifecycle, broader M1 and production readiness remain outside this acceptance. Do not label the entire milestone complete.
- Reproduction note for next QA run: initialize a fresh private datadir before starting a newly extracted ZIP; the worker transcript includes initialization but the short committed recipe omits that step. Existing initialized data is retained; no installation or cleanup required now.
- T08 is a recommendation, not a merge already executed. Integration must preserve accepted history c94a046 -> 9056439 -> 835346d -> 37b4f6b and the coordinator ledger, then validate the final combined state.

## T07 fallback review — 835346d, 2026-09-10

- Reviewed exact single-file commit and clean worker status. No application diff since accepted 9056439, so the unchanged full suite was not rerun by the coordinator in this turn.
- Inspected task `Implement staff authentication`, ID `01a08871-17c7-7752-8b7b-68d5d29fb433`, T07 turn `01a08915-4357-7822-b52e-6972e0d3e2ce`: completed browser calls cover English login/select/reload/logout, invalid credentials, empty assignments, foreign/unassigned navigation, revoked context, suspended-tenant logout, Arabic locale inspection and Arabic select/reload/logout. One incorrect Arabic selector was corrected in a later successful call. Review used action history and runtime output; screenshots were not independently rendered by the coordinator.
- Retained SQLite evidence exists at the recorded temporary path; read-only inspection found expected migrated tables, 0 foreign-key-check issues and 3 session rows. Row count alone is not evidence of authentication state; the worker's logout-state assertion is recorded separately.
- Worker command output confirms 21 tests / 81 assertions and formatting/docs checks. Current listener inspection found no listeners at 3306-3308 or 8187-8188; no docker/mysqld command found on PATH. These limited checks do not prove absence of every possible installation. Worker discovery identified only XAMPP MariaDB 10.4.32, not the required MySQL 8.4.
- Accepted T07A as SQLite fallback only. T07 stays blocked until T07B provisions the real target engine and verifies migrations, constraints, access denials and database-session browser flows. No application defect newly confirmed. No merge/push or shared service changes.
- T07B must prove resolved MySQL test connection rather than rely on tracked SQLite/array-session PHPUnit settings. Record exact server version, InnoDB tables, isolated host/port/schema, command results and browser evidence references. Stop only task-owned processes after verification and preserve non-secret reproduction instructions.

## Auth correction accepted — 9056439, 2026-09-10

- Verified five-file diff, parent c94a046, clean worktree; logout is auth-only with operational tenant guards intact; non-string rate-limit email input maps to empty-email/IP bucket and normal string normalization is preserved.
- Independently executed `php artisan test`: 21 tests / 81 assertions PASS; Pint PASS; documentation validator PASS (29 files, 0 errors/warnings); diff check PASS.
- Independent temporary regression probes: 2 tests / 4 assertions PASS. Logout returned 302 with authentication cleared; array email returned JSON validation failure. The probe now uses normal exception rendering for validation instead of disabling it.
- Build not repeated: correction touches no frontend or build dependency; successful c94a046 build remains applicable. No MySQL/browser/PHP 8.5 claim.
- T07 acceptance requires actual MySQL 8.4/InnoDB identity, migrations and cross-tenant constraints, and real HTTP/browser login -> branch select -> logout using database sessions, plus negative access, stale context and RTL/LTR checks. Explicitly verify resolved test configuration: tracked phpunit.xml forces SQLite/array sessions, so an ordinary green suite is insufficient MySQL evidence. Use an isolated temporary configuration; no shared DB or tracked defaults changed.
- Integration recommendation after T07 review: preserve 03108c6 -> c94a046 -> 9056439 history (then any accepted follow-ups), protect coordinator docs and unrelated untracked files, and rerun integrated checks. No merge performed now.

## Auth commit review — c94a046, 2026-09-10 (findings closed by 9056439)

- P2: `routes/web.php:18` registers logout inside `tenant.access`. After the logged-in user's tenant becomes inactive, POST `/logout` returns 404 and `auth()->check()` remains true. Move logout to an auth-only boundary while retaining CSRF and session invalidation; keep protected operational routes tenant-guarded.
- P2: `app/Providers/AppServiceProvider.php:27` casts unvalidated email input to string in throttle middleware. POST `/login` with `email` as an array raises `Array to string conversion` before `LoginRequest` can return validation errors. Safely normalize only string input for the rate key and retain normal validation/throttling.
- Independently rerun: `php artisan test` PASS (19 tests / 72 assertions); `php vendor/bin/pint --test` PASS; `npm run build` PASS (optional fontaine notice); documentation validator PASS (29 files, 0 errors/warnings); `git diff --check` PASS; worker tracked tree remains clean.
- Two additional isolated regression probes in `%TEMP%/PlayNexusAuthReviewTest.php`: 1 failed assertion (logout 404, still authenticated), 1 exception (array email at provider line 27). They use SQLite in memory and do not modify application files. Command: `php vendor/bin/phpunit --configuration phpunit.xml <probe-path>` from worker root.
- MySQL/database sessions, real-browser RTL/LTR and target PHP 8.5 remain unverified. No new full-M1 or integration acceptance claim.
- Follow-up acceptance: suspended-tenant logout succeeds, clears authentication/context and invalidates session; malformed login input receives validation failure rather than server exception; valid credentials, throttling and all existing tenant/branch denials remain covered. Worker returns files, commit, exact checks/results and remaining risks. Review corrected commit before integration.

## Historical ledger — preserved, superseded where inconsistent above

## Current Goal

Advance Milestone M0 by closing the repository's implementation-blocking Gate G1 decisions before Laravel schema/API freeze and scaffold work.

## Current Repository State

- Branch: `main`; HEAD `b6d09e1` (`docs: establish PlayNexus implementation baseline`)
- Worktrees: main checkout plus worker worktree `C:/Users/N/.codex/worktrees/344d/PlayNexus` at the same baseline commit
- Main working tree: untracked `.codex/` runtime metadata and this orchestration ledger; no implementation files changed
- Repository stage: audited documentation baseline; no Laravel application, migrations, routes, or application tests exist
- Canonical specification: `docs/`; visual baseline: `DESIGN.md`
- Milestone: M0 — decisions and scaffold
- Documentation QA is recorded as passing in `.ai/TEST_RESULTS.md`; those checks do not prove runtime behavior
- Worker report exists in the worker worktree at `deliverables/g1-decision-impact-report.md`; it is ignored by the repository's `deliverables/` rule and is not part of the main checkout

## Architecture Context

- Target: one Laravel modular monolith with Blade and selective Livewire
- Database target: MySQL; tenant-owned records require explicit tenant scoping
- Money: integer minor units plus ISO currency; timestamps stored in UTC
- Safety boundaries: authorization, guardian verification, auditability, and transactional checkout/payment/refund flows
- Unresolved decisions must not be silently implemented in schema, API, permissions, migrations, or UI

## Task Graph

- T00 — Startup repository and documentation inspection — DONE
- T01 — Reconcile M0/G1 decision register and implementation impacts — DONE (reviewed worker report)
- T02 — Synchronize approved G1 decisions in canonical docs and `.ai/DECISIONS.md` — IN_PROGRESS; baseline decisions recorded, contract sync pending
- T03 — Select exact supported Laravel/tool versions and scaffold baseline — BLOCKED; depends on T02 and remaining launch constraints
- T04 — Implement M1 foundation vertical slice and prove tenant isolation — BLOCKED; depends on T03
- T05 — Independent review and integration verification — BLOCKED; depends on T04 and subsequent slice work

## Dependencies

- T02 depends on the reviewed T01 impact matrix and now-approved baseline decisions; it still requires canonical contract synchronization
- T03 depends on T02; do not scaffold a schema/API from unresolved G1 assumptions
- T04 depends on T03
- T05 depends on integrated implementation and real verification results

## Execution Waves

### Wave 1 — Complete

- T01 — Decision-impact investigation completed; all 19 requested OQs mapped to owners, affected contracts, gate impact, minimum decisions, checks, and contradictions

### Wave 2 — In progress

- T02 — Synchronize approved decisions across canonical docs and `.ai/` records; resolve remaining contract references

### Wave 3 — Waiting

- T03 — Laravel scaffold and exact command capture

### Wave 4 — Waiting

- T04 — M1 access/branch foundation vertical slice

### Wave 5 — Waiting

- T05 — Independent review, integration, and tenant-isolation verification

## Agent Ownership

- Orchestrator: task graph, worker prompts, integration order, review, and verification
- T01: completed by investigation worker; report reviewed
- T02–T05: unassigned until their dependencies clear

## Status

- T00: DONE
- T01: DONE
- T02: IN_PROGRESS; baseline decisions approved, remaining decisions still open
- T03–T05: BLOCKED or waiting on documented dependencies
- No implementation worker is ready; documentation synchronization is the active orchestrator task

## Integration Order

1. Obtain and record authorized G1 decisions in canonical docs and `.ai/DECISIONS.md`.
2. Choose supported versions and scaffold Laravel; capture exact commands in `AGENTS.md`/README as required.
3. Implement M1 foundation with focused tenancy/authorization tests.
4. Run independent review and whole-system verification before advancing to M2.

## Risks / Decisions

- The worker report claimed `docs/agent-plan.md` was missing, but that was a stale worker-worktree observation; the file exists in the main checkout and is now reconciled.
- The worker report is evidence of analysis only, not approval of any product/legal/finance/safety choice.
- Do not invent values for payments, currencies/tax/receipts, pricing, guardian verification, child data, duplicate handling, checkout topology, incident scope, or cashier close.
- Shared files (`routes/*`, migrations, OpenAPI, permission docs, `.ai/*`) require one owner per wave.

## Decisions

- T01 is accepted as DONE after reviewing the report and validating its conclusions against the current main checkout.
- The initial baseline decisions are authorized and recorded; implementation remains blocked until affected contracts are synchronized and receipt, pricing, and guardian-verification decisions are closed.

## 2026-09-10 update

Initial MVP baseline decisions are approved and recorded. Canonical contract synchronization remains the next documentation task before Laravel scaffolding. Receipt numbering/content, guardian verification, pricing, and dependent workflow decisions remain blocked.


## 2026-09-10 final decision sync

T02 is DONE. T03 is READY to scaffold the approved Laravel baseline. Do not add online payments, offline writes, pause pricing, or unsanctioned receipt/legal integrations.


## T03 review — 2026-09-10 (supersedes earlier readiness claims)

- T03: CHANGES_REQUIRED. Scaffold exists only in C:/Users/N/.codex/worktrees/344d/PlayNexus, detached at b6d09e1, uncommitted. Not integrated.
- Reviewed composer.json, config/app.php, phpunit.xml, README.md, ignore rules, worker ledger and reported verification. Tests/build are worker-reported, not independently rerun in this review.
- Confirmed findings: application timezone Africa/Cairo contradicts UTC persistence; README install requires ignored .codex/composer.phar without provisioning instructions; file currently exists despite worker cleanup claim; worker ledger is a five-line replacement missing task graph/ownership/status.
- Root cause of missing coordination context: coordinator changes including docs/agent-plan.md remain uncommitted and were never included in the worker starting commit. This is not merely a stale observation by the worker.
- Fix wave: same T03 worker corrects UTC configuration and reproducible setup documentation, retains bounded scaffold scope, reruns targeted verification, and delivers a focused commit excluding docs/agent-plan.md and runtime/secrets. Coordinator owns the canonical ledger.
- T04: BLOCKED pending T03 review/integration and a versioned common documentation baseline. MySQL migration/runtime remains unverified; missing CLI alone does not prove no database service exists.
- T02: REVIEW_REQUIRED. Prior append-only amendments and OpenAPI extension did not reconcile existing paths/schemas or contradictory pause rules; earlier DONE/synchronization PASS claims were too strong. This must be resolved before dependent business implementation.
- Actual coordinator branch: codex/first. Do not replace current approved decisions with worker baseline .ai files during integration.


## T03 review result — 2026-09-10

- T03: REVIEW_REQUIRED pending integration. Worker commit `7866de3b7c04386621217adb8b9c43c46c588cae` on `codex/t03-scaffold-review` contains the Laravel 13.31.0 scaffold and requested corrections.
- Reviewed report: UTC application timezone, reproducible Composer prerequisite, guarded environment setup, accurate status records, and excluded orchestrator ledger/runtime artifacts.
- Worker-reported checks passed: 2 PHPUnit tests/2 assertions, Vite build, UTC config resolution, documentation validator, and diff check. MySQL migration/runtime and PHP 8.5 remain unverified/blocking.
- Integration rule: preserve main-branch approved decision docs and canonical `docs/agent-plan.md`; integrate only the worker commit's scaffold/status files after a clean diff review. T04 remains blocked until integration and test rerun.

## T04 review result — 2026-09-10

- T04: DONE for the SQLite-verified M1 slice after follow-up commit `03108c6a7c07698bb9271a82d32d8ab4ceb1bea7`.
- Integrated into `codex/first` by fast-forward from `739df0e` to `03108c6`.
- Tenant context is user-derived; branch assignment is active/tenant-scoped; composite foreign keys reject cross-tenant pivot rows; focused suite reports 5 tests/15 assertions and full suite 7 tests/17 assertions.
- MySQL migration/runtime remains BLOCKED_BY_ENVIRONMENT and must be verified before production readiness, but does not block planning the next isolated M1 slice.
- T05 review/integration for this slice is complete enough to advance; next objective is the smallest approved M1 access/branch UI or authentication boundary slice, selected from the repository after inspecting current gaps.

## 2026-09-12 T14 approved implementation contract

Under the user's authorization to execute the proposed three-worker owner-read wave, the coordinator selects the narrow explicit equivalent allowed by T12: `tenant_owners` contains non-null unsigned-bigint `tenant_id`, `user_id`, timestamps; composite primary key `(tenant_id,user_id)` and composite foreign key to `users(tenant_id,id)` with cascade delete. No backfill/inference from branch roles, no generic RBAC tables or assignment endpoint. This is an implementation decision for this slice, not approval of the entire draft matrix. Existing users remain non-owners until explicitly provisioned outside this UI.

Native `TenantPolicy::view(User,Tenant)` grants only a freshly queried active user whose persisted tenant matches the active target tenant and whose exact tenant_owners row exists. Unknown/null/platform identities and branch-only owner strings deny. Policy foreign/inactive scope is denyAsNotFound (404); in-scope missing owner assignment is 403. Existing tenant middleware enforces account revocation and inactive tenant behavior. Do not grant new branch access.

GET `/app/tenant`, route `tenant.show`, existing `auth` + `tenant.access`, resolves tenant from a freshly queried authenticated user (never request IDs), authorizes view, renders `tenant.show`. View data: `$tenant` (Tenant), `$staff` (LengthAwarePaginator of only id, name, email, status; tenant-filtered users, order by id, 25/page). User query/body tenant identifiers are ignored and never switch scope. No API/JSON expansion. UI navigation from dashboard appears only for policy-authorized owner; page has own tenant name, read-only staff name/email/localized status, empty state, pagination, locale controls and return-to-dashboard. Existing locale POST redirect remains unchanged.

T15 owns new migration, TenantPolicy, TenantReadController, provider policy registration, routes only (User model only if essential). T16 owns resources/views/tenant/show.blade.php, dashboard Blade navigation, lang/en/tenant.php and lang/ar/tenant.php only. T17 owns tests/Feature/TenantOwnerReadTest.php only. Each commits in its isolated branch; no shared .ai edits. Tests cover constraints, owner without branch assignment, staff and spoofed-role denials, fresh revocation, inactive account/tenant, foreign policy 404, request-ID isolation, scoped pagination and en/ar rendering. Run executable combined tests only after dependencies integrate; missing peer implementation is not PASS. Browser explicitly deferred. Coordinator reviews actual commits, integrates locally on codex/first and records one combined verification. No main merge or push.

## 2026-09-12 T18-T20 parallel feature contract

User authorized three Luna/xhigh agents for the next feature wave. Each delivers a complete feature (backend, UI, focused tests) in its own worktree from this contract commit. Browser remains DEFERRED_BY_USER. Coordinator alone edits shared docs/status, web route composition, provider registration and tenant-profile navigation; no main merge/push/shared DB. Workers do not install dependencies: parent executes focused feature suites and one combined suite after integration using existing vendor. Worker delivery must list exact base/SHA/files, implemented acceptance behaviors, actual checks, missing checks and REVIEW_REQUIRED. No result is accepted solely from a progress claim.

### T18 owner branch access (A)

Explicit active tenant_owners grants view/list/select of ALL active branches in the same active tenant without branch_user rows. Staff retain current assignment/role rules and 404 scope/403 permission semantics. Fresh user, tenant, branch and ownership state must be used. Remove owner privilege on next request when ownership is revoked; selected context survives only if remaining staff assignment independently permits it. Foreign/inactive branches stay 404. No branch writes, no new staff permissions. Own User.php only if needed (keep activeBranches assignment semantics; add clearly named accessibleBranches query if needed), BranchPolicy, BranchContextController, EnsureBranchAccess, EnsureTenantAccess only for branch-context validation, dashboard branch copy if needed, new OwnerBranchAccessTest and adjust only obsolete owner-no-branch expectation in TenantOwnerReadTest. No TenantPolicy/route/provider/audit edits. Test owner list/select/direct read/reload, inactive/foreign denial, revocation, staff fallback and existing staff regressions. Existing dashboard is the UI; no redundant page.

### Shared owner administration contract for B/C

Fresh active owner of own active tenant only; authorize via existing TenantPolicy::view as the exact owner predicate (do not broaden it). Owner assignment management and platform identities are excluded. All targets resolve within authenticated tenant BEFORE mutation; foreign/null tenant IDs return404, same-tenant non-owner403. Deny managing any user with tenant_owners row, including self (403), so owner transfer/last-owner semantics cannot be bypassed. No request-writable tenant_id, owner flag, password or arbitrary role map. New pages are under existing auth + tenant.access route group.

Writes run in DB::transaction. Lock active tenant row first, then refresh/authorize actor and owner assignment, then lock target user; C then locks/rechecks branch and pivot. All these admin writes lock the same tenant row to serialize this bounded low-volume operation. Revalidate actor status, tenant ownership and target scope INSIDE transaction. Check explicit expected state and return409 if stale; invalid form422 JSON / standard redirect+errors HTML. Require reason_code in staffing_change/access_review/correction. Append successful state-changing audit row in SAME transaction; no-op does not claim/change/audit a mutation. Audit failure rolls back mutation. Generate UUID request_id server-side; no client-supplied correlation trust. audit_logs schema is preprovided. Snapshots only status or branch_id/role/is_active, no names/emails/passwords. actor_type=user, outcome=success, occurred_at UTC; no audit update/delete route. This is successful-admin-mutation audit only, not complete denial/security audit coverage.

### T19 existing staff account status (B)

GET `/app/staff` staff.index, PATCH `/app/staff/{user}/status` staff.status. New routes/staff.php loaded by parent inside auth+tenant.access. Own StaffStatusController, new views/staff/index.blade.php, lang/en/staff.php lang/ar/staff.php, tests/Feature/StaffManagementTest.php and routes/staff.php only. Tenant-filtered list 25/page, display name/email/status, skip mutation controls on self/owner rows. Update to active/suspended/disabled only, expected_status required one of invited/active/suspended/disabled. No user creation/email/password reset/invitation sending. Action staff.status.changed; subject_type=user, subject_id target ID, branch_id null, before_json/after_json {status}. Inline localized reason/status controls, validation/success feedback, fixed route redirect and accessible page navigation. Tests owner success, nonowner/owner target/foreign deny, invalid input, stale409, audit contents/rollback, and target's already-authenticated session denied on next protected request. User.status remains outside mass assignment.

### T20 fixed branch-role assignments (C)

GET `/app/assignments` assignments.index; PUT `/app/assignments/{user}/{branch}` assignments.update. New routes/assignments.php loaded by parent inside auth+tenant.access. Own BranchAssignmentController, views/assignments/index.blade.php, lang/en/assignments.php lang/ar/assignments.php, tests/Feature/BranchAssignmentManagementTest.php and routes/assignments.php only. Read page selects one same-tenant non-owner staff member via optional user_id; paginate staff picker25 to avoid loading all users; show active branches with current pivot role/state using scoped queries. Missing choice shows useful selection state. Updates may assign/change/reactivate/revoke pivot; do not delete rows. Allow only new role branch_manager/reception_staff/cashier, is_active boolean; legacy reception remains readable but not a new grant. Required expected_role nullable string(max50) and expected_is_active nullable boolean: both null means no previous pivot, otherwise match current role/state or409. Target account must be active, branch active/same tenant (inactive branch404; inactive target409); no owner targets. Action staff.branch_assignment.changed; subject_type=user subject_id target, branch_id branch; snapshots absent=null or {branch_id,role,is_active}. Test grant/change/revoke, no permission via arbitrary roles, foreign user/branch404, nonowner/owner target deny, inactive state, stale write409, audit/rollback, revoked role/context denied next request. Do not edit User, BranchPolicy or existing middleware; A owns those.

### Coordinator acceptance

Inspect each exact diff and tests, return only concrete defects to owner, merge independently delivered commits (A then B then C). Register route files and owner navigation centrally. Run focused new tests and full suite, Pint/build/docs/diff once; rerun only checks affected by fixes. Record actual evidence and clear incomplete scope. No manual/browser/MySQL acceptance claims.
## 2026-09-12 M1/M2 audit remediation

The confirmed remediation scope separates family action abilities, denies Cashier generic guardian/child/relationship maintenance, centralizes role-aware server-side family presentation, filters inactive family search records, controls staff-email uniqueness races, makes branch creation retry-safe with a tenant-scoped creation key, and improves warning/mobile navigation/assignment responsiveness. Consent, emergency/safety data, guardian merge, relationship endpoints, visit history, and all M3 expansion remain excluded pending their existing decisions and acceptance gates.
