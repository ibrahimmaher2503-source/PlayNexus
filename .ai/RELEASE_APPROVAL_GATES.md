# Checkpoint 00.3 - Release / External Approval Gates

These are approvals or evidence gates, not unresolved Product behavior. Status values are limited to `NOT_STARTED`, `IN_PROGRESS`, `READY_FOR_APPROVAL`, `APPROVED`, and `BLOCKED`.

| Approval | Owner | Evidence required | Current status | Gate |
|---|---|---|---|---|
| Product scope and behavior baseline | Product Owner / Sponsor | Approved OQ-01 through OQ-24 decisions, explicit exclusions and change-control ownership | APPROVED | G0/G1 scope baseline |
| Commercial plan launch values | Commercial Owner | Final launch monthly/yearly EGP prices, discount presentation, manual invoice operating procedure and tenant communication | IN_PROGRESS | OQ-04 commercial launch |
| Notification procurement | Procurement / Commercial / Operations | DEC-NOT-01 through DEC-NOT-05 answered, contracts, sender verification, approved templates, credentials/callback ownership | BLOCKED | Real customer notification release |
| Operations and child safety | Operations / Safety | Branch procedures for guardian verification/override, outage continuity, capacity, refund handoff, escalation contacts and trained staff walkthrough | IN_PROGRESS | G3 pilot readiness |
| Finance and reconciliation | Finance / Operator accounting | Approved branch tax configuration, payment/refund reconciliation fixtures, receipt wording/number scope, manual SaaS invoice procedure and sign-off | IN_PROGRESS | G3/G4 finance release |
| Legal / Privacy / DPO | Controller Legal/DPO | Final Arabic/English notice, controller/DPO contacts, lawful basis, processors/transfers, licensing assessment, actual retention/legal-hold schedule, DSAR/breach procedures and dry run | BLOCKED | G3/G4 privacy release |
| Security policy values | Security / Operations / Legal | DEC-SEC-01 through DEC-SEC-03 answered; risk owners named | BLOCKED | Production security freeze |
| Security implementation validation | Security | TLS/cookie/config scan, managed-secret evidence/rotation, MFA proof, rate tests, dependency/secret/SAST/DAST/manual access tests, no unresolved Critical/High finding | IN_PROGRESS | G4 security approval |
| Engineering architecture/capacity | Engineering / Architecture | Selected staging/production topology; OQ-21 workload profile covering normal/peak users, tenants, branches, sessions, transactions, notifications, report horizon/page/date caps/data horizon; deployed p95 results | IN_PROGRESS | G2/G3 solution and capacity readiness |
| OQ-04 technical acceptance | Engineering / QA | MySQL 8.4 migration/constraint/locking/concurrency evidence and real-browser plan/subscription/manual-billing/limit flows | BLOCKED | OQ-04 implementation acceptance |
| Backup and disaster recovery | Engineering / Operations / Security | DEC-DR-01 answer, encrypted automatic backup evidence, alerts, checksum, isolated restore report and reconciled tenant/session/finance/receipt/audit data | BLOCKED | G3 restore gate |
| Observability and support operations | Operations / Engineering | Deployed uptime/error/queue/scheduler/database/resource/backup alerts, named responders, runbooks and exercised alert routes | NOT_STARTED | G3 production operations |
| Deployment / CI/CD | Engineering / Release Manager | Hosted CI result, selected staging, safe migration/rollback rehearsal, immutable artifact/configuration record, smoke checks and rollback authority | IN_PROGRESS | G3/G4 deployment gate |
| Accessibility and compatibility | QA / Product / Operations | Automated accessibility scan, keyboard/screen-reader smoke, contrast/focus exceptions, approved browser/tablet/scanner/printer matrix | NOT_STARTED | G3 usability gate |
| Integrated QA | QA | Full release-candidate regression on production-equivalent database/configuration, negative authorization, concurrency/idempotency, localization/RTL and defect disposition | IN_PROGRESS | G3 QA approval |
| Pilot Tenant UAT | Pilot Tenant Owner and role representatives | Signed Owner/Manager/Reception/Cashier task scripts, training evidence, reconciliation and safety outcomes, accepted issues | NOT_STARTED | G3/G4 UAT |
| Product release acceptance | Product Owner / Sponsor | Confirm intended release matches approved scope, open decisions are closed or excluded, UAT/metrics reviewed | NOT_STARTED | G4 Product approval |
| Final go/no-go | Named Sponsor, Product, Operations, Finance, Security | All mandatory gates approved, residual risks accepted, rollback/support contacts named, launch record signed | BLOCKED | G4 production launch |

## Interpretation

- Product decision approval does not satisfy deployment, regulatory, financial, security or UAT approval.
- OQ-21 target behavior is approved; its workload fixture and deployed measurements belong to Engineering/QA evidence above.
- GAP-07 behavior is approved; Security/Legal validation is a release gate while implementation remains partial.
- GAP-08 engineering behavior is approved; destructive retention execution remains disabled until the Legal/DPO gate is approved.
- No gate is marked `APPROVED` except the recorded Product scope/behavior baseline.
