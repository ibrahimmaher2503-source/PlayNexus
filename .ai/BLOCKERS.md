# Blockers

## 2026-09-14 M0 closure gate

M0 repairs and their local SQLite/MySQL 8.4 equivalents pass. The sole M0 closure blocker is external: `.github/workflows/ci.yml` has not yet run on the hosted provider because this local review did not push. Composer is documented as a workstation prerequisite; strict validation passed through the available local phar.

A diagnostic full MySQL run found two M4 assertion mismatches and one M4 concurrency-test query error. They do not invalidate the focused M0 MySQL gate and were intentionally not repaired during the M0-only review; resolve them when M4 is the approved review scope.

## 2026-09-14 M4 runtime-evidence gates

M4 implementation is locally accepted: lifecycle HTML posts now redirect correctly, independent idempotency keys prevent cross-action replay conflicts, and the quote calculator moves the overtime boundary when extensions are applied. Focused SQLite coverage is 26 passed plus one explicit MySQL-only concurrency skip (197 assertions); scoped Pint, Vite, Blade, routes, documentation and whitespace checks pass.

The remaining runtime gate is the isolated MySQL 8.4/InnoDB listener at `127.0.0.1:33417`, which is not running, so lock/concurrency evidence has not been refreshed. Authenticated browser acceptance is now PASS on isolated SQLite at `127.0.0.1:8215` with a synthetic Owner: extension, last-four guardian verification, frozen invoice, `pending_payment`, English LTR and Arabic RTL all rendered and behaved as expected. M5 payment/completion, receipt, refund, child release, shifts, pause/resume and provider notifications remain explicitly out of scope.

## 2026-09-13 M4 preparation accepted; M5 gates

No local engineering blocker remains for M3 pricing, tickets, ticket-backed check-in, hard branch capacity, masked live board or the read-only live estimate. Exact time/tax fixtures, non-mutation, malformed-snapshot recovery, focused/full SQLite, PHP 8.5, full isolated MySQL, true multi-process concurrency and authenticated bilingual responsive Edge QA all pass.

The first M4 preparation slice is locally accepted by focused SQLite tests (8 tests / 66 assertions): Reception/Manager verification, exact frozen quote, cashier denial, manager override reason/audit, stale lock, idempotent replay/conflict, terminal state, and safe guardian failures. OQ-19 is resolved as reception-to-cashier handoff; M5 still must implement matching payment posting and atomic completion. Full regression, isolated MySQL, browser, payment/refund/receipt/shift, and production acceptance remain open. OQ-09 continues to gate financial refund execution. Production remains gated by the selected runtime database/migration plan, Legal/DPO approval, deployment security, monitoring and operational drills.

## 2026-09-13 historical ticket-only gates

No local engineering blocker remains for the ticket-only type/issue/QR/validate/correct/cancel/reprint boundary: full isolated MySQL and SQLite gates, true MySQL multi-process retry evidence, and scoped authenticated headless-browser visual/QR/print QA pass. Interactive CUA remains unavailable (`User unavailable`); full M2 browser UAT is still outstanding because the ticket journey does not cover it.

At this ticket-only checkpoint, M3 still required ticket consumption/check-in/session creation, capacity/live board and approved tax calculator fixtures. The current section above supersedes that historical state. OQ-09 and posted-payment reversal implementation still gate actual financial refunds; ticket cancellation must not be presented as refunded money. Existing runtime/database 8206 needs the new migrations against an explicitly selected database; no shared-runtime migration or restart was performed. Production Legal/DPO, deployment/security and operational gates remain open.

## 2026-09-13 M3 ticket boundary

OQ-18 is closed: Egypt MVP tickets are branch-specific, service-date-bound, transfer-locked after the first successful scan, and refund-eligible only while unused with in-scope manager/owner approval, reason, audit, and a linked reversal when paid. Ticket/QR/check-in implementation is now unblocked. OQ-09 still blocks the exact refund window, payment method, and execution mechanics; finance-approved worked tax examples still block calculator/checkout acceptance.

## 2026-09-13 Egypt M2 release gates

No product-decision or core implementation blocker remains for the approved M2 family contract. Full SQLite and isolated MySQL 8.4.11/InnoDB regression passes 241 tests / 1,997 assertions. Remaining release gates are real browser acceptance and production Legal/DPO approval of the deployed Arabic-first notice, controller/DPO identity, processors, transfers, retention operation, and marketing-provider/licensing details. Visit history and automated retention execution depend on M3 session/last-visit data by approved waiver; they do not block continued local development.

## 2026-09-12 remediation boundary

Fresh SQLite and isolated MySQL 8.4.11/InnoDB acceptance are complete at 234 tests / 1,943 assertions. OQ-17, the Egypt consent/retention baseline, emergency/safety policy, relationship lifecycle, visit-history dependency, and OQ-20 deferral are now product-approved. Remaining M2 blockers are implementation/tests of the newly approved contract, real browser acceptance, and production Legal/DPO approval of the actual notice/controller/processor/transfer/licensing details. These gates do not authorize M3 expansion.

## 2026-09-12 M3 first-slice boundary

No blocker prevents immutable fixed-duration pricing-rule creation/listing/version replacement because OQ-16 is approved. OQ-18 is now closed and ticket/QR/check-in implementation may proceed within the approved branch/date/transfer/refund-eligibility contract. OQ-09 and finance-approved worked tax examples remain required before refund execution and tax-total calculation/checkout acceptance. The browser bridge is currently unavailable. Fresh isolated MySQL 8.4.11/InnoDB acceptance passes for the current repository.

## 2026-09-12 M2 start boundary

No product-decision blocker remains for the M2 family-registry/profile contract. The first slice predates the approved Egypt contract and must now be extended with tenant-phone uniqueness, versioned consent/withdrawal, emergency/safety fields, and relationship lifecycle before M2 completion.

## 2026-09-12 M1 closure

No blocker remains for M1 local acceptance. T30 MySQL, T31 PHP 8.5, and T32 browser acceptance are closed. Production readiness remains blocked on deployment-specific TLS, managed secrets, monitoring, backup/restore, and operational approval. M2 and later schema/API freeze still depend on their documented product/legal decisions; they do not reopen M1.

## 2026-09-12 current runtime blocker

T30 MySQL 8.4 and T32 browser acceptance are closed. PHP 8.5 compatibility (T31) remains unverified; full milestone and production readiness must not be claimed from the completed local slice.

No blocker prevents documentation delivery.

Laravel implementation is blocked from schema/API freeze by the open decisions in `docs/00-INDEX.md`, especially payment mode, launch country/currency/tax/receipt rules, pricing examples, guardian verification, checkout/payment topology, child/DOB and duplicate policy, and incident/shift scope. Owners and gates are recorded in the BRD OQ-01–OQ-24 register.

## 2026-09-10 update

The initial MVP baseline decisions are now recorded in `.ai/DECISIONS.md`: Egypt/EGP/Cairo, Arabic/English, online-only, record-only payments, keyboard-input QR/barcode, branch-scoped roles, default-deny support access, minimum-data privacy baseline, optional child DOB, and branded SaaS only.

OQ-08 receipt numbering/content, OQ-12 guardian verification, and OQ-16 pricing rules are now approved and synchronized. Remaining blockers are Laravel scaffolding prerequisites, legal/tax integration confirmation, and later M2–M6 operational decisions not needed for the foundation slice.
## 2026-09-12 M2 remediation boundary

The confirmed Cashier authorization and JSON PII defects are closed in code and full SQLite/MySQL regression. The formerly open M2 product decisions are now approved in `.ai/DECISIONS.md`; implementation, negative tests, real browser acceptance, and production Legal/DPO sign-off remain. Visit history is explicitly waived until M3 provides sessions, and incidents/photos/merge remain deferred.
