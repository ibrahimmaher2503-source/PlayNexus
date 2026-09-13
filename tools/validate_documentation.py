#!/usr/bin/env python3
"""Fast structural checks for the PlayNexus documentation pack."""

from __future__ import annotations

import json
import re
import sys
from html.parser import HTMLParser
from pathlib import Path

try:
    import yaml
except ModuleNotFoundError:  # keep the repo check usable before PHP/Python tooling is installed
    yaml = None


ROOT = Path(__file__).resolve().parents[1]
PACK = ROOT / "docs"

REQUIRED = [
    ROOT / "README.md",
    ROOT / "AGENTS.md",
    ROOT / "PRODUCT.md",
    ROOT / "DESIGN.md",
    PACK / "00-INDEX.md",
    PACK / "01-PRD-Baseline.md",
    *[PACK / f"{number:02d}-{name}.md" for number, name in [
        (2, "BRD"),
        (3, "SRS"),
        (4, "User-Stories"),
        (5, "Use-Cases"),
        (6, "Architecture-Document"),
        (7, "Database-ERD"),
        (8, "Permission-Matrix"),
        (9, "API-Specification"),
        (10, "UI-UX-Wireframes"),
        (11, "Testing-Strategy"),
        (12, "Tooling-and-Delivery-Guide"),
    ]],
    PACK / "contracts" / "openapi.yaml",
    PACK / "wireframes" / "playnexus-wireframes.html",
    PACK / "13-Traceability-Matrix.md",
    PACK / "14-Coding-Standards.md",
    PACK / "15-Delivery-Milestones.md",
    PACK / "16-Implementation-Checklist.md",
    PACK / "17-Definition-of-Done.md",
    PACK / "18-Security-Checklist.md",
]

MARKDOWN_LINK = re.compile(r"\[[^\]]+\]\(([^)]+)\)")
CODE_FENCE = re.compile(r"^```", re.MULTILINE)
JSON_FENCE = re.compile(r"```json\s*\n(.*?)\n```", re.DOTALL | re.IGNORECASE)
PLACEHOLDER = re.compile(r"\b(?:TODO|TBD|TBC|FIXME|LOREM\s+IPSUM)\b", re.IGNORECASE)
FR_ID = re.compile(r"(?<![A-Z0-9-])(?:FR|NFR|SEC|DATA|INT|LOC)-[A-Z0-9][A-Z0-9-]*\b")
US_ID = re.compile(r"(?<![A-Z0-9-])US-[A-Z0-9][A-Z0-9-]*\b")
UC_ID = re.compile(r"(?<![A-Z0-9-])UC-[A-Z0-9][A-Z0-9-]*\b")
OQ_ID = re.compile(r"(?<![A-Z0-9-])OQ-[0-9]{2}\b")


class HtmlProbe(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.buttons = 0
        self.main = 0
        self.scripts = 0

    def handle_starttag(self, tag: str, attrs) -> None:
        if tag == "button":
            self.buttons += 1
        elif tag == "main":
            self.main += 1
        elif tag == "script":
            self.scripts += 1


def local_link_target(source: Path, raw_target: str) -> Path | None:
    target = raw_target.strip().split("#", 1)[0]
    if not target or re.match(r"^(?:https?://|mailto:|tel:)", target):
        return None
    return (source.parent / target.replace("%20", " ")).resolve()


def main() -> int:
    errors: list[str] = []
    warnings: list[str] = []

    for path in REQUIRED:
        if not path.is_file():
            errors.append(f"missing required file: {path.relative_to(ROOT)}")
        elif path.stat().st_size < 80:
            errors.append(f"required file is unexpectedly small: {path.relative_to(ROOT)}")

    markdown_files = sorted(ROOT.glob("*.md")) + sorted((ROOT / "docs").rglob("*.md")) + sorted((ROOT / ".ai").glob("*.md"))
    for path in markdown_files:
        text = path.read_text(encoding="utf-8")
        if len(CODE_FENCE.findall(text)) % 2:
            errors.append(f"unbalanced code fence: {path.relative_to(ROOT)}")
        for match in JSON_FENCE.finditer(text):
            try:
                json.loads(match.group(1))
            except json.JSONDecodeError as exc:
                start_line = text.count("\n", 0, match.start()) + 1
                errors.append(
                    f"invalid JSON example in {path.relative_to(ROOT)} near line "
                    f"{start_line}: {exc.msg}"
                )
        if PLACEHOLDER.search(text):
            warnings.append(f"review placeholder marker: {path.relative_to(ROOT)}")
        for match in MARKDOWN_LINK.finditer(text):
            target = local_link_target(path, match.group(1))
            if target is not None and not target.exists():
                errors.append(
                    f"broken local link in {path.relative_to(ROOT)} -> {match.group(1)}"
                )

    srs_path = PACK / "03-SRS.md"
    stories_path = PACK / "04-User-Stories.md"
    cases_path = PACK / "05-Use-Cases.md"
    if srs_path.exists() and stories_path.exists():
        srs_ids = set(FR_ID.findall(srs_path.read_text(encoding="utf-8")))
        story_text = stories_path.read_text(encoding="utf-8")
        missing = sorted(set(FR_ID.findall(story_text)) - srs_ids)
        if missing:
            errors.append(f"user stories reference undefined SRS IDs: {', '.join(missing)}")
    else:
        srs_ids = set()

    if stories_path.exists() and cases_path.exists():
        story_ids = set(US_ID.findall(stories_path.read_text(encoding="utf-8")))
        case_text = cases_path.read_text(encoding="utf-8")
        missing = sorted(set(US_ID.findall(case_text)) - story_ids)
        if missing:
            errors.append(f"use cases reference undefined story IDs: {', '.join(missing)}")
        case_ids = set(UC_ID.findall(case_text))
    else:
        story_ids, case_ids = set(), set()

    brd_path = PACK / "02-BRD.md"
    oq_ids = set(OQ_ID.findall(brd_path.read_text(encoding="utf-8"))) if brd_path.exists() else set()
    for path in sorted(PACK.glob("*.md")):
        text = path.read_text(encoding="utf-8")
        if path != srs_path:
            missing_requirements = sorted(
                requirement_id
                for requirement_id in set(FR_ID.findall(text))
                if requirement_id not in srs_ids
                and not any(candidate.startswith(requirement_id + "-") for candidate in srs_ids)
            )
            if missing_requirements:
                errors.append(
                    f"{path.relative_to(ROOT)} references undefined SRS IDs: "
                    + ", ".join(missing_requirements)
                )
        if path != stories_path:
            missing_stories = sorted(set(US_ID.findall(text)) - story_ids)
            if missing_stories:
                errors.append(
                    f"{path.relative_to(ROOT)} references undefined story IDs: "
                    + ", ".join(missing_stories)
                )
        if path != cases_path:
            missing_cases = sorted(set(UC_ID.findall(text)) - case_ids)
            if missing_cases:
                errors.append(
                    f"{path.relative_to(ROOT)} references undefined use-case IDs: "
                    + ", ".join(missing_cases)
                )
        missing_oqs = sorted(set(OQ_ID.findall(text)) - oq_ids)
        if missing_oqs:
            errors.append(
                f"{path.relative_to(ROOT)} references undefined open-question IDs: "
                + ", ".join(missing_oqs)
            )

    spec_path = PACK / "contracts" / "openapi.yaml"
    if spec_path.exists() and yaml is not None:
        try:
            spec_source = spec_path.read_text(encoding="utf-8")
            syntax_tree = yaml.compose(spec_source)

            def check_duplicate_yaml_keys(node, location: str = "root") -> None:
                if isinstance(node, yaml.nodes.MappingNode):
                    seen: set[str] = set()
                    for key_node, value_node in node.value:
                        key = str(getattr(key_node, "value", "<complex-key>"))
                        if key in seen:
                            errors.append(f"duplicate OpenAPI YAML key at {location}: {key}")
                        seen.add(key)
                        check_duplicate_yaml_keys(value_node, f"{location}.{key}")
                elif isinstance(node, yaml.nodes.SequenceNode):
                    for index, child in enumerate(node.value):
                        check_duplicate_yaml_keys(child, f"{location}[{index}]")

            if syntax_tree is not None:
                check_duplicate_yaml_keys(syntax_tree)

            spec = yaml.safe_load(spec_source)
            if not isinstance(spec, dict):
                errors.append("OpenAPI root must be an object")
            else:
                if not str(spec.get("openapi", "")).startswith("3.1"):
                    errors.append("OpenAPI document must declare version 3.1.x")
                if not isinstance(spec.get("paths"), dict) or not spec["paths"]:
                    errors.append("OpenAPI document has no paths")
                if not isinstance(spec.get("components"), dict):
                    errors.append("OpenAPI document has no components object")
                operation_ids: list[str] = []
                for path_name, path_item in spec.get("paths", {}).items():
                    if not isinstance(path_item, dict):
                        continue
                    inherited_parameters = path_item.get("parameters", [])
                    for method in ("get", "post", "put", "patch", "delete", "options", "head"):
                        operation = path_item.get(method)
                        if not isinstance(operation, dict):
                            continue
                        operation_id = operation.get("operationId")
                        if not operation_id:
                            errors.append(f"OpenAPI operation has no operationId: {method.upper()} {path_name}")
                        else:
                            operation_ids.append(str(operation_id))

                        placeholders = set(re.findall(r"\{([^}]+)\}", path_name))
                        declared: set[str] = set()
                        for parameter in [*inherited_parameters, *operation.get("parameters", [])]:
                            if isinstance(parameter, dict) and parameter.get("in") == "path":
                                declared.add(str(parameter.get("name")))
                            elif isinstance(parameter, dict) and "$ref" in parameter:
                                ref_name = str(parameter["$ref"]).rsplit("/", 1)[-1]
                                component = spec.get("components", {}).get("parameters", {}).get(ref_name, {})
                                if isinstance(component, dict) and component.get("in") == "path":
                                    declared.add(str(component.get("name")))
                        if placeholders != declared:
                            errors.append(
                                f"OpenAPI path parameters mismatch for {method.upper()} {path_name}: "
                                f"path={sorted(placeholders)}, declared={sorted(declared)}"
                            )

                duplicate_operation_ids = sorted(
                    {item for item in operation_ids if operation_ids.count(item) > 1}
                )
                if duplicate_operation_ids:
                    errors.append(
                        "duplicate OpenAPI operationIds: " + ", ".join(duplicate_operation_ids)
                    )

                api_markdown_path = PACK / "09-API-Specification.md"
                if api_markdown_path.exists():
                    catalog_operations = {
                        (method.lower(), path.split("?", 1)[0])
                        for method, path in re.findall(
                            r"`(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\s+(/[^`\s]+)",
                            api_markdown_path.read_text(encoding="utf-8"),
                        )
                    }
                    contract_operations = {
                        (method, path_name)
                        for path_name, path_item in spec.get("paths", {}).items()
                        if isinstance(path_item, dict)
                        for method in ("get", "post", "put", "patch", "delete", "options", "head")
                        if isinstance(path_item.get(method), dict)
                    }
                    # First-party session routes are separate from the planned bearer API.
                    for web_route in spec.get("x-playnexus-implemented-web-ticketing", {}).get("routes", []):
                        matched = re.fullmatch(r"(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD) (/app/[^\s]+)", str(web_route))
                        if not matched:
                            errors.append(f"invalid implemented web route: {web_route}")
                        else:
                            contract_operations.add((matched[1].lower(), matched[2]))
                    missing_from_contract = sorted(catalog_operations - contract_operations)
                    missing_from_catalog = sorted(contract_operations - catalog_operations)
                    if missing_from_contract:
                        errors.append(
                            "API Markdown operations missing from OpenAPI: "
                            + ", ".join(f"{method.upper()} {path}" for method, path in missing_from_contract)
                        )
                    if missing_from_catalog:
                        errors.append(
                            "OpenAPI operations missing from API Markdown: "
                            + ", ".join(f"{method.upper()} {path}" for method, path in missing_from_catalog)
                        )

                refs: set[str] = set()

                def collect_refs(value) -> None:
                    if isinstance(value, dict):
                        for key, child in value.items():
                            if key == "$ref" and isinstance(child, str):
                                refs.add(child)
                            else:
                                collect_refs(child)
                    elif isinstance(value, list):
                        for child in value:
                            collect_refs(child)

                collect_refs(spec)
                for ref in sorted(refs):
                    if not ref.startswith("#/"):
                        errors.append(f"external OpenAPI reference is not allowed in MVP contract: {ref}")
                        continue
                    current = spec
                    try:
                        for segment in ref[2:].split("/"):
                            current = current[segment.replace("~1", "/").replace("~0", "~")]
                    except (KeyError, TypeError):
                        errors.append(f"unresolved OpenAPI reference: {ref}")
        except Exception as exc:
            errors.append(f"OpenAPI YAML parse failed: {exc}")
    elif spec_path.exists():
        spec_text = spec_path.read_text(encoding="utf-8")
        if not re.search(r"^openapi:\s*['\"]?3\.1", spec_text, re.MULTILINE):
            errors.append("OpenAPI document must declare version 3.1.x")
        if not re.search(r"^paths:\s*$", spec_text, re.MULTILINE):
            errors.append("OpenAPI document has no paths section")
        if not re.search(r"^components:\s*$", spec_text, re.MULTILINE):
            errors.append("OpenAPI document has no components section")
        warnings.append("PyYAML is unavailable; OpenAPI received only baseline text checks")

    html_path = PACK / "wireframes" / "playnexus-wireframes.html"
    if html_path.exists():
        try:
            probe = HtmlProbe()
            probe.feed(html_path.read_text(encoding="utf-8"))
            if probe.main != 1:
                errors.append(f"wireframe HTML should contain one main landmark, found {probe.main}")
            if probe.buttons < 4:
                errors.append("wireframe HTML needs navigable screen controls")
            if probe.scripts < 1:
                warnings.append("wireframe HTML has no script; confirm navigation is still usable")
        except Exception as exc:
            errors.append(f"wireframe HTML parse failed: {exc}")

    print(f"Markdown files checked: {len(markdown_files)}")
    print(f"Requirement IDs found in SRS: {len(srs_ids)}")
    print(f"User story IDs found: {len(story_ids)}")
    print(f"Use case IDs found: {len(case_ids)}")
    print(f"Open question IDs found: {len(oq_ids)}")
    if spec_path.exists() and yaml is not None and isinstance(locals().get("spec"), dict):
        operation_count = sum(
            1
            for path_item in spec.get("paths", {}).values()
            if isinstance(path_item, dict)
            for method in path_item
            if method in {"get", "post", "put", "patch", "delete", "options", "head"}
        )
        print(f"OpenAPI paths/operations: {len(spec.get('paths', {}))}/{operation_count}")
    for warning in warnings:
        print(f"WARNING: {warning}")
    for error in errors:
        print(f"ERROR: {error}")
    if errors:
        print(f"FAILED: {len(errors)} error(s), {len(warnings)} warning(s)")
        return 1
    print(f"PASSED: 0 errors, {len(warnings)} warning(s)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
