<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface as SymfonyExpressionFunctionProviderInterface;

/**
 * Implement this in a host service to add functions to expression conditionals. Implementations are autoconfigured
 * with the {@code nowo_word_template.expression_function_provider} tag (any Symfony function provider can also be
 * tagged by hand). Functions must be pure (no I/O, no side effects), validate their arguments and throw
 * {@see ExpressionFunctionError} on bad input. Template expressions are only evaluated, never compiled, so the
 * compiler callable of each {@see ExpressionFunction} is never called.
 */
interface ExpressionFunctionProviderInterface extends SymfonyExpressionFunctionProviderInterface
{
    public const TAG = 'nowo_word_template.expression_function_provider';
}
