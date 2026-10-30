<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use InvalidArgumentException;
use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;

/**
 * Raised by an expression function on bad input (wrong type, value too large, unparsable date).
 * Throw it from host functions too: {@see ConditionalExpressionEvaluator} turns it into a
 * {@see ConditionalExpressionException} that honours {@code on_error}.
 */
final class ExpressionFunctionError extends InvalidArgumentException
{
}
