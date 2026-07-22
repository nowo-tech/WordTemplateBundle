# Spec-driven development

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of production code in `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **WordTemplateBundle** guarantees to applications that integrate it (placeholders, context types, configuration). This is spelled out below and in [`USAGE.md`](USAGE.md) / [`CONFIGURATION.md`](CONFIGURATION.md); **PHPUnit** and **PHPStan** enforce it in CI.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); tests and static analysis are the mechanical proof.

---

## User stories

The sections above/below state **behavior**; this subsection states **intent** in backlog-friendly form. Each story maps to the [bundle functional scope](#bundle-functional-scope) and to [`USAGE.md`](USAGE.md).

| ID | Story |
| --- | --- |
| US-01 | **As a** Symfony integrator, **I want** to call `WordTemplateProcessorInterface::process($templatePath, $context)` **so that** I obtain a filled `.docx` (`ProcessedDocument`) without hand-coding PHPWord `TemplateProcessor` wiring. |
| US-02 | **As a** integrator, **I want** nested PHP arrays in `$context` to become dot-key placeholders (e.g. `client.city`) **so that** Word authors can use `${client.city}` without I flatten arrays manually. |
| US-03 | **As a** integrator, **I want** scalars, booleans, `null`, and `Stringable` values merged predictably (`setValue`, empty for `null`, `1`/`0` for booleans) **so that** simple mail-merge fields behave consistently. |
| US-04 | **As a** integrator, **I want** to pass `TableRows` for a declared anchor column **so that** repeating invoice/line items map to cloned table rows with `#1`, `#2`, … placeholders. |
| US-05 | **As a** integrator, **I want** to pass `HtmlContent` for a block placeholder **so that** rich fragments from HTML render inside the document via PHPWord (within PHPWord limits). |
| US-06 | **As a** integrator, **I want** to pass `ImageSource` with optional width/height **so that** logos or signatures replace image placeholders. |
| US-07 | **As a** platform maintainer, **I want** `nowo_word_template.macro_opening` / `macro_closing` configurable **so that** they match what template authors typed in Word (defaults `${` / `}`). |
| US-08 | **As a** integrator, **I want** to pass `ConditionalBlock` for `${#if name}` … `${#endif name}` regions **so that** whole paragraphs or sections appear or disappear from PHP. |
| US-09 | **As a** integrator, **I want** nested conditional blocks and separate `conditional_*` delimiters **so that** inner regions resolve before outer ones and markers can differ from scalar placeholders. |

**Out of scope for these stories:** running Word VBA; inline `#if` inside a single paragraph (use a computed scalar instead); `elseif` / `else` in template syntax (v1).

---

## Bundle functional scope

**Goal:** fill a **Microsoft Word `.docx` template** using PHPWord’s [`TemplateProcessor`](https://phpoffice.github.io/PHPWord/docs/classes/PhpOffice-PhpWord-TemplateProcessor.html), driven by a **PHP context array** passed to `WordTemplateProcessorInterface::process()`.

**In scope**

- **Placeholders** in the document use the configured macro delimiters (default `${` … `}`). Authors type placeholders in Word; the bundle does **not** run Word VBA macros — “macros” here means **template placeholders** understood by PHPWord.
- **Nested arrays** in context are **flattened** to dot keys (e.g. `['client' => ['city' => 'Madrid']]` → `client.city`) for scalar replacement.
- **Context value types** and their mapping to PHPWord:

| PHP value | Behaviour |
| --- | --- |
| Scalars / `null` | `setValue`; booleans → `1` / `0`; `null` → empty string. |
| Nested arrays | Flattened before `setValue` (dot keys). |
| `Stringable` | Cast to string, then `setValue`. |
| `TableRows` | `cloneRow` on an anchor column, then `setValue` for `placeholder#N` per row/cell. |
| `ConditionalBlock` | Show or remove `${#if block}` … `${#endif block}` regions (nested, inside-out); markers use `conditional_*` config keys. |
| `HtmlContent` | `setComplexBlock` with HTML rendered via PHPWord (`Html::addHtml`). |
| `ImageSource` | `setImageValue` with optional width/height. |

- **Symfony integration:** the bundle registers the **`nowo_word_template`** extension. Placeholder delimiters: `macro_opening`, `macro_closing`. Conditional delimiters: `conditional_if_opening`, `conditional_if_closing`, `conditional_endif_opening`, `conditional_endif_closing`. Merge **`timeout`** (default **180**, **REQ-RUNTIME-001**). See [`CONFIGURATION.md`](CONFIGURATION.md).

- **Compatibility:** PHP **8.2+**, Symfony **7.x / 8.x** (`composer.json`).

**Explicit non-goals**

- Executing or embedding **Word VBA**.
- **Guaranteeing** pixel-perfect or full Word feature parity (headers/footers, advanced numbering, etc. depend on PHPWord and template design; rich HTML has the usual PHPWord limits noted in [`README.md`](../README.md) and [`USAGE.md`](USAGE.md)).

**Demos** (`demo/symfony8`, FrankenPHP) illustrate integration: dynamic form from `listVariables()`, scalars (dot keys), PHP-computed inline scalar (`client_tier_label`), `HtmlContent`, `TableRows`, `ConditionalBlock` (including nested `funding_detail`), `ImageSource` (`demo_logo`), filled `.docx` / PDF download, blank template download, and commented `config/packages/nowo_word_template.yaml`. Demos are **not** part of the Packagist package API.

---

## Validating the functional spec

- Run **`composer qa`** (or **`make qa`** in Docker): coding standard check, PHPStan, PHPUnit.
- **Line coverage** must meet the project minimum (`composer coverage-check`).
- New or changed behavior should add or adjust **tests** under `tests/` rather than relying on prose alone.

---

## Requirement identifiers (`REQ-*`)

`REQ-*` labels live in comments next to Make targets or conventions. They let PRs, issues, and docs point at the same **tooling** behavior without hunting through diffs.

| ID | Where | What it marks |
| --- | --- | --- |
| `REQ-MAKE-001` | Root [`Makefile`](../Makefile) | Docker-driven development workflow for the bundle |
| `REQ-DEMO-005` | [`demo/symfony8/Makefile`](../demo/symfony8/Makefile) | Canonical `make up` behavior (wait, `composer install`, “Demo started at: …” message) |
| `REQ-DEMO-007` | [`demo/symfony8/Makefile`](../demo/symfony8/Makefile) | `make update-bundle`: sync mounted bundle, autoload, cache |

When you change scripted behavior, **update the existing `REQ-*` comment** if the requirement ID still describes the rule, or **introduce a new `REQ-*`** and reference it from the PR description and any affected docs.

---

## Suggested workflow for contributors

1. **Clarify behavior** in an issue or draft PR: acceptance criteria for the **bundle** (functional spec) and, if relevant, for **Makefiles/demos** (`REQ-*`).
2. **Implement** with tests and static analysis; keep coverage at or above the floor.
3. **Anchor scripts and demos** when dev UX changes: add or adjust `REQ-*` comments.
4. **Ship docs** for integrators when behavior or configuration changes: [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`CHANGELOG.md`](CHANGELOG.md), and [`UPGRADING.md`](UPGRADING.md) when consumers must change code or config.
5. **Keep Spec Kit artifacts in sync** when production code under `src/` changes:
   - Update [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) and [`code-inventory.md`](../specs/001-baseline/code-inventory.md).
   - Follow the maintainer checklist in [`SPEC-KIT.md`](SPEC-KIT.md).
   - For **new features**, use Cursor Agent skills (`/speckit-specify`, `/speckit-plan`, `/speckit-tasks`) as documented in SPEC-KIT.

---


## GitHub Spec Kit (summary)

This repository uses [GitHub Spec Kit](https://github.com/github/spec-kit) with **Cursor Agent** (`cursor-agent` integration).

| Artifact | Path |
| --- | --- |
| **Operator manual** (install, init, usage) | [`SPEC-KIT.md`](SPEC-KIT.md) |
| Baseline spec | [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) |
| Code inventory (100%) | [`specs/001-baseline/code-inventory.md`](../specs/001-baseline/code-inventory.md) |
| Constitution | [`.specify/memory/constitution.md`](../.specify/memory/constitution.md) |
| Cursor Agent skills | [`.cursor/skills/`](../.cursor/skills/) (`speckit-*`) |

**Quick start (maintainers):**

```bash
# Install Specify CLI (once per machine) — see SPEC-KIT.md
specify init --here --force --integration cursor-agent --script sh
specify integration list   # Cursor → installed (default)
```

In Cursor Agent, start a new feature with `/speckit-specify <description>`. For day-to-day tooling details, skills reference, folder layout, and troubleshooting, read **[`SPEC-KIT.md`](SPEC-KIT.md)**.

---

## Relationship to Engram / external checklists

[`ENGRAM.md`](ENGRAM.md) covers Nowo-wide documentation checklist items. This document ties together **what the bundle does**, **how we verify it**, and **local `REQ-*` habits**. Both can coexist: Engram for org-level compliance, this file for product + traceability expectations.

---

## See also

- [`SPEC-KIT.md`](SPEC-KIT.md) — GitHub Spec Kit manual (install, structure, usage)
- [`USAGE.md`](USAGE.md)
- [`CONFIGURATION.md`](CONFIGURATION.md)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`RELEASE.md`](RELEASE.md)
- [`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md)
