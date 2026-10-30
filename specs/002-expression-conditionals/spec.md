# Feature Specification: Expression conditionals and template functions

**Feature Branch**: `feat/expression-conditionals`  
**Created**: 2026-10-30  
**Status**: Implemented (target release 1.8.0)  
**Input**: Let template authors write the condition of a conditional block in the `.docx` (`${#if total > 1000}` … `${#endif}`) with a safe expression language and Twig-like functions, without breaking named blocks.

**Related docs**: [`docs/USAGE.md` — Expression conditionals](../../docs/USAGE.md#expression-conditionals), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#conditional-expressions), [`docs/SECURITY.md`](../../docs/SECURITY.md#expression-conditionals), [`docs/FRANKENPHP-WORKER-AUDIT.md`](../../docs/FRANKENPHP-WORKER-AUDIT.md)  
**Baseline**: [`../001-baseline/spec.md`](../001-baseline/spec.md) (not contradicted: named blocks keep their exact behaviour) — code inventory in [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

---

## User Scenarios & Testing

| ID | Scenario | Proof |
| --- | --- | --- |
| US-EXPR-01 | A template author writes `${#if total > 1000}` … `${#endif}` and the block is kept or removed from the context value | `ExpressionConditionalsUsageDocTest` (first example), `ConditionalExpressionApplicatorTest` |
| US-EXPR-02 | Existing templates with `${#if name}` … `${#endif name}` + `ConditionalBlock` behave exactly as in 1.7, with the feature on or off | existing `ConditionalBlockApplicatorTest`, `ConditionalsUsageDocTest`, `WordTemplateProcessorIntegrationTest` unchanged; `testNamedBlocksAreLeftToTheLegacyApplicator` |
| US-EXPR-03 | Named and expression blocks nest in any combination | `testMixedNestingWithLegacyBlocks`, `testNamedAndExpressionBlocksTogether` |
| US-EXPR-04 | Word smart quotes, dashes, special spaces and split runs do not break expressions | `ExpressionTextNormalizerTest`, `testWordSplitRunsAndSmartQuotesAreHealed` |
| US-EXPR-05 | A typo / bad expression fails clearly (default) or is hidden / kept per configuration | `testErrorModes`, `testEvaluationErrorModes`, `testLayoutProblems*` |
| US-EXPR-06 | A host validates an uploaded template before merging | `testListingAndLinting`, kernel test |
| US-EXPR-07 | Twig-like functions (`upper(name)`, `length(lines)`, `date('+30 days')`) | `BuiltinExpressionFunctionsTest`, function rows of the doc test |
| US-EXPR-08 | A host adds its own functions or disables built-ins | `testHostProviderAddsAndOverridesFunctions`, `testAllowAndDenyLists`, `testHostFunctionProviderIsAutoconfiguredAndDenyListApplies` |
| US-EXPR-09 | Body, table cells, headers and footers | `testBodyTableCellHeaderAndFooter` |

---

## Requirements

### Syntax and classification

- **FR-EXPR-001**: `${#if <expression>}` … `${#endif}` (nameless end marker = `conditional_endif_opening` + `conditional_endif_closing`, optional whitespace) defines an expression block. Markers are paired like brackets in document order per part; a nameless end marker closes the nearest open opening marker. A named end marker closes the nearest open opening marker with the same name (case-insensitive) and leaves that block to `ConditionalBlockApplicator` unchanged. Classification is syntactic and independent of the context.
- **FR-EXPR-002**: Disabled by default (`conditional_expressions.enabled: false`): no expression is evaluated and markers stay as literal text (1.7 behaviour).
- **FR-EXPR-003**: Named blocks are applied first; expression blocks are then evaluated outside-in, so the expressions inside a removed block are never evaluated. Each marker must be in its own paragraph; marker paragraphs are removed; a block must not cross table cells / rows / content controls.
- **FR-EXPR-004**: Expression text is normalised before parsing: leftover tags stripped, XML entities decoded, typographic quotes / dashes / special spaces replaced, zero-width characters dropped; markers split across runs are healed by `BrokenMacroNormalizer`.

### Evaluation

- **FR-EXPR-010**: Symfony ExpressionLanguage without its default functions (`SandboxedExpressionLanguage`); expressions are evaluated, never compiled. Method calls are rejected; the result must be a bool (a top-level `matches` 0/1 is converted).
- **FR-EXPR-011**: Variables (`ExpressionVariables`): identifier-named top-level context keys; scalars as is; lists as lists; associative arrays as read-only `ExpressionMap` (dot and bracket access, missing key = unknown variable, `??` / `?.` safe); `Stringable` → string; backed enum → value; pure enum → name; `DateTimeInterface` → `'Y-m-d'`; `ConditionalBlock` visibility exposed under its identifier block name unless a context key uses it; `TableRows`, `HtmlContent`, `ImageSource` and other objects not exposed. `ContextFlattener` renders enums and dates the same way in placeholders.
- **FR-EXPR-012**: Parsed expressions are cached in a bounded PSR-6 pool (`ArrayAdapter`, 256 entries) per evaluator service.
- **FR-EXPR-013**: `max_length` (default 500) bounds the expression length.

### Functions

- **FR-EXPR-020**: Built-in pure functions (`BuiltinExpressionFunctions`), Twig-named: `lower`, `upper`, `capitalize`, `title`, `trim`, `length`, `replace`, `slice`, `starts_with`, `ends_with`, `abs`, `round`, `floor`, `ceil`, `min`, `max`, `number_format`, `count`, `keys`, `first`, `last`, `join`, `empty`, `default`, `is_null`, `is_defined`, `now`, `date`, `date_diff_days`. Arguments are type-checked (`ExpressionFunctionError`); strings ≤ 64 KiB and lists ≤ 10,000 elements in and out.
- **FR-EXPR-021**: `now()` / `date()` use a PSR-20 clock (Symfony `clock` service, `NativeClock` fallback) and `conditional_expressions.timezone`.
- **FR-EXPR-022**: Host providers implementing `ExpressionFunctionProviderInterface` are autoconfigured with tag `nowo_word_template.expression_function_provider` (built-ins at priority 1000; a later function with the same name overrides). `functions.allow` / `functions.deny` filter all functions; an unknown name is a configuration error.
- **FR-EXPR-023**: `contains` and `in` stay operators (not functions); Twig filters are written as function calls.

### Errors

- **FR-EXPR-030**: `ConditionalExpressionException` (implements `WordTemplateExceptionInterface`, exposes `$expression`) for invalid syntax, unknown function, forbidden construct, unknown variable, evaluation failure, non-bool result, too long, and layout problems (stray nameless end marker, unclosed non-name opening marker, same-paragraph pair, marker sharing a paragraph with another block's marker, block crossing containers).
- **FR-EXPR-031**: `conditional_expressions.on_error`: `exception` (default) throws; `hide` removes the block; `keep` leaves its markers as text and still evaluates nested blocks. Layout problems are left as text in `hide` / `keep`. One `warning` log per merge with the error count (never expressions or values).

### Inspection

- **FR-EXPR-040**: `ConditionalExpressionInspectorInterface` (implemented by `WordTemplateProcessor`, public alias): `listConditionalExpressions()` (normalised, unique, main part + headers + footers) and `lintConditionalExpressions(path, variableNames)` (layout + syntax + functions + length + top-level names), independent of `enabled`.
- **FR-EXPR-041**: With `enabled: true`, `listConditionalBlocks()` returns named blocks only; `listVariables()` omits the nameless end marker.

### Configuration

- **FR-EXPR-050**: `conditional_expressions.{enabled, on_error, max_length, timezone, functions.allow, functions.deny}` exposed as `nowo_word_template.conditional_expressions.*` parameters; `ConditionalExpressionEvaluator` and `BuiltinExpressionFunctions` are services.

---

## Non-goals

- `${#else}` / `${#elseif}` (write the opposite expression).
- Inline blocks inside one paragraph.
- Twig `|filter` pipe syntax (`|` is bitwise OR in ExpressionLanguage).
- Loops (`${#for}`) — separate feature.

---

## Success Criteria

- **SC-EXPR-001**: Every operator / data-type / function / error row and every worked example of the USAGE section is executed by `ExpressionConditionalsUsageDocTest`.
- **SC-EXPR-002**: Existing tests pass unchanged; PHP line coverage stays 100%.
- **SC-EXPR-003**: Every new `src/` file is mapped in `001-baseline/code-inventory.md`.
- **SC-EXPR-004**: Igor and the FrankenPHP PHPStan rulesets report no worker-state issue.
