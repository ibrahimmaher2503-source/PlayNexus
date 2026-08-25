# PlayNexus Product Context

## Register

product

## Users

- Venue owners and tenant administrators managing one or more entertainment branches.
- Branch managers supervising revenue, attendance, capacity, staff actions, and exceptions.
- Reception staff registering guardians and children and running fast, safety-critical check-in and checkout flows.
- Cashiers processing tickets, add-ons, payments, discounts, refunds, and receipts.
- Game operators monitoring capacity, queues, participation, incidents, and maintenance requests.
- Parents and guardians reviewing their own children, sessions, bookings, notifications, and payment history.

These are product-wide personas from the PRD. For the MVP, authenticated parent self-service, games/queues, birthday bookings, memberships, and loyalty are not committed unless an explicit scope decision adds them; the operational web users are primarily platform administrators, tenant/branch management, reception, and cashier staff.

The primary MVP context is a busy reception or cashier desk on desktop or tablet. Users may be non-technical, interrupted, and working under time pressure.

## Product Purpose

PlayNexus is a multi-tenant, multi-branch operating system for kids entertainment venues in Egypt, Saudi Arabia, the UAE, GCC, and MENA. It replaces fragmented manual workflows with reliable registration, session timing, ticketing, POS, payments, safety verification, and reporting. MVP success means faster service, accurate billing, controlled checkout, strong tenant isolation, and an operational core that can later support memberships, CRM, bookings, AI, and white-label products.

## Brand Personality

Trustworthy, fast, reassuring. The interface should feel operational and calm, with a restrained child-friendly warmth that never reduces the seriousness of safety and money workflows.

## Anti-references

- Not a noisy arcade interface with neon colors, playful controls, or decorative animation.
- Not a dense accounting ERP that requires training to understand routine actions.
- Not a generic dashboard made from repeated cards with no clear operational priority.
- Not a mobile-consumer flow stretched onto reception desktops.

## Design Principles

1. Put the next operational action first, especially during check-in, live sessions, checkout, and payment.
2. Make child safety and guardian verification explicit; never hide overrides or audit consequences.
3. Reduce typing and screen changes for reception and cashier users without removing validation.
4. Show time, money, capacity, and status in formats that are hard to misread at a glance.
5. Design English, Arabic, LTR, RTL, desktop, and tablet behavior together rather than as later adaptations.

## Accessibility & Inclusion

- Target WCAG 2.2 AA for the web application.
- Support keyboard operation, visible focus, semantic labels, clear validation, and non-color status cues.
- Support Arabic and English content, RTL and LTR layouts, regional currencies, and locale-aware date/time formats.
- Avoid essential information conveyed only through motion, icons, or color.
- Keep operational text plain and readable for non-technical staff.

## Source and Assumptions

This context is derived from the supplied PlayNexus PRD v1.0 (June 2026). The re-attached source was verified byte-for-byte on 24 August 2026 with SHA-256 `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2`. The visual identity, final brand references, and final accessibility acceptance process are not yet approved; the current wireframes therefore use a neutral product baseline.
