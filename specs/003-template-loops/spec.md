# Feature Specification: Template loops

**Feature Branch**: `feat/for-loops` (stacked on `feat/expression-conditionals`)  
**Created**: 2026-10-30  
**Status**: Implemented (target release 1.8.0, together with [`002-expression-conditionals`](../002-expression-conditionals/spec.md))  
**Input**: Repeat paragraphs or table rows per element of a list from the template (`${#for line in lines}` … `${#endfor}`), with Twig-like loop variables, conditions inside the body and nested loops.

**Related docs**: [`docs/USAGE.md` — Loops](../../docs/USAGE.md#loops), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#loops), [`docs/SECURITY.md`](../../docs/SECURITY.md#expression-conditionals)  
**Code inventory**: [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

---

## User Scenarios & Testing

| ID | Scenario | Proof |
| --- | --- | --- |
| US-LOOP-01 | Repeat paragraphs per element with `${line.name}` and `${loop.index}` | `LoopApplicatorTest::testParagraphLoopWithItemAndLoopVariables`, USAGE "Repeat paragraphs" example |
| US-LOOP-02 | Repeat table rows (markers in rows of their own) | `testTableRowLoop`, `LoopsIntegrationTest::testInvoiceWithRowLoopParagraphLoopHeaderAndConditions`, USAGE "Repeat table rows" example |
| US-LOOP-03 | Conditions and nested loops inside a body see the loop variables (`loop.parent`) | `testNestedLoopsIfInsideForAndParent`, USAGE example |
| US-LOOP-04 | Empty list removes the block; "no items" via an opposite condition | `testEmptyListRemovesTheBlock`, USAGE "Empty lists" example |
| US-LOOP-05 | Maps with `key, item`; iterables from functions (`slice()`, `keys()`) | `testScalarItemsKeysMapsAndIndexes`, `testIterableExpressionWithFunctions` |
| US-LOOP-06 | Errors per `on_error`, iteration / output caps | `testFailuresThrowInExceptionMode`, `testHideAndKeepModes`, `testOutputSizeIsBounded`, `testErrorsAndLogging` |
| US-LOOP-07 | Validate templates up front | `testDiscoveryAndScopedLint`, `LoopsIntegrationTest::testListingAndLinting` |

---

## Requirements

- **FR-LOOP-001**: `${#for <item> in <expression>}` / `${#for <key>, <item> in <expression>}` … `${#endfor}`; delimiters `loop_for_opening` (`${#for`), `loop_for_closing`, `loop_endfor_opening` (`${#endfor`), `loop_endfor_closing`. `${#endfor}` closes the nearest open loop; unclosed `${#if …}` markers in between are discarded (error unless a plain name). A nameless `${#endif}` cannot close a loop; a named `${#endif}` never pairs across an open loop (left as text).
- **FR-LOOP-002**: Opt-in `loops.enabled` (default `false`, markers left as text), independent from `conditional_expressions.enabled`.
- **FR-LOOP-003**: The iterable is an expression evaluated by `ConditionalExpressionEvaluator::evaluateValue()` and must be a list or a map; maps iterate key → value.
- **FR-LOOP-004**: Paragraph loops repeat the content between the marker paragraphs; when the markers sit in different rows of the same table the rows between them are repeated (marker rows removed). The same row rule applies to expression conditionals.
- **FR-LOOP-005**: Per iteration the scope gains `<item>`, `<key>` and `loop` (`index`, `index0`, `revindex`, `revindex0`, `first`, `last`, `length`, `parent`); blocks in the body are rendered outside-in with that scope (shadowing outer names), then `${item…}` / `${key}` / `${loop…}` placeholders are replaced, XML-escaped (`bool` → `1`/`0`, `null` → empty; lists / maps are errors).
- **FR-LOOP-006**: Limits: `loops.max_iterations` (1000) per loop, `loops.max_total_iterations` (10000) per merge, 32 MiB per expanded part (`LoopSettings::MAX_OUTPUT_BYTES`). Errors follow `conditional_expressions.on_error` (`hide`: loop removed / placeholder emptied; `keep`: loop left as text / placeholder kept).
- **FR-LOOP-007**: `listLoops()`; `lintConditionalExpressions()` lints loop heads and body expressions with the loop variables in scope; with loops enabled, `listVariables()` omits loop markers and loop-variable placeholders.

## Non-goals

- `${#else}` / `${#empty}` (use `${#if length(items) == 0}`), `break` / `continue`, loops over objects, inline loops inside one paragraph.
- Replacing `TableRows` (both coexist; no anchor inside a loop body).

## Success Criteria

- **SC-LOOP-001**: Every loop example of USAGE is executed by `ExpressionConditionalsUsageDocTest`.
- **SC-LOOP-002**: 100% line coverage; Igor and FrankenPHP rulesets clean; new files mapped in the code inventory.
