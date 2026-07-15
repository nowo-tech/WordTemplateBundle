# Feature Specification: WordTemplateBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Last updated**: 2026-07-15  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/word-template-bundle`  
**Configuration root**: `nowo_word_template`

Symfony bundle wrapping PHPWord **TemplateProcessor** to fill `.docx` templates from PHP context: scalars, nested arrays (dot keys), `TableRows`, `ConditionalBlock`, `HtmlContent`, and `ImageSource` values.

### Compatibility (integrators)

| Requirement | Range |
| --- | --- |
| PHP | **8.2+** (`<8.6` in `composer.json`) |
| Symfony | **7.x / 8.x** (`^7.0 \|\| ^8.0` on bundle components) |

Symfony **6.4** is no longer supported as of **1.1.0** (see [`docs/UPGRADING.md`](../../docs/UPGRADING.md)).

---

## User Scenarios & Testing

Aligned with [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md) US-01–US-09.

| ID | Scenario | Proof |
| --- | --- | --- |
| US-01 | `process()` returns `ProcessedDocument` | Integration tests |
| US-02 | Nested arrays → dot keys | `ContextFlattenerTest`, integration |
| US-03 | Scalars / bool / null | Integration tests |
| US-04 | `TableRows` → `cloneRow` | Integration tests |
| US-05 | `HtmlContent` | Integration tests |
| US-06 | `ImageSource` | Integration tests |
| US-07 | Configurable placeholder delimiters | `ConfigurationTest`, integration |
| US-08 | `ConditionalBlock` show/hide regions | Unit + integration tests |
| US-09 | Configurable conditional delimiters; nested blocks inside-out | Unit + integration tests |

**Demo** (`demo/symfony8`, not Packagist API): exercises all context types above plus inline scalar choice (PHP-computed `${client_tier_label}`), nested `optional_funding` / `funding_detail`, and commented `nowo_word_template.yaml`.

---

## Requirements

### Bundle & configuration

- **FR-BUNDLE-001**: `WordTemplateBundle` entry class.
- **FR-CFG-001**: Config tree root `nowo_word_template` with placeholder delimiters `macro_opening` (default `${`), `macro_closing` (default `}`).
- **FR-CFG-002**: Conditional delimiters `conditional_if_opening` (default `${#if`), `conditional_if_closing` (default `}`), `conditional_endif_opening` (default `${#endif`), `conditional_endif_closing` (default `}`).
- **FR-CFG-003**: Parameters wired to `WordTemplateProcessor` via `services.yaml`.

### Processing

- **FR-PROC-001**: `WordTemplateProcessorInterface` — `process()`, `listVariables()`.
- **FR-PROC-002**: `WordTemplateProcessor` — opens template with configured delimiters; applies **conditional blocks first** (inside-out), then `TableRows`, then scalars / `HtmlContent` / `ImageSource`.
- **FR-PROC-003**: `ProcessedDocument` — path/stream accessors and `dispose()`.
- **FR-PROC-004**: `listVariables()` omits conditional marker names (`#if …`, `#endif …`).

### Domain models (**FR-MDL-002**)

| Model | Role |
| --- | --- |
| `HtmlContent` | Rich HTML via `setComplexBlock` |
| `ImageSource` | Image path + optional dimensions |
| `TableRows` | Repeating table row anchor + row data |
| `ConditionalBlock` | `blockName` + `visible`; Twig-style `${#if name}` … `${#endif name}` regions |

### Utilities (**FR-UTIL-001**)

- `ContextFlattener` — dot keys; preserves model objects.
- `ConditionalBlockApplicator` — resolves conditional regions in WordprocessingML paragraph boundaries; **nested blocks** resolved deepest-first.
- `TemplateProcessorBridge` — internal access to document XML parts for conditional transforms.

### Errors (**FR-ERR-001**)

- `TemplateNotFoundException`, `InvalidContextValueException`, `WordTemplateExceptionInterface`.

### DI (**FR-DI-001**)

- `Resources/config/services.yaml`, `Resources/config/nowo_word_template.yaml` (commented example).

---

## Conditional blocks (v1 scope)

**In scope**

- Simple `if` / `endif` pairs only (no `elseif` / `else` in template syntax).
- Block markers in **separate Word paragraphs** (PHPWord paragraph constraint).
- **Nested** regions with distinct block names (inside-out processing).
- Visibility controlled from PHP via `ConditionalBlock($name, $visible)`.

**Out of scope (v1)**

- Inline `#if` markers mid-paragraph (use a **scalar** computed in PHP for “word A or B” in one line).
- Template-side expression evaluation.
- Word VBA execution.

**Template authoring (defaults)**

```
${#if optional_funding}
${#if funding_detail}
${optional_funding.note}
${#endif funding_detail}
${#endif optional_funding}
```

---

## Success Criteria

- **SC-001**: 100% of production files in `src/` appear in [`code-inventory.md`](code-inventory.md) with requirement IDs (**18/18** mapped).
- **SC-002**: Configuration keys in `docs/CONFIGURATION.md` match `Configuration.php`.
- **SC-003**: `composer qa` / `make release-check` pass in CI (PHPUnit, PHPStan).
- **SC-004**: No Packagist-visible behavior change without spec, inventory, and test updates.

---

## Validation

| Check | Command |
| --- | --- |
| Full QA | `make release-check` or `composer qa` |
| Code inventory audit | `find src -type f ! -path '*/assets/dist/*' \| wc -l` |

When changing behavior, update this spec, `code-inventory.md`, integrator docs, and tests.
