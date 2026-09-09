# PlayNexus UI/UX Wireframes

**Document status:** Draft MVP interaction baseline pending stakeholder approval  
**Audience:** Product, UX, Laravel, QA, and operations teams  
**Source:** PlayNexus PRD v1.0, June 2026  
**Target:** Responsive web application for reception and cashier desktop/tablet use  
**Accessibility assumption:** WCAG 2.2 AA  
**Interactive companion:** [Open the standalone wireframe navigator](./wireframes/playnexus-wireframes.html)

## 1. Experience intent

PlayNexus is an operational tool used by receptionists, cashiers, managers, and authorized safety staff in bright, noisy venues. People will be interrupted, may wear gloves, and often need to confirm identity, time, money, or capacity quickly. The interface should therefore feel calm, familiar, and direct.

The MVP wireframes are intentionally neutral and low fidelity. They define hierarchy, workflow, content, state, directionality, and responsive behavior. They do not claim a final brand identity.

### Product principles

1. Put the next operational action first.
2. Keep child identity and guardian verification visible in safety-critical flows.
3. Show branch, local time, currency, and effective role context wherever they affect an action.
4. Reduce typing by searching existing records before offering creation.
5. Never rely on color alone for status or urgency.
6. Keep destructive, financial, and override actions explicit and auditable.
7. Preserve the operator's place when validation fails or a network request is retried.
8. Design Arabic/RTL and English/LTR together.

## 2. MVP scope represented

The wireframes cover the complete staff-operated MVP baseline:

1. Login
2. Tenant onboarding
3. Operations dashboard
4. Parent and child quick registration
5. Check-in
6. Live sessions
7. Checkout and payment
8. POS
9. Reports
10. Staff and permissions
11. Branch settings
12. Tenant administration
13. Ticket lifecycle
14. Operational notifications
15. Incidents and audit — conditional on OQ-20; audit remains MVP

Future parent portals/apps, game setup and participation queues, birthday bookings, memberships, loyalty, marketing campaigns, cashier shift/cash-drawer management, advanced inventory/HR, franchise controls, AI, marketplace, white-label products, and offline writes are not designed here. Branch-wide check-in capacity remains an MVP configuration decision; it does not introduce the deferred games/queue module.

## 3. Roles and default landing points

| Role | Default landing point | Primary quick action |
| --- | --- | --- |
| Super Admin | WF-12 Tenant administration, outside the venue shell | Create, activate, or suspend tenant |
| Tenant Owner | Dashboard, all permitted branches | Compare branches |
| Branch Manager | Branch dashboard | Resolve alerts and approvals |
| Reception Staff | Live sessions | Register and check in child |
| Cashier | POS | Start sale or collect checkout balance |
| Game Operator | No dedicated MVP game/queue surface | No MVP access by default; incident access only if OQ-20 and an explicit assignment are approved |
| Parent/Guardian | No authenticated MVP application | Notification recipient and data subject; staff performs MVP actions |

The navigation must be permission aware. Hidden destinations are not a security control; every server request must still authorize the action.

## 4. Information architecture

```text
Platform administration (Super Admin, separate shell)
`-- Tenants
    |-- Create / activate / suspend
    `-- Minimum subscription status (commercial rules pending)

PlayNexus venue operations
|-- Operations
|   |-- Dashboard
|   |-- Register family
|   |-- Check in
|   |-- Live sessions
|   `-- POS
|-- Tickets
|   |-- Issue / validate
|   `-- Cancel / reprint / scan history
|-- Customers
|   |-- Guardians
|   `-- Children
|-- Safety
|   |-- Incidents
|   `-- Audit trail
|-- Communications
|   `-- Operational notification log
|-- Reports
|   |-- Revenue
|   |-- Attendance
|   |-- Sessions
|   `-- Staff activity
|-- Team
|   |-- Staff
|   |-- Roles and permissions
|   `-- Approval history
`-- Settings
    |-- Business
    |-- Branch
    |-- Pricing and tickets
    |-- Taxes and receipts
    `-- Notifications
```

### Global shell

- **Top bar:** product name, branch switcher, current branch-local clock, locale switch, operational notifications, and user menu.
- **Primary navigation:** persistent left rail at desktop widths and a labelled drawer at tablet widths.
- **Page header:** breadcrumb when depth exceeds one, plain page title, short context, then one primary action.
- **Content:** task-first layout. Use tables and split panes where comparison matters; do not turn every item into a card.
- **Status region:** polite live region for saves and background refreshes. Critical failures use an alert region with recovery action.
- **Connection state:** when the browser is offline or stale, show a persistent banner and disable money or safety writes. Offline operation is not an MVP promise.

## 5. Directionality and localization

### English/LTR

- Navigation rail starts on the left.
- Primary content reads left to right.
- Back affordances point left.
- Tables keep labels left aligned and numeric money columns aligned by decimal where feasible.

### Arabic/RTL

- Set `lang="ar"` and `dir="rtl"` on the document root.
- Mirror navigation, breadcrumbs, progress steps, directional icons, split panes, and previous/next controls.
- Do not mirror universal icons such as search, print, plus, check, warning, or media controls.
- Keep phone numbers, QR values, receipt numbers, email addresses, and technical identifiers isolated with `dir="ltr"`.
- Use CSS logical properties in implementation. Avoid separate Arabic templates.
- Translate operational copy, validation, receipts, exported column names, and notification messages, not only navigation labels.

### Regional formats

- Store timestamps in UTC and render in the branch time zone.
- Show an explicit time-zone abbreviation or branch name where ambiguity matters.
- Store money in integer minor units and render with the configured ISO currency, for example `EGP 450.00`, `SAR 450.00`, or `AED 450.00`.
- Use locale-aware dates but avoid ambiguous all-numeric short dates in confirmations. Prefer `24 Aug 2026, 14:35` or its localized equivalent.
- Persist phone numbers in canonical international form after local input normalization.

## 6. Responsive behavior

| Width | Shell | Task behavior |
| --- | --- | --- |
| 1280 px and above | Full navigation rail, top bar, split-pane workflows | Tables show all operational columns. Registration and checkout use two columns. |
| 900 to 1279 px | Compact labelled rail or collapsible rail | Secondary panels narrow. Actions remain visible without horizontal page scroll. |
| 768 to 899 px | Top bar plus navigation drawer | Multi-column forms become one column. Sticky summary appears below the form or as a bottom action bar. |
| Below 768 px | Supported for emergency review, not the primary MVP station | Dense tables become labelled rows. Safety and money flows remain usable, but hardware scanning and printing need device validation. |

Rules:

- Touch targets are at least 44 by 44 CSS pixels for primary operational controls.
- The persistent checkout/POS summary never covers focused fields or validation messages.
- Tables may scroll inside a labelled region only when a stacked alternative would hide operational comparisons.
- Scanner input behaves like keyboard input and never depends on pointer interaction.
- A 200 percent zoom layout must retain actions and content without two-dimensional page scrolling, except for data tables where allowed by WCAG.

## 7. Reusable components and states

| Component | Purpose | Required states and notes |
| --- | --- | --- |
| App shell | Stable branch and user context | Desktop rail, tablet drawer, RTL mirror, permission-aware items |
| Branch switcher | Select permitted branch scope | Current, loading, unavailable, no access; never silently switch during an in-progress sale |
| Search combobox | Find guardian, child, ticket, or item | Idle, typing, loading, results, no match, error; keyboard arrows and Enter |
| Status badge | Session, ticket, branch, or payment status | Text plus icon/shape; never color only |
| Data table | Compare operational records | Sort, filter, pagination, empty, loading skeleton, partial error, selected row |
| Form field | Capture validated data | Optional label visible, help, dirty, valid, invalid, disabled, read-only |
| Step indicator | Onboarding or task progress | Current step announced, completed, error, optional; not the only navigation |
| Timer | Live session elapsed or remaining time | Screen reader label, explicit start/end data, stale state, never rapid visual flashing |
| Money summary | Subtotal, discount, tax, paid, due | Currency repeated, negative/refund clearly labelled, recalculation notice |
| Quantity stepper | POS quantity | Typed input allowed, min/max, disabled, validation, 44 px controls |
| Confirmation panel | Checkout, refund, or override | Inline where space allows; requires reason and permission context |
| Approval request | Manager approval for override/discount/refund | Pending, approved, rejected, expired, requester cancelled; audit reference |
| Toast/status message | Non-blocking result | Polite live region, concise, dismissible where persistent; errors also remain near source |
| Empty state | Teach first valid action | Explain why empty, one appropriate action, no decorative filler |
| Skeleton | Preserve layout during load | Matches final structure, no spinner-only page |
| Stale/offline banner | Prevent unsafe writes | Timestamp of last refresh, retry button, impacted actions disabled |
| Print/receipt preview | Verify output before reprint | Receipt number, copy label, branch, date/time, reprint audit note |

### Common status vocabulary

- Session: Active, Paused, Due soon, Overtime, Completed, Cancelled.
- Tenant: Pending, Active, Suspended.
- Staff: Invited, Active, Suspended.
- Order: Draft, Paid, Voided, Refunded. Split or partial payment is not an MVP state.
- Payment: Posted or Voided; a refund is a separate Posted reversal record and moves the order to Refunded. A failed attempt is labelled Failed but does not create a Posted payment.
- Ticket: Issued, Consumed, Expired, Cancelled. Reprint is an event, not a state.
- Notification: `queued`, `sending` (UI: Processing), `sent`, `delivered`, `failed_retryable`, `failed_permanent`, `stale` (UI: Cancelled/Stale).
- Incident: Open, Under review, Closed; exact transition/reopen policy remains OQ-20.
- Branch: Active, Inactive.
- Data freshness: Live, Updating, Stale, Offline.

Use exactly one canonical label per domain status in both UI and API documentation.

## 8. Screen specifications and ASCII wireframes

ASCII layouts show reading order in English/LTR. Arabic/RTL mirrors structural direction while preserving logical focus order.

### WF-01 Login

**Goal:** Get a staff member into the correct tenant and branch safely with minimal delay.

**Primary content:** email or staff identifier, password, show password, remember device when policy permits, sign in, forgot password, locale switch. Tenant branding may appear only after tenant resolution; the neutral PlayNexus identity is the fallback.

**Behavior:** Rate-limit attempts, preserve the identifier after validation failure, never reveal whether a specific account exists, and redirect to the last permitted branch unless a branch choice is required.

```text
+--------------------------------------------------------------+
| PlayNexus                                    [English v]      |
|                                                              |
|                 Sign in to operations                        |
|                 Staff ID or email [____________________]      |
|                 Password          [____________________] [o]  |
|                 [ ] Remember this device                     |
|                 [ Sign in ]                                  |
|                 Forgot password?                             |
|                                                              |
|                 [Inline error or support reference]           |
+--------------------------------------------------------------+
```

**Accessibility:** Move focus to the error summary after a failed submit; link each error to its field; support password manager paste.

### WF-02 Tenant onboarding

**Goal:** Create the minimum valid tenant, first branch, owner, and pricing context before operations.

**Steps:** Business, first branch, local rules, owner, review. Allow save and resume. Show required fields only; advanced settings remain in Settings.

```text
+--------------------------------------------------------------------------------+
| PlayNexus setup                  Business > Branch > Local rules > Owner > Review |
|--------------------------------------------------------------------------------|
| First branch                                                                  |
| Branch name *          [_______________________________________]                |
| Country *              [Egypt v]      Time zone * [Africa/Cairo v]             |
| Currency *             [EGP v]        Tax mode *  [Prices include tax v]       |
| Capacity               [____]         Status      [Active v]                   |
| Opening hours          [Configure weekday hours]                              |
|                                                                                |
| [Save and exit]                                      [Back] [Continue]          |
+--------------------------------------------------------------------------------+
```

**Validation:** Currency and time zone become controlled settings after financial operations start. Explain that a manager can change opening hours later.

**Completion:** Present three actions: register a family, configure pricing, invite staff. Recommend configuring one ticket price before the first check-in.

### WF-03 Operations dashboard

**Goal:** Tell a manager or operator what needs attention now, then provide current operational context.

**Hierarchy:** urgent exceptions, live operational strip, recent activity, then trend summaries. Avoid a grid of equal metric cards.

```text
+--------------------------------------------------------------------------------+
| PlayNexus | Downtown branch v | 14:35 EEST | Alerts 2 | Nora v                 |
| Dashboard | Register | Check in | Live sessions | POS | Reports | Team | Settings|
|--------------------------------------------------------------------------------|
| Today at Downtown                                      [Register] [Check in]    |
|                                                                                |
| ACTION REQUIRED                                                                |
| [!] 2 sessions overtime     [Review sessions]                                  |
| [!] 1 payment failed        [Retry or record payment]                          |
|--------------------------------------------------------------------------------|
| LIVE NOW       48 active | 6 due in 15 min | 72 / 100 capacity                 |
| [Open live sessions]                                                          |
|--------------------------------------------------------------------------------|
| Revenue today              Attendance today          Recent staff activity      |
| EGP 18,420                 126 check-ins             14:31 Checkout by Omar     |
| vs selected comparison     Peak 17:00                14:28 Discount approved    |
+--------------------------------------------------------------------------------+
```

**Empty state:** New branches see a setup checklist, not zero-filled metrics.

**Refresh:** Show the last successful refresh time. Do not move focus or reorder a row while the user is interacting with it.

### WF-04 Parent and child quick registration

**Goal:** Find an existing family first, or register a guardian and at least one child in one short flow.

**Flow:** Search by phone or child name, select match, otherwise create guardian, add child, confirm consent and emergency details, then check in or save.

```text
+--------------------------------------------------------------------------------+
| Register family                                            Step 1 of 2          |
| Search first: phone or child name [__________________________] [Search]          |
|--------------------------------------------------------------------------------|
| GUARDIAN                              CHILDREN                                  |
| Full name * [____________________]    Child 1                                   |
| Mobile *    [+20 _______________]     Full name * [____________________]        |
| Email        [____________________]    Date of birth [____/____/________]        |
| Emergency    [Same as mobile v]       Notes [allergy, access, safety only]      |
| Preferred language [Arabic v]         [Add another child]                      |
| [ ] Operational contact consent                                             |
| [ ] Marketing consent (optional and separate)                                |
|--------------------------------------------------------------------------------|
| Duplicate found? [Review possible match without losing this form]              |
| [Cancel]                                         [Save] [Save and check in]      |
+--------------------------------------------------------------------------------+
```

**Rules:** A child must have at least one guardian. Duplicate detection warns, then allows an authorized merge/review flow rather than silently blocking. Do not collect unnecessary sensitive child data.

**Error recovery:** Keep all entered fields and highlight only invalid or conflicting values.

### WF-05 Check-in

**Goal:** Start one valid session with child, guardian, pricing rule, branch, staff actor, and optional wristband/QR.

```text
+--------------------------------------------------------------------------------+
| Check in child                                Downtown branch | 14:35 EEST       |
| Child [Search name, guardian phone, or scan___________________]                 |
|--------------------------------------------------------------------------------|
| SELECTED CHILD                         SESSION                                  |
| Maya Ahmed, age 7                      Ticket / plan * [60 minutes v]           |
| Guardian: Ahmed Ali +20...             Starts        [Now v]                   |
| Notes: Peanut allergy                  Wristband/QR  [Scan or generate]         |
| Last visit: 19 Aug 2026                Price         EGP 180.00                 |
| [Change child]                         Expected end  15:35 EEST                 |
|                                                                                |
| Safety acknowledgement [ ] Notes reviewed with operator                        |
|--------------------------------------------------------------------------------|
| Capacity after check-in: 73 / 100                         [Start session]        |
+--------------------------------------------------------------------------------+
```

**Guardrails:** Disable start until all required data exists. If a child already has an active session, open that session instead. If the branch is inactive or capacity is reached, block by default and show the permitted manager override path.

**Success:** Show session ID, child, start and expected end, ticket/QR, and one action to print or return to Live sessions.

### WF-06 Live sessions

**Goal:** Monitor, find, pause, resume, extend, or begin checkout without losing situational awareness.

```text
+------------------------------------------------------------------------------------------------+
| Live sessions              [Search/scan________________] [Status v] [Due time v] [Refresh]      |
| 48 active | 4 paused | 6 due soon | 2 overtime | Updated 14:35:08                              |
|------------------------------------------------------------------------------------------------|
| Child       Guardian      Started   Remaining       Status       Band      Next action           |
| Maya Ahmed  Ahmed Ali     14:02     27 min          Active       A104      [Open] [Checkout]     |
| Sami Noor   Huda Noor     13:25     00 min +10      Overtime     B221      [Open] [Checkout]     |
| Lina Omar   Omar Saleh    14:20     Paused 08 min   Paused       C019      [Open] [Resume]       |
|------------------------------------------------------------------------------------------------|
| Selected: Sami Noor | EGP 35 overage | [Pause] [Extend] [Adjust] [Cancel] [Checkout]            |
+------------------------------------------------------------------------------------------------+
```

**Updates:** Prefer a modest polling interval for MVP. Update status and elapsed values without stealing focus. Announce only meaningful status changes, not every timer tick.

**Concurrency:** A selected row includes a version or updated timestamp. If another staff member changes it, refresh the detail and explain the conflict before allowing another write.

**Controlled changes:** Cancellation requires permission and reason. Manual time/price adjustment requires its dedicated permission, reason, before/after values, and manager approval where configured. Completed and Cancelled sessions remain terminal; corrections are separate additive records.

### WF-07 Checkout and payment

**Goal:** Verify the collecting guardian, finalize immutable time/price inputs, and submit the payment/completion handoff safely. The single-screen completion shown below is the recommended pilot default, pending the explicit OQ-19 station-handoff decision.

```text
+--------------------------------------------------------------------------------+
| Checkout: Maya Ahmed                 Session PN-20481 | Started 14:02            |
|--------------------------------------------------------------------------------|
| 1. GUARDIAN VERIFICATION                  2. BILL                               |
| Authorized guardian [Ahmed Ali v]         Base ticket       EGP 180.00          |
| Verify with          [QR / phone / ID v]  Extra 12 minutes  EGP  42.00          |
| Reference            [________________]   Discount          EGP   0.00          |
| [ ] Guardian identity confirmed           Tax               EGP  31.08          |
|                                           TOTAL             EGP 253.08          |
| Notes: Allergy alert acknowledged                                              |
|--------------------------------------------------------------------------------|
| 3. PAYMENT                                                                     |
| Method [Cash v]  Amount received [________]  Change due EGP 0.00                |
| [Manager override] requires reason and approval                                |
|--------------------------------------------------------------------------------|
| [Back to session]                              [Complete checkout and receipt]   |
+--------------------------------------------------------------------------------+
```

**Rules:** Recalculate the quote on the server at final submission. Make the payment/completion handoff idempotent so two terminals cannot pay or complete it twice, and expose a recoverable intermediate state if OQ-19 selects separate stations. A guardian mismatch requires a permitted manager override, reason, and audit entry. Completed sessions are read-only except for a separate approved adjustment or refund workflow.

**Recovery:** If payment fails, keep the session operational, preserve verified guardian evidence only for a short policy-defined window, and show a safe retry. If payment posts but completion fails, show `Paid: checkout pending`, reuse the original payment/receipt, and resume completion only after revalidating settlement and guardian evidence. A payment or receipt alone must never imply that child release/session completion succeeded.

### WF-08 POS

**Goal:** Sell tickets, F&B, merchandise, add-ons, and extensions in one fast cashier surface.

```text
+------------------------------------------------------------------------------------------------+
| POS | Cashier: Omar | Downtown | [New sale] [Transactions / refunds] [Scan item____________]  |
|------------------------------------------------------------------------------------------------|
| CATALOG / SEARCH                                      CURRENT SALE                              |
| [Tickets] [F&B] [Merchandise] [Add-ons]               60 min ticket      1  EGP 180.00         |
| Search [____________________]                          Water bottle       2  EGP  40.00         |
| 60 min ticket EGP 180 [Add]                           [Edit quantities or remove]               |
| 30 min ticket EGP 110 [Add]                           --------------------------------          |
| Water bottle EGP 20 [Add]                             Subtotal             EGP 220.00          |
| Socks EGP 45 [Add]                                    Discount [Add code]  EGP   0.00          |
|                                                       Tax                  EGP  30.80          |
|                                                       TOTAL                EGP 250.80          |
|------------------------------------------------------------------------------------------------|
| [Clear]                                                           [Take payment]                |
+------------------------------------------------------------------------------------------------+
| Find posted receipt [R-________________] [Open]  Full refund requires permission, reason, audit |
```

**Keyboard:** Scanner input works from anywhere unless a text field intentionally owns input. Provide visible shortcut help, but every shortcut has a pointer alternative.

**Approval and refund:** Discounts over the configured threshold and full refunds open an inline approval panel. Refund shows the immutable original payment/receipt, refundable balance, reason, approver when required, and resulting linked reversal. Retry cannot create a second refund. Cashier shifts, split tender, partial payments, partial refunds, and inventory depletion are not MVP behavior.

### WF-09 Reports

**Goal:** Answer one operational or financial question with explicit scope, freshness, and reconciliation context.

```text
+------------------------------------------------------------------------------------------------+
| Reports > Revenue                                                                 [Export CSV]  |
| Date range [Today v]  Branch [Downtown v]  Cashier [All v]  [Apply]                           |
| Data through 14:30 EEST | Includes recorded payments | Excludes void drafts                  |
|------------------------------------------------------------------------------------------------|
| Net revenue EGP 18,420 | Transactions 164 | Refunds EGP 320 | Unpaid checkout EGP 253.08       |
|------------------------------------------------------------------------------------------------|
| Time      Receipt       Cashier     Type        Gross       Refund       Net                    |
| 14:28     R-001742      Omar        Checkout    253.08      0.00         253.08                 |
| 14:21     R-001741      Sara        POS         120.00      0.00         120.00                 |
|------------------------------------------------------------------------------------------------|
| Showing 1 to 25 of 164                               [Previous] [1] [2] [Next]                  |
+------------------------------------------------------------------------------------------------+
```

**Behavior:** Filters are represented in the URL and survive refresh. Money reports include totals that reconcile to underlying transactions. Exports use the same authorization and tenant/branch scope as the visible report.

**Performance:** Default to today and one branch for operational roles. Require narrower filters or asynchronous export only after measured query or export cost justifies it.

### WF-10 Staff and permissions

**Goal:** Invite and manage staff, assign branch scope and a role, and inspect effective access before saving.

```text
+------------------------------------------------------------------------------------------------+
| Team > Staff                                                        [Invite staff member]      |
| Search [________________] Role [All v] Branch [Downtown v] Status [Active v]                    |
|------------------------------------------------------------------------------------------------|
| Name          Role              Branch scope        Status       Last active       Actions      |
| Sara Hassan   Reception Staff   Downtown            Active       14:31             [Manage]     |
| Omar Adel     Cashier           Downtown            Active       14:28             [Manage]     |
|------------------------------------------------------------------------------------------------|
| MANAGE SARA                                                                                   |
| Role * [Reception Staff v]  Branches * [Downtown x]                                            |
| Effective access: register families, check in, view live sessions, complete standard checkout  |
| Cannot: refund, approve overrides, edit pricing, manage staff                                  |
| [Deactivate account]                                                     [Save changes]         |
+------------------------------------------------------------------------------------------------+
```

**Guardrails:** Do not allow the last tenant owner to remove their own owner access. Role changes invalidate or refresh active sessions according to security policy. Custom permission editing can be deferred until a pilot proves predefined roles insufficient.

### WF-11 Branch settings

**Goal:** Configure branch-local operating rules while making risky changes and effective dates clear.

```text
+--------------------------------------------------------------------------------+
| Settings > Branch                                  Downtown branch | Active      |
| [General] [Hours] [Capacity] [Pricing] [Tax and receipt] [Alerts]              |
|--------------------------------------------------------------------------------|
| GENERAL                                                                        |
| Branch name *       [Downtown________________________________]                  |
| Country             [Egypt]                 Locked after first transaction      |
| Time zone *         [Africa/Cairo v]                                             |
| Currency            [EGP]                   Locked after first transaction      |
| Capacity *          [100____]                                                   |
| Status              [Active v]                                                  |
|                                                                                |
| Changing status to inactive blocks new sessions but does not end active ones.   |
| [Discard changes]                                               [Save changes]  |
+--------------------------------------------------------------------------------+
```

**Settings safety:** Pricing and tax changes use an effective timestamp and never rewrite completed transactions. A branch deactivation confirmation states the count of active sessions and its exact effect.

**Subscreens:** Opening hours, approved branch-capacity policy, versioned pricing rules with worked-example preview, tax/receipt fields, and operational alert settings use the same save/error/audit conventions. Exact pricing, tax, receipt, capacity, and alert behavior remains blocked on the named decisions in Section 12.

### WF-12 Tenant administration

**Goal:** Let a Super Admin provision, activate, suspend, or reactivate one tenant without entering unrelated tenant data or deleting history.

```text
+------------------------------------------------------------------------------------------------+
| Platform > Tenants                                                    [Create tenant]            |
| Search [____________________]  Status [All v]  Plan [Commercial rules pending v]                 |
|------------------------------------------------------------------------------------------------|
| Tenant             Owner             Branches   Status       Last change              Action    |
| Little Stars       nora@...           2          Active       24 Aug, 14:20             [Manage]   |
| Fun Yard           owner@...          1          Pending      Invitation not accepted   [Resume]   |
|------------------------------------------------------------------------------------------------|
| SUSPEND LITTLE STARS                                                                            |
| Impact: staff access revoked; history retained; new venue work blocked                           |
| Reason * [________________________________________________________] [Cancel] [Suspend tenant]     |
+------------------------------------------------------------------------------------------------+
```

**States and safety:** Pending, Active, and Suspended transitions require permission, reason where applicable, concurrency control, and platform audit. Support access to tenant content is denied by default and remains an OQ-14 decision. Subscription plans/limits are not invented while OQ-04 is open.

### WF-13 Ticket lifecycle

**Goal:** Issue, validate, cancel, reprint, and investigate a ticket without changing its entitlement history.

```text
+------------------------------------------------------------------------------------------------+
| Tickets                                  [Issue ticket] [Scan / validate]                        |
| Search ticket / QR [____________________] Status [All v] Valid on [24 Aug 2026] [Apply]          |
|------------------------------------------------------------------------------------------------|
| Ticket       Type          Valid until          Status       Last scan              Action      |
| TK-A71Q      60 minutes    24 Aug, 16:00 EEST   Issued       Not scanned            [Open]       |
| TK-C22M      Family        24 Aug, 15:00 EEST   Consumed     14:02 Valid            [History]    |
|------------------------------------------------------------------------------------------------|
| TK-A71Q | same opaque QR | EGP 180.00 | Issued                                                   |
| [Reprint same ticket]  [Cancel ticket: reason required]  [View scan history]                    |
+------------------------------------------------------------------------------------------------+
```

**States and safety:** An Issued ticket may become Consumed, Cancelled, or Expired. Consumed, Cancelled, and Expired are terminal for entry; reprint preserves identifier, QR, price, validity, and state. Every validation attempt records a safe result, and the QR carries no child-sensitive data.

### WF-14 Operational notifications

**Goal:** Show truthful session-ending and receipt delivery outcomes and allow only safe, bounded retry/resend.

```text
+------------------------------------------------------------------------------------------------+
| Communications > Operational notifications                                                      |
| Type [All v] Status [Needs attention v] Branch [Downtown v] Date [Today v] [Apply]               |
|------------------------------------------------------------------------------------------------|
| Time    Subject / event       Channel   Recipient     Status             Attempt       Action    |
| 14:31   Receipt R-001742      SMS       •••• 0000     Sent               1             [Details]  |
| 14:15   Session ending       Email     a•••@...      FailedRetryable    2 of 3        [Retry]    |
| 14:05   Stale ending alert   SMS       •••• 2211     Cancelled/Stale    Not sent      [Details]  |
|------------------------------------------------------------------------------------------------|
| Provider accepted means Sent, not Delivered. Delivery requires a verified callback.              |
+------------------------------------------------------------------------------------------------+
```

**States and safety:** Canonical codes are `queued`, `sending`, `sent`, `delivered`, bounded `failed_retryable`, `failed_permanent`, and `stale`; UI labels may show Processing and Cancelled/Stale. Provider failure never rolls back a session/payment/receipt. Resend reuses the original receipt facts and is idempotent. Marketing campaigns remain disabled; stored marketing consent does not authorize an MVP campaign.

### WF-15 Incidents and audit — conditional incident panel

**Goal:** Show the proposed create/search/follow-up incident flow if OQ-20 is approved, alongside the required audit-history concept. If OQ-20 is not approved, hide incident controls and retain audit access only.

```text
+------------------------------------------------------------------------------------------------+
| Safety > Incidents                                                     [Record incident]        |
| Child [All v] Date [Today v] Branch [Downtown v] Reporter [All v] Status [Open v] [Apply]        |
|------------------------------------------------------------------------------------------------|
| INC-0028 | Open | Maya Ahmed | Downtown | Reported 14:12 by Sara                                |
| Category [Approved list v] Severity [Approved list v] Occurred [24 Aug, 14:08 EEST]             |
| Original description [read-only after creation___________________________________________]      |
| Follow-up note * [_______________________________________________________________________]      |
| Next state [Under review v]                                      [Append update]                 |
|------------------------------------------------------------------------------------------------|
| HISTORY: 14:12 Created by Sara | 14:25 Note added by Nora | immutable actor/time references     |
+------------------------------------------------------------------------------------------------+
```

**States and safety:** Baseline labels are Open, Under review, and Closed; exact transition, reopen, severity, category, visibility, retention, and escalation rules remain OQ-20. Updates append instead of replacing original facts. Searches and detail fields enforce tenant/branch/role scope. The UI states that PlayNexus records incidents but is not emergency dispatch or medical advice.

## 9. Critical cross-screen flows

### First usable branch

```text
Tenant onboarding -> First branch -> One pricing rule -> Invite staff -> Register family -> Check in
```

### Standard visit

```text
Search family -> Register if absent -> Check in -> Live monitoring -> Guardian verification
-> Server-calculated bill -> Payment -> Session completed -> Receipt
```

### Exception checkout

```text
Checkout -> Guardian mismatch or manual adjustment -> Permission check -> Manager approval
-> Reason captured -> Recalculate -> Payment -> Completion -> Audit log
```

### POS sale

```text
Scan/select items -> Optional customer -> Discount policy check -> Payment
-> Durable transaction -> Receipt -> Revenue/staff-activity reporting
```

### Ticket exception

```text
Find or scan ticket -> Validate current scope/time/state -> Cancel with reason or reprint same identity
-> Append scan/reprint/cancel event -> Never revive a terminal ticket
```

### Notification recovery

```text
Domain event -> Queue -> Attempt -> Sent or bounded failure -> Verified Delivered callback when supported
-> Staff sees truthful terminal/current state -> Core transaction remains committed
```

### Incident follow-up

```text
Record original facts -> Manager review -> Append note/permitted state change -> Scoped search -> Audit history
```

## 10. Accessibility acceptance notes

The implementation target is WCAG 2.2 AA, including the following product-specific checks:

- Every interactive function is keyboard operable with visible focus.
- Focus order follows the visual and logical task order in both LTR and RTL.
- A skip link reaches main content.
- Authentication does not block password managers or paste.
- Repeated entry is minimized by reusing known guardian and child information with confirmation.
- Errors are identified in text, summarized, and linked to fields.
- Status, urgency, and payment outcome use text plus an icon or shape, not color alone.
- Focus is never obscured by sticky headers, drawers, or bottom action bars.
- Pointer targets meet the 24 CSS pixel WCAG minimum, with a 44 pixel product target for primary controls.
- Dragging is not required for any task.
- Timeouts warn users and allow extension unless safety or security policy prevents it.
- Timer changes do not produce repeated screen-reader announcements.
- At 200 percent zoom and narrow tablet layouts, all core workflows remain operable.
- Arabic labels, error text, numbers, and mixed-direction identifiers are manually checked with a native reader.

## 11. Content and interaction conventions

- Buttons use verbs: `Start session`, `Complete checkout`, `Save changes`.
- Destructive actions name the object: `Cancel session`, `Deactivate branch`.
- Confirmation copy states consequence, not a generic `Are you sure?`.
- Avoid success-only color. Use `Paid` with a check icon and text.
- Show the last four digits or a masked reference when identity/payment data is sensitive.
- Never expose internal database IDs as the only user-facing reference. Use readable session and receipt numbers.
- Use inline panels for review and approval. Reserve dialogs for short, interruptive confirmations that cannot safely remain in page context.

## 12. Prototype limits and implementation handoff

The HTML companion demonstrates screen navigation, locale/direction switching, representative status updates, and low-fidelity responsive structure. It does not submit data, calculate authoritative money, scan hardware, print receipts, or model permission enforcement.

Before implementation starts, product and operations should approve:

1. The exact checkout identity evidence accepted in each launch country.
2. Whether payment is only recorded or processed online in MVP.
3. Required receipt and tax fields per launch country.
4. Capacity override policy.
5. Pricing, pause, rounding, and overtime rules.
6. The first supported barcode scanner and receipt printer combinations.
7. Whether checkout/payment/completion is one terminal action or a reception-to-cashier handoff (OQ-19).
8. Notification provider/channel, templates, lead times, retry bounds, callbacks, and escalation (OQ-05 and OQ-13).
9. Incident categories, severities, transitions/reopen rules, visibility, retention, and escalation (OQ-20).
10. Child age/date fields and duplicate-family handling (OQ-15 and OQ-17).
11. Subscription plans and tenant/branch/user limits; billing automation is not assumed (OQ-04).
12. Launch-country privacy, consent, retention, deletion, residency, and support-access rules (OQ-07 and OQ-14).

These decisions materially affect acceptance criteria, data retention, and test coverage.

## Approved MVP decision amendment — 2026-09-10

The completion screen must show QR verification, guardian confirmation choice, blocked-verification state, manager-override reason flow, and an explainable quote with package duration, 10-minute grace, 30-minute overtime units, tax, and total. Pause controls are omitted from the MVP flow.
