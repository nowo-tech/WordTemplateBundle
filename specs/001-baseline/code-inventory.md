# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/word-template-bundle`  
**Last audited**: 2026-10-20

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification (PHP + YAML only; no TypeScript or build outputs).

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001, FR-CFG-002, FR-CFG-004 |
| `DependencyInjection/WordTemplateExtension.php` | DI extension | FR-CFG-003 |
| `WordTemplateBundle.php` | Bundle entry | FR-BUNDLE-001 |

## Domain models

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Model/HtmlContent.php` | Domain model | FR-MDL-002 |
| `Model/ImageSource.php` | Domain model | FR-MDL-002 |
| `Model/TableRows.php` | Domain model | FR-MDL-002 |
| `Model/ConditionalBlock.php` | Domain model | FR-MDL-002 |

## Support utilities

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Util/ContextFlattener.php` | Support utility | FR-UTIL-001 |
| `Util/ConditionalBlockApplicator.php` | Conditional regions | FR-UTIL-001 |
| `Util/BrokenMacroNormalizer.php` | Re-joins Word-split placeholder / conditional markers | FR-UTIL-002 |
| `Util/HtmlImageSanitizer.php` | Drops remote / oversized / non-allowlisted `<img>` in `HtmlContent` (SSRF guard) | FR-SEC-001 |
| `Util/PathAllowlist.php` | Keeps template, image and output paths under `allowed_roots` | FR-SEC-001, FR-CFG-005 |

## Runtime

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Runtime/ProcessDeadline.php` | Merge timeout | FR-PROC-005, FR-CFG-004 |

## Exceptions

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Exception/InvalidContextValueException.php` | Domain exception | FR-ERR-001 |
| `Exception/ProcessingTimedOutException.php` | Domain exception | FR-ERR-001 |
| `Exception/TemplateNotFoundException.php` | Domain exception | FR-ERR-001 |
| `Exception/WordTemplateExceptionInterface.php` | Domain exception | FR-ERR-001 |
| `Exception/PathNotAllowedException.php` | Path outside `allowed_roots` | FR-ERR-001, FR-SEC-001 |

## Document processing

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Processor/WordTemplateProcessor.php` | Template processor | FR-PROC-002, FR-PROC-004, FR-PROC-005 |
| `Processor/TemplateProcessorBridge.php` | Template processor (internal) | FR-PROC-002 |
| `Processor/WordTemplateProcessorInterface.php` | Processor contract | FR-PROC-001 |
| `Result/ProcessedDocument.php` | Processed document result | FR-PROC-003 |
| `Processor/PhpWordHtmlState.php` | Scopes PHPWord global `Html` parser state to one merge (worker safety) | FR-PROC-007 |

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/nowo_word_template.yaml` | Service wiring | FR-DI-001 |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle & DI | 3 | 3 |
| Domain models | 4 | 4 |
| Support utilities | 5 | 5 |
| Runtime | 1 | 1 |
| Exceptions | 5 | 5 |
| Document processing | 5 | 5 |
| Symfony config | 2 | 2 |
| **Total production sources** | **25** | **25** |

Audit: `git ls-files src | wc -l` → 25.
