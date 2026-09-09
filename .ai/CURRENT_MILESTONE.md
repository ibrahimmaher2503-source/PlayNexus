# Current Milestone

## Milestone

M0: Laravel foundation scaffold installed; database-backed runtime remains unverified.

## Goal

Laravel 13 foundation exists with no business modules. Default PHPUnit tests, frontend build, and file-session/cache smoke are verified; MySQL migrations and runtime are not. Close required decisions before adding domain schema.

## Next three actions

1. Provision or select an isolated MySQL 8.4 database without changing shared services, then verify the default runtime and migration path.
2. Close required G1 decisions in the approved register.
3. Select the first approved M1 tenant/branch story; keep business modules and speculative contracts out of the scaffold.
