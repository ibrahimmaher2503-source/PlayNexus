# Checkpoint 00.3 - Owner Decision Question Pack

Only genuinely open choices appear here. Approved OQs, implementation gaps, test environments and release sign-offs are intentionally excluded.

## DEC-NOT-01 - Production notification vendors

**Owner:** Procurement / Product Commercial  
**Current known state:** WhatsApp is the approved primary channel, SMS the fallback, and email is approved for receipts/administration. No vendor is selected.  
**Why the decision is required:** Vendor APIs, credentials, delivery callbacks, regional availability and commercial terms differ. Engineering cannot safely invent the vendor.  
**What is blocked:** Real provider adapters, credentials, callback verification and customer delivery.

**Recommended default:** Keep the current local/database transport until Procurement supplies a compliant contracted vendor for each enabled channel.

**Options:**

- A. Procurement names and approves one vendor for each enabled channel.
- B. Launch the pilot with customer delivery disabled and retain local/database notification records only.
- C. Reopen the approved channel mix through formal scope change.

**Required response:** `DEC-NOT-01: A` plus vendor names, or `DEC-NOT-01: B/C`.

## DEC-NOT-02 - Notification sender identities

**Owner:** Operations + Procurement  
**Current known state:** No approved WhatsApp number, SMS sender ID or receipt email domain/address is recorded.  
**Why the decision is required:** Sender ownership determines verification, customer recognition, support and offboarding.  
**What is blocked:** Provider onboarding and production credentials.

**Recommended default:** Operator-owned verified identities, with PlayNexus receiving only least-privilege service access.

**Options:**

- A. Each venue/operator owns its verified channel identities.
- B. PlayNexus owns shared platform identities.
- C. Mixed ownership, documented separately per channel.

**Required response:** `DEC-NOT-02: A/B/C`.

## DEC-NOT-03 - Bilingual operational templates

**Owner:** Operations + Legal/Privacy  
**Current known state:** Locale-ready variables exist, but exact customer-facing Arabic/English text and mandatory legal wording are not approved.  
**Why the decision is required:** Engineering cannot decide operational tone, mandatory disclosures or which personal/financial details may appear.  
**What is blocked:** Real session-ending and receipt/admin messages.

**Recommended default:** Operator-approved Arabic-first templates with English equivalents and minimum necessary data.

**Options:**

- A. Approve operator-specific Arabic/English templates before provider launch.
- B. Approve one centrally managed PlayNexus template set for all pilot tenants.
- C. Keep customer delivery disabled until templates are approved.

**Required response:** `DEC-NOT-03: A/B/C` and attach the approved copy for A or B.

## DEC-NOT-04 - Provider delivery callbacks

**Owner:** Procurement + Engineering/Security  
**Current known state:** The approved lifecycle may consume verified delivery callbacks, but no selected provider proves callback support or authentication.  
**Why the decision is required:** “Sent” cannot be called “Delivered” without trustworthy provider evidence.  
**What is blocked:** Callback endpoint design and delivered-state reporting.

**Recommended default:** Require authenticated, idempotent delivery callbacks for any channel that will display `delivered`; otherwise remain sent-only.

**Options:**

- A. Callback support and verification are mandatory vendor-selection criteria.
- B. Launch sent-only; never display `delivered` for channels without verified callbacks.
- C. Disable the affected provider/channel.

**Required response:** `DEC-NOT-04: A/B/C`.

## DEC-NOT-05 - Notification cost ownership

**Owner:** Commercial / Finance  
**Current known state:** Channel order is approved, but no party is assigned provider/message fees.  
**Why the decision is required:** Cost ownership affects vendor contracting, plan pricing, limits and tenant communications.  
**What is blocked:** Procurement approval and launch commercial terms.

**Recommended default:** Keep provider delivery disabled until costs and any tenant limits are explicitly contracted.

**Options:**

- A. Costs are included in PlayNexus SaaS pricing under an approved allowance.
- B. Costs are passed through or contracted directly to each tenant.
- C. Customer delivery remains disabled in V1.

**Required response:** `DEC-NOT-05: A/B/C`.

## DEC-SEC-01 - Absolute authenticated-session timeout

**Owner:** Security + Operations  
**Current known state:** Staff idle timeout is approved at 30 minutes and privileged/platform idle timeout at 15 minutes. No absolute maximum session lifetime is approved. A prior engineering recommendation recorded eight hours.  
**Why the decision is required:** An absolute lifetime limits long-lived stolen sessions but can interrupt long venue shifts.  
**What is blocked:** Final production session configuration and its acceptance test.

**Recommended default:** Eight-hour absolute maximum, matching the recorded engineering recommendation, with reauthentication afterward.

**Options:**

- A. Approve an eight-hour absolute maximum.
- B. Security/Operations supplies another exact maximum.
- C. Approve no absolute maximum beyond idle expiry, with explicit risk acceptance.

**Required response:** `DEC-SEC-01: A`, or `DEC-SEC-01: B - <duration>`, or `DEC-SEC-01: C`.

## DEC-SEC-02 - Exact rate-limit matrix

**Owner:** Security + Architecture  
**Current known state:** Throttling is approved in principle. The application currently uses five login attempts per minute keyed by normalized email plus IP, and 120 requests per minute on several sensitive route groups; this is not an approved complete matrix and does not cover every search/reset case explicitly.  
**Why the decision is required:** Limits must resist abuse without allowing one tenant or shared network to deny service to another.  
**What is blocked:** Production rate configuration and rate-limit acceptance evidence.

**Recommended default:** Security reviews the current values and supplies the missing reset/search/QR keys and values as one approved matrix.

**Options:**

- A. Approve the current implemented values where present and provide exact missing entries.
- B. Replace them with a complete Security-owned matrix.
- C. Block production until a penetration/load exercise recommends the matrix.

**Required response:** `DEC-SEC-02: A/B/C`; A or B must attach exact values and keys.

## DEC-SEC-03 - Audit and technical-log retention

**Owner:** Security + Legal/DPO + Operations  
**Current known state:** Audit must be immutable and separate from technical logs. Family operational data has a three-year eligibility baseline, but no approved duration exists for audit/log online storage, archive or disposal. A prior recommendation proposed 90 days online followed by approved archive.  
**Why the decision is required:** Retention affects accountability, privacy exposure, legal holds, storage and incident investigation.  
**What is blocked:** Archive/disposal implementation and production privacy/security approval.

**Recommended default:** Use the recorded 90-day online period as a provisional operational target, with Legal/DPO approving archive duration and hold exceptions before destructive execution.

**Options:**

- A. Approve 90 days online plus a separately stated archive duration and hold policy.
- B. Legal/Security supplies different online and archive durations.
- C. Retain indefinitely until a lawful schedule is approved; accept the privacy/storage risk explicitly.

**Required response:** `DEC-SEC-03: A/B/C`; A or B must include archive duration and hold authority.

## DEC-DR-01 - Production RPO/RTO and backup authority

**Owner:** Engineering/Architecture + Operations + Security  
**Current known state:** Encrypted automatic backups and restore testing are required, but numeric recovery point/time objectives, retention, region and restore authority are not approved.  
**Why the decision is required:** These values determine production topology, backup frequency, cost, runbooks and acceptance testing.  
**What is blocked:** Final disaster-recovery design and the production restore gate.

**Recommended default:** No numeric default is defensible without the selected hosting topology and business downtime/data-loss tolerance.

**Options:**

- A. Owners provide exact RPO, RTO, retention, region and restore-authority values for the selected platform.
- B. Select the hosting platform first, then approve those values before production deployment.
- C. Formally accept no production launch until the DR profile is approved.

**Required response:** `DEC-DR-01: A/B/C`; A must include all requested values.
