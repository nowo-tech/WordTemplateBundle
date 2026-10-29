<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use InvalidArgumentException;

/**
 * Thrown when a context value cannot be merged into the template (e.g. a table rows value without any data row).
 */
final class InvalidContextValueException extends InvalidArgumentException implements WordTemplateExceptionInterface
{
}
