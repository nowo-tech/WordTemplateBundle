# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/word-template-bundle`  
**Last audited**: 2026-07-07

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and `*.test.ts` under `src/` are out of Packagist scope. Built assets under `Resources/public/` are documented as Vite/build outputs.

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/WordTemplateExtension.php` | DI extension | FR-CFG-002 |
| `WordTemplateBundle.php` | Bundle entry | FR-BUNDLE-001 |

## Domain models

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Model/HtmlContent.php` | Domain model | FR-MDL-002 |
| `Model/ImageSource.php` | Domain model | FR-MDL-002 |
| `Model/TableRows.php` | Domain model | FR-MDL-002 |

## Support utilities

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Util/ContextFlattener.php` | Support utility | FR-UTIL-001 |

## Exceptions

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Exception/InvalidContextValueException.php` | Domain exception | FR-ERR-001 |
| `Exception/TemplateNotFoundException.php` | Domain exception | FR-ERR-001 |
| `Exception/WordTemplateExceptionInterface.php` | Domain exception | FR-ERR-001 |

## Document processing

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Processor/WordTemplateProcessor.php` | Template processor | FR-PROC-002 |
| `Processor/WordTemplateProcessorInterface.php` | Processor contract | FR-PROC-001 |
| `Result/ProcessedDocument.php` | Processed document result | FR-PROC-003 |

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/nowo_word_template.yaml` | Service wiring | FR-DI-001 |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle & DI | 3 | 3 |
| Domain models | 3 | 3 |
| Support utilities | 1 | 1 |
| Exceptions | 3 | 3 |
| Document processing | 3 | 3 |
| Symfony config | 2 | 2 |
| **Total production sources** | **15** | **15** |

Audit: `find src -type f ! -path '*/assets/dist/*' ! -name '*.test.ts' | wc -l`
