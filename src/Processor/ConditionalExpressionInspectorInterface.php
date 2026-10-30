<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Processor;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;

/**
 * Inspects the expression conditionals ({@code ${#if <expression>}} … {@code ${#endif}}) and loops
 * ({@code ${#for item in items}} … {@code ${#endfor}}) of a .docx template, so hosts can validate templates (e.g. on
 * upload) before merging them. Works whether or not {@code conditional_expressions.enabled} / {@code loops.enabled}
 * are set.
 */
interface ConditionalExpressionInspectorInterface
{
    /**
     * Returns the unique expressions of the template's expression blocks (main part, headers, footers), normalised
     * (XML entities decoded, Word typographic quotes / dashes / spaces replaced).
     *
     * @return list<string>
     */
    public function listConditionalExpressions(string $templatePath): array;

    /**
     * Returns the unique loop heads of the template ({@code item in items}, {@code key, line in order.lines}), normalised.
     *
     * @return list<string>
     */
    public function listLoops(string $templatePath): array;

    /**
     * Checks every expression block and loop without merging: layout problems (stray or missing end markers, markers sharing
     * a paragraph, blocks crossing table cells, invalid loop heads), syntax, forbidden constructs, length and unknown
     * variables — the loop variables ({@code item}, {@code key}, {@code loop}) are known inside each loop body.
     *
     * @param list<string> $variableNames top-level names the context will provide (e.g. {@code client} for {@code client.country})
     *
     * @return list<ConditionalExpressionException> empty when the template is valid
     */
    public function lintConditionalExpressions(string $templatePath, array $variableNames): array;
}
