<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use RuntimeException;

/**
 * Thrown when a template exceeds a resource limit of expression blocks and loops (markers per part, nesting depth,
 * evaluations per merge). Always thrown, whatever {@code conditional_expressions.on_error} says (fail closed).
 */
final class TemplateLimitExceededException extends RuntimeException implements WordTemplateExceptionInterface
{
}
