# M6 Operations, Restore, and Pilot Runbook

## Scope and release status

This runbook covers the bounded PlayNexus M6 local/staging candidate: core reports, database-transport operational notifications, audit search/export, synthetic pilot data, migration rehearsal, monitoring, restore verification, staff walkthrough, and support triage. It does not authorize production deployment, real email/SMS/WhatsApp, provider credentials, Finance/Legal approval, or staff/go-no-go sign-off.

## Required processes

Run the Laravel web process, one queue worker, and the scheduler against the same selected environment and database. Use the platform's process manager in staging/production; do not run ad-hoc interactive workers as a durability strategy.

```powershell
php artisan queue:work --tries=1 --timeout=60
php artisan schedule:work
```

The notification job owns its persisted bounded attempt counter. Queue-level retries stay at one so two retry systems cannot multiply attempts. The scheduler collects due session-ending and receipt intents every minute. The local `database` transport records provider acceptance as `sent`; it never marks `delivered`.

## Safe release sequence

1. Confirm the exact target environment, database server/version, database name, host/port, queue connection, cache/session connections, and application URL. Never infer production from `.env` names.
2. Put a release candidate through dependency audits, secret scanning, full tests, MySQL migration/rollback rehearsal, browser acceptance, and an isolated restore drill.
3. Capture a database backup and its checksum before migration. Keep encryption and retention outside the repository.
4. Run `php artisan migrate --force`, then `php artisan about`, `php artisan route:list`, `php artisan schedule:list`, `php artisan queue:monitor default:100`, and `/up`.
5. Start/reload web, queue, and scheduler processes. Verify one due synthetic notification, report totals, audit access, English LTR, and Arabic RTL.
6. Record approvers and evidence. If any mandatory gate is missing, status remains `PARTIAL` or `BLOCKED`.

## Migration and rollback rehearsal

Use an isolated copy, never the shared or production database. Record server/version and storage engine before the run.

```powershell
php artisan migrate:fresh --force
php artisan migrate:rollback --step=2 --force
php artisan migrate --force
php artisan migrate:status
```

Migration `000024` is the append-only audit trigger and `000023` creates notifications, so the isolated two-step rollback must remove both layers; migration forward must restore the notification tables and audit triggers while all earlier tables remain. Destructive rollback is a rehearsal tool, not the preferred production recovery path. For an applied production migration, restore/forward-fix from the verified backup unless the incident commander explicitly selects rollback after impact review.

## MySQL backup and isolated restore drill

Use credentials supplied through the environment or an approved option file; never place secrets in commands, logs, or this repository.

```powershell
mysqldump --single-transaction --routines --triggers --set-gtid-purged=OFF --databases <source_database> > <encrypted_backup_path>
Get-FileHash -Algorithm SHA256 <encrypted_backup_path>
mysql -e "CREATE DATABASE <isolated_restore_database> CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql --database=<isolated_restore_database> --execute="source C:/absolute/path/to/verified-backup.sql"
```

After restore, point only a task-local process at the isolated database. Verify migration status, tenant/branch row counts, paid/refunded reconciliation, session counts, audit rows, notification messages/attempts, and representative receipt numbers. Drop the isolated database only after evidence is captured. A successful dump without this restore verification is not backup readiness.

## Synthetic pilot data

On a local or isolated staging database only:

```powershell
php artisan db:seed --class=Database\\Seeders\\M6PilotSeeder --force
php artisan notifications:collect
php artisan queue:work --stop-when-empty --tries=1
```

The seeder is environment-guarded, idempotent, and labels names/emails with `[DEMO]` or `.test`. It creates no real child, guardian, destination, or payment data. It supplies one paid/completed visit, one due active session, one receipt, one payment, one staff audit event, and deterministic notification intents.

## Bounded performance profile

Until OQ-21 is approved, the recommended Egypt-pilot default is a maximum inclusive 31-day synchronous range, 25 rows per page, 500 rows per CSV chunk, and 100 records per notification collection chunk. Capture database query count, wall time, peak memory, HTTP status, and result count for each standard action and each report against synthetic production-shaped data. Proposed local candidate targets are p95 under 1 second for standard actions and under 2 seconds for report pages; these are engineering defaults, not approved SLOs.

The final 2026-09-15 local rehearsal used isolated MySQL 8.4.11/InnoDB with 1,000 synthetic committed orders/payments after occurrence-based payment/refund reconciliation. Ten authenticated revenue-report navigations rendered the bounded 25-row page with a 165 ms median and 194 ms observed p95/max, no horizontal overflow, and no browser warnings/errors. This is local engineering evidence only, not a staging capacity result or approved SLO.

## Monitoring and alert review

Monitor `/up`, HTTP 5xx/429 rates, slow requests/queries, queue depth and age, failed jobs, scheduler heartbeat, notification counts by terminal state, report failures/timeouts, database connections/storage/replication where applicable, and backup age/restore-drill age. Alerts must identify environment and correlation ID without including child notes, guardian contact, ticket payloads, receipt payloads, secrets, or payment references.

Initial support severity:

- P1: cross-tenant disclosure, wrong guardian release, duplicated/corrupted payment or refund, unrecoverable database outage.
- P2: branch-wide checkout/report/notification outage, sustained queue backlog, restore/backup failure.
- P3: isolated validation, localization, layout, or single-record operational issue.

Contain first, preserve request/audit IDs and timestamps, do not rewrite financial/audit facts, and escalate P1 immediately to engineering/security and the accountable business owner.

## Staff walkthrough and go/no-go

Use separate Owner, Branch Manager, Reception, and Cashier accounts. Walk through: sign-in and branch identity; English/Arabic switch; revenue reconciliation and CSV; attendance/session filters; staff activity and masked audit export; notification intent, sent-versus-delivered language, failure/retry state; transaction/receipt lookup; keyboard navigation and focus; support correlation ID; and logout/session handling.

Record attendee, role, environment, date/time, scenario result, open issue, trainer, and explicit sign-off. The release stays `LOCALLY_ENGINEERING_ACCEPTED` until staging, monitoring, restore drill, Finance/Legal approvals, staff rehearsal/sign-off, and named go/no-go approval all pass; only then may it be called `PILOT_READY`.
