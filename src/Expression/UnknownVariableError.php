<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use OutOfBoundsException;

use function sprintf;

/**
 * Raised by {@see ExpressionMap} when an expression reads a key the context does not have.
 *
 * @internal converted to {@see ConditionalExpressionException} by {@see ConditionalExpressionEvaluator}
 */
final class UnknownVariableError extends OutOfBoundsException
{
    public function __construct(public readonly string $variable)
    {
        parent::__construct(sprintf('Unknown variable "%s".', $variable));
    }
}
