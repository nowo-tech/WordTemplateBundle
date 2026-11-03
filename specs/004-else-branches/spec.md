# Feature Specification: Else branches in expression conditionals

**Feature Branch**: `feat/else-branches`  
**Created**: 2026-11-03  
**Status**: Implemented (target release 1.9.0)  
**Input**: Write "otherwise" in the template: `${#if a > 5000}` … `${#elseif a > 1000}` … `${#else}` … `${#endif}`, instead of a second block with the opposite condition.

**Related docs**: [`docs/USAGE.md` — Nesting, "else" and one of N](../../docs/USAGE.md#nesting-else-and-one-of-n), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#conditional-expressions), [`docs/UPGRADING.md` — To 1.9.0](../../docs/UPGRADING.md#to-190)  
**Builds on**: [`002-expression-conditionals`](../002-expression-conditionals/spec.md), [`003-template-loops`](../003-template-loops/spec.md)  
**Code inventory**: [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

---

## User Scenarios & Testing

| ID | Scenario | Proof |
| --- | --- | --- |
| US-ELSE-01 | Two-way choice with `${#else}` | `ElseBranchesApplicatorTest::testIfElse`, USAGE "else" example |
| US-ELSE-02 | One of N with `${#elseif}` and a fallback; first true branch wins, later expressions not evaluated | `testFirstTrueBranchWinsAndElseIsTheFallback`, `testLaterBranchesAreNotEvaluated`, USAGE "One of N" example |
| US-ELSE-03 | Nested blocks and loops inside branches; branches inside loop bodies | `testNestedBlocksInsideBranches`, `testBranchesInsideLoopsSeeTheLoopVariables`, `testLoopsInsideBranchesAreDiscoveredAndLinted` |
| US-ELSE-04 | Whole table rows per branch; branches inside one cell; headers | `testTableRowBranches`, `testBranchesInsideOneCell`, `ElseBranchesIntegrationTest::testBranchesInBodyTableAndHeader` |
| US-ELSE-05 | Misplaced branch markers are layout errors per `on_error` | `testStructureProblemsThrowInExceptionMode`, `testStructureProblemsAreReportedAndLeftAsTextInOtherModes` |
| US-ELSE-06 | List and lint branch expressions up front | `testDiscoveryAndLintCoverElseifExpressionsAndBranchChildren`, `ElseBranchesIntegrationTest::testListingLintingAndDisabledFlag` |

---

## Requirements

- **FR-ELSE-001**: Inside an expression block, `${#elseif <expression>}` and `${#else}` split the content into branches; delimiters `conditional_elseif_opening` (`${#elseif`), `conditional_elseif_closing`, `conditional_else_opening` (`${#else`), `conditional_else_closing`. A branch marker belongs to the innermost open `${#if …}`; markers are only recognised when `conditional_expressions.enabled` is true (otherwise left as text).
- **FR-ELSE-002**: Rendering evaluates the `if` expression, then each `elseif` expression in order, and renders the first true branch; `${#else}` is rendered when no expression is true; with no true branch and no else nothing is rendered. Expressions after the chosen branch are not evaluated. Each evaluated expression counts towards `max_evaluations`. All marker paragraphs (or rows) are removed.
- **FR-ELSE-003**: Layout: each branch marker sits in its own paragraph — or, when the block works on whole rows, in a row of its own — and no branch may cut table cells, rows or content controls in half. Errors (per `on_error`, markers left as text): a branch marker with no open block, whose innermost open marker is a loop, in a block closed by a named `${#endif name}`, in a block that is never closed, after the `${#else}` of its block, sharing a paragraph with another marker.
- **FR-ELSE-004**: An evaluation error in any evaluated branch applies `on_error` to the whole block (`hide`: removed; `keep`: every marker and branch kept as text, nested blocks still evaluated).
- **FR-ELSE-005**: `listConditionalExpressions()` includes `elseif` expressions; `lintConditionalExpressions()` lints them and the blocks of every branch; with expression conditionals enabled `listVariables()` omits branch markers.

## Non-goals

- Branches in named blocks (`${#if name}` … `${#endif name}` + `ConditionalBlock`): the named-block contract stays exactly as in 1.7.
- `${#else}` / `${#empty}` on loops (wrap the loop in a conditional), inline branches inside one paragraph.

## Success Criteria

- **SC-ELSE-001**: The USAGE "else" and "One of N" examples use branches and are executed by `ExpressionConditionalsUsageDocTest`.
- **SC-ELSE-002**: 100% line coverage; PHPStan, Rector, Igor and FrankenPHP rulesets clean; files mapped in the code inventory.
