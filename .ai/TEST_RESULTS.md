# Test Results

No application tests have run because the Laravel application has not been scaffolded.

## 2026-08-24 documentation QA

| Check | Real result |
|---|---|
| Compare original and re-attached PRD with SHA-256 | Pass: both 27,198 bytes; hash `D9119C1A3325DD8DBF318296EB52DDFF95B064A0C0061EAD96CAAD68D9FEF5D2` |
| `tools/validate_documentation.py` with PyYAML | Pass after adding `DESIGN.md`: 29 Markdown files, 199 SRS IDs, 50 stories, 14 use cases, 24 OQs, 56 OpenAPI paths/70 operations; 0 errors, 0 warnings |
| `python -m openapi_spec_validator docs/contracts/openapi.yaml` | Pass: OpenAPI 3.1 contract OK |
| `tools/smoke_wireframes.cjs` | Pass: 15 screens, desktop/tablet/narrow and RTL layouts, unique IDs, labels/accessibility names, and no console errors |
| Python compile and Node syntax check for QA scripts | Pass |
| Scope/tool artifact scan | Pass: 0 PostgreSQL/psql references and 0 Word/DOCX artifacts or generators |
| Design fallback color contrast check | Pass for the approved non-purple/non-cream palette: primary text `14.34:1`, muted text `5.44:1`, primary action pairs `5.90:1`, and semantic text pairs `5.25:1` or better |
| Final ZIP content audit | Pass after adding `DESIGN.md`: 34 entries, all required artifacts present, and 0 Word/DOCX, QA-output, generator, duplicate-summary, or `__pycache__` entries |

These checks validate documentation structure and the static contract/prototype only. They do not demonstrate application behavior, migrations, tenant isolation, money/time correctness, security, or release readiness; those require the Laravel scaffold and real tests.

The first post-move OpenAPI and browser commands could not load their isolated validator and Playwright dependencies from the default shell. They were rerun successfully with the workspace's bundled Python/Node runtimes and isolated QA dependencies; no specification or wireframe defect caused those environment failures.
