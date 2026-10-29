<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use InvalidArgumentException;

/**
 * Thrown when the .docx template path does not exist or is not readable.
 */
final class TemplateNotFoundException extends InvalidArgumentException implements WordTemplateExceptionInterface
{
}
