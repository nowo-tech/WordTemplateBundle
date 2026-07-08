# Feature Specification: WordTemplateBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/word-template-bundle`  
**Configuration root**: `nowo_word_template`


Symfony bundle wrapping PHPWord **TemplateProcessor** to fill `.docx` templates from PHP context: scalars, nested arrays (dot keys), `TableRows`, `HtmlContent`, and `ImageSource` values.

---

## User Scenarios & Testing

Aligned with [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md) US-01–US-07: process templates, flatten context, typed value mapping, configurable macro delimiters.

---

## Requirements

- **FR-BUNDLE-001 / FR-CFG-001 / FR-CFG-002**: Bundle and `nowo_word_template` config (`macro_opening`, `macro_closing`).
- **FR-PROC-001 / FR-PROC-002**: `WordTemplateProcessorInterface` and default processor using PHPWord.
- **FR-PROC-003**: `ProcessedDocument` result with stream/path accessors.
- **FR-MDL-002**: Value objects `HtmlContent`, `ImageSource`, `TableRows`.
- **FR-UTIL-001**: `ContextFlattener` for dot-key placeholders.
- **FR-ERR-001**: Template not found, invalid context exceptions.
- **FR-DI-001**: Default config snippet + services YAML.

---

## Success Criteria

- **SC-001**: 100% of production files in `src/` appear in [`code-inventory.md`](code-inventory.md) with requirement IDs (15/15 mapped).
- **SC-002**: Configuration keys in `docs/CONFIGURATION.md` match `Configuration.php`.
- **SC-003**: `composer qa` / `make release-check` pass in CI (PHPUnit, PHPStan).
- **SC-004**: No Packagist-visible behavior change without spec, inventory, and test updates.

---

## Validation

| Check | Command |
| --- | --- |
| Full QA | `make release-check` or `composer qa` |
| Code inventory audit | `find src -type f \| wc -l` |

When changing behavior, update this spec, `code-inventory.md`, integrator docs, and tests.
