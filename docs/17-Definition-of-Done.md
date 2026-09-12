# Definition of Done

A backlog item is done only when:

- behavior and scope match an approved requirement and acceptance criteria;
- UI includes relevant loading, empty, validation, conflict, success, and denied states;
- tenant and branch scope, permission, state, validation, audit, and privacy behavior are implemented;
- schema, API contract, localization keys, and documentation are updated when affected;
- focused automated tests cover risky rules and the real commands/results are recorded;
- code is formatted, reviewed, and free of known high-severity defects;
- migrations are safe for existing pilot data and a rollback or recovery approach is known;
- accessibility, responsive LTR/RTL behavior, logs/metrics, and operational support are considered for the changed surface;
- `.ai/PROGRESS.md`, `.ai/TEST_RESULTS.md`, `.ai/DECISIONS.md`, and `.ai/HANDOFF.md` reflect reality.

## M1 exit record — 2026-09-12

M1 meets this gate for its implemented access-and-branch boundary: scoped authorization and audit behavior have focused regressions; the full suite passes on PHP 8.4.21, PHP 8.5.8, SQLite, and isolated MySQL 8.4.11/InnoDB; the production build, formatter, dependency audits, and documentation validator pass; and Arabic RTL/English LTR browser checks cover the responsive shell and settings/audit surfaces. Release operations, backup/restore, and later business workflows are not part of this milestone exit.
