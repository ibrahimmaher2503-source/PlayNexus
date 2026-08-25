# Handoff

## Current state

The repository contains the audited PlayNexus product context, the canonical `DESIGN.md`, and one canonical documentation set directly under `docs/` in Markdown/YAML/HTML. The re-attached PRD matches the verified source hash. No Laravel application has been scaffolded and no application tests have run.

## How to continue

1. Read `README.md`, `AGENTS.md`, `DESIGN.md`, and `docs/00-INDEX.md`.
2. Review the detailed BRD through traceability matrix and resolve OQ-01–OQ-24 by gate priority.
3. Update `.ai/DECISIONS.md` with approvals.
4. Scaffold the chosen Laravel release, replace provisional commands, and implement M1 as a visible vertical slice.

The API contract currently validates as OpenAPI 3.1 with 56 paths, 70 operations, and 93 schemas. The interactive HTML wireframe contains 15 responsive keyboard-navigable screens. Incident artifacts are conditional on OQ-20. There is deliberately no Word/DOCX generator or artifact.

## Risks

- Incorrect tenant scoping is a critical security risk.
- Undefined pricing/pause/rounding rules can create billing disputes.
- Weak guardian verification or untracked overrides can create child-safety risk.
- Adding growth modules before the operational core is proven will delay the pilot.
