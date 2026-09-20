# PlayNexus Design System

## 2026-09-15 application shell redesign

The venue shell now uses one permission-aware, task-first sidebar grouped into Operations, Sales & tickets, Insights and Management. Desktop keeps a 248px information-rich state and a persistent 80px icon rail; tablet/mobile use the native modal drawer. The current branch, branch-local time, notification shortcut, tenant and signed-in role remain visible without competing with the work surface. The same structure mirrors in Arabic RTL, preserves 44px targets and visible focus, and uses the existing petrol-teal/cool-mineral tokens without a new component library.

## 2026-09-15 M6 application

Reports and operational-notification history reuse the full-screen desktop shell, petrol-teal/cool-mineral tokens, compact responsive tables, native date/select controls, minimum 44px targets, visible focus, semantic text plus color, server-rendered empty/validation/denied states, and logical RTL/LTR spacing. No new design system or frontend dependency was introduced.

**Status:** M1 interface baseline implemented; brand approval still pending
**Register:** Product interface  
**Primary surfaces:** Reception, cashier, branch operations, management  
**Implemented stack:** Laravel Blade, small vanilla JavaScript enhancements, Tailwind CSS 4; Livewire is not installed and remains optional only if a measured interaction later requires it.

**Desktop and Arabic closure — 2026-09-14:** Operational pages consistently use a 1440px desktop canvas; sparse boards expand through adaptive columns and ticket data stays tabular at desktop widths. Eleven authenticated Arabic RTL pages were checked at 1920×1080 with no horizontal page overflow or raw translation keys. Copy uses concise Modern Standard Arabic familiar to Egyptian staff, avoids dialect and backend vocabulary, and states the action or consequence directly.

**M1 acceptance — 2026-09-12:** The shared authenticated navigation, tenant settings, semantic state tokens, skip link, 44px controls, Arabic RTL/English LTR behavior, tablet overflow behavior, and reduced-motion fallback were checked in the real browser. No console warnings or errors were present in the accepted journey.

**Application shell follow-up — 2026-09-12:** Authenticated tenant pages now use the specified 248px desktop sidebar, 64px top bar, and sub-1024px navigation drawer. The shell keeps branch, locale, user, and sign-out context in one consistent location; navigation remains permission-aware and uses only implemented routes.

**Ticket surface — 2026-09-13:** The implemented ticket-only page prioritizes keyboard/manual scan validation, explicit branch/date/family context and native lifecycle disclosures. The same ticket table becomes labeled cards on narrower screens, preserving actions without horizontal scrolling. Local square QR rendering and scoped single-page A5 output include status/holder-lock facts; cancellation explicitly does not refund money. Authenticated Arabic/English headless-browser mobile/desktop/tablet evidence is recorded in `.ai/TEST_RESULTS.md`; sessions and checkout are not implied.

**Check-in/live-session surface — 2026-09-13:** The implemented session page leads with branch/capacity context, then a compact server-filtered board and a visually distinct ticket consume-and-start action for authorized staff. Cashier receives an explicit read-only state. Session cards keep child, guardian, branch, state, ticket, branch-local start/expected end and elapsed time scannable on desktop, tablet and mobile without a wide table. Success/conflict/denial copy states whether anything changed; no money or checkout promise appears. Authenticated Arabic/English responsive Edge evidence is in `.ai/TEST_RESULTS.md`.

**Read-only estimate follow-up — 2026-09-13:** Active session cards now add one quiet teal estimate panel with a prominent total, compact base/grace/overtime/tax details, server as-of time and an explicit non-final/no-checkout/no-payment notice. Desktop uses at most two cards per row so money labels stay readable; mobile remains a single linear card. No new component library or decorative pattern was added.

## 1. Design direction

PlayNexus should feel modern, calm, warm, and operationally trustworthy. It is not an arcade-themed interface and not a dense accounting ERP.

**Physical scene:** A receptionist or cashier uses a desktop or tablet at a bright, busy venue entrance while speaking with a guardian, watching children, handling money, and responding to interruptions. The interface must remain easy to scan under daylight and strong indoor lighting.

This scene requires a light theme with cool porcelain surfaces, graphite text, and restrained petrol-teal emphasis. Color supports priority and state; it does not decorate every surface.

### Visual character

- Cool porcelain canvas instead of pure white.
- Deep petrol teal for primary actions, current navigation, and focus.
- Graphite typography instead of pure black.
- Clear spacing and strong labels instead of decorative containers.
- Soft geometry with controlled corners, not oversized rounded cards.
- Child-friendly warmth without playful controls, neon color, or cartoon styling.
- Familiar product patterns that disappear into the task.
- Purple and cream are deliberately excluded from this baseline.

## 2. Color strategy

Use a **restrained** product palette. Cool mineral neutrals should carry most of the interface. Petrol teal should normally occupy less than 10% of an operational screen and appear only where it communicates action, selection, focus, or brand identity.

The OKLCH values are the canonical CSS tokens. Hex values are practical fallbacks and design-tool references. Verify final rendered contrast after fonts and browser styles are implemented.

### Core palette

| Token | OKLCH | Hex fallback | Purpose |
|---|---|---|---|
| `canvas` | `oklch(0.968 0.007 205)` | `#F3F6F7` | Application background |
| `surface` | `oklch(0.989 0.004 205)` | `#FAFCFC` | Main work surface, menus, forms |
| `surface-subtle` | `oklch(0.938 0.013 205)` | `#E9EFF0` | Side navigation, toolbars, grouped rows |
| `surface-hover` | `oklch(0.908 0.020 205)` | `#DEE8EA` | Neutral hover and selected secondary rows |
| `ink` | `oklch(0.245 0.025 210)` | `#17262B` | Primary text and important values |
| `ink-muted` | `oklch(0.490 0.020 210)` | `#56676C` | Secondary labels and helper text |
| `ink-disabled` | `oklch(0.650 0.015 210)` | `#89979A` | Disabled text only |
| `border` | `oklch(0.860 0.018 205)` | `#CDD9DB` | Inputs, separators, table structure |
| `border-strong` | `oklch(0.720 0.030 205)` | `#97ACB0` | Strong grouping and selected controls |
| `primary` | `oklch(0.480 0.100 205)` | `#006D77` | Primary action and active navigation |
| `primary-hover` | `oklch(0.400 0.090 205)` | `#005963` | Primary hover |
| `primary-active` | `oklch(0.330 0.075 205)` | `#00434B` | Primary pressed state |
| `primary-soft` | `oklch(0.920 0.025 205)` | `#D9ECEE` | Selected rows and informative teal tint |
| `focus` | `oklch(0.620 0.110 200)` | `#0095A3` | Focus ring, never the only state cue |

Never use pure `#000000` or `#FFFFFF`. Do not use gradient text, decorative gradients, or glassmorphism.

### Semantic colors

Semantic colors never replace a written label or icon. Use the soft color for backgrounds and the strong color for text or icon.

| State | Strong | Soft | Typical label |
|---|---|---|---|
| Success | `#18794E` | `#E5F5EC` | Paid, checked in, delivered |
| Warning | `#9A5B00` | `#FFF2D7` | Ending soon, approval required |
| Danger | `#B42318` | `#FDE8E6` | Failed, overdue, blocked |
| Information | `#275DAD` | `#E8F0FC` | Processing, scheduled, informational |
| Neutral | `#5E5662` | `#EEE8F0` | Draft, paused, inactive |

### Color usage rules

- One screen has one obvious petrol-teal primary action.
- Secondary actions use porcelain surfaces, borders, and dark text.
- Destructive actions use danger color and explicit consequence copy.
- Money and time values use `ink`; state color belongs to the adjacent status, not the number itself.
- Selected navigation uses `primary-soft`, petrol-teal text, and a clear icon or weight change.
- Do not place colored accent strips on cards, rows, or alerts.
- Charts use petrol teal first, then information blue, success green, warning amber, and muted graphite. Never use color alone to identify a series.

## 3. CSS token contract

Use semantic tokens in components. Do not place raw palette values throughout Blade templates.

```css
:root {
    color-scheme: light;

    --pn-canvas: oklch(0.968 0.007 205);
    --pn-surface: oklch(0.989 0.004 205);
    --pn-surface-subtle: oklch(0.938 0.013 205);
    --pn-surface-hover: oklch(0.908 0.020 205);

    --pn-ink: oklch(0.245 0.025 210);
    --pn-ink-muted: oklch(0.490 0.020 210);
    --pn-ink-disabled: oklch(0.650 0.015 210);
    --pn-border: oklch(0.860 0.018 205);
    --pn-border-strong: oklch(0.720 0.030 205);

    --pn-primary: oklch(0.480 0.100 205);
    --pn-primary-hover: oklch(0.400 0.090 205);
    --pn-primary-active: oklch(0.330 0.075 205);
    --pn-primary-soft: oklch(0.920 0.025 205);
    --pn-focus: oklch(0.620 0.110 200);

    --pn-success: #18794E;
    --pn-success-soft: #E5F5EC;
    --pn-warning: #9A5B00;
    --pn-warning-soft: #FFF2D7;
    --pn-danger: #B42318;
    --pn-danger-soft: #FDE8E6;
    --pn-info: #275DAD;
    --pn-info-soft: #E8F0FC;
}
```

Map these tokens once through Tailwind 4 theme variables after the Laravel scaffold exists. Components should consume names such as `canvas`, `surface`, `primary`, `danger`, and `ink-muted` rather than raw teal shade numbers.

## 4. Typography

### Families

- English and numeric UI: `Inter`, then `Segoe UI`, then `system-ui`, then `sans-serif`.
- Arabic UI: `IBM Plex Sans Arabic`, then `Segoe UI`, then `Tahoma`, then `Arial`, then `sans-serif`.
- Self-host production fonts when licensing permits. Use system fallbacks during the first scaffold.
- Use one family per language on a screen. Do not use display fonts in labels, tables, buttons, or data.

### Scale

| Role | Size / line height | Weight | Use |
|---|---|---:|---|
| Page title | `1.75rem / 2.125rem` | 700 | One `h1` per screen |
| Section title | `1.25rem / 1.625rem` | 700 | Major work regions |
| Subsection | `1rem / 1.375rem` | 650 | Table or form groups |
| Body | `0.9375rem / 1.5rem` | 400 | Default interface copy |
| Label | `0.8125rem / 1.125rem` | 600 | Forms and compact metadata |
| Caption | `0.75rem / 1rem` | 500 | Supporting metadata only |
| Operational value | `1.25rem / 1.5rem` | 700 | Time, money, capacity |

- Use tabular numerals for timers, money, capacity, and receipt references.
- Keep prose at 65 to 75 characters per line.
- Do not use uppercase for Arabic. Use sentence case for English labels.
- Keep IDs, codes, currency amounts, and phone numbers in a bidirectional isolation element such as `<bdi dir="ltr">` when rendered inside Arabic UI.

## 5. Spacing and geometry

Use a 4px base grid with deliberate rhythm.

| Token | Value | Typical use |
|---|---:|---|
| `space-1` | 4px | Icon gaps, compact metadata |
| `space-2` | 8px | Control internals |
| `space-3` | 12px | Compact rows |
| `space-4` | 16px | Default component gap |
| `space-5` | 20px | Form groups |
| `space-6` | 24px | Panel padding |
| `space-8` | 32px | Section separation |
| `space-10` | 40px | Major page rhythm |

### Radius

- Inputs and buttons: 10px.
- Panels and menus: 14px.
- Small status tags: 999px only when the shape represents a compact tag.
- Do not make tables, page regions, or every control pill-shaped.

### Elevation

- Prefer borders and surface contrast over shadows.
- Menus and floating confirmations may use `0 12px 32px rgb(23 38 43 / 0.12)`.
- Main panels use no shadow or a maximum of `0 1px 2px rgb(23 38 43 / 0.06)`.
- Never nest elevated cards inside elevated cards.

## 6. Application shell

### Desktop

- Desktop is the primary operational layout. At 1024px and above, use the full available application workspace; do not render a stretched tablet composition.
- Persistent side navigation: 248px expanded, 80px collapsed.
- Top bar: 64px with tenant, branch, current shift context when approved, locale, and user menu.
- Main content maximum readable width: 1440px, with full-width operational tables when needed.
- At 1366px, comparison-heavy operational data remains a desktop table. Switch to cards only when columns can no longer remain legible, not from one page-specific breakpoint.
- Page actions align with the title on wide screens and move below it when space is constrained.

### Tablet

- Navigation becomes a drawer below 1024px.
- Primary actions remain visible without horizontal scrolling.
- Tables keep the columns required to make the decision; secondary columns move to an expandable row or detail surface.
- Touch targets are at least 44px by 44px.

### RTL

- Mirror navigation, breadcrumbs, step direction, icon placement, and form alignment.
- Do not mirror media controls, numeric direction, QR/barcodes, or universal directional symbols when mirroring changes their meaning.
- Use CSS logical properties such as `margin-inline`, `padding-inline`, `inset-inline`, and `text-align: start`.
- Focus order follows task order, not visual hacks.

## 7. Component vocabulary

Build a small reusable Blade/Livewire vocabulary. Every interactive component requires default, hover, focus, active, disabled, loading, validation, and permission-denied behavior where applicable.

### Buttons

- `Primary`: solid petrol teal, porcelain text. One dominant action per work region.
- `Secondary`: porcelain surface, strong border, dark text.
- `Quiet`: text or icon action for low-priority behavior.
- `Danger`: danger text/border by default, solid danger only for the final destructive confirmation.
- Loading keeps the original width, disables repeat submission, and changes the label to a clear verb such as `Saving` or `Processing payment`.

### Inputs

- Minimum height: 44px; search and checkout controls may be 48px.
- Labels remain visible above fields. Placeholder text never replaces a label.
- Focus uses a 2px petrol-teal ring with an additional border change.
- Validation messages sit directly below the field and explain how to recover.
- Scanners should feed standard focused inputs, not require a custom scanner-only control.

### Tables and operational lists

- Use tables when users compare rows and columns; use lists when rows have different content.
- Sticky headers are allowed for long operational tables.
- Row height: 48px compact, 56px standard.
- Keep names and primary states on the start side; money, time, and row actions align on the end side.
- Selected rows use `primary-soft` plus a selection control. Hover alone must not look selected.
- Empty states explain the next action and offer it when permitted.
- Loading uses row-shaped skeletons, not a centered spinner.

### Panels

- Use a panel only when content has a real boundary, independent action, or distinct state.
- Prefer headings, dividers, whitespace, and split layouts before adding another card.
- Never use identical grids of cards for unrelated dashboard information.

### Status tags

- Always combine color with text and, for high-risk states, a simple icon.
- Use stable vocabulary from the SRS and API contract.
- Do not show internal enum codes directly to staff.

### Alerts and confirmations

- Inline alerts appear beside the affected task.
- Use a drawer for review-heavy approval or detail flows.
- Use a modal only when a short, blocking confirmation is necessary, usually for destructive or financially irreversible actions.
- Confirmation copy names the child, amount, session, consequence, and required reason as applicable.

### Feedback

- Success feedback confirms the completed action and its reference.
- Error feedback preserves entered data and provides a recovery action.
- Toasts are supplementary; critical success or failure remains visible in the work region.

## 8. Critical screen patterns

### Reception and check-in

- Search is the first focus target.
- Guardian and child identity remain visible through the flow.
- Consent, safety flags, duplicate warnings, ticket eligibility, and branch capacity appear before confirmation.
- The final action names the child and selected ticket.

### Live sessions

- Prioritize child name, elapsed/remaining time, state, guardian, and next permitted action.
- Ending-soon and overdue states use label, icon, and color.
- Extend, adjust, cancel, and checkout must be permission and state aware; pause/resume is excluded from the Egypt MVP.
- Avoid a dashboard-card layout; use a scan-friendly operational board or table.

### Checkout

- Keep guardian verification visible and unresolved until completed or explicitly overridden.
- Show an explainable time and pricing breakdown before payment.
- Separate ordinary actions from approvals and destructive actions.
- Preserve a visible recovery state when payment succeeds but session completion or receipt delivery fails.

### POS and payment

- Keep cart, totals, payment method, approval status, and submit action within one scan path.
- Use tabular numerals and align decimal/minor-unit displays consistently.
- Disable repeat payment submission while processing and show the idempotent recovery result.

### Reports

- Filters describe tenant, branch, timezone, date range, and currency context.
- Tables remain the primary evidence; charts summarize rather than replace them.
- Export actions state scope and permission consequences.

## 9. Motion

- Standard transition: 160ms, `cubic-bezier(0.25, 1, 0.5, 1)`.
- Drawer and larger reveal: 220ms with the same ease-out curve.
- Animate opacity and transform only. Do not animate layout dimensions during operational work.
- Motion communicates state change, loading, disclosure, or confirmation only.
- Respect `prefers-reduced-motion` and remove nonessential transitions.

## 10. Accessibility baseline

- Target WCAG 2.2 AA.
- Keyboard operation and visible focus are mandatory for every critical workflow.
- Text and interactive control contrast must be verified against the real rendered background.
- Do not rely on color, motion, placeholder text, or icons alone.
- Use semantic headings, landmarks, tables, labels, descriptions, and error associations.
- Announce Livewire validation, background completion, and conflicts through appropriate live regions.
- Preserve zoom to 200% and reflow without losing the primary action.
- Minimum pointer target: 44px by 44px for primary operational controls.

## 11. Content style

- Use short verbs: `Check in`, `Verify guardian`, `Record payment`, `Issue refund`.
- State the object and consequence for sensitive actions.
- Avoid technical codes, vague `Submit` labels, jokes, or celebratory copy in safety and finance flows.
- Arabic should be operational Modern Standard Arabic with locally reviewed terms, not literal English word order.
- Error copy follows: what happened, what remains safe, and what the user can do next.

## 12. Do and avoid

### Do

- Make the next permitted action obvious.
- Keep identity, branch, time, and money context visible.
- Use cool porcelain surfaces for calm and petrol teal for controlled emphasis.
- Design loading, empty, validation, conflict, denied, success, and recovery states with the happy path.
- Reuse the same components and vocabulary across screens.

### Avoid

- Neon arcade visuals, cartoons, confetti, or decorative animation.
- Pure black, pure white, gradient text, glass panels, and colored side stripes.
- Repeated identical cards, nested cards, or dashboards with no operational priority.
- Hidden guardian verification, hidden price breakdowns, or ambiguous destructive controls.
- A separate design-system package during MVP.

## 13. Implementation checklist

Before a UI slice is complete:

- [ ] Uses semantic tokens rather than raw colors.
- [ ] Has one clear primary action.
- [ ] Covers loading, empty, validation, error, success, denied, and conflict states as applicable.
- [ ] Works with keyboard and touch.
- [ ] Works at supported desktop and tablet widths.
- [ ] Works in English/LTR and Arabic/RTL.
- [ ] Keeps tenant, branch, identity, time, money, and safety context visible where relevant.
- [ ] Uses canonical SRS/API statuses and permission-aware actions.
- [ ] Has no essential information conveyed only by color or icon.
- [ ] Has focused tests or verified browser evidence proportional to risk.

## 14. Governance

`DESIGN.md` is the canonical visual and interaction baseline. Update it when a reusable token, component rule, typography choice, or cross-screen interaction changes. Screen-specific behavior remains in `docs/10-UI-UX-Wireframes.md`; product and accessibility principles remain in `PRODUCT.md`.

Brand logo, final font licensing, final color approval, dark mode, and customer-selectable themes are not approved requirements. Treat them as future decisions rather than implied MVP scope.
