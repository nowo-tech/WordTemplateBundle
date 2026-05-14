# Spec-driven development

In this repository, **spec-driven development** has two layers that stay in sync:

1. **Product behavior** — what **WordTemplateBundle** guarantees to applications that integrate it (placeholders, context types, configuration). This is spelled out below and in [`USAGE.md`](USAGE.md) / [`CONFIGURATION.md`](CONFIGURATION.md); **PHPUnit** and **PHPStan** enforce it in CI.
2. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); tests and static analysis are the mechanical proof.

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
| `HtmlContent` | `setComplexBlock` with HTML rendered via PHPWord (`Html::addHtml`). |
| `ImageSource` | `setImageValue` with optional width/height. |

- **Symfony integration:** the bundle registers the **`nowo_word_template`** extension; options include `macro_opening` and `macro_closing` (must match what template authors type in Word). See [`CONFIGURATION.md`](CONFIGURATION.md).

**Explicit non-goals**

- Executing or embedding **Word VBA**.
- **Guaranteeing** pixel-perfect or full Word feature parity (headers/footers, advanced numbering, etc. depend on PHPWord and template design; rich HTML has the usual PHPWord limits noted in [`README.md`](../README.md) and [`USAGE.md`](USAGE.md)).

**Demos** (`demo/symfony7`, `demo/symfony8`) illustrate integration (forms, download, optional PDF via PhpWord+DomPDF); they are **not** part of the Packagist package API — the contract for consumers is the processor, models, and extension above.

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
| `REQ-DEMO-005` | [`demo/symfony7/Makefile`](../demo/symfony7/Makefile), [`demo/symfony8/Makefile`](../demo/symfony8/Makefile) | Canonical `make up` behavior (wait, `composer install`, “Demo started at: …” message) |
| `REQ-DEMO-007` | Same demo Makefiles | `make update-bundle`: sync mounted bundle, autoload, cache |

When you change scripted behavior, **update the existing `REQ-*` comment** if the requirement ID still describes the rule, or **introduce a new `REQ-*`** and reference it from the PR description and any affected docs.

---

## Suggested workflow for contributors

1. **Clarify behavior** in an issue or draft PR: acceptance criteria for the **bundle** (functional spec) and, if relevant, for **Makefiles/demos** (`REQ-*`).
2. **Implement** with tests and static analysis; keep coverage at or above the floor.
3. **Anchor scripts and demos** when dev UX changes: add or adjust `REQ-*` comments.
4. **Ship docs** for integrators when behavior or configuration changes: [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`CHANGELOG.md`](CHANGELOG.md), and [`UPGRADING.md`](UPGRADING.md) when consumers must change code or config.

---

## Relationship to Engram / external checklists

[`ENGRAM.md`](ENGRAM.md) covers Nowo-wide documentation checklist items. This document ties together **what the bundle does**, **how we verify it**, and **local `REQ-*` habits**. Both can coexist: Engram for org-level compliance, this file for product + traceability expectations.

---

## See also

- [`USAGE.md`](USAGE.md)
- [`CONFIGURATION.md`](CONFIGURATION.md)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`RELEASE.md`](RELEASE.md)
- [`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md)
