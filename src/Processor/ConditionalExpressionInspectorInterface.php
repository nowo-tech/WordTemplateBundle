<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Processor;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;

/**
 * Inspects the expression conditionals ({@code ${#if <expression>}} … {@code ${#endif}}) of a .docx template,
 * so hosts can validate templates (e.g. on upload) before merging them. Works whether or not
 * {@code conditional_expressions.enabled} is set.
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
     * Checks every expression block without merging: layout problems (stray or missing end markers, markers sharing
     * a paragraph, blocks crossing table cells), syntax, forbidden constructs, length and unknown variables.
     *
     * @param list<string> $variableNames top-level names the context will provide (e.g. {@code client} for {@code client.country})
     *
     * @return list<ConditionalExpressionException> empty when the template is valid
     */
    public function lintConditionalExpressions(string $templatePath, array $variableNames): array;
}
