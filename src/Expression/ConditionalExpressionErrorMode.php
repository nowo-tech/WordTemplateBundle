<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;

/**
 * What happens when an expression conditional fails (configuration {@code conditional_expressions.on_error}).
 */
enum ConditionalExpressionErrorMode: string
{
    /** Throw {@see ConditionalExpressionException} (default). */
    case Exception = 'exception';

    /** Remove the block (markers and content) as if the expression were false; logged as a warning. */
    case Hide = 'hide';

    /** Leave the block untouched (markers stay as literal text, content shown); logged as a warning. */
    case Keep = 'keep';
}
