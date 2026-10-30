# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/word-template-bundle`  
**Last audited**: 2026-10-30

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
| `Exception/TemplateLimitExceededException.php` | Resource limit of expression blocks / loops exceeded (fail closed) | FR-ERR-001, FR-LOOP-006 |
| `Exception/ConditionalExpressionException.php` | Invalid expression conditional ([002](../002-expression-conditionals/spec.md)) | FR-ERR-001, FR-EXPR-030 |

## Document processing

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Processor/WordTemplateProcessor.php` | Template processor | FR-PROC-002, FR-PROC-004, FR-PROC-005 |
| `Processor/TemplateProcessorBridge.php` | Template processor (internal) | FR-PROC-002 |
| `Processor/WordTemplateProcessorInterface.php` | Processor contract | FR-PROC-001 |
| `Result/ProcessedDocument.php` | Processed document result | FR-PROC-003 |
| `Processor/PhpWordHtmlState.php` | Scopes PHPWord global `Html` parser state to one merge (worker safety) | FR-PROC-007 |
| `Processor/ConditionalExpressionInspectorInterface.php` | List / lint expression conditionals ([002](../002-expression-conditionals/spec.md)) | FR-EXPR-040 |

## Expression conditionals and loops ([`002`](../002-expression-conditionals/spec.md), [`003`](../003-template-loops/spec.md))

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Util/ConditionalExpressionApplicator.php` | Marker pairing, classification, layout checks (rows), outside-in scoped rendering of conditionals and loops | FR-EXPR-001, FR-EXPR-003, FR-EXPR-031, FR-LOOP-004, FR-LOOP-005 |
| `Expression/ExpressionBlock.php` | One expression block (offsets, regions) | FR-EXPR-001, FR-EXPR-003 |
| `Expression/ExpressionTextNormalizer.php` | Entities, smart quotes, dashes, spaces | FR-EXPR-004 |
| `Expression/SandboxedExpressionLanguage.php` | ExpressionLanguage without default functions | FR-EXPR-010 |
| `Expression/ConditionalExpressionEvaluator.php` | Parse, check, evaluate, bounded cache, function registry | FR-EXPR-010, FR-EXPR-012, FR-EXPR-013, FR-EXPR-022 |
| `Expression/ConditionalExpressionErrorMode.php` | `on_error` modes | FR-EXPR-031 |
| `Expression/ConditionalExpressionReport.php` | Per-merge collector of swallowed errors | FR-EXPR-031 |
| `Expression/ExpressionVariables.php` | Context → expression variables | FR-EXPR-011 |
| `Expression/ExpressionMap.php` | Read-only map (dot / bracket access) | FR-EXPR-011 |
| `Expression/UnknownVariableError.php` | Missing map key | FR-EXPR-011, FR-EXPR-030 |
| `Expression/BuiltinExpressionFunctions.php` | Built-in functions, clock / time zone | FR-EXPR-020, FR-EXPR-021, FR-EXPR-023 |
| `Expression/ExpressionFunctionProviderInterface.php` | Host function providers (autoconfigured tag) | FR-EXPR-022 |
| `Expression/ExpressionFunctionError.php` | Bad function input | FR-EXPR-020, FR-EXPR-030 |
| `Expression/LoopSettings.php` | Loop delimiters and limits ([003](../003-template-loops/spec.md)) | FR-LOOP-001, FR-LOOP-006 |
| `Util/MarkerStack.php` | Bracket matching of if / for markers per scan | FR-EXPR-001, FR-LOOP-001 |
| `Util/BoundaryIndex.php` | Paragraph / row boundaries indexed once per part (linear layout check) | FR-EXPR-003, FR-LOOP-004 |
| `Expression/ExpressionLimits.php` | Marker, depth, evaluation and output limits | FR-LOOP-006 |

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
| Exceptions | 7 | 7 |
| Document processing | 6 | 6 |
| Expression conditionals and loops | 17 | 17 |
| Symfony config | 2 | 2 |
| **Total production sources** | **45** | **45** |

Audit: `git ls-files src | wc -l` → 45.
