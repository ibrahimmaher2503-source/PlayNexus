# Wave 01 - Foundation, Access and Configuration

**Date:** 2026-09-17  
**Scope:** Platform regression; Tenant profile and onboarding; Branch configuration; authentication/account security; Staff/RBAC.  
**Final Wave status:** `CLOSED_WITH_BLOCKERS`  
**Production Ready:** No.

## Scope and initial state

Checkpoint 01 Platform Administration was an `IMPLEMENTED` protected baseline. Tenant/Branch configuration existed but the onboarding path was fragmented and the active-session branch-deactivation rule remained undecided. Authentication lacked the approved OQ-22 idle controls and mandatory Platform MFA. Staff status changes invalidated sessions, but reactivation and identity changes needed stronger revocation and the Staff identity workflow lacked a UI.

OQ-04 plan/subscription design was deliberately not reopened. Existing branch/user limit guards were exercised only as integrations.

## Defects found and changes made

- Added the approved 30-minute staff and 15-minute privileged idle controls with fail-closed session clocks. The unapproved absolute lifetime remains configurable and unset.
- Added mandatory Platform MFA and configurable Owner MFA using `pragmarx/google2fa`, encrypted secrets/recovery-code hashes, replay-resistant counters, one-use recovery codes, audit and auth-version rotation.
- Isolated MFA throttling by authenticated account and IP. One account behind a shared IP cannot exhaust another Tenant or Platform Admin's factor bucket.
- Raised reset passwords to the approved 12-character minimum and retained generic, throttled reset responses.
- Added Owner-only Staff identity edit UI with optimistic conflict checks, tenant scoping, session/reset-token revocation on email change and privacy-safe audit evidence.
- Made every real Staff status transition advance `auth_version`; reactivation cannot revive an old session. A Manager cannot globally suspend Staff who also have an active assignment outside the Manager's scope.
- Preserved correlation/request IDs in Tenant, Branch, Staff, role and assignment audit events.
- Added an Owner-only read-only setup guide derived from current Tenant records. It links profile, branch readiness, Staff, pricing and catalog preparation without creating a second source of truth or inventing activation gates.
- Extracted `BranchReadiness`, so activation and the setup guide use one exact predicate.
- Added IANA timezone suggestions and clearer required/identity/regional grouping to configuration forms.
- Fixed a mobile/tablet RTL Branch Assignment overflow and removed duplicate native/custom Staff confirmation.
- Made refund eligibility use the immutable receipt timezone rather than mutable Branch settings, keeping historical business-day treatment stable.

## Backend, security/RBAC and data verification

- Platform and Tenant sessions use separate access middleware and version keys; Platform MFA is required before Platform operations.
- Tenant context is derived from the authenticated User. Tenant, Branch, Staff and assignment identifiers are scoped again under locks before writes.
- Owner, Manager, Reception and Cashier negative paths were exercised. Unknown roles/permissions fail closed.
- Staff/Branch relationships retain tenant composite constraints and historical inactive pivots; no operational/financial cascade deletion was added.
- Tenant/Branch/Staff writes remain atomic and use optimistic state/version inputs where applicable.
- Platform suspension, Staff suspension/reactivation and assignment removal revoke established access on the next request.
- Manual inspection found and corrected the receipt-timezone drift. Historical report regrouping after a Branch timezone change remains a separate policy decision, not an inferred change.

## UI/UX and accessibility

Actual Microsoft Edge CDP journeys covered Platform and Tenant login, mandatory MFA, setup/profile, Branch CRUD/settings/status/selector/history, Staff create/edit/status, role/assignment changes, Manager/Reception/Cashier authorization, reset failure, English, Arabic RTL and 390/820/1440 px layouts. The final responsive recheck found no horizontal overflow. Console errors and HTTP 5xx responses were empty.

Forms expose labels, field-linked errors, status messages, focus rings, non-color status text and confirmations. The shared dialog traps/restores focus. Formal automated accessibility scanning, screen-reader acceptance and production UAT remain release gates.

## Audit

Wave-sensitive changes record actor, Tenant/Branch/subject, action, outcome, safe before/after, reason and request correlation where applicable. Authentication/MFA failures and successes use the existing authentication/platform audit mechanisms. Ordinary application routes do not expose audit mutation.

## Browser journeys

- Platform Admin password login, MFA enrollment/recovery display and Tenant list regression.
- Tenant Owner login; setup guide; invalid then valid profile edit.
- Branch draft creation; invalid capacity; valid New York timezone/tax/receipt configuration; activate, select, deactivate, prove operational denial and historical report access, reactivate.
- Staff create and identity edit; Cashier then Reception assignment; custom role creation.
- Manager assigned-Branch list and unassigned/revoked Branch 404 while remaining assigned Branch list stays available.
- Reception Family navigation allowed and Tenant settings denied; Cashier POS allowed and Branch/Staff administration denied.
- Established Staff session revoked by suspension and not restored by reactivation.
- Generic reset request and tampered reset token error.
- English LTR and Arabic RTL at 390, 820 and 1440 px for setup, profile, Branch, Staff, assignments, roles and MFA.

Evidence: `deliverables/qa/wave-01/browser-results.json`, `deliverables/qa/wave-01/assignment-recheck.json`, and screenshots in the same directory.

## Security / negative tests

Cross-Tenant and cross-Branch identifiers, unauthorized Platform access, Owner-target mutations, Manager out-of-scope Staff/Branches, Reception/Cashier administration, invalid roles/permissions, MFA replay/recovery reuse, reset enumeration/tampering, stale writes and already-established revoked sessions are covered by focused tests. The browser run independently exercised representative role and established-session denials.

## Automated tests and regression

- Focused Wave regression: 138 tests, 1,290 assertions, pass.
- MFA/session/refund-timezone binding regression: 24 tests, 153 assertions, pass.
- Final full SQLite suite: 473 total, 469 passed, four MySQL-only skipped, 3,859 assertions.
- `vendor/bin/pint --test`, `npm run build`, `php artisan view:cache`, Composer audit and npm audit pass.
- Documentation validator passes 42 Markdown files with zero errors and two pre-existing placeholder warnings; `git diff --check` passes with line-ending warnings only.
- The safe-database guard rejected the current non-disposable MySQL configuration. No current Wave MySQL result is claimed.

The final browser command and exact result are recorded in `.ai/TEST_RESULTS.md`; previously closed Platform, support-access and downstream Branch-dependent modules stayed green in the full regression.

## Remaining decision blockers

1. **Branch deactivation with an already-active session:** approved sources do not choose whether to block deactivation or allow a controlled handoff/continued checkout. New work is blocked and history is preserved; no in-flight-session policy was invented.
2. **DEC-SEC-01:** absolute authenticated-session lifetime is not approved. Idle timeouts are enforced; `AUTH_ABSOLUTE_MINUTES` remains unset by default and fails closed if malformed.
3. **DEC-SEC-02:** the exact complete rate-limit matrix is not approved. Login, reset and MFA have safe bounded baselines; the remaining values/routes await approval.

## Remaining production gates

- Current Wave changes were not rerun against MySQL or staging.
- Production TLS/cookie/secrets configuration, formal Security review and SAST/DAST remain.
- Formal accessibility scan, keyboard/screen-reader acceptance and moderated UAT remain.
- Exact absolute timeout, rate matrix and audit/log retention require the registered owners.
- Hosted CI, deployment rehearsal, load acceptance and final go/no-go remain.

## HTML task mapping

Status terms below are the Master HTML values. Approved local behavior is complete for status منفذ; it is not Production Ready.

| Section | Task | Status | Evidence | Gap |
|---|---|---|---|---|
| 01 Platform | super admin login ومساحة platform منفصلة عن tenant users. | منفذ | Platform routes/middleware, MFA, Platform tests and Edge login | Production security/UAT only |
| 01 Platform | create tenant مع idempotency وعدم إنشاء duplicate tenant. | منفذ | `PlatformTenantController`; idempotency/atomic tests | Production environment gate |
| 01 Platform | دعوة أول tenant owner بصورة صحيحة وآمنة. | منفذ | Hashed one-use invitation and acceptance tests | External delivery provider is separately scoped |
| 01 Platform | activate / suspend / reactivate tenant مع reason وaudit. | منفذ | Locked status action, auth-version revocation, audit and regression | Production UAT only |
| 01 Platform | عرض قائمة tenants مع search/filter/status/pagination. | منفذ | Platform list controller/UI/tests and Edge regression | Production UAT only |
| 01 Platform | tenant details page تجمع plan/subscription/usage/branches/users/health. | جزئي | Safe detail/usage page exists | OQ-04 commercial summary remains separately PARTIAL |
| 01 Platform | منع super admin من الوصول الدائم لبيانات tenant التشغيلية. | منفذ | Separate context plus negative/support-access tests | Production security sign-off |
| 01 Platform | break-glass/support access: reason + scope + expiry + revoke + audit. | منفذ | Scoped grants, expiry/revoke/audit and tests | Production Legal/Security sign-off |
| 01 Platform | tenant-visible support-access history. | منفذ | Owner-only scoped history page and tests | Production UAT only |
| 01 Platform | platform-wide settings تكون محدودة ومؤمنة ومراجعة. | منفذ | Only approved bounded controls/configuration exist; protected actions are authorized/audited | Broader generic settings are not approved scope |
| 01 Platform | super admin ui واضح ولا يخلط platform controls مع tenant operations. | منفذ | Separate layout/routes and current Edge regression | Formal accessibility/UAT gate |
| 04 Onboarding | onboarding wizard أو flow واضح بعد قبول الدعوة. | منفذ | Owner setup guide links the existing secured workflows | Provider delivery remains separate |
| 04 Onboarding | business name/contact/legal/receipt identity. | منفذ | Display/legal name, initial active Owner contact and immutable Branch receipt identity are shown/consumed | Production legal wording approval |
| 04 Onboarding | default locale / country / currency / timezone. | منفذ | Editable locale/IANA timezone, EGP and fixed approved Egypt V1 country context | Future multi-country behavior is outside V1 |
| 04 Onboarding | minimum setup validation قبل التشغيل. | منفذ | Shared `BranchReadiness` activation gate | In-flight deactivation policy separate |
| 04 Onboarding | onboarding progress/checklist. | منفذ | Dynamically derived Owner setup guide; no duplicate state | Production UAT only |
| 04 Onboarding | initial branch creation. | منفذ | Safe inactive draft create plus activation readiness | Production UAT only |
| 04 Onboarding | initial admin/staff setup. | منفذ | Setup link plus scoped Staff/assignment UI | Delivery transport separate |
| 04 Onboarding | initial pricing/catalog setup. | منفذ | Setup links current pricing/catalog UIs and counts | Not a Branch activation gate |
| 04 Onboarding | operational readiness checklist قبل تفعيل الفرع. | منفذ | One shared readiness predicate in UI/domain | Production UAT only |
| 04 Onboarding | audit لتعديلات البيانات الحساسة. | منفذ | Profile/Branch/Staff/a11y-safe audit tests | Retention is separate decision |
| 04 Onboarding | ui لا يطلب من صاحب المكان معلومات تقنية غير ضرورية. | منفذ | Guided IANA inputs, grouped fields, setup workflow, browser review | Moderated UAT gate |
| 05 Branches | create / edit / activate / deactivate branch. | منفذ | Controllers/UI/locked mutations and browser journey | Active-session policy blocker noted separately |
| 05 Branches | branch belongs to tenant الصحيح دائمًا. | منفذ | Tenant-scoped binding/policies/composite ownership tests | Production security sign-off |
| 05 Branches | address/location text. | منفذ | Validated Branch setting and UI | N/A |
| 05 Branches | opening hours and closed days. | جزئي | Seven-day validated locale-neutral model/UI | Enforcement beyond readiness not claimed |
| 05 Branches | capacity. | منفذ | Positive validation and transactional check-in guard | Production load gate |
| 05 Branches | timezone. | جزئي | IANA validation, UTC timestamps and snapshot integrations | Historical report regrouping policy unresolved |
| 05 Branches | currency. | منفذ | EGP config, integer money and historical snapshot tests | Finance sign-off |
| 05 Branches | tax mode/rate configuration. | منفذ | bps validation and immutable pricing/receipt snapshots | Finance sign-off |
| 05 Branches | receipt settings. | منفذ | Prefix/identity/timezone consumed by receipt generation | Legal/Finance wording approval |
| 05 Branches | permitted payment methods. | منفذ | Cash-only Branch validation and settlement recheck | Online capture deferred |
| 05 Branches | branch-specific pricing/catalog availability. | محجوب بقرار | Branch-scoped pricing/catalog policies and tests | Production UAT |
| 05 Branches | inactive branch يمنع sessions/orders الجديدة ويحافظ على التاريخ. | منفذ | Operational writes denied; report history retained and browser-tested | In-flight active-session consequence needs decision |
| 05 Branches | currency change guard بعد وجود posted finance. | منفذ | Server guard and immutable historical currency tests | N/A |
| 05 Branches | branch selector يعمل حسب role/scope. | منفذ | Fresh assignment checks and browser revocation | N/A |
| 05 Branches | branch settings ui واضح مع validation. | منفذ | Field errors, sections, confirmations, EN/AR responsive Edge pass | Formal accessibility/UAT gate |
| 06 Auth | login للactive user والactive tenant فقط. | منفذ | Credential controller plus fresh User/Tenant middleware checks | Deployed cookie/TLS gate |
| 06 Auth | generic login errors بدون user enumeration. | منفذ | Generic responses and negative tests | N/A |
| 06 Auth | logout يبطل session فعليًا. | منفذ | Session invalidate/token regenerate test | N/A |
| 06 Auth | password reset token single-use + expiry. | منفذ | Broker token flow and tests | Production mail delivery separate |
| 06 Auth | password reset لا يكشف وجود الحساب. | منفذ | Generic response and throttling tests/browser | N/A |
| 06 Auth | revoke existing sessions بعد password security reset. | منفذ | `auth_version` mutation/middleware tests | N/A |
| 06 Auth | revocation عند user suspension / tenant suspension. | منفذ | Established-session Staff/Tenant revocation tests/browser | N/A |
| 06 Auth | session idle timeout والabsolute timeout. | محجوب بقرار | Approved 30/15-minute idle controls pass | DEC-SEC-01 absolute duration open |
| 06 Auth | secure cookies / csrf / rotation. | منفذ | CSRF middleware and session rotation covered locally | Production HTTPS/cookie configuration unverified |
| 06 Auth | mfa للsuper admin وإذا اعتمد للtenant owner. | منفذ | Encrypted/replay-safe MFA, recovery, audit, tests and Edge enrollment | Owner production enablement is configurable |
| 06 Auth | rate limiting للlogin/reset والواجهات الحساسة. | محجوب بقرار | Login/reset/per-user MFA baseline and isolation test | DEC-SEC-02 exact complete matrix open |
| 06 Auth | security events audit. | منفذ | Authentication/MFA/denial audit with correlation | DEC-SEC-03 retention open |
| 06 Auth | login/reset ui واضح بالعربي والإنجليزي. | منفذ | EN/AR login/MFA/reset browser views and field errors | Formal accessibility/UAT gate |
| 07 Staff/RBAC | invite/create staff داخل tenant الصحيح. | منفذ | Tenant-scoped creation/reset credential path and tests | External delivery provider separate |
| 07 Staff/RBAC | assign multiple roles/branches حسب القرار. | منفذ | Composite pivot workflow and OQ-10 tests | N/A |
| 07 Staff/RBAC | tenant owner permissions. | منفذ | Policies and negative Platform/cross-Tenant tests | N/A |
| 07 Staff/RBAC | branch manager permissions داخل assigned branches فقط. | منفذ | Fresh scoped policy, cross-assignment guard and Edge 404 | N/A |
| 07 Staff/RBAC | reception permissions. | منفذ | Permission Matrix tests and Edge allow/deny journey | N/A |
| 07 Staff/RBAC | cashier permissions. | منفذ | Permission Matrix tests and Edge allow/deny journey | N/A |
| 07 Staff/RBAC | custom tenant roles لو معتمدة. | جزئي | Bounded enforced `branches.view` role UI/tests | Catalog expansion requires enforced actions first |
| 07 Staff/RBAC | permission keys تغطي كل protected action. | جزئي | Every current protected Wave action is covered by policy/middleware; custom keys are exposed only when enforced | Future modules must add enforcement before exposing new keys |
| 07 Staff/RBAC | default deny server-side. | منفذ | Unknown/unassigned roles denied in policy tests | N/A |
| 07 Staff/RBAC | id/url/body tampering لا يوسع scope. | منفذ | Binding/policy/adversarial tests | Production security sign-off |
| 07 Staff/RBAC | role/branch assignment changes apply في interval معتمد. | منفذ | Effective on next request; established browser context loses Branch | N/A |
| 07 Staff/RBAC | suspend/reactivate staff مع بقاء attribution التاريخي. | منفذ | Status/audit/auth-version behavior and browser journey | N/A |
| 07 Staff/RBAC | manager approval workflow للأفعال الحساسة. | منفذ | Discount/refund/safety approval records and regressions | Operations UAT |
| 07 Staff/RBAC | self-approval rules واضحة. | منفذ | Requester/reviewer separation tests | Operations UAT |
| 07 Staff/RBAC | requester + approver + reason محفوظين. | منفذ | Immutable approval/audit records | Retention policy open |
| 07 Staff/RBAC | staff management ui لا يسمح privilege escalation. | منفذ | Allowlisted role/body checks plus Owner/Manager browser paths | Formal security sign-off |
| 07 Staff/RBAC | permission matrix متطابقة مع code/tests/ui. | منفذ | Owner/Manager/Reception/Cashier and bounded custom role agree across routes, policies, tests and rendered controls | Recheck when later modules add actions |

### Cross-cutting Wave 1 tasks

| Section | Task | Status | Evidence | Gap |
|---|---|---|---|---|
| 32 Localization | arabic translations complete for critical flows. | منفذ | Wave pages and validation exercised in Arabic | Legal wording approval remains |
| 32 Localization | english translations complete. | منفذ | Wave pages and validation exercised in English | N/A |
| 32 Localization | rtl layout. | منفذ | Edge 390/820/1440 RTL; overflow recheck clean | Formal accessibility gate |
| 32 Localization | ltr layout. | منفذ | Edge 390/820/1440 LTR | Formal accessibility gate |
| 32 Localization | mixed arabic/latin names. | منفذ | Demo Latin identities rendered in RTL with bidi isolation | N/A |
| 32 Localization | unicode search. | منفذ | Scoped escaped Staff/Tenant search and localized browser UI | Production-volume testing |
| 32 Localization | branch timezone display/filter. | منفذ | Branch-local clocks/dates and IANA timezone usage | Historical report rule open |
| 32 Localization | utc storage. | منفذ | UTC audit/auth/operational timestamps and local rendering | Production DB verification |
| 32 Localization | currency formatting. | منفذ | Integer EGP display helpers and browser pages | Finance sign-off |
| 32 Localization | tax/receipt wording configurable. | منفذ | Branch tax mode/rate/prefix settings and snapshots | Legal/Finance sign-off |
| 32 Localization | phone normalization. | منفذ | Session locale switch exercised across Wave pages | N/A |
| 32 Localization | locale switch persists. | منفذ | Integer values and locale regression tests | Production UAT |
| 32 Localization | no hard-coded user-facing business copy in critical flows. | منفذ | Wave views use translation resources | Repository-wide later modules reviewed separately |
| 33 UI/UX | global navigation منطقي حسب role. | منفذ | Permission-aware navigation and role browser journeys | UAT |
| 33 UI/UX | role-based landing/dashboard. | منفذ | Owner/Manager/Reception/Cashier dashboards exercised | UAT |
| 33 UI/UX | consistent spacing/typography/components. | منفذ | Existing DESIGN tokens/components reused | UAT |
| 33 UI/UX | form patterns موحدة. | منفذ | Consistent labels/errors/save bars/pending state | N/A |
| 33 UI/UX | error messages plain/actionable. | منفذ | Field and business errors exercised | UAT |
| 33 UI/UX | loading states. | منفذ | Shared submit pending/aria-busy behavior | Slow-network formal test later |
| 33 UI/UX | empty states. | منفذ | Staff/Branch/assignment empty states | UAT |
| 33 UI/UX | success states. | منفذ | Profile/Branch/Staff flash/status feedback | N/A |
| 33 UI/UX | conflict/stale-data states. | منفذ | Expected version/state 409 coverage | N/A |
| 33 UI/UX | confirmation for destructive/sensitive actions. | منفذ | Branch/Staff/assignment confirmations exercised | N/A |
| 33 UI/UX | preserve safe form data after validation errors. | منفذ | Blade `old()` and browser invalid-then-valid journeys | N/A |
| 33 UI/UX | search/filter ux. | منفذ | Tenant/Staff search and clear/filter controls | UAT |
| 33 UI/UX | tables pagination/sorting/responsive behavior. | منفذ | Pagination and responsive/card transformations work | No user sorting on every Wave table |
| 33 UI/UX | responsive desktop/tablet/mobile as applicable. | منفذ | Edge 390/820/1440 pass | Formal device matrix later |
| 33 UI/UX | do not redesign screens that are already effective. | منفذ | Changes limited to confirmed defects and setup guidance | N/A |
| 34 Accessibility | keyboard-only critical flows. | جزئي | Semantic native controls and focus styles exist | Dedicated keyboard acceptance not run |
| 34 Accessibility | visible focus. | منفذ | Consistent focus rings in Wave controls | Formal scan pending |
| 34 Accessibility | labels/accessible names. | منفذ | Form labels, SR table labels and named controls | Formal scan pending |
| 34 Accessibility | logical reading order. | جزئي | Semantic headings/forms/lists reviewed in rendered pages | Screen-reader acceptance pending |
| 34 Accessibility | contrast. | جزئي | DESIGN semantic tokens used | Automated contrast scan pending |
| 34 Accessibility | non-color-only state communication. | منفذ | Text statuses and warnings accompany color | Formal scan pending |
| 34 Accessibility | screen-reader smoke test. | جزئي | Semantic structure reviewed | No actual screen-reader run |
| 34 Accessibility | modal focus management. | منفذ | Confirm dialog traps/restores focus in shared JS | Manual assistive-tech acceptance pending |
| 34 Accessibility | form errors linked to fields. | جزئي | `aria-invalid`/`aria-describedby` plus alert summaries | N/A |
| 34 Accessibility | rtl accessibility validation. | جزئي | RTL visual/browser behavior passes | Screen-reader RTL acceptance absent |
| 34 Accessibility | accessibility scan plus manual review. | جزئي | Manual application-level review performed | Formal automated scan/certification absent |
| 35 Errors/concurrency | validation failure creates no partial business records. | منفذ | Transactional negative tests for provisioning/config/Staff | N/A |
| 35 Errors/concurrency | cross-scope errors leak no protected existence. | منفذ | Foreign resources produce 404/403 as appropriate | Security sign-off |
| 35 Errors/concurrency | stable conflict response for stale state. | منفذ | Version/state conflict tests | N/A |
| 35 Errors/concurrency | current safe state returned where appropriate. | منفذ | Safe messages/redirects exist | Not every JSON 409 returns a state representation |
| 35 Errors/concurrency | db transactions/locks where required. | جزئي | Tenant/Branch/Staff/assignment locks and transactions | Current MySQL rerun absent |
| 35 Errors/concurrency | idempotency uniqueness constraints. | جزئي | Tenant/Branch keys and assignment/email uniqueness | Current MySQL rerun absent |
| 35 Errors/concurrency | user-visible correlation/reference id. | منفذ | Request IDs propagated in headers/audit | Not every validation page displays the reference |
| 35 Errors/concurrency | technical details stay in protected logs. | منفذ | Generic UI errors and protected diagnostic logging | Production log-access review |
| 36 Data | every tenant-owned aggregate has unambiguous tenant scope. | منفذ | Explicit/composite Tenant ownership and scoping tests | Whole-product audit remains separate |
| 36 Data | branch relationships belong to same tenant. | منفذ | Composite FKs/validation and adversarial tests | N/A |
| 36 Data | non-guessable public identifiers. | منفذ | Invitations/idempotency keys are random | Authenticated internal routes still use scoped numeric IDs |
| 36 Data | utc timestamps + timezone context. | منفذ | UTC writes and Branch/receipt/ticket timezone snapshots | Historical report grouping decision open |
| 36 Data | exact monetary representation. | منفذ | Integer minor units plus ISO currency | Finance sign-off |
| 36 Data | pricing/tax/receipt version snapshots. | منفذ | Immutable transaction/receipt snapshots | N/A |
| 36 Data | posted finance append-only. | منفذ | Immutable posting/refund reversal models/tests | Finance sign-off |
| 36 Data | audit append-only. | منفذ | DB append-only protection and mutation denial | Retention decision open |
| 36 Data | foreign keys/unique constraints match business invariants. | منفذ | Tenant/Branch/User/pivot constraints and clean migration | Current MySQL rerun absent |
| 36 Data | indexes for major search/report paths. | منفذ | Existing scoped/indexed list paths | Production query-plan review later |
| 36 Data | no orphan records. | جزئي | FK ownership and non-destructive status transitions | Current MySQL rerun absent |
| 36 Data | migrations tested on clean and existing db. | جزئي | Clean SQLite Wave migration and full RefreshDatabase pass | Existing production-like MySQL rehearsal absent |
| 36 Data | forward deploy + rollback/roll-forward plan. | جزئي | Forward additive MFA migration is safe | Staging rollback rehearsal absent |
| 36 Data | production mysql behavior tested, not sqlite only. | منفذ | Historical MySQL baseline exists | Current Wave migration/concurrency not rerun on MySQL |
| 41 Security | tenant isolation tests: query/object/cache/queue/file/report/search. | جزئي | Strong query/object/report/search negatives pass | Cache/queue/file breadth not fully proven |
| 41 Security | branch isolation tests. | منفذ | Policy/binding/assignment tests and browser tampering | Security sign-off |
| 41 Security | privilege-escalation tests. | منفذ | Role/body/target/Owner/Platform negatives pass | Security sign-off |
| 41 Security | session/cookie/csrf tests. | منفذ | CSRF and session rotation/version behavior pass | Deployed cookie/TLS proof absent |
| 41 Security | input validation/injection/xss/path traversal/mass assignment. | جزئي | Allowlisted validated inputs and escaped Blade | Formal broad DAST/SAST absent |
| 41 Security | secret scanning. | جزئي | Secrets excluded by convention and no secret printed | Hosted secret scanner evidence absent |
| 41 Security | dependency scanning. | جزئي | Composer/npm audit commands pass | Continuous CI gate absent |
| 41 Security | sast. | جزئي | Pint/tests/manual review | No formal SAST run |
| 41 Security | dast/manual critical-flow review. | جزئي | Real browser and adversarial manual review | Formal DAST absent |
| 41 Security | rate-limit testing. | منفذ | Login/reset/MFA and cross-account isolation tested | DEC-SEC-02 complete matrix open |
| 41 Security | no unresolved critical/high unless formally accepted. | منفذ | No Critical/High found in local checks | Formal Security sign-off absent |
| 42 QA | automated unit tests for calculations/state guards/normalization. | منفذ | Domain/support tests pass | N/A |
| 42 QA | integration tests for db constraints/scoping/permissions/transactions. | منفذ | Focused and full suites pass on SQLite | Current MySQL rerun absent |
| 42 QA | feature/api tests. | منفذ | Wave controllers and negative paths covered | N/A |
| 42 QA | critical browser journeys by role. | منفذ | Platform/Owner/Manager/Reception/Cashier Edge journeys | Moderated UAT later |
| 42 QA | arabic browser pass. | منفذ | RTL role/config pages at three widths | Legal wording approval |
| 42 QA | english browser pass. | منفذ | LTR role/config pages at three widths | N/A |
| 42 QA | desktop/tablet viewport pass. | منفذ | 1440/820 plus 390 mobile evidence | Formal device matrix later |
| 42 QA | repeat-submit scenarios. | منفذ | Idempotency/conflict/duplicate tests | Current MySQL concurrency rerun absent |
| 42 QA | concurrency scenarios. | منفذ | Locks/uniques and historical MySQL evidence | Current Wave MySQL contention not rerun |
| 42 QA | failure injection where material. | منفذ | Validation/rollback/provider-independent invitation tests | Broader infrastructure injection later |
| 42 QA | security negative tests. | منفذ | Cross-Tenant/Branch/role/MFA/reset negatives pass | Security sign-off |
| 42 QA | performance/resilience tests. | منفذ | Bounded/paginated queries | Approved production workload execution absent |
| 42 QA | regression suite after every gap fix. | منفذ | Focused then full suite rerun | N/A |
| 42 QA | test evidence linked to requirements. | منفذ | This Wave file and `.ai/TEST_RESULTS.md` | N/A |

## Status counts

| Status | Count |
|---|---:|
| منفذ | 127 |
| جزئي | 23 |
| ناقص | 0 |
| لا يعمل | 0 |
| يحتاج UI | 0 |
| محجوب بقرار | 3 |
| مؤجل | 0 |
| Production Ready | 0 |
| **Total Wave 1 tasks reviewed** | **153** |

The three decision-blocked rows are the two representations of the registered OQ-22 policy blockers. The separate active-session deactivation procedure is also unresolved. No Wave 1 task is classified missing or broken.
